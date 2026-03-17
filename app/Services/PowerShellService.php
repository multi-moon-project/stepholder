<?php

namespace App\Services;

use Symfony\Component\Process\Process;

class PowerShellService
{
    public function start()
    {

        $process = new Process([
            'pwsh',
            '-ExecutionPolicy','Bypass',
            '-File',
            base_path('scripts/start.ps1')
        ]);

        $process->setTimeout(10);

        $process->run();

        if (!$process->isSuccessful()) {
            throw new \Exception($process->getErrorOutput());
        }

        return json_decode($process->getOutput(), true);
    }
}