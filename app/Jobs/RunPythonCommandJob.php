<?php

namespace App\Jobs;

use App\Models\CommandJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class RunPythonCommandJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 0; // 🔥 disable timeout Laravel

    protected $jobId;

    public function __construct($jobId)
    {
        $this->jobId = $jobId;
    }

    public function handle(): void
    {
        $job = CommandJob::find($this->jobId);
        if (!$job) return;

        // 🔥 UNIQUE DEBUG ID
        $debugId = 'PYJOB-' . $job->id . '-' . substr(md5(uniqid()), 0, 6);

        Log::info("[$debugId] START JOB", [
            'job_id' => $job->id
        ]);

        $job->update([
            'status' => 'running',
            'started_at' => now()
        ]);

        $tempDir = storage_path('app/tmp/' . Str::uuid());

        try {

            File::makeDirectory($tempDir, 0777, true, true);

            // 🔥 PATH DEBUG
            $pythonPath = base_path('venv/bin/python');
            $scriptPath = base_path('main.py');

            Log::info("[$debugId] PYTHON PATH", [
                'python' => $pythonPath,
                'script' => $scriptPath
            ]);

            // 🔥 TEST PYTHON VERSION DULU
            $testProcess = new Process([$pythonPath, '-c', 'import sys; print(sys.version)']);
            $testProcess->run();

            Log::info("[$debugId] PYTHON VERSION", [
                'output' => $testProcess->getOutput(),
                'error' => $testProcess->getErrorOutput()
            ]);

            // ============================
            // 🚀 RUN MAIN.PY
            // ============================
            $process = new Process([
                $pythonPath,
                '-u',
                $scriptPath
            ]);

            $process->setWorkingDirectory($tempDir);
            $process->setTimeout(null);

            Log::info("[$debugId] START PROCESS");

            $process->start();

            $outputBuffer = '';

            foreach ($process as $type => $data) {

                // 🔥 LOG SEMUA OUTPUT
                Log::info("[$debugId] PYTHON OUTPUT", [
                    'type' => $type,
                    'chunk' => trim($data)
                ]);

                $outputBuffer .= $data;

                // ============================
                // 🎯 DETECT USER CODE
                // ============================
                if (!$job->user_code) {
                    if (preg_match('/enter the code ([A-Z0-9]+)/i', $outputBuffer, $match)) {

                        Log::info("[$debugId] USER CODE FOUND", [
                            'code' => $match[1]
                        ]);

                        $job->update([
                            'user_code' => $match[1],
                            'verification_uri' => 'https://login.microsoft.com/device'
                        ]);
                    }
                }
            }

            // ============================
            // ❌ PROCESS ERROR
            // ============================
            if (!$process->isSuccessful()) {

                Log::error("[$debugId] PYTHON FAILED", [
                    'output' => $outputBuffer,
                    'error' => $process->getErrorOutput()
                ]);

                throw new \Exception($process->getErrorOutput() ?: $outputBuffer);
            }

            Log::info("[$debugId] PROCESS FINISHED");

            // ============================
            // ✅ SUCCESS (sementara)
            // ============================
            $job->update([
                'status' => 'success',
                'output' => $outputBuffer
            ]);

        } catch (\Exception $e) {

            Log::error("[$debugId] JOB FAILED", [
                'error' => $e->getMessage()
            ]);

            $job->update([
                'status' => 'failed',
                'error' => $e->getMessage()
            ]);

        } finally {

            Log::info("[$debugId] END JOB");

            // cleanup optional
            // File::deleteDirectory($tempDir);
        }
    }
}