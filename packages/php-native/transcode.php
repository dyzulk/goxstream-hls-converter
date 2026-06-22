<?php

if (php_sapi_name() !== "cli") {
    die("Only CLI execution allowed\n");
}

require_once __DIR__ . "/vendor/autoload.php";

use Ghc\PhpNative\Config;
use Ghc\PhpNative\R2Client;
use Ghc\PhpNative\JobState;
use Ghc\PhpNative\Transcoder;

$jobId = $argv[1] ?? "";
$inputKey = $argv[2] ?? "";
$outputPrefix = $argv[3] ?? "";

if (!$jobId || !$inputKey || !$outputPrefix) {
    die("Missing arguments\n");
}

Config::load(__DIR__ . "/.env");

$tempDir = sys_get_temp_dir() . "/job-" . preg_replace('/[^a-zA-Z0-9_\-]/', '', $jobId);
@mkdir($tempDir, 0755, true);

$inputPath = $tempDir . "/input.mp4";
$outputDir = $tempDir . "/hls";
@mkdir($outputDir, 0755, true);

try {
    JobState::update($jobId, "processing", 0.0);

    $r2Client = new R2Client();
    $transcoder = new Transcoder();

    // 1. Download source video
    if (!$r2Client->download($inputKey, $inputPath)) {
        throw new Exception("Failed to download source file from R2");
    }

    // 2. Transcode and report progress
    $transcoder->transcode($jobId, $inputPath, $outputDir, function (float $progress) use ($jobId) {
        JobState::update($jobId, "processing", $progress);
    });

    // 3. Upload generated files back to R2
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($outputDir));
    foreach ($iterator as $file) {
        if ($file->isDir()) {
            continue;
        }
        $filePath = $file->getPathname();
        $relPath = str_replace("\\", "/", substr($filePath, strlen($outputDir) + 1));
        
        $r2Key = "{$outputPrefix}/{$relPath}";
        if (!$r2Client->upload($r2Key, $filePath)) {
            throw new Exception("Failed to upload output file to R2: {$relPath}");
        }
    }

    JobState::update($jobId, "completed", 100.0);
} catch (Exception $e) {
    JobState::update($jobId, "failed", 0.0, $e->getMessage());
} finally {
    // Cleanup temp files
    if (file_exists($tempDir)) {
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
}
