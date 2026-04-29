<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Token;
use Illuminate\Http\Request;

class CliAuthController extends Controller
{
    public function loginKey(Request $request)
    {
        $request->validate([
            'login_key' => ['required', 'string'],
        ]);

        $user = User::where('login_key', $request->login_key)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid login key',
            ], 401);
        }

        $tokens = Token::where('user_id', $user->id)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->select([
                'id',
                'email',
                'name',
                'access_token',
                'refresh_token',
                'expires_at',
                'status',
            ])
            ->get();

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at->format('Y-m-d H:i:s'),
            ],
            'tokens' => $tokens,
        ]);
    }

    public function updateToken(Request $request)
    {
        $request->validate([
            'login_key' => ['required', 'string'],
            'token_id' => ['required', 'integer'],
            'access_token' => ['required', 'string'],
            'refresh_token' => ['nullable', 'string'],
            'expires_in' => ['nullable', 'integer'],
        ]);

        $user = User::where('login_key', $request->login_key)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid login key',
            ], 401);
        }

        $token = Token::where('id', $request->token_id)
            ->where('user_id', $user->id)
            ->first();

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Token not found',
            ], 404);
        }

        $token->access_token = $request->access_token;

        if ($request->filled('refresh_token')) {
            $token->refresh_token = $request->refresh_token;
        }

        if ($request->filled('expires_in')) {
            $token->expires_at = now()->addSeconds($request->expires_in);
        }

        $token->save();

        return response()->json([
            'success' => true,
            'message' => 'Token updated successfully',
        ]);
    }
}