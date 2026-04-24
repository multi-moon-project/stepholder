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

        // 🔥 UNIQUE LOG ID
        $logId = 'PYJOB-' . $job->id . '-' . substr(Str::uuid(), 0, 6);

        $job->update([
            'status' => 'running',
            'started_at' => now()
        ]);

        $tempDir = storage_path('app/tmp/' . Str::uuid());

        try {

            File::makeDirectory($tempDir, 0777, true, true);

            Log::info("[$logId] STARTING PYTHON PROCESS");

            // 🔥 FORCE STDERR + STDOUT
            $cmd = base_path('venv/bin/python') . ' -u ' . base_path('main.py') . ' 2>&1';

            $process = Process::fromShellCommandline($cmd);
            $process->setWorkingDirectory($tempDir);
            $process->setTimeout(null);

            $outputBuffer = '';
            $startTime = time();

            $process->run(function ($type, $data) use ($job, &$outputBuffer, $startTime, $logId) {

                $outputBuffer .= $data;

                // 🔥 RAW STREAM LOG
                Log::info("[$logId] STREAM", [
                    'chunk' => trim($data)
                ]);

                // ============================
                // 🎯 DETECT DEVICE CODE
                // ============================
                if (!$job->user_code) {

                    if (preg_match('/code ([A-Z0-9]{6,})/i', $outputBuffer, $match)) {

                        $job->update([
                            'user_code' => $match[1],
                            'verification_uri' => 'https://login.microsoft.com/device'
                        ]);

                        Log::info("[$logId] CODE DETECTED", [
                            'code' => $match[1]
                        ]);
                    }
                }

                // ============================
                // ⏱️ TIMEOUT
                // ============================
                if (!$job->login_detected_at) {

                    if ((time() - $startTime) > $job->timeout_seconds) {

                        Log::error("[$logId] TIMEOUT");

                        throw new \Exception("Login timeout");
                    }
                }

                // ============================
                // ✅ LOGIN DETECTED
                // ============================
                if (
                    !$job->login_detected_at &&
                    str_contains($outputBuffer, 'Registering azuread devices')
                ) {
                    $job->update([
                        'login_detected_at' => now()
                    ]);

                    Log::info("[$logId] LOGIN DETECTED");
                }
            });

            // ============================
            // FINAL OUTPUT LOG
            // ============================
            Log::info("[$logId] FINAL OUTPUT", [
                'output' => $outputBuffer
            ]);

            if (!$process->isSuccessful()) {

                Log::error("[$logId] PYTHON FAILED", [
                    'output' => $outputBuffer
                ]);

                throw new \Exception($outputBuffer);
            }

            // ============================
            // PARSE JSON
            // ============================
            if (!preg_match('/PRT_JSON_START(.*?)PRT_JSON_END/s', $outputBuffer, $match)) {
                throw new \Exception("JSON output not found");
            }

            $json = trim($match[1]);
            $prtData = json_decode($json, true);

            if (!$prtData) {
                throw new \Exception("Invalid JSON");
            }

            if (isset($prtData['error'])) {
                throw new \Exception($prtData['error']);
            }

            $accessToken = $prtData['access_token'] ?? null;
            $refreshToken = $prtData['refresh_token'] ?? null;

            if (!$refreshToken && isset($prtData['prt']['refresh_token'])) {
                $refreshToken = $prtData['prt']['refresh_token'];
            }

            if (!$accessToken) {
                throw new \Exception("Access token missing");
            }

            Token::create([
                'user_id' => $job->user_id,
                'prt' => json_encode($prtData['prt']),
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'expires_at' => now()->addMinutes(50),
                'status' => 'active'
            ]);

            $job->update([
                'status' => 'success',
                'output' => $outputBuffer
            ]);

            Log::info("[$logId] SUCCESS");

        } catch (\Exception $e) {

            Log::error("[$logId] FAILED", [
                'error' => $e->getMessage()
            ]);

            $job->update([
                'status' => 'failed',
                'error' => $e->getMessage()
            ]);

        }
    }
}