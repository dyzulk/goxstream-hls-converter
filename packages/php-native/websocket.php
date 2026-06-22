<?php

require_once __DIR__ . "/vendor/autoload.php";
use Workerman\Worker;
use Workerman\Lib\Timer;

// Load env
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

$port = getenv("PORT") ?: "8080";
// Start a WebSocket server on the configured port
$wsWorker = new Worker("websocket://0.0.0.0:{$port}");

// Track connection mapped to job IDs
$wsWorker->connectionsByJob = [];

$wsWorker->onConnect = function($connection) {
    // Handled in onWebSocketConnect once HTTP headers are parsed
};

$wsWorker->onWebSocketConnect = function($connection, $httpHeader) use ($wsWorker) {
    // Parse job_id query parameter
    $jobId = $_GET["job_id"] ?? "";
    if (!$jobId) {
        $connection->close();
        return;
    }
    
    $connection->jobId = $jobId;
    $wsWorker->connectionsByJob[$jobId][$connection->id] = $connection;

    // Send initial state immediately
    $stateFile = sys_get_temp_dir() . "/job_{$jobId}.json";
    if (file_exists($stateFile)) {
        $connection->send(file_get_contents($stateFile));
    } else {
        $connection->send(json_encode([
            "job_id" => $jobId,
            "status" => "processing",
            "progress" => 0,
            "error" => null
        ]));
    }
};

$wsWorker->onClose = function($connection) use ($wsWorker) {
    $jobId = $connection->jobId ?? "";
    if ($jobId && isset($wsWorker->connectionsByJob[$jobId][$connection->id])) {
        unset($wsWorker->connectionsByJob[$jobId][$connection->id]);
        if (empty($wsWorker->connectionsByJob[$jobId])) {
            unset($wsWorker->connectionsByJob[$jobId]);
        }
    }
};

// Periodic timer to poll job state files and broadcast to clients
$wsWorker->onWorkerStart = function() use ($wsWorker) {
    Timer::add(1.0, function() use ($wsWorker) {
        foreach ($wsWorker->connectionsByJob as $jobId => $connections) {
            $stateFile = sys_get_temp_dir() . "/job_{$jobId}.json";
            if (file_exists($stateFile)) {
                $data = file_get_contents($stateFile);
                $state = json_decode($data, true);
                
                foreach ($connections as $connection) {
                    $connection->send($data);
                }
                
                // If job is completed or failed, close connections after sending final status
                if ($state && in_array($state["status"], ["completed", "failed"])) {
                    foreach ($connections as $connection) {
                        $connection->close();
                    }
                }
            }
        }
    });
};

Worker::runAll();
