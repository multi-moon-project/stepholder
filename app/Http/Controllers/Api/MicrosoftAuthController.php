<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\DeviceLogin;
use App\Models\Account;
use App\Models\Token;
use App\Models\User;

class MicrosoftAuthController extends Controller
{
    /**
     * START DEVICE LOGIN
     */
    public function start(Request $request)
    {
        $apiKey = $request->query('api_key');

        $user = User::where('api_key', $apiKey)->first();

        if (!$user) {
            return response()->json([
                "error" => "Unauthorized"
            ], 401);
        }

        $response = Http::asForm()->post(
            "https://login.microsoftonline.com/common/oauth2/v2.0/devicecode",
            [
                "client_id" => "d3590ed6-52b3-4102-aeff-aad2292ab01c",
                "scope" => "offline_access https://graph.microsoft.com/.default"
            ]
        );

        $data = $response->json();

        if (!isset($data['device_code'])) {
            return response()->json([
                "error" => "Failed to get device code"
            ], 500);
        }

        $login = DeviceLogin::create([
            'user_id' => $user->id,
            'device_code' => $data['device_code'],
            'user_code' => $data['user_code'],
            'expires_at' => now()->addSeconds($data['expires_in']),
            'completed' => false
        ]);

        return response()->json([
            "login_id" => $login->id,
            "verification_uri" => $data['verification_uri'],
            "user_code" => $data['user_code'],
            "interval" => $data['interval'] ?? 5
        ]);
    }

    /**
     * POLL DEVICE LOGIN
     */
    public function poll(Request $request, $login_id)
    {
        $apiKey = $request->query('api_key');

        $user = User::where('api_key', $apiKey)->first();

        if (!$user) {
            return response()->json([
                "error" => "Unauthorized"
            ], 401);
        }

        $login = DeviceLogin::where('id', $login_id)
            ->where('user_id', $user->id)
            ->first();

        if (!$login) {
            return response()->json([
                "error" => "Login not found"
            ], 404);
        }

        // expired check
        if ($login->expires_at && now()->gt($login->expires_at)) {
            return response()->json([
                "status" => "expired"
            ]);
        }

        // kalau sudah selesai
        if ($login->completed) {
            return response()->json([
                "status" => "success"
            ]);
        }

        try {

            $response = Http::asForm()->post(
                "https://login.microsoftonline.com/common/oauth2/v2.0/token",
                [
                    "grant_type" => "urn:ietf:params:oauth:grant-type:device_code",
                    "client_id" => "d3590ed6-52b3-4102-aeff-aad2292ab01c",
                    "device_code" => $login->device_code
                ]
            );

            $data = $response->json();

            // masih menunggu login
            if (isset($data['error']) && $data['error'] === 'authorization_pending') {
                return response()->json([
                    "status" => "waiting"
                ]);
            }

            // expired / invalid
            if (isset($data['error']) && $data['error'] === 'invalid_grant') {

                if ($login->completed) {
                    return response()->json([
                        "status" => "success"
                    ]);
                }

                return response()->json([
                    "status" => "expired"
                ]);
            }

            // SUCCESS LOGIN
            if (isset($data['access_token'])) {

                $accessToken = $data['access_token'];

                // ambil user data dari Microsoft
                $graphResponse = Http::withToken($accessToken)
                    ->timeout(10)
                    ->retry(3, 200)
                    ->get('https://graph.microsoft.com/v1.0/me');

                $graphUser = $graphResponse->json();

                $email = $graphUser['mail']
                    ?? $graphUser['userPrincipalName']
                    ?? 'unknown';

                $name = $graphUser['displayName']
                    ?? $email;

                // simpan account
                Account::create([
                    'user_id' => $login->user_id,
                    'provider' => 'microsoft'
                ]);

                // simpan token
                Token::create([
                    'user_id' => $login->user_id,
                    'email' => $email,
                    'name' => $name,
                    'access_token' => $data['access_token'],
                    'refresh_token' => $data['refresh_token'] ?? null,
                    'expires_at' => now()->addSeconds($data['expires_in']),
                    'status' => 'active'
                ]);

                // tandai selesai
                $login->completed = true;
                $login->save();

                return response()->json([
                    "status" => "success"
                ]);
            }

            return response()->json([
                "status" => "error",
                "error" => "unknown_error"
            ]);

        } catch (\Exception $e) {

            \Log::error("Microsoft Device Login Error", [
                "error" => $e->getMessage()
            ]);

            return response()->json([
                "status" => "error",
                "error" => "server_error"
            ]);
        }
    }
}