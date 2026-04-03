<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class GraphWebhookController extends Controller
{
    public function handle(Request $request)
    {
        // 🔥 WAJIB: VALIDATION TOKEN (Microsoft Graph)
        if ($request->has('validationToken')) {
            return response($request->get('validationToken'), 200)
                ->header('Content-Type', 'text/plain');
        }

        // 🔥 DEBUG LOG
        \Log::info("WEBHOOK HIT", $request->all());

        // 🔥 TRIGGER REALTIME (SSE)
        cache()->put('mail_ping', now()->timestamp);

        return response()->json(['ok' => true]);
    }
}