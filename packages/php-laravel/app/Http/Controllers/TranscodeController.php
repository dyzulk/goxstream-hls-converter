<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use App\Services\JobState;

class TranscodeController extends Controller
{
    public function health(): JsonResponse
    {
        return response()->json([
            "status" => "healthy",
            "service" => "video-converter"
        ]);
    }

    public function transcode(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            "job_id" => "required|string",
            "input_key" => "required|string",
            "output_prefix" => "required|string",
        ]);

        if ($validator->fails()) {
            return response()->json([
                "error" => "job_id, input_key, and output_prefix are required"
            ], 400);
        }

        $jobId = (string) $request->input("job_id");
        $inputKey = (string) $request->input("input_key");
        $outputPrefix = (string) $request->input("output_prefix");

        // Save initial state
        JobState::update($jobId, "processing", 0.0);

        // Spawn background transcoding process in a cross-platform way
        $cmd = "php " . escapeshellarg(base_path("artisan")) . " transcode:run " . escapeshellarg($jobId) . " " . escapeshellarg($inputKey) . " " . escapeshellarg($outputPrefix);
        
        if (strncasecmp(PHP_OS, "WIN", 3) === 0) {
            $handle = popen("start /B " . $cmd, "r");
            if ($handle !== false) {
                pclose($handle);
            }
        } else {
            exec($cmd . " > /dev/null 2>&1 &");
        }

        return response()->json([
            "status" => "processing",
            "job_id" => $jobId
        ]);
    }
}
