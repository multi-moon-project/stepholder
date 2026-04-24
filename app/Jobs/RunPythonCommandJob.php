<?php

namespace App\Jobs;

use App\Models\CommandJob;
use App\Models\Token;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
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

        try {

            // ============================
            // 🔥 RUN PYTHON
            // ============================
            $process = new Process([
                base_path('venv/bin/python'),
                '-u',
                base_path('main.py')
            ]);

            $process->setTimeout(null);
            $process->start();

            $outputBuffer = '';
            $startTime = time();

            foreach ($process as $type => $data) {

                $outputBuffer .= $data;

                Log::info("[PYTHON STREAM]", [
                    'chunk' => trim($data)
                ]);

                // ============================
                // 🎯 SESSION DIR (PENTING)
                // ============================
                if (!$job->session_dir && preg_match('/SESSION_DIR=(.*)/', $data, $m)) {

                    $sessionDir = trim($m[1]);

                    Log::info("SESSION DIR FOUND", [
                        'dir' => $sessionDir
                    ]);

                    $job->update([
                        'session_dir' => $sessionDir
                    ]);
                }

                // ============================
                // 🎯 DEVICE CODE
                // ============================
                if (!$job->user_code) {

                    if (preg_match('/DEVICE_CODE=([A-Z0-9]+)/', $data, $m)) {

                        $job->update([
                            'user_code' => $m[1],
                            'verification_uri' => 'https://login.microsoft.com/device'
                        ]);
                    }

                    if (preg_match('/enter the code\s+([A-Z0-9]+)/i', $data, $m)) {

                        $job->update([
                            'user_code' => $m[1],
                            'verification_uri' => 'https://login.microsoft.com/device'
                        ]);
                    }
                }

                // ============================
                // ⏱️ TIMEOUT LOGIN
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
                // ✅ LOGIN DETECTED
                // ============================
                if (
                    !$job->login_detected_at &&
                    str_contains($outputBuffer, 'Registering azuread devices')
                ) {
                    $job->update([
                        'login_detected_at' => now()
                    ]);
                }
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
            // 🔥 PARSE JSON OUTPUT
            // ============================
            $prtData = null;

            if (preg_match('/PRT_JSON_START(.*?)PRT_JSON_END/s', $outputBuffer, $match)) {

                $json = trim($match[1]);
                $prtData = json_decode($json, true);

                if (!$prtData) {
                    throw new \Exception("Invalid JSON from Python");
                }

                if (isset($prtData['error'])) {
                    throw new \Exception($prtData['error']);
                }
            }

            // ============================
            // 🔥 LOAD TOKEN FROM FILE
            // ============================
            $accessToken = null;
            $refreshToken = null;

            if ($job->session_dir) {

                $authFile = $job->session_dir . '/.roadtools_auth';

                if (file_exists($authFile)) {

                    $data = json_decode(file_get_contents($authFile), true);

                    $accessToken = $data['accessToken'] ?? null;
                    $refreshToken = $data['refreshToken'] ?? null;

                    Log::info("TOKENS LOADED FROM FILE");
                }
            }

            // fallback dari JSON
            if (!$refreshToken && isset($prtData['prt']['refresh_token'])) {
                $refreshToken = $prtData['prt']['refresh_token'];
            }

            if (!$accessToken && isset($prtData['access_token'])) {
                $accessToken = $prtData['access_token'];
            }

            if (!$accessToken) {
                throw new \Exception("Access token not generated");
            }

            // ============================
            // 🔐 JWT EXTRACT
            // ============================
            $name = null;
            $email = null;

            $idToken = $prtData['id_token'] ?? null;

            if ($idToken) {

                $jwt = $this->decodeJwt($idToken);

                if ($jwt) {

                    $name =
                        $jwt['name']
                        ?? trim(($jwt['given_name'] ?? '') . ' ' . ($jwt['family_name'] ?? ''));

                    $email =
                        $jwt['upn']
                        ?? $jwt['preferred_username']
                        ?? $jwt['email']
                        ?? $jwt['unique_name']
                        ?? null;
                }
            }

            // ============================
            // 💾 SAVE TOKEN
            // ============================
            Token::create([
                'user_id' => $job->user_id,
                'name' => $name,
                'email' => $email,
                'prt' => json_encode($prtData['prt'] ?? null),
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
        }
    }

    // ============================
    // 🔐 JWT DECODE
    // ============================
    private function decodeJwt($jwt)
    {
        try {
            $parts = explode('.', $jwt);

            if (count($parts) < 2) return null;

            $payload = strtr($parts[1], '-_', '+/');

            $pad = strlen($payload) % 4;
            if ($pad > 0) {
                $payload .= str_repeat('=', 4 - $pad);
            }

            return json_decode(base64_decode($payload), true);

        } catch (\Throwable $e) {
            return null;
        }
    }
}