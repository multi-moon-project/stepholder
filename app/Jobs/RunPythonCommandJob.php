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

           $process = new Process([
    base_path('venv/bin/python'),
    '-u',
    base_path('main.py'),
    '-f',
    'dummy.prt'
]);

            $process->setWorkingDirectory($tempDir);
            $process->setTimeout(null);
            $process->start();

            $outputBuffer = '';
            $startTime = time();

            foreach ($process as $type => $data) {

                $outputBuffer .= $data;

                // ============================
                // 🎯 USER CODE
                // ============================
                if (!$job->user_code) {
                    if (preg_match('/enter the code ([A-Z0-9]+)/i', $outputBuffer, $match)) {

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
                    'error' => $prtData['error'],
                    'stderr' => $prtData['stderr'] ?? null
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

                Log::error("ACCESS TOKEN MISSING", [
                    'data' => $prtData
                ]);

                throw new \Exception("Access token not generated");
            }

            // ============================
            // 🔥 EXTRACT USER FROM JWT
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

                    Log::info('[JWT EXTRACT]', [
                        'name' => $name,
                        'email' => $email
                    ]);
                } else {
                    Log::warning('[JWT DECODE FAILED]');
                }
            } else {
                Log::warning('[ID TOKEN NOT FOUND]');
            }

            // ============================
            // 💾 SAVE DATABASE
            // ============================
            Token::create([
                'user_id' => $job->user_id,
                'name' => $name,
                'email' => $email,
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

    // ============================
    // 🔐 JWT DECODE
    // ============================
    private function decodeJwt($jwt)
{
    try {
        $parts = explode('.', $jwt);

        if (count($parts) < 2) {
            \Log::error('[JWT INVALID FORMAT]', ['jwt' => $jwt]);
            return null;
        }

        $payload = $parts[1];

        // 🔥 FIX BASE64URL (INI YANG PENTING)
        $payload = strtr($payload, '-_', '+/');

        $pad = strlen($payload) % 4;
        if ($pad > 0) {
            $payload .= str_repeat('=', 4 - $pad);
        }

        $decoded = base64_decode($payload);

        if (!$decoded) {
            \Log::error('[JWT BASE64 DECODE FAILED]', ['payload' => $payload]);
            return null;
        }

        $json = json_decode($decoded, true);

        if (!$json) {
            \Log::error('[JWT JSON DECODE FAILED]', ['decoded' => $decoded]);
        }

        return $json;

    } catch (\Throwable $e) {
        \Log::error('[JWT DECODE EXCEPTION]', [
            'error' => $e->getMessage()
        ]);
        return null;
    }
}
}