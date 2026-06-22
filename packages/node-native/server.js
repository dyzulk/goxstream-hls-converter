import http from "http";
import fs from "fs";
import path from "path";
import os from "os";
import { exec, spawn } from "child_process";
import { WebSocketServer } from "ws";

// 1. Intercept HTTP requests to spoof r2.internal locally
const internalHost = process.env.GOX_R2_INTERNAL_HOST || "r2.internal";
const mockTarget = process.env.GOX_R2_MOCK_RESOLVE_TARGET || "127.0.0.1:3000";
const [mockIp, mockPort] = mockTarget.split(":");

const originalRequest = http.request;
http.request = function (options, callback) {
  let opts = options;
  if (typeof options === "string") {
    opts = new URL(options);
  }
  
  const hostname = opts.hostname || opts.host;
  if (hostname === internalHost) {
    opts.hostname = mockIp;
    opts.port = mockPort || 80;
    if (opts.headers) {
      opts.headers["Host"] = internalHost;
    } else {
      opts.headers = { Host: internalHost };
    }
  }
  return originalRequest.call(this, opts, callback);
};

// 2. Storage Helper Functions
function downloadFile(url, destPath) {
  return new Promise((resolve, reject) => {
    http.get(url, (res) => {
      if (res.statusCode !== 200) {
        reject(new Error(`Failed to download: status code ${res.statusCode}`));
        return;
      }
      const fileStream = fs.createWriteStream(destPath);
      res.pipe(fileStream);
      fileStream.on("finish", () => {
        fileStream.close();
        resolve();
      });
      fileStream.on("error", reject);
    }).on("error", reject);
  });
}

function uploadFile(localPath, r2URL) {
  return new Promise((resolve, reject) => {
    const fileStream = fs.createReadStream(localPath);
    const stats = fs.statSync(localPath);
    
    let contentType = "application/octet-stream";
    if (localPath.endsWith(".m3u8")) {
      contentType = "application/x-mpegURL";
    } else if (localPath.endsWith(".ts")) {
      contentType = "video/MP2T";
    }

    const url = new URL(r2URL);
    const req = http.request({
      hostname: url.hostname,
      port: url.port,
      path: url.pathname,
      method: "PUT",
      headers: {
        "Content-Length": stats.size,
        "Content-Type": contentType
      }
    }, (res) => {
      if (res.statusCode !== 200) {
        let body = "";
        res.on("data", chunk => body += chunk);
        res.on("end", () => reject(new Error(`Upload failed: status ${res.statusCode}, body: ${body}`)));
      } else {
        resolve();
      }
    });

    req.on("error", reject);
    fileStream.pipe(req);
  });
}

// 3. Job State Management
const jobs = new Map();

function getOrCreateJob(jobId) {
  if (jobs.has(jobId)) {
    return jobs.get(jobId);
  }
  const job = {
    job_id: jobId,
    status: "processing",
    progress: 0,
    error: null,
    clients: new Set()
  };
  jobs.set(jobId, job);
  return job;
}

function updateJobProgress(job, progress) {
  job.progress = progress;
  broadcastJobState(job);
}

function updateJobCompleted(job) {
  job.status = "completed";
  job.progress = 100;
  broadcastJobState(job);
  closeClients(job);
}

function updateJobError(job, err) {
  console.error(`[${job.job_id}] Error:`, err);
  job.status = "failed";
  job.error = err.message;
  broadcastJobState(job);
  closeClients(job);
}

function broadcastJobState(job) {
  const data = JSON.stringify({
    job_id: job.job_id,
    status: job.status,
    progress: job.progress,
    error: job.error
  });
  for (const client of job.clients) {
    try {
      client.send(data);
    } catch (e) {
      client.close();
      job.clients.delete(client);
    }
  }
}

function closeClients(job) {
  for (const client of job.clients) {
    try {
      client.close();
    } catch (e) {}
  }
  job.clients.clear();
}

// 4. Video Transcoding Logic
function getVideoDuration(filePath) {
  const ffprobeCmd = process.env.GOX_FFPROBE_PATH || "ffprobe";
  return new Promise((resolve) => {
    exec(`"${ffprobeCmd}" -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 "${filePath}"`, (err, stdout) => {
      if (err) {
        resolve(0);
      } else {
        resolve(parseFloat(stdout.trim()) || 0);
      }
    });
  });
}

