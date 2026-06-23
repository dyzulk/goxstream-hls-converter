<?php

namespace App\Services;

class JobState
{
    public static function getFilePath(string $jobId): string
    {
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

    /**
     * @return array{job_id: string, status: string, progress: float, error: string|null}|null
     */
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
        if (!is_array($data)) {
            return null;
        }
        /** @var array{job_id: string, status: string, progress: float, error: string|null} $data */
        return $data;
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
