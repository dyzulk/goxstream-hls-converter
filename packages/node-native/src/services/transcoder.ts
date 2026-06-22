import { spawn, exec } from 'child_process';
import path from 'path';
import fs from 'fs';
import os from 'os';
import { downloadFile, uploadFile } from './storage.js';
import { updateProgress, updateCompleted, updateError, JobState } from './jobs.js';

export function getVideoDuration(filePath: string): Promise<number> {
  const ffprobeCmd = process.env.GOX_FFPROBE_PATH || 'ffprobe';
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

export function parseDurationToSeconds(tStr: string): number {
  const parts = tStr.split(':');
  if (parts.length !== 3) return 0;
  const hours = parseFloat(parts[0]) || 0;
  const mins = parseFloat(parts[1]) || 0;
  const secs = parseFloat(parts[2]) || 0;
  return hours * 3600 + mins * 60 + secs;
}

export async function runTranscoding(
  jobId: string,
  inputKey: string,
  outputPrefix: string,
  job: JobState
): Promise<void> {
  const internalHost = process.env.GOX_R2_INTERNAL_HOST || 'r2.internal';
  const tempDir = path.join(os.tmpdir(), 'job-' + jobId);
  fs.mkdirSync(tempDir, { recursive: true });

  const inputPath = path.join(tempDir, 'input.mp4');
  const outputDir = path.join(tempDir, 'hls');
  fs.mkdirSync(outputDir, { recursive: true });

  try {
    // 1. Download
    console.log(`[${jobId}] Downloading raw video: ${inputKey}`);
    await downloadFile(`http://${internalHost}/${inputKey}`, inputPath);

    // 2. Duration check
    const duration = await getVideoDuration(inputPath);
    console.log(`[${jobId}] Video duration: ${duration} seconds`);

    // Prepare target dirs
    for (const res of ['360p', '480p', '720p', '1080p']) {
      fs.mkdirSync(path.join(outputDir, res), { recursive: true });
    }

    // 3. FFMpeg execution
    console.log(`[${jobId}] Starting ffmpeg transcoding`);
    const args = [
      '-i', inputPath,
      '-filter_complex', '[0:v]split=4[v1][v2][v3][v4]; [v1]scale=-2:1080[v1out]; [v2]scale=-2:720[v2out]; [v3]scale=-2:480[v3out]; [v4]scale=-2:360[v4out]',
      '-map', '[v1out]', '-map', '0:a', '-c:v:0', 'libx264', '-b:v:0', '5000k', '-maxrate:v:0', '5350k', '-bufsize:v:0', '7500k', '-c:a:0', 'aac', '-b:a:0', '192k',
      '-map', '[v2out]', '-map', '0:a', '-c:v:1', 'libx264', '-b:v:1', '2800k', '-maxrate:v:1', '2996k', '-bufsize:v:1', '4200k', '-c:a:1', 'aac', '-b:a:1', '128k',
      '-map', '[v3out]', '-map', '0:a', '-c:v:2', 'libx264', '-b:v:2', '1400k', '-maxrate:v:2', '1498k', '-bufsize:v:2', '2100k', '-c:a:2', 'aac', '-b:a:2', '128k',
      '-map', '[v4out]', '-map', '0:a', '-c:v:3', 'libx264', '-b:v:3', '800k', '-maxrate:v:3', '856k', '-bufsize:v:3', '1200k', '-c:a:3', 'aac', '-b:a:3', '96k',
      '-f', 'hls',
      '-hls_time', '6',
      '-hls_playlist_type', 'vod',
      '-master_pl_name', 'master.m3u8',
      '-var_stream_map', 'v:0,a:0 v:1,a:1 v:2,a:2 v:3,a:3',
      '-hls_segment_filename', path.join(outputDir, '%v', 'segment_%03d.ts'),
      path.join(outputDir, '%v', 'play.m3u8')
    ];

    const ffmpegCmd = process.env.GOX_FFMPEG_PATH || 'ffmpeg';
    const ffmpegProcess = spawn(ffmpegCmd, args);
    const timeReg = /time=([0-9:.]+)/;

    ffmpegProcess.stderr.on('data', (data) => {
      const line = data.toString();
      const match = line.match(timeReg);
      if (match && match[1] && duration > 0) {
        const curSecs = parseDurationToSeconds(match[1]);
        const progress = Math.min((curSecs / duration) * 100, 100);
        updateProgress(job, parseFloat(progress.toFixed(2)));
      }
    });

    await new Promise<void>((resolve, reject) => {
      ffmpegProcess.on('close', (code) => {
        if (code === 0) resolve();
        else reject(new Error(`FFmpeg exited with code ${code}`));
      });
      ffmpegProcess.on('error', reject);
    });

    // Rename index folders to human readable resolution names
    const indexMap: Record<string, string> = { '0': '1080p', '1': '720p', '2': '480p', '3': '360p' };
    for (const [idx, name] of Object.entries(indexMap)) {
      const src = path.join(outputDir, idx);
      const dest = path.join(outputDir, name);
      if (fs.existsSync(src)) {
        if (fs.existsSync(dest)) fs.rmSync(dest, { recursive: true, force: true });
        fs.renameSync(src, dest);
      }
    }

    // Fix master.m3u8 manifest file references
    const masterPath = path.join(outputDir, 'master.m3u8');
    if (fs.existsSync(masterPath)) {
      let masterData = fs.readFileSync(masterPath, 'utf8');
      masterData = masterData
        .replace(/0\/play\.m3u8/g, '1080p/play.m3u8')
        .replace(/1\/play\.m3u8/g, '720p/play.m3u8')
        .replace(/2\/play\.m3u8/g, '480p/play.m3u8')
        .replace(/3\/play\.m3u8/g, '360p/play.m3u8');
      fs.writeFileSync(masterPath, masterData, 'utf8');
    }

    // 4. Upload HLS segments and playlists back to R2
    console.log(`[${jobId}] Uploading generated files to R2`);
    const walkDir = async (currentPath: string) => {
      const files = fs.readdirSync(currentPath);
      for (const file of files) {
        const fullPath = path.join(currentPath, file);
        if (fs.statSync(fullPath).isDirectory()) {
          await walkDir(fullPath);
        } else {
          const relPath = path.relative(outputDir, fullPath).replace(/\\/g, '/');
          const r2Url = `http://${internalHost}/${outputPrefix}/${relPath}`;
          await uploadFile(fullPath, r2Url);
        }
      }
    };
    await walkDir(outputDir);

    updateCompleted(job);
  } catch (err: any) {
    updateError(job, err);
  } finally {
    fs.rmSync(tempDir, { recursive: true, force: true });
  }
}