function parseDurationToSeconds(tStr) {
  const parts = tStr.split(":");
  if (parts.length !== 3) return 0;
  const hours = parseFloat(parts[0]) || 0;
  const mins = parseFloat(parts[1]) || 0;
  const secs = parseFloat(parts[2]) || 0;
  return hours * 3600 + mins * 60 + secs;
}

async function runTranscoding(jobId, inputKey, outputPrefix, job) {
  const tempDir = path.join(os.tmpdir(), "job-" + jobId);
  fs.mkdirSync(tempDir, { recursive: true });

  const inputPath = path.join(tempDir, "input.mp4");
  const outputDir = path.join(tempDir, "hls");
  fs.mkdirSync(outputDir, { recursive: true });

  try {
    // 1. Download
    console.log(`[${jobId}] Downloading raw video: ${inputKey}`);
    await downloadFile(`http://${internalHost}/${inputKey}`, inputPath);

    // 2. Duration check
    const duration = await getVideoDuration(inputPath);
    console.log(`[${jobId}] Video duration: ${duration} seconds`);

    // Prepare target dirs
    for (const res of ["360p", "480p", "720p", "1080p"]) {
      fs.mkdirSync(path.join(outputDir, res), { recursive: true });
    }

    // 3. FFMpeg execution
    console.log(`[${jobId}] Starting ffmpeg transcoding`);
    const args = [
      "-i", inputPath,
      "-filter_complex", "[0:v]split=4[v1][v2][v3][v4]; [v1]scale=-2:1080[v1out]; [v2]scale=-2:720[v2out]; [v3]scale=-2:480[v3out]; [v4]scale=-2:360[v4out]",
      "-map", "[v1out]", "-map", "0:a", "-c:v:0", "libx264", "-b:v:0", "5000k", "-maxrate:v:0", "5350k", "-bufsize:v:0", "7500k", "-c:a:0", "aac", "-b:a:0", "192k",
      "-map", "[v2out]", "-map", "0:a", "-c:v:1", "libx264", "-b:v:1", "2800k", "-maxrate:v:1", "2996k", "-bufsize:v:1", "4200k", "-c:a:1", "aac", "-b:a:1", "128k",
      "-map", "[v3out]", "-map", "0:a", "-c:v:2", "libx264", "-b:v:2", "1400k", "-maxrate:v:2", "1498k", "-bufsize:v:2", "2100k", "-c:a:2", "aac", "-b:a:2", "128k",
      "-map", "[v4out]", "-map", "0:a", "-c:v:3", "libx264", "-b:v:3", "800k", "-maxrate:v:3", "856k", "-bufsize:v:3", "1200k", "-c:a:3", "aac", "-b:a:3", "96k",
      "-f", "hls",
      "-hls_time", "6",
      "-hls_playlist_type", "vod",
      "-master_pl_name", "master.m3u8",
      "-var_stream_map", "v:0,a:0 v:1,a:1 v:2,a:2 v:3,a:3",
      "-hls_segment_filename", path.join(outputDir, "%v", "segment_%03d.ts"),
      path.join(outputDir, "%v", "play.m3u8")
    ];

    const ffmpegCmd = process.env.GOX_FFMPEG_PATH || "ffmpeg";
    const ffmpeg = spawn(ffmpegCmd, args);
    const timeReg = /time=([0-9:.]+)/;

    ffmpeg.stderr.on("data", (data) => {
      const line = data.toString();
      const match = line.match(timeReg);
      if (match && match[1] && duration > 0) {
        const curSecs = parseDurationToSeconds(match[1]);
        const progress = Math.min((curSecs / duration) * 100, 100);
        updateJobProgress(job, parseFloat(progress.toFixed(2)));
      }
    });

    await new Promise((resolve, reject) => {
      ffmpeg.on("close", (code) => {
        if (code === 0) resolve();
        else reject(new Error(`FFmpeg exited with code ${code}`));
      });
      ffmpeg.on("error", reject);
    });

    // Rename index folders to human readable resolution names
    const indexMap = { "0": "1080p", "1": "720p", "2": "480p", "3": "360p" };
    for (const [idx, name] of Object.entries(indexMap)) {
      const src = path.join(outputDir, idx);
      const dest = path.join(outputDir, name);
      if (fs.existsSync(src)) {
        if (fs.existsSync(dest)) fs.rmdirSync(dest);
        fs.renameSync(src, dest);
      }
    }

    // Fix master.m3u8 manifest file references
    const masterPath = path.join(outputDir, "master.m3u8");
    if (fs.existsSync(masterPath)) {
      let masterData = fs.readFileSync(masterPath, "utf8");
      masterData = masterData
        .replace(/0\/play\.m3u8/g, "1080p/play.m3u8")
        .replace(/1\/play\.m3u8/g, "720p/play.m3u8")
        .replace(/2\/play\.m3u8/g, "480p/play.m3u8")
        .replace(/3\/play\.m3u8/g, "360p/play.m3u8");
      fs.writeFileSync(masterPath, masterData, "utf8");
    }

    // 4. Upload HLS segments and playlists back to R2
    console.log(`[${jobId}] Uploading generated files to R2`);
    const walkDir = async (currentPath) => {
      const files = fs.readdirSync(currentPath);
      for (const file of files) {
        const fullPath = path.join(currentPath, file);
        if (fs.statSync(fullPath).isDirectory()) {
          await walkDir(fullPath);
        } else {
          const relPath = path.relative(outputDir, fullPath).replace(/\\/g, "/");
          const r2Url = `http://${internalHost}/${outputPrefix}/${relPath}`;
          await uploadFile(fullPath, r2Url);
        }
      }
    };
    await walkDir(outputDir);

    updateJobCompleted(job);
  } catch (err) {
    updateJobError(job, err);
  } finally {
    fs.rmSync(tempDir, { recursive: true, force: true });
  }
}

