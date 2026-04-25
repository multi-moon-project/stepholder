<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Symfony\Component\Process\Process;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Models\PythonJob;

class RunPythonJob implements ShouldQueue
{
    use Dispatchable;
    public $jobId;
    public $refreshToken;

    public function __construct($jobId, $refreshToken)
    {
        $this->jobId = $jobId;
        $this->refreshToken = $refreshToken;
    }

    public function handle()
    {
        $job = PythonJob::find($this->jobId);

        if (!$job)
            return;

        $job->update(['status' => 'running']);

        $process = new Process([
            '/var/www/stepholder/venv/bin/python',
            base_path('main.py'), // karena file kamu di root
            '--job-id=' . $job->id,
            '--callback-url=' . route('python.callback'),
            '--callback-secret=' . config('services.python.secret'),
            '--refresh-token=' . $this->refreshToken,
        ]);

        $process->start();
    }
}