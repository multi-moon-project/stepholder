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

            // 🚀 RUN PYTHON
            $process = new Process([
                base_path('venv/bin/python'),
                '-u', // 🔥 IMPORTANT: realtime output
                base_path('main.py')
            ]);

            $process->setWorkingDirectory($tempDir);
            $process->setTimeout(null);
            $process->start();

            $outputBuffer = '';
            $startTime = time();

            // ============================
            // 🔥 REALTIME LOOP (FIX)
            // ============================
            while ($process->isRunning()) {

                $outputBuffer .= $process->getIncrementalOutput();
                $outputBuffer .= $process->getIncrementalErrorOutput();

                // DEBUG LOG
                Log::info('[PYTHON STREAM]', ['out' => $outputBuffer]);

                // ============================
                // 🎯 USER CODE DETECT
                // ============================
                if (!$job->user_code) {

                    if (preg_match('/enter the code ([A-Z0-9]+)/i', $outputBuffer, $match)) {

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
                // ⏱️ TIMEOUT
                // ============================
                if (!$job->login_detected_at) {

                    if ((time() - $startTime) > $job->timeout_seconds) {

                        $process->stop(1);

                        $job->update([
                            'status' => 'expired',
                            'error' => 'User did not login in time'
                        ]);

                        return;
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

                usleep(300000); // 🔥 prevent CPU 100%
            }

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

            // ============================
            // ❌ PYTHON ERROR
            // ============================
            if (isset($prtData['error'])) {

                Log::error("PYTHON TOKEN ERROR", [
                    'error' => $prtData['error']
                ]);

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