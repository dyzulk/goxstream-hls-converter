<?php

require_once __DIR__ . "/vendor/autoload.php";

use Workerman\Worker;
use Workerman\Lib\Timer;
use Ghc\PhpNative\Config;
use Ghc\PhpNative\JobState;

Config::load(__DIR__ . "/.env");

$port = Config::get("PORT", "8080");

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
    $rawState = JobState::readRaw($jobId);
    if ($rawState !== null) {
        $connection->send($rawState);
    } else {
        $connection->send(json_encode([
            "job_id" => $jobId,
            "status" => "processing",
            "progress" => 0.0,
            "error" => null
        ], JSON_UNESCAPED_SLASHES));
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
            $state = JobState::read($jobId);
            $rawState = JobState::readRaw($jobId);

            if ($state !== null && $rawState !== null) {
                foreach ($connections as $connection) {
                    $connection->send($rawState);
                }
                
                // If job is completed or failed, close connections after sending final status
                if (in_array($state["status"], ["completed", "failed"])) {
                    foreach ($connections as $connection) {
                        $connection->close();
                    }
                }
            }
        }
    });
};

Worker::runAll();
