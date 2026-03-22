<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Token;
use Illuminate\Support\Facades\Auth;

class AuthenticateWithToken
{
    public function handle(Request $request, Closure $next)
    {
        // 🔹 ambil token dari header
        $rawToken = $request->bearerToken();

        if (!$rawToken) {
            return response()->json(['error' => 'Token not provided'], 401);
        }

        // 🔹 hash token
        $hashed = hash('sha256', $rawToken);

        // 🔹 cari token
        $token = Token::where('access_token', $hashed)->first();

        if (!$token || !$token->isValid()) {
            return response()->json(['error' => 'Invalid or expired token'], 401);
        }

        // 🔹 login sebagai owner
        $owner = $token->user;
        Auth::login($owner);

        $user = Auth::user();

        // 🔥 kalau sub-user (login pakai session misalnya)
        if ($request->has('sub_user_id')) {

            $subUser = \App\Models\User::find($request->sub_user_id);

            if (!$subUser || $subUser->owner_id !== $owner->id) {
                return response()->json(['error' => 'Invalid sub user'], 403);
            }

            // 🔹 cek apakah sub-user boleh akses token ini
            $allowed = $subUser->accessibleTokens()
                ->where('tokens.id', $token->id)
                ->exists();

            if (!$allowed) {
                return response()->json(['error' => 'Forbidden (token access denied)'], 403);
            }

            // 🔹 override auth jadi sub-user
            Auth::login($subUser);
        }

        // 🔹 inject token ke request (biar bisa dipakai di controller)
        $request->attributes->set('current_token', $token);

        return $next($request);
    }
}