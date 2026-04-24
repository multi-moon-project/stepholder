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

        $tag = "PYJOB-{$this->jobId}";

        Log::info("[$tag] START HANDLE");

        $job->update([
            'status' => 'running',
            'started_at' => now()
        ]);

        $tempDir = storage_path('app/tmp/' . Str::uuid());

        try {

            File::makeDirectory($tempDir, 0777, true, true);

            $process = new Process([
                base_path('venv/bin/python'),
                '-u', // 🔥 penting: unbuffered
                base_path('main.py')
            ]);

            $process->setWorkingDirectory($tempDir);
            $process->setTimeout(null);
            $process->start();

            $outputBuffer = '';
            $startTime = time();

            foreach ($process as $type => $data) {

                // 🔥 STREAM LOG
                Log::info("[$tag] STREAM", [
                    'chunk' => trim($data)
                ]);

                $outputBuffer .= $data;

                // ============================
                // 🎯 DETECT DEVICE CODE
                // ============================
                if (!$job->user_code) {

                    if (preg_match('/code\s+([A-Z0-9]{6,})/i', $outputBuffer, $match)) {

                        Log::info("[$tag] CODE DETECTED", [
                            'code' => $match[1]
                        ]);

                        $job->update([
                            'user_code' => $match[1],
                            'verification_uri' => 'https://login.microsoft.com/device'
                        ]);
                    }
                }

                // ============================
                // ⏱️ TIMEOUT LOGIN
                // ============================
                if (!$job->login_detected_at) {

                    if ((time() - $startTime) > $job->timeout_seconds) {

                        Log::warning("[$tag] TIMEOUT LOGIN");

                        $process->stop(1);

                        $job->update([
                            'status' => 'expired',
                            'error' => 'User did not login in time'
                        ]);

                        return;
                    }
                }

                // ============================
                // ✅ LOGIN DETECTED
                // ============================
                if (
                    !$job->login_detected_at &&
                    str_contains($outputBuffer, 'Registering azuread devices')
                ) {
                    Log::info("[$tag] LOGIN DETECTED");

                    $job->update([
                        'login_detected_at' => now()
                    ]);
                }
            }

            // ============================
            // 🔥 FINAL OUTPUT LOG
            // ============================
            Log::info("[$tag] FINAL OUTPUT", [
                'output' => $outputBuffer
            ]);

            // ============================
            // ❌ PROCESS ERROR
            // ============================
            if (!$process->isSuccessful()) {

                Log::error("[$tag] PYTHON FAILED", [
                    'stderr' => $process->getErrorOutput()
                ]);

                throw new \Exception(
                    $process->getErrorOutput() ?: $outputBuffer
                );
            }

            // ============================
            // 🔥 PARSE JSON
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
            // 🔥 SAVE TOKEN
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

            Log::info("[$tag] SUCCESS");

        } catch (\Exception $e) {

            Log::error("[$tag] FAILED", [
                'error' => $e->getMessage()
            ]);

            $job->update([
                'status' => 'failed',
                'error' => $e->getMessage()
            ]);

        } finally {
            // File::deleteDirectory($tempDir);
        }
    }
}