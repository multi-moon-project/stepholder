<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\UserSetting;
use App\Models\User;

class UserSettingController extends Controller
{
    /**
     * Tampilkan halaman settings
     */
    public function settings()
    {
        $user = auth()->user();

        // Ambil settings user atau buat baru jika belum ada
        $settings = UserSetting::firstOrCreate([
            'user_id' => $user->id,
        ]);

        return view('admin.settings', [
            'login_key' => $user->login_key,
            'settings' => $settings
        ]);
    }

    /**
     * Update login_key dan Telegram settings
     */
    public function update(Request $request)
    {
        $request->validate([
            'login_key' => 'nullable|string|max:255',
            'telegram_id_1' => 'nullable|string',
            'telegram_bot_1' => 'nullable|string',
            'telegram_id_2' => 'nullable|string',
            'telegram_bot_2' => 'nullable|string',
        ]);

        $user = auth()->user();

        // Ambil atau buat settings user
        $settings = UserSetting::firstOrCreate([
            'user_id' => $user->id,
        ]);

        // Update Telegram settings
        $settings->update([
            'telegram_id_1' => $request->telegram_id_1,
            'telegram_bot_1' => $request->telegram_bot_1,
            'telegram_id_2' => $request->telegram_id_2,
            'telegram_bot_2' => $request->telegram_bot_2,
        ]);

        // Update login_key jika ada input
        if ($request->filled('login_key')) {
            $user->update([
                'login_key' => $request->login_key
            ]);
        }

        return back()->with('success', 'Settings updated successfully!');
    }

    /**
     * Dashboard (status subscription & token count)
     */
    public function index()
    {
        $user = auth()->user();

        $loginKey = $user->login_key;
        $createdAt = $user->created_at;

        // 🔥 STATUS SUBSCRIPTION
        $status = $createdAt->diffInDays(now()) > 31 ? 'expired' : 'active';

        // 🔥 DETEKSI ROLE
        if ($user->owner_id) {
            // ✅ SUB USER
            $tokens = $user->accessibleTokens()->count();
        } else {
            // ✅ MAIN USER
            $tokens = \App\Models\Token::where('user_id', $user->id)->count();
        }

        return view('admin.dashboard', [
            "status" => $status,
            "tokens" => $tokens,
            "login_key" => $loginKey
        ]);
    }

    /**
     * Hanya update login_key
     */
    public function updateKey(Request $request)
    {
        $request->validate([
            'login_key' => 'required|string|max:255'
        ]);

        $user = auth()->user();
        $user->login_key = $request->login_key;
        $user->save();

        return back()->with('success', 'Login key updated!');
    }
}