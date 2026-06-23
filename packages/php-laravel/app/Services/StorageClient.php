<?php

namespace App\Services;

use Exception;

class StorageClient
{
    public function download(string $url, string $destPath): bool
    {
        if ($url === '') {
            return false;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        $fp = fopen($destPath, 'w+');
        if (! $fp) {
            curl_close($ch);

            return false;
        }
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);

        return $status === 200;
    }

    public function upload(string $url, string $contentOrPath): bool
    {
        if ($url === '') {
            return false;
        }

        $content = (is_file($contentOrPath) && file_exists($contentOrPath))
            ? (file_get_contents($contentOrPath) ?: '')
            : $contentOrPath;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $content);

        $contentType = 'application/octet-stream';
        if (str_ends_with($url, '.m3u8')) {
            $contentType = 'application/x-mpegURL';
        } elseif (str_ends_with($url, '.ts')) {
            $contentType = 'video/MP2T';
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: {$contentType}",
        ]);

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status !== 200) {
            $responseStr = is_string($response) ? $response : '';
            throw new Exception("HTTP upload failed with code {$status}: {$responseStr}");
        }

        return true;
    }
}
