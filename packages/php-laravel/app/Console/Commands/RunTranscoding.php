<?php

namespace App\Console\Commands;

use App\Events\TranscodingProgress;
use App\Services\JobState;
use App\Services\StorageClient;
use App\Services\Transcoder;
use Exception;
use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class RunTranscoding extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'transcode:run {job_id} {input_url} {upload_url_prefix}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Download source video from URL, transcode it to HLS, and upload segments back to the upload URL prefix.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $jobId = (string) $this->argument('job_id');
        $inputUrl = (string) $this->argument('input_url');
        $uploadUrlPrefix = (string) $this->argument('upload_url_prefix');

        $this->info("Starting transcoding job: {$jobId}");

        $tempDir = sys_get_temp_dir().'/job-'.preg_replace('/[^a-zA-Z0-9_\-]/', '', $jobId);
        @mkdir($tempDir, 0755, true);

        $inputPath = $tempDir.'/input.mp4';
        $outputDir = $tempDir.'/hls';
        @mkdir($outputDir, 0755, true);

        try {
            // Update initial state
            JobState::update($jobId, 'processing', 0.0);
            broadcast(new TranscodingProgress($jobId, 'processing', 0.0));

            $storageClient = new StorageClient;
            $transcoder = new Transcoder;

            // 1. Download source video
            $this->info('Downloading source file from URL...');
            if (! $storageClient->download($inputUrl, $inputPath)) {
                throw new Exception('Failed to download source file from URL');
            }

            // 2. Transcode and report progress
            $this->info('Starting ffmpeg transcode...');
            $transcoder->transcode($jobId, $inputPath, $outputDir, function (float $progress) use ($jobId) {
                JobState::update($jobId, 'processing', $progress);
                broadcast(new TranscodingProgress($jobId, 'processing', $progress));
                $this->info("Transcoding progress: {$progress}%");
            });

            // 3. Upload generated files
            $this->info('Uploading output files...');
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($outputDir));
            foreach ($iterator as $file) {
                if ($file->isDir()) {
                    continue;
                }
                $filePath = $file->getPathname();
                $relPath = str_replace('\\', '/', substr($filePath, strlen($outputDir) + 1));

                $uploadUrl = "{$uploadUrlPrefix}/{$relPath}";
                if (! $storageClient->upload($uploadUrl, $filePath)) {
                    throw new Exception("Failed to upload output file: {$relPath}");
                }
            }

            JobState::update($jobId, 'completed', 100.0);
            broadcast(new TranscodingProgress($jobId, 'completed', 100.0));
            $this->info("Job {$jobId} completed successfully.");

        } catch (Exception $e) {
            $this->error("Job {$jobId} failed: ".$e->getMessage());
            JobState::update($jobId, 'failed', 0.0, $e->getMessage());
            broadcast(new TranscodingProgress($jobId, 'failed', 0.0, $e->getMessage()));
        } finally {
            // Cleanup temp files
            if (file_exists($tempDir)) {
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($tempDir, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::CHILD_FIRST
                );
                foreach ($files as $fileinfo) {
                    $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
                    @$todo($fileinfo->getRealPath());
                }
                @rmdir($tempDir);
            }
        }

        return Command::SUCCESS;
    }
}
