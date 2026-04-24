<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Models\User;
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
}