// 5. Create Server
const server = http.createServer((req, res) => {
  const url = new URL(req.url, `http://${req.headers.host}`);
  
  if (url.pathname === "/health" && req.method === "GET") {
    res.writeHead(200, { "Content-Type": "application/json" });
    res.end(JSON.stringify({ status: "healthy", service: "video-converter" }));
    return;
  }

  if (url.pathname === "/transcode" && req.method === "POST") {
    let body = "";
    req.on("data", chunk => body += chunk);
    req.on("end", () => {
      try {
        const payload = JSON.parse(body);
        const { job_id, input_key, output_prefix } = payload;
        
        if (!job_id || !input_key || !output_prefix) {
          res.writeHead(400, { "Content-Type": "text/plain" });
          res.end("job_id, input_key, and output_prefix are required");
          return;
        }

        const job = getOrCreateJob(job_id);
        runTranscoding(job_id, input_key, output_prefix, job);

        res.writeHead(200, { "Content-Type": "application/json" });
        res.end(JSON.stringify({ status: "processing", job_id }));
      } catch (err) {
        res.writeHead(400, { "Content-Type": "text/plain" });
        res.end("Invalid JSON body");
      }
    });
    return;
  }

  res.writeHead(404, { "Content-Type": "text/plain" });
  res.end("Not Found");
});

// 6. Integrate WebSockets using the ws package
const wss = new WebSocketServer({ noServer: true });

server.on("upgrade", (request, socket, head) => {
  const url = new URL(request.url, `http://${request.headers.host}`);
  
  if (url.pathname === "/ws") {
    const jobId = url.searchParams.get("job_id");
    if (!jobId) {
      socket.write("HTTP/1.1 400 Bad Request\r\n\r\n");
      socket.destroy();
      return;
    }

    wss.handleUpgrade(request, socket, head, (ws) => {
      const job = getOrCreateJob(jobId);
      job.clients.add(ws);

      // Send initial state
      ws.send(JSON.stringify({
        job_id: job.job_id,
        status: job.status,
        progress: job.progress,
        error: job.error
      }));

      ws.on("close", () => {
        job.clients.delete(ws);
      });
    });
  } else {
    socket.destroy();
  }
});

const port = process.env.PORT || 8080;
server.listen(port, () => {
  console.log(`Goxstream Node Native Server listening on :${port}`);
});
