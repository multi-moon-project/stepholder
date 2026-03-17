<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\DeviceLogin;
use App\Models\Account;
use App\Models\Token;

class MicrosoftAuthController extends Controller
{
    public function start()
{

    $response = Http::asForm()->post(
        "https://login.microsoftonline.com/common/oauth2/v2.0/devicecode",
        [
            "client_id" => "d3590ed6-52b3-4102-aeff-aad2292ab01c",
            "scope" => "offline_access https://graph.microsoft.com/.default"
        ]
    );

    $data = $response->json();

    $login = DeviceLogin::create([
        'user_id' => 1, // sementara hardcode
        'device_code' => $data['device_code'],
        'user_code' => $data['user_code'],
        'expires_at' => now()->addSeconds($data['expires_in'])
    ]);

  return response()->json([
    "login_id" => $login->id,
    "verification_uri" => $data['verification_uri'],
    "user_code" => $data['user_code'],
    "interval" => $data['interval'] ?? 5
]);

}

public function poll($login_id)
{
    $login = DeviceLogin::findOrFail($login_id);

    // jika sudah selesai jangan call microsoft lagi
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

        // masih menunggu user login
        if (isset($data['error']) && $data['error'] == 'authorization_pending') {
            return response()->json([
                "status" => "waiting"
            ]);
        }

        // device code sudah pernah dipakai
        if (isset($data['error']) && $data['error'] == 'invalid_grant') {

            if ($login->completed) {
                return response()->json([
                    "status" => "success"
                ]);
            }

            return response()->json([
                "status" => "expired"
            ]);
        }

        // jika token berhasil didapat
        if (isset($data['access_token'])) {

            $accessToken = $data['access_token'];

            // ambil data user dari microsoft graph
            $response = Http::withToken($accessToken)
                ->timeout(10)
                ->retry(3, 200)
                ->get('https://graph.microsoft.com/v1.0/me');

            $user = $response->json();

            $email =
                $user['mail']
                ?? $user['userPrincipalName']
                ?? 'unknown';

            $name =
                $user['displayName']
                ?? $email;

            // simpan account
            $account = Account::create([
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
                'expires_at' => now()->addSeconds($data['expires_in'])
            ]);

            // tandai login selesai
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
