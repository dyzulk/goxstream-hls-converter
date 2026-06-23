<?php

namespace App\Services;

use Exception;

class Transcoder
{
    private string $ffmpegBin;
    private string $ffprobeBin;

    public function __construct()
    {
        $this->ffmpegBin = (string) config("services.transcoder.ffmpeg_path", "ffmpeg");
        $this->ffprobeBin = (string) config("services.transcoder.ffprobe_path", "ffprobe");
    }

    /**
     * Probes the video file using ffprobe to get its duration in seconds.
     */
    public function getDuration(string $inputPath): float
    {
        // Wrap path in quotes to handle windows spaces properly
        $cmd = escapeshellcmd($this->ffprobeBin) . " -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 " . escapeshellarg($inputPath);
        $output = shell_exec($cmd);
        if (!is_string($output)) {
            return 0.0;
        }
        return (float) trim($output);
    }

    /**
     * Splits and transcodes the input video to adaptive multi-bitrate HLS streams.
     * Reports progress via the $onProgress callback.
     */
    public function transcode(string $jobId, string $inputPath, string $outputDir, callable $onProgress): void
    {
        $duration = $this->getDuration($inputPath);

        // Pre-create output directory structure
        foreach (["360p", "480p", "720p", "1080p"] as $res) {
            @mkdir($outputDir . "/" . $res, 0755, true);
        }

        $ffmpegCmd = escapeshellcmd($this->ffmpegBin) . " -i " . escapeshellarg($inputPath) . " " .
            "-filter_complex \"[0:v]split=4[v1][v2][v3][v4]; [v1]scale=-2:1080[v1out]; [v2]scale=-2:720[v2out]; [v3]scale=-2:480[v3out]; [v4]scale=-2:360[v4out]\" " .
            "-map \"[v1out]\" -map 0:a -c:v:0 libx264 -b:v:0 5000k -maxrate:v:0 5350k -bufsize:v:0 7500k -c:a:0 aac -b:a:0 192k " .
            "-map \"[v2out]\" -map 0:a -c:v:1 libx264 -b:v:1 2800k -maxrate:v:1 2996k -bufsize:v:1 4200k -c:a:1 aac -b:a:1 128k " .
            "-map \"[v3out]\" -map 0:a -c:v:2 libx264 -b:v:2 1400k -maxrate:v:2 1498k -bufsize:v:2 2100k -c:a:2 aac -b:a:2 128k " .
            "-map \"[v4out]\" -map 0:a -c:v:3 libx264 -b:v:3 800k -maxrate:v:3 856k -bufsize:v:3 1200k -c:a:3 aac -b:a:3 96k " .
            "-f hls -hls_time 6 -hls_playlist_type vod -master_pl_name master.m3u8 " .
            "-var_stream_map \"v:0,a:0 v:1,a:1 v:2,a:2 v:3,a:3\" " .
            "-hls_segment_filename " . escapeshellarg($outputDir . "/%v/segment_%03d.ts") . " " .
            escapeshellarg($outputDir . "/%v/play.m3u8") . " 2>&1";

        $handle = popen($ffmpegCmd, "r");
        if (!$handle) {
            throw new Exception("Failed to execute ffmpeg process");
        }

        while (!feof($handle)) {
            $line = fgets($handle);
            if (is_string($line) && preg_match('/time=([0-9:.]+)/', $line, $matches) && $duration > 0) {
                $parts = explode(":", $matches[1]);
                if (count($parts) === 3) {
                    $curSecs = (float)$parts[0] * 3600 + (float)$parts[1] * 60 + (float)$parts[2];
                    $progress = min(($curSecs / $duration) * 100, 100);
                    $onProgress(round($progress, 2));
                }
            }
        }
        pclose($handle);

        // Rename index folder names from numbers to resolution tags
        $indexMap = ["0" => "1080p", "1" => "720p", "2" => "480p", "3" => "360p"];
        foreach ($indexMap as $idx => $name) {
            $src = $outputDir . "/" . $idx;
            $dest = $outputDir . "/" . $name;
            if (file_exists($src)) {
                if (file_exists($dest)) {
                    // Clean up if it exists
                    $this->deleteDirectory($dest);
                }
                rename($src, $dest);
            }
        }

        // Adjust master manifest to point to named resolution playlists
        $masterPath = $outputDir . "/master.m3u8";
        if (file_exists($masterPath)) {
            $masterData = file_get_contents($masterPath);
            if (is_string($masterData)) {
                $masterData = str_replace(
                    ["0/play.m3u8", "1/play.m3u8", "2/play.m3u8", "3/play.m3u8"],
                    ["1080p/play.m3u8", "720p/play.m3u8", "480p/play.m3u8", "360p/play.m3u8"],
                    $masterData
                );
                file_put_contents($masterPath, $masterData);
            }
        } else {
            throw new Exception("Transcoding completed, but master manifest was not created");
        }
    }

    private function deleteDirectory(string $dir): void
    {
        if (!file_exists($dir)) return;
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->deleteDirectory("$dir/$file") : unlink("$dir/$file");
        }
        rmdir($dir);
    }
}
