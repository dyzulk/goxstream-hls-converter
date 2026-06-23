<?php

require_once dirname(__DIR__) . "/vendor/autoload.php";

use Ghc\PhpNative\JobState;

header("Content-Type: application/json");

// Simple router for PHP built-in server
$uri = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);

if ($uri === "/health" && $_SERVER["REQUEST_METHOD"] === "GET") {
    echo json_encode(["status" => "healthy", "service" => "video-converter"]);
    exit;
}

if ($uri === "/transcode" && $_SERVER["REQUEST_METHOD"] === "POST") {
    $body = file_get_contents("php://input");
    $data = json_decode($body, true);

    $jobId = $data["job_id"] ?? "";
    $inputUrl = $data["input_url"] ?? "";
    $uploadUrlPrefix = $data["upload_url_prefix"] ?? "";

    if (!$jobId || !$inputUrl || !$uploadUrlPrefix) {
        http_response_code(400);
        echo json_encode(["error" => "job_id, input_url, and upload_url_prefix are required"]);
        exit;
    }

    // Save initial state
    JobState::update($jobId, "processing", 0.0);

    // Spawn background transcoding process in a cross-platform way
    $cmd = "php " . escapeshellarg(dirname(__DIR__) . "/transcode.php") . " " . escapeshellarg($jobId) . " " . escapeshellarg($inputUrl) . " " . escapeshellarg($uploadUrlPrefix);
    
    if (strncasecmp(PHP_OS, "WIN", 3) === 0) {
        pclose(popen("start /B " . $cmd, "r"));
    } else {
        exec($cmd . " > /dev/null 2>&1 &");
    }

    http_response_code(200);
    echo json_encode(["status" => "processing", "job_id" => $jobId]);
    exit;
}

http_response_code(404);
echo json_encode(["error" => "Not Found"]);
