<?php

require_once __DIR__ . "/vendor/autoload.php";

use Workerman\Worker;
use Workerman\Lib\Timer;
use Ghc\PhpNative\Config;
use Ghc\PhpNative\JobState;

Config::load(__DIR__ . "/.env");

$port = Config::get("PORT", "8080");

class MyWorker extends Worker
{
    public $onWebSocketConnect;
}

// Start a WebSocket server on the configured port
$wsWorker = new MyWorker("websocket://0.0.0.0:{$port}");

// Track connection mapped to job IDs
$connectionsByJob = [];
$jobIdsByConnectionId = [];

$wsWorker->onConnect = function($connection) {
    // Handled in onWebSocketConnect once HTTP headers are parsed
};

$wsWorker->onWebSocketConnect = function($connection, $httpHeader) use (&$connectionsByJob, &$jobIdsByConnectionId) {
    // Parse job_id query parameter
    $jobId = $_GET["job_id"] ?? "";
    if (!$jobId) {
        $connection->close();
        return;
    }
    
    $connId = $connection->id;
    $jobIdsByConnectionId[$connId] = $jobId;
    $connectionsByJob[$jobId][$connId] = $connection;

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

$wsWorker->onClose = function($connection) use (&$connectionsByJob, &$jobIdsByConnectionId) {
    $connId = $connection->id;
    if (isset($jobIdsByConnectionId[$connId])) {
        $jobId = $jobIdsByConnectionId[$connId];
        unset($connectionsByJob[$jobId][$connId]);
        if (empty($connectionsByJob[$jobId])) {
            unset($connectionsByJob[$jobId]);
        }
        unset($jobIdsByConnectionId[$connId]);
    }
};

// Periodic timer to poll job state files and broadcast to clients
$wsWorker->onWorkerStart = function() use (&$connectionsByJob) {
    Timer::add(1.0, function() use (&$connectionsByJob) {
        foreach ($connectionsByJob as $jobId => $connections) {
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
