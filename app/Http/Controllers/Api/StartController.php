<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PowerShellService;
use Illuminate\Support\Facades\Http;

class StartController extends Controller
{

   public function start(PowerShellService $ps)
{

    $data = $ps->start();

    cache()->put('device_code', $data['device_code'], 900);

    return response()->json([
        "status" => "device_login_required",
        "verification_uri" => $data['verification_uri'],
        "user_code" => $data['user_code'],
        "expires_in" => $data['expires_in']
    ]);

}

public function poll()
{
    try {

        $device_code = cache()->get('device_code');

        if(!$device_code){
            return response()->json([
                "status" => "no_device_code"
            ]);
        }

        $response = Http::asForm()->post(
            "https://login.microsoftonline.com/common/oauth2/v2.0/token",
            [
                "grant_type" => "urn:ietf:params:oauth:grant-type:device_code",
                "client_id" => "d3590ed6-52b3-4102-aeff-aad2292ab01c",
                "device_code" => $device_code
            ]
        );

        $data = $response->json();

        if(!$data){
            return response()->json([
                "status" => "empty_response"
            ]);
        }

        // Jika masih menunggu login user
        if(isset($data['error'])){

            if($data['error'] == "authorization_pending"){
                return response()->json([
                    "status" => "waiting"
                ]);
            }

            // jika device code sudah expired
            if($data['error'] == "expired_token"){
                cache()->forget('device_code');

                return response()->json([
                    "status" => "expired"
                ]);
            }

            return response()->json([
                "status" => "oauth_error",
                "error" => $data['error']
            ]);
        }

        // LOGIN BERHASIL
        if(isset($data['access_token'])){

            // HAPUS device_code supaya tidak dipakai lagi
            cache()->forget('device_code');

            return response()->json([
                "status" => "success",
                "access_token" => $data['access_token'],
                "refresh_token" => $data['refresh_token'] ?? null
            ]);
        }

        return response()->json([
            "status" => "unknown",
            "data" => $data
        ]);

    } catch (\Throwable $e) {

        return response()->json([
            "status" => "exception",
            "message" => $e->getMessage()
        ]);
    }
}

}