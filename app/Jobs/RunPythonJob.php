<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Symfony\Component\Process\Process;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Models\PythonJob;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunPythonJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $jobId;
    public $refreshToken;

    public function __construct($jobId, $refreshToken = null)
    {
        $this->jobId = $jobId;
        $this->refreshToken = $refreshToken;
    }

    public function handle()
    {
        $job = PythonJob::find($this->jobId);

        if (!$job) {
            return;
        }

        // 🔥 set status awal
        $job->update(['status' => 'running']);

        // 🔥 base args
        $args = [
            '/var/www/stepholder/venv/bin/python',
            base_path('main.py'),
            '--job-id=' . $job->id,
            '--callback-url=' . route('python.callback'),
            '--callback-secret=' . config('services.python.secret'),
        ];

        // 🔥 hanya kirim refresh_token kalau ADA
        if (!empty($this->refreshToken)) {
            $args[] = '--refresh-token=' . $this->refreshToken;
        }

        $process = new Process($args);

        // 🔥 penting: beri timeout sedikit di atas python (python = 5 menit)
        $process->setTimeout(360);

        // 🔥 optional: kalau mau lihat error di log Laravel
        $process->setIdleTimeout(360);

        // 🔥 start async (tidak blocking worker)
        $process->start();

        // 🔥 optional logging (debug)
        // \Log::info('Python process started', ['job_id' => $job->id]);
    }
}