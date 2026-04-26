<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\DeviceLogin;
use App\Models\User;
use App\Jobs\PollMicrosoftDeviceLoginJob;

class MicrosoftAuthController extends Controller
{
    private string $clientId = "d3590ed6-52b3-4102-aeff-aad2292ab01c";

    // =========================================
    // 🚀 START LOGIN
    // =========================================
    public function start(Request $request)
    {
        $apiKey = $request->query('api_key');

        $user = User::where('api_key', $apiKey)->first();

        if (!$user) {
            return response()->json(["error" => "Unauthorized"], 401);
        }

        try {

            // =============================
            // REQUEST DEVICE CODE
            // =============================
            $response = Http::asForm()->post(
                "https://login.microsoftonline.com/common/oauth2/v2.0/devicecode",
                [
                    "client_id" => $this->clientId,
                    "scope" => "offline_access https://graph.microsoft.com/.default"
                ]
            );

            $data = $response->json();

            if (!isset($data['device_code'])) {

                \Log::error("DEVICE CODE FAILED", [
                    "response" => $data
                ]);

                return response()->json(["error" => "Failed to get device code"], 500);
            }

            // =============================
            // SIMPAN KE DB
            // =============================
            $login = DeviceLogin::create([
                'user_id' => $user->id,
                'device_code' => $data['device_code'],
                'user_code' => $data['user_code'],
                'expires_at' => now()->addSeconds($data['expires_in']),
                'completed' => false,

                // 🔥 QUEUE FIELD
                'status' => 'pending',
                'interval' => $data['interval'] ?? 5,
                'next_poll_at' => now()->addSeconds($data['interval'] ?? 5),
                'retry_count' => 0
            ]);

            // =============================
            // 🚀 DISPATCH JOB
            // =============================
            PollMicrosoftDeviceLoginJob::dispatch($login->id)->onQueue('auth');

            return response()->json([
                "login_id" => $login->id,
                "verification_uri" => $data['verification_uri'],
                "user_code" => $data['user_code'],
                "interval" => $data['interval'] ?? 5,
                "status" => "pending"
            ]);

        } catch (\Exception $e) {

            \Log::error("START ERROR", [
                "message" => $e->getMessage()
            ]);

            return response()->json([
                "error" => "server_error"
            ], 500);
        }
    }

    // =========================================
    // 🔍 POLL STATUS (NO MICROSOFT CALL)
    // =========================================
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

        // =============================
        // HANDLE EXPIRED
        // =============================
        if ($login->expires_at && now()->gt($login->expires_at) && !$login->completed) {

            if ($login->status !== 'expired') {
                $login->update([
                    'status' => 'expired'
                ]);
            }
        }

        // =============================
        // RETURN STATUS DARI DB
        // =============================
        return response()->json([
            "login_id" => $login->id,
            "status" => $login->status,
            "completed" => $login->completed,
            "expires_at" => $login->expires_at,
            "next_poll_at" => $login->next_poll_at,
            "retry_count" => $login->retry_count,
            "last_error" => $login->last_error
        ]);
    }
}