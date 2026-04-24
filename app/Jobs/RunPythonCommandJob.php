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

        $tag = "PYJOB-{$job->id}";

        Log::info("[$tag] START");

        $job->update([
            'status' => 'running',
            'started_at' => now()
        ]);

        try {

            // ============================
            // 🚀 COMMAND FIX
            // ============================
            $process = new Process([
                base_path('venv/bin/python'),
                '-u',
                base_path('main.py'),
                '-f',
                $job->file ?? 'dummy.prt' // 🔥 FIX PENTING
            ]);

            Log::info("[$tag] CMD", [
                'cmd' => $process->getCommandLine()
            ]);

            $process->setTimeout(null);
            $process->start();

            $buffer = '';
            $startTime = time();

            // ============================
            // 🔄 STREAM OUTPUT
            // ============================
            foreach ($process as $type => $data) {

                $buffer .= $data;

                Log::info("[$tag] OUTPUT", [
                    'chunk' => trim($data)
                ]);

                // ============================
                // 🎯 DETECT DEVICE CODE
                // ============================
                if (!$job->user_code) {
                    if (preg_match('/enter the code ([A-Z0-9]+)/i', $buffer, $match)) {

                        Log::info("[$tag] CODE FOUND", [
                            'code' => $match[1]
                        ]);

                        $job->update([
                            'user_code' => $match[1],
                            'verification_uri' => 'https://login.microsoft.com/device'
                        ]);
                    }
                }

                // ============================
                // ⏱️ TIMEOUT
                // ============================
                if ((time() - $startTime) > $job->timeout_seconds) {

                    Log::warning("[$tag] TIMEOUT");

                    $process->stop(1);

                    $job->update([
                        'status' => 'expired',
                        'error' => 'Login timeout'
                    ]);

                    return;
                }
            }

            // ============================
            // ❌ ERROR
            // ============================
            if (!$process->isSuccessful()) {

                Log::error("[$tag] PYTHON FAILED", [
                    'output' => $buffer,
                    'error' => $process->getErrorOutput()
                ]);

                throw new \Exception($process->getErrorOutput() ?: $buffer);
            }

            // ============================
            // 🔥 PARSE JSON
            // ============================
            if (!preg_match('/PRT_JSON_START(.*?)PRT_JSON_END/s', $buffer, $match)) {
                throw new \Exception("JSON not found");
            }

            $json = trim($match[1]);
            $data = json_decode($json, true);

            if (!$data) {
                throw new \Exception("Invalid JSON");
            }

            // ============================
            // 💾 SAVE TOKEN
            // ============================
            Token::create([
                'user_id' => $job->user_id,
                'prt' => json_encode($data['prt'] ?? []),
                'access_token' => $data['access_token'] ?? null,
                'refresh_token' => $data['refresh_token'] ?? null,
                'expires_at' => now()->addMinutes(50),
                'status' => 'active'
            ]);

            $job->update([
                'status' => 'success',
                'output' => $buffer
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
        }
    }
}