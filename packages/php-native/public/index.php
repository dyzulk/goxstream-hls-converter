<?php

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
    $inputKey = $data["input_key"] ?? "";
    $outputPrefix = $data["output_prefix"] ?? "";

    if (!$jobId || !$inputKey || !$outputPrefix) {
        http_response_code(400);
        echo json_encode(["error" => "job_id, input_key, and output_prefix are required"]);
        exit;
    }

    // Save initial state to file
    $stateFile = sys_get_temp_dir() . "/job_{$jobId}.json";
    $initialState = [
        "job_id" => $jobId,
        "status" => "processing",
        "progress" => 0.0,
        "error" => null
    ];
    file_put_contents($stateFile, json_encode($initialState));

    // Spawn background transcoding process in a cross-platform way
    $cmd = "php " . escapeshellarg(dirname(__DIR__) . "/transcode.php") . " " . escapeshellarg($jobId) . " " . escapeshellarg($inputKey) . " " . escapeshellarg($outputPrefix);
    
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
