<?php

if (php_sapi_name() !== "cli") {
    die("Only CLI execution allowed");
}

$jobId = $argv[1] ?? "";
$inputKey = $argv[2] ?? "";
$outputPrefix = $argv[3] ?? "";

if (!$jobId || !$inputKey || !$outputPrefix) {
    die("Missing arguments");
}

$stateFile = sys_get_temp_dir() . "/job_{$jobId}.json";

function updateState($status, $progress, $error = null) {
    global $stateFile, $jobId;
    $state = [
        "job_id" => $jobId,
        "status" => $status,
        "progress" => $progress,
        "error" => $error
    ];
    file_put_contents($stateFile, json_encode($state));
}

// 1. Load Env
function loadEnv($path) {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            $value = substr($value, 1, -1);
        }
        putenv("$name=$value");
        $_ENV[$name] = $value;
    }
}

loadEnv(__DIR__ . "/.env");
$internalHost = getenv("GOX_R2_INTERNAL_HOST") ?: "r2.internal";
$mockTarget = getenv("GOX_R2_MOCK_RESOLVE_TARGET") ?: "127.0.0.1:3000";
$mockIp = explode(":", $mockTarget)[0];
$mockPort = explode(":", $mockTarget)[1] ?? "80";

// Helper to make Curl requests with DNS override (CURLOPT_RESOLVE)
function makeCurlRequest($url, $method = "GET", $body = null, $destFile = null) {
    global $internalHost, $mockIp, $mockPort;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    
    // DNS Spoofing: resolve $internalHost to $mockIp
    curl_setopt($ch, CURLOPT_RESOLVE, [
        "{$internalHost}:80:{$mockIp}",
        "{$internalHost}:3000:{$mockIp}",
        "{$internalHost}:{$mockPort}:{$mockIp}"
    ]);
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Host: {$internalHost}"]);

    if ($method === "PUT" && $body) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        $contentType = "application/octet-stream";
        if (str_ends_with($url, ".m3u8")) {
            $contentType = "application/x-mpegURL";
        } elseif (str_ends_with($url, ".ts")) {
            $contentType = "video/MP2T";
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Host: {$internalHost}",
            "Content-Type: {$contentType}"
        ]);
    }

    if ($destFile) {
        $fp = fopen($destFile, "w+");
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);
        return $status === 200;
    } else {
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($status !== 200) {
            throw new Exception("HTTP request failed with code {$status}: {$response}");
        }
        return $response;
    }
}

// 2. Download raw file
$tempDir = sys_get_temp_dir() . "/job-" . $jobId;
@mkdir($tempDir, 0755, true);

$inputPath = $tempDir . "/input.mp4";
$outputDir = $tempDir . "/hls";
@mkdir($outputDir, 0755, true);

try {
    updateState("processing", 0.0);

    // Download video
    $downloadUrl = "http://{$internalHost}/{$inputKey}";
    if (!makeCurlRequest($downloadUrl, "GET", null, $inputPath)) {
        throw new Exception("Failed to download source file");
    }

    // Get Duration via ffprobe
    $durationCmd = "ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 " . escapeshellarg($inputPath);
    $duration = (float) trim(shell_exec($durationCmd));

    // Create HLS folders
    foreach (["360p", "480p", "720p", "1080p"] as $res) {
        @mkdir($outputDir . "/" . $res, 0755, true);
    }

    // Run FFMpeg
    $ffmpegCmd = "ffmpeg -i " . escapeshellarg($inputPath) . " " .
        "-filter_complex \"[0:v]split=4[v1][v2][v3][v4]; [v1]scale=-2:1080[v1out]; [v2]scale=-2:720[v2out]; [v3]scale=-2:480[v3out]; [v4]scale=-2:360[v4out]\" " .
        "-map \"[v1out]\" -map 0:a -c:v:0 libx264 -b:v:0 5000k -maxrate:v:0 5350k -bufsize:v:0 7500k -c:a:0 aac -b:a:0 192k " .
        "-map \"[v2out]\" -map 0:a -c:v:1 libx264 -b:v:1 2800k -maxrate:v:1 2996k -bufsize:v:1 4200k -c:a:1 aac -b:a:1 128k " .
        "-map \"[v3out]\" -map 0:a -c:v:2 libx264 -b:v:2 1400k -maxrate:v:2 1498k -bufsize:v:2 2100k -c:a:2 aac -b:a:2 128k " .
        "-map \"[v4out]\" -map 0:a -c:v:3 libx264 -b:v:3 800k -maxrate:v:3 856k -bufsize:v:3 1200k -c:a:3 aac -b:a:3 96k " .
        "-f hls -hls_time 6 -hls_playlist_type vod -master_pl_name master.m3u8 " .
        "-var_stream_map \"v:0,a:0 v:1,a:1 v:2,a:2 v:3,a:3\" " .
        "-hls_segment_filename " . escapeshellarg($outputDir . "/%v/segment_%03d.ts") . " " .
        escapeshellarg($outputDir . "/%v/play.m3u8") . " 2>&1";

    $handle = popen($ffmpegCmd, "r");
    if ($handle) {
        while (!feof($handle)) {
            $line = fgets($handle);
            if (preg_match('/time=([0-9:.]+)/', $line, $matches) && $duration > 0) {
                $parts = explode(":", $matches[1]);
                if (count($parts) === 3) {
                    $curSecs = (float)$parts[0] * 3600 + (float)$parts[1] * 60 + (float)$parts[2];
                    $progress = min(($curSecs / $duration) * 100, 100);
                    updateState("processing", round($progress, 2));
                }
            }
        }
        pclose($handle);
    }

    // Rename index folders
    $indexMap = ["0" => "1080p", "1" => "720p", "2" => "480p", "3" => "360p"];
    foreach ($indexMap as $idx => $name) {
        $src = $outputDir . "/" . $idx;
        $dest = $outputDir . "/" . $name;
        if (file_exists($src)) {
            @rmdir($dest);
            rename($src, $dest);
        }
    }

    // Adjust master manifest
    $masterPath = $outputDir . "/master.m3u8";
    if (file_exists($masterPath)) {
        $masterData = file_get_contents($masterPath);
        $masterData = str_replace(
            ["0/play.m3u8", "1/play.m3u8", "2/play.m3u8", "3/play.m3u8"],
            ["1080p/play.m3u8", "720p/play.m3u8", "480p/play.m3u8", "360p/play.m3u8"],
            $masterData
        );
        file_put_contents($masterPath, $masterData);
    }

    // Upload generated files back to R2
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($outputDir));
    foreach ($iterator as $file) {
        if ($file->isDir()) continue;
        $filePath = $file->getPathname();
        $relPath = str_replace("\\", "/", substr($filePath, strlen($outputDir) + 1));
        
        $uploadUrl = "http://{$internalHost}/{$outputPrefix}/{$relPath}";
        makeCurlRequest($uploadUrl, "PUT", file_get_contents($filePath));
    }

    updateState("completed", 100.0);
} catch (Exception $e) {
    updateState("failed", 0.0, $e->getMessage());
} finally {
    // Cleanup temp files
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($tempDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($files as $fileinfo) {
        $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
        @$todo($fileinfo->getRealPath());
    }
    @rmdir($tempDir);
}
