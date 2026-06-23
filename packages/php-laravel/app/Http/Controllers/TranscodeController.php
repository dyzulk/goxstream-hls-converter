<?php

namespace App\Http\Controllers;

use App\Services\JobState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TranscodeController extends Controller
{
    public function health(): JsonResponse
    {
        return response()->json([
            'status' => 'healthy',
            'service' => 'video-converter',
        ]);
    }

    public function transcode(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'job_id' => 'required|string',
            'input_url' => 'required|string',
            'upload_url_prefix' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'job_id, input_url, and upload_url_prefix are required',
            ], 400);
        }

        $jobId = (string) $request->input('job_id');
        $inputUrl = (string) $request->input('input_url');
        $uploadUrlPrefix = (string) $request->input('upload_url_prefix');

        // Save initial state
        JobState::update($jobId, 'processing', 0.0);

        // Spawn background transcoding process in a cross-platform way
        $cmd = 'php '.escapeshellarg(base_path('artisan')).' transcode:run '.escapeshellarg($jobId).' '.escapeshellarg($inputUrl).' '.escapeshellarg($uploadUrlPrefix);

        if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
            $handle = popen('start /B '.$cmd, 'r');
            if ($handle !== false) {
                pclose($handle);
            }
        } else {
            exec($cmd.' > /dev/null 2>&1 &');
        }

        return response()->json([
            'status' => 'processing',
            'job_id' => $jobId,
        ]);
    }
}
