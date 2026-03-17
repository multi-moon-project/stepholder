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
    public function start(Request $request)
    {
        $apiKey = $request->query('api_key');

        $user = User::where('api_key', $apiKey)->first();

        if (!$user) {
            return response()->json(["error" => "Unauthorized"], 401);
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
            return response()->json(["error" => "Failed"], 500);
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

    public function poll(Request $request, $login_id)
    {
        $apiKey = $request->query('api_key');

        $user = User::where('api_key', $apiKey)->first();

        if (!$user) {
            return response()->json(["error" => "Unauthorized"], 401);
        }

        $login = DeviceLogin::where('id', $login_id)
            ->where('user_id', $user->id)
            ->first();

        if (!$login) {
            return response()->json(["error" => "Login not found"], 404);
        }

        if ($login->completed) {
            return response()->json(["status" => "success"]);
        }

        if ($login->expires_at && now()->gt($login->expires_at)) {
            return response()->json(["status" => "expired"]);
        }

        try {

            \Log::info("=== POLL START ===", ["login_id" => $login_id]);

            // =============================
            // TOKEN REQUEST
            // =============================
            $response = Http::asForm()->post(
                "https://login.microsoftonline.com/common/oauth2/v2.0/token",
                [
                    "grant_type" => "urn:ietf:params:oauth:grant-type:device_code",
                    "client_id" => "d3590ed6-52b3-4102-aeff-aad2292ab01c",
                    "device_code" => $login->device_code
                ]
            );

            $data = $response->json();

            \Log::info("TOKEN RAW", ["status" => $response->status()]);

            // waiting login user
            if (isset($data['error']) && $data['error'] === 'authorization_pending') {
                return response()->json(["status" => "waiting"]);
            }

            // expired / reused
            if (isset($data['error']) && $data['error'] === 'invalid_grant') {
                return response()->json(["status" => "expired"]);
            }

            if (!isset($data['access_token'])) {
                return response()->json([
                    "status" => "error",
                    "error" => "no_access_token"
                ]);
            }

            $accessToken = $data['access_token'];

            \Log::info("ACCESS TOKEN OK");

            // =============================
            // 🔥 GRAPH RETRY LOOP (PENTING)
            // =============================
            $graphUser = null;

            for ($i = 0; $i < 5; $i++) {

                sleep(3); // kasih napas

                $graphResponse = Http::withToken($accessToken)
                    ->timeout(20)
                    ->get('https://graph.microsoft.com/v1.0/me');

                if ($graphResponse->status() == 200) {
                    $graphUser = $graphResponse->json();
                    break;
                }

                if ($graphResponse->status() == 429) {
                    \Log::warning("GRAPH 429 RETRY " . $i);
                    sleep(5);
                    continue;
                }

                \Log::error("GRAPH ERROR", [
                    "status" => $graphResponse->status()
                ]);

                break;
            }

            // 🔥 kalau masih gagal → balik waiting
            if (!$graphUser) {
                return response()->json([
                    "status" => "waiting"
                ]);
            }

            // =============================
            // PARSE USER
            // =============================
            $email = $graphUser['mail']
                ?? $graphUser['userPrincipalName']
                ?? null;

            $name = $graphUser['displayName'] ?? 'Microsoft User';

            if (!$email) {
                return response()->json([
                    "status" => "waiting"
                ]);
            }

            \Log::info("USER OK", ["email" => $email]);

            // =============================
            // MARK COMPLETE
            // =============================
            $login->completed = true;
            $login->save();

            // =============================
            // SAVE DB
            // =============================
            Account::create([
                'user_id' => $login->user_id,
                'provider' => 'microsoft'
            ]);

            Token::create([
                'user_id' => $login->user_id,
                'email' => $email,
                'name' => $name,
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'] ?? null,
                'expires_at' => now()->addSeconds($data['expires_in']),
                'status' => 'active',
                
            ]);

            \Log::info("LOGIN SUCCESS");

            return response()->json([
                "status" => "success"
            ]);

        } catch (\Exception $e) {

            \Log::error("FATAL ERROR", [
                "message" => $e->getMessage()
            ]);

            return response()->json([
                "status" => "error",
                "error" => "server_error"
            ]);
        }
    }
}