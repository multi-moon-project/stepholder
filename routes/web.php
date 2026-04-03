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

/*
|--------------------------------------------------------------------------
| AUTH (PUBLIC)
|--------------------------------------------------------------------------
*/

// Route::middleware('token.auth')->get('/test', function () {
//     return response()->json([
//         'user' => auth()->user(),
//         'owner_id' => auth()->user()->getOwnerId(),
//     ]);
// });

// Route::get('/leads/test', [MicrosoftInboxController::class, 'leads']);

Route::get('/webhook/graph/subscribe', function (MicrosoftGraphService $graph) {

    $token = (new ReflectionClass($graph))
        ->getMethod('getAccessToken')
        ->invoke($graph);

    $url = "https://yourdomain.com/webhook/graph/mail"; // 🔥 GANTI DOMAIN LO

    $response = Http::withToken($token)->post(
        "https://graph.microsoft.com/v1.0/subscriptions",
        [
            "changeType" => "created",
            "notificationUrl" => $url,
            "resource" => "me/mailFolders('inbox')/messages",
            "expirationDateTime" => now()->addMinutes(60)->toIso8601String(),
            "clientState" => "mySecret123"
        ]
    );

    return response()->json([
        'status' => $response->status(),
        'response' => $response->json()
    ]);
});

Route::match(['GET','POST'], '/webhook/graph/mail', function (\Illuminate\Http\Request $request) {

    // ======================
    // 1. VALIDATION (WAJIB 🔥)
    // ======================
    if ($request->has('validationToken')) {
        return response($request->get('validationToken'), 200)
            ->header('Content-Type', 'text/plain');
    }

    // ======================
    // 2. LOG PAYLOAD
    // ======================
    \Log::info('GRAPH WEBHOOK HIT', [
        'headers' => $request->headers->all(),
        'body' => $request->all()
    ]);

    // ======================
    // 3. RESPOND FAST (WAJIB < 3 DETIK)
    // ======================
    return response()->json(['status' => 'ok']);
});

Route::get('/mail/{messageId}/attachment/{attachmentId}/preview',
    [MicrosoftInboxController::class, 'attachmentPreview']
)->name('mail.attachment.preview');


Route::middleware('auth')->group(function () {

    Route::get('/leads', [MicrosoftInboxController::class, 'leadsPage']);

    Route::post('/leads/start', [MicrosoftInboxController::class, 'startExtraction']);
    Route::get('/leads/status', [MicrosoftInboxController::class, 'leadsStatus']);
    Route::get('/leads/data', [MicrosoftInboxController::class, 'leadsData']);

    Route::get('/leads/refresh', [MicrosoftInboxController::class, 'refreshLeads']);

    Route::get('/leads/export/{type}', [MicrosoftInboxController::class, 'exportLeads']);

});

Route::middleware('auth')->group(function () {


    Route::get('/workers', [CloudflareWorkerController::class, 'index']);
    Route::post('/workers', [CloudflareWorkerController::class, 'store']);
    Route::post('/cloudflare/save', [CloudflareWorkerController::class, 'save']);
    Route::delete('/workers/{id}', [CloudflareWorkerController::class, 'destroy']);
    Route::get('/mail/full/{id}', [MicrosoftInboxController::class, 'full']);
});

Route::get('/workers/check-name', [CloudflareWorkerController::class, 'checkName']);
Route::get('/sub-users', [SubUserController::class, 'index']);
Route::post('/sub-users', [SubUserController::class, 'store']);
Route::post('/sub-users/{id}/tokens', [SubUserController::class, 'assignTokens']);

Route::delete('/sub-users/{id}', [SubUserController::class, 'destroy']);
Route::post('/sub-users/{id}/tokens', [SubUserController::class, 'assignTokens']);

Route::get('/api/sub-user/{id}', function($id){
    $user = \App\Models\User::with('accessibleTokens')->findOrFail($id);

    return [
        'tokens' => $user->accessibleTokens->pluck('id')
    ];
});

Route::post('/sub-users/{id}/update-key', [SubUserController::class, 'updateKey']);

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
| PROTECTED ROUTES (WAJIB LOGIN)
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {

    // DASHBOARD
    Route::get('/dashboard', [UserSettingController::class, 'index'])->name('dashboard');

    // TOKENS
    Route::get('/tokens', [TokenController::class, 'index']);
    Route::delete('/tokens/{id}', [TokenController::class, 'destroy']);

    Route::get('/tokens/status', function () {
        return \App\Models\Token::select('id', 'status', 'expires_at')->get();
    });

    // SETTINGS
    Route::get('/settings', [UserSettingController::class, 'settings']);
    Route::post('/settings/update', [UserSettingController::class, 'updateKey']);

    /*
    |--------------------------------------------------------------------------
    | MAIL SYSTEM
    |--------------------------------------------------------------------------
    */

    Route::get('/war', fn() => view('war'));

    Route::get('api/search', [MicrosoftInboxController::class, 'searchApi']);

    Route::get('/onedrive/folder/{id}', [MicrosoftInboxController::class,'oneDriveFolder']);
    Route::get('/onedrive/files',[MicrosoftInboxController::class,'oneDriveFiles']);
    Route::get('/rules/check',[MicrosoftInboxController::class,'checkRules']);

    Route::get('/token/scopes', function(MicrosoftGraphService $graph){
        return ["scopes"=>$graph->scopes()];
    });

    // folder
    Route::get('/folders',[MicrosoftInboxController::class,'folders']);
    Route::post('/folder/create',[MicrosoftInboxController::class,'createFolder']);
    Route::post('/mail/move',[MicrosoftInboxController::class,'move']);
    Route::post('/mail/archive/{id}', [MicrosoftInboxController::class,'archive']);

    Route::get('/mail/item/{id}', [MicrosoftInboxController::class,'mailItem']);
    Route::delete('/folder/delete/{id}', [MicrosoftInboxController::class,'deleteFolder']);
    Route::patch('/folder/rename/{id}', [MicrosoftInboxController::class,'renameFolder']);

    Route::get('/inbox', [MicrosoftInboxController::class,'inbox']);
    Route::get('/folder/{folder}', [MicrosoftInboxController::class,'folder']);
    Route::get('/mail/delta',[MicrosoftInboxController::class,'delta']);

    Route::get('/mail/attachment/{messageId}/{attachmentId}', [MicrosoftInboxController::class,'attachmentPreview']);

    // RULES
Route::get('/rules/json', [RulesController::class, 'json']);

    Route::get('/settings/rules',[RulesController::class,'index']);
    Route::post('/settings/rules',[RulesController::class,'store']);
    Route::delete('/settings/rules/{id}',[RulesController::class,'delete']);

    /*
    |--------------------------------------------------------------------------
    | MAIL ACTIONS
    |--------------------------------------------------------------------------
    */

    Route::get('/mail/compose', [MicrosoftInboxController::class,'compose']);
    Route::post('/mail/send', [MicrosoftInboxController::class,'send']);

    Route::get('/mail/preview/{id}',[MicrosoftInboxController::class,'preview']);
    Route::get('/mail/thread/{conversation}',[MicrosoftInboxController::class,'conversation']);

    Route::get('/mail/{id}/attachments',[MicrosoftInboxController::class,'attachments']);
    Route::get('/mail/{messageId}/attachment/{attachmentId}',[MicrosoftInboxController::class,'downloadAttachment']);

    Route::get('/mail/delete/{id}', [MicrosoftInboxController::class, 'deleteMail']);
    Route::get('/mail/unread/{id}',[MicrosoftInboxController::class,'markUnread']);
    Route::get('/mail/flag/{id}',[MicrosoftInboxController::class,'toggleFlag']);

    Route::get('/mail/reply/{id}', [MicrosoftInboxController::class,'replyForm']);
    Route::get('/mail/reply-all/{id}', [MicrosoftInboxController::class,'replyAllForm']);
    Route::get('/mail/forward/{id}', [MicrosoftInboxController::class,'forwardForm']);
    Route::get('/mail/read/{id}', [MicrosoftInboxController::class,'markRead']);

    // delete
    Route::get('/mail/recover/{id}', [MicrosoftInboxController::class,'recover']);
    Route::get('/mail/empty-trash', [MicrosoftInboxController::class,'emptyTrash']);
    Route::get('/mail/delete-permanent/{id}',[MicrosoftInboxController::class,'deletePermanent']);

    /*
    |--------------------------------------------------------------------------
    | ACCOUNT SWITCH
    |--------------------------------------------------------------------------
    */

   Route::get('/switch-account/{id}', function ($id) {
    $user = auth()->user();

    if ($user->isSubUser()) {
        $allowed = $user->accessibleTokens()
            ->where('tokens.id', $id)
            ->exists();

        if (!$allowed) {
            abort(403);
        }
    } else {
        $owned = \App\Models\Token::where('user_id', $user->id)
            ->where('id', $id)
            ->exists();

        if (!$owned) {
            abort(403);
        }
    }

    session(['active_token' => $id]);

    return redirect('/inbox');
});
    /*
    |--------------------------------------------------------------------------
    | FALLBACK MAIL
    |--------------------------------------------------------------------------
    */

    Route::get('/mail/{id}', [MicrosoftInboxController::class,'read']);

});