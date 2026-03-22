<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\UserSetting;
use App\Models\User;

class UserSettingController extends Controller
{
    //
 public function update(Request $request)
{
     $loginKey = auth()->user()->login_key;
    $request->validate([
        'telegram_id_1' => 'nullable|string',
        'telegram_bot_1' => 'nullable|string',
        'telegram_id_2' => 'nullable|string',
        'telegram_bot_2' => 'nullable|string',
        'login_key' => 'nullable|string',
    ]);

    $settings = UserSetting::firstOrCreate([
        'user_id' => auth()->id(),
    ]);

    $settings2 = User::firstOrCreate([
        'login_key' => $loginKey,
    ]);

    $settings->update([
        'telegram_id_1' => $request->telegram_id_1,
        'telegram_bot_1' => $request->telegram_bot_1,
        'telegram_id_2' => $request->telegram_id_2,
        'telegram_bot_2' => $request->telegram_bot_2,
        
    ]);

    $settings2->update([
        'login_key' => $request->login_key,
    ]);

    return back()->with('success', 'Telegram settings updated!');
}

public function index()
{
    $user = auth()->user();

    $loginKey = $user->login_key;
    $createdAt = $user->created_at;

    // 🔥 STATUS SUBSCRIPTION
    if ($createdAt->diffInDays(now()) > 31) {
        $status = 'expired';
    } else {
        $status = 'active';
    }

    // 🔥 DETEKSI SUB USER ATAU MAIN USER
    if ($user->is_sub_user ?? false) {

        // ✅ SUB USER → ambil dari relasi
        $tokens = $user->accessibleTokens()->count();

    } else {

        // ✅ MAIN USER → ambil dari user_id
        $tokens = \App\Models\Token::where('user_id', $user->id)->count();
    }

    return view('admin.dashboard', [
        "status"    => $status,
        "tokens"    => $tokens,
        "login_key" => $loginKey
    ]);
}


public function settings()
{
    $user = auth()->user();

    return view('admin.settings', [
        'login_key' => $user->login_key
    ]);
}

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


