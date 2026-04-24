<?php

namespace App\Jobs;

use App\Models\CommandJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class RunPythonCommandJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 0;

    protected $jobId;
    protected $trace;

    public function __construct($jobId, $trace)
    {
        $this->jobId = $jobId;
        $this->trace = $trace;
    }

    public function handle(): void
    {
        $trace = $this->trace;

        Log::info("[$trace] HANDLE START");

        $job = CommandJob::find($this->jobId);

        if (!$job) {
            Log::error("[$trace] JOB NOT FOUND", [
                'job_id' => $this->jobId
            ]);
            return;
        }

        Log::info("[$trace] JOB FOUND");

        $job->update([
            'status' => 'running',
            'started_at' => now()
        ]);

        try {

            $python = base_path('venv/bin/python');
            $script = base_path('main.py');

            Log::info("[$trace] PATH CHECK", [
                'python' => $python,
                'script' => $script
            ]);

            // 🔥 TEST PYTHON
            $test = new Process([$python, '-c', 'print("HELLO_DEBUG")']);
            $test->run();

            Log::info("[$trace] PYTHON TEST", [
                'out' => $test->getOutput(),
                'err' => $test->getErrorOutput()
            ]);

            // 🔥 RUN SCRIPT
            $process = new Process([
                $python,
                '-u',
                $script
            ]);

            $process->setTimeout(null);

            Log::info("[$trace] START PROCESS");

            $buffer = '';

            $process->start();

            foreach ($process as $type => $data) {

                $buffer .= $data;

                Log::info("[$trace] PYTHON OUTPUT", [
                    'type' => $type,
                    'data' => trim($data)
                ]);

                // 🎯 DETECT DEVICE CODE
                if (!$job->user_code && preg_match('/enter the code ([A-Z0-9]+)/i', $data, $m)) {

                    Log::info("[$trace] DEVICE CODE FOUND", [
                        'code' => $m[1]
                    ]);

                    $job->update([
                        'user_code' => $m[1],
                        'verification_uri' => 'https://login.microsoft.com/device'
                    ]);
                }
            }

            Log::info("[$trace] PROCESS END", [
                'success' => $process->isSuccessful()
            ]);

            $job->update([
                'status' => 'success',
                'output' => $buffer
            ]);

        } catch (\Throwable $e) {

            Log::error("[$trace] ERROR", [
                'msg' => $e->getMessage()
            ]);

            $job->update([
                'status' => 'failed',
                'error' => $e->getMessage()
            ]);
        }

        Log::info("[$trace] HANDLE END");
    }
}