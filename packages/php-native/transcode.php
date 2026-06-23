<?php

if (php_sapi_name() !== "cli") {
    die("Only CLI execution allowed\n");
}

require_once __DIR__ . "/vendor/autoload.php";

use Ghc\PhpNative\Config;
use Ghc\PhpNative\StorageClient;
use Ghc\PhpNative\JobState;
use Ghc\PhpNative\Transcoder;

$jobId = $argv[1] ?? "";
$inputUrl = $argv[2] ?? "";
$uploadUrlPrefix = $argv[3] ?? "";

if (!$jobId || !$inputUrl || !$uploadUrlPrefix) {
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

    $storageClient = new StorageClient();
    $transcoder = new Transcoder();

    // 1. Download source video
    if (!$storageClient->download($inputUrl, $inputPath)) {
        throw new Exception("Failed to download source file from URL");
    }

    // 2. Transcode and report progress
    $transcoder->transcode($jobId, $inputPath, $outputDir, function (float $progress) use ($jobId) {
        JobState::update($jobId, "processing", $progress);
    });

    // 3. Upload generated files
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($outputDir));
    foreach ($iterator as $file) {
        if ($file->isDir()) {
            continue;
        }
        $filePath = $file->getPathname();
        $relPath = str_replace("\\", "/", substr($filePath, strlen($outputDir) + 1));
        
        $uploadUrl = "{$uploadUrlPrefix}/{$relPath}";
        if (!$storageClient->upload($uploadUrl, $filePath)) {
            throw new Exception("Failed to upload output file: {$relPath}");
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

