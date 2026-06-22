<?php

namespace Ghc\PhpNative;

use Exception;

class R2Client
{
    private string $internalHost;
    private string $mockIp;
    private string $mockPort;

    public function __construct()
    {
        $this->internalHost = Config::get("GOX_R2_INTERNAL_HOST", "r2.internal");
        $mockTarget = Config::get("GOX_R2_MOCK_RESOLVE_TARGET", "127.0.0.1:3000");
        
        $parts = explode(":", $mockTarget);
        $this->mockIp = $parts[0];
        $this->mockPort = $parts[1] ?? "80";
    }

    public function download(string $key, string $destPath): bool
    {
        $url = "http://{$this->internalHost}/{$key}";
        return $this->request($url, "GET", null, $destPath);
    }

    public function upload(string $key, string $contentOrPath): bool
    {
        $url = "http://{$this->internalHost}/{$key}";
        
        // If contentOrPath is a valid existing local file path, upload its contents
        $content = (is_file($contentOrPath) && file_exists($contentOrPath)) 
            ? file_get_contents($contentOrPath) 
            : $contentOrPath;
            
        return $this->request($url, "PUT", $content);
    }

    public function request(string $url, string $method = "GET", ?string $body = null, ?string $destFile = null): bool
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        
        // DNS Spoofing: override internal host domain to target mock ip/port
        curl_setopt($ch, CURLOPT_RESOLVE, [
            "{$this->internalHost}:80:{$this->mockIp}",
            "{$this->internalHost}:3000:{$this->mockIp}",
            "{$this->internalHost}:{$this->mockPort}:{$this->mockIp}"
        ]);
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $headers = ["Host: {$this->internalHost}"];

        if ($method === "PUT" && $body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            $contentType = "application/octet-stream";
            if (str_ends_with($url, ".m3u8")) {
                $contentType = "application/x-mpegURL";
            } elseif (str_ends_with($url, ".ts")) {
                $contentType = "video/MP2T";
            }
            $headers[] = "Content-Type: {$contentType}";
        }
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($destFile) {
            $fp = fopen($destFile, "w+");
            if (!$fp) {
                curl_close($ch);
                return false;
            }
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            fclose($fp);
            return $status === 200;
        } else {
            $response = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($status !== 200) {
                throw new Exception("HTTP request failed with code {$status}: {$response}");
            }
            return true;
        }
    }
}
