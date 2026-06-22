<?php

namespace Ghc\PhpNative;

class JobState
{
    public static function getFilePath(string $jobId): string
    {
        // Sanitizing job_id to prevent path traversal/security issues
        $safeJobId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $jobId);
        return sys_get_temp_dir() . "/job_{$safeJobId}.json";
    }

    public static function update(string $jobId, string $status, float $progress, ?string $error = null): void
    {
        $filePath = self::getFilePath($jobId);
        $state = [
            "job_id" => $jobId,
            "status" => $status,
            "progress" => $progress,
            "error" => $error
        ];
        file_put_contents($filePath, json_encode($state, JSON_UNESCAPED_SLASHES));
    }

    public static function read(string $jobId): ?array
    {
        $filePath = self::getFilePath($jobId);
        if (!file_exists($filePath)) {
            return null;
        }
        $content = file_get_contents($filePath);
        if ($content === false) {
            return null;
        }
        $data = json_decode($content, true);
        return is_array($data) ? $data : null;
    }

    public static function readRaw(string $jobId): ?string
    {
        $filePath = self::getFilePath($jobId);
        if (!file_exists($filePath)) {
            return null;
        }
        $content = file_get_contents($filePath);
        return $content !== false ? $content : null;
    }
}
