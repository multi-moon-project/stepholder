<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use App\Models\User;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
   public function store(Request $request)
{
    $request->validate([
        'login_key' => 'required|string',
    ]);

    // Cari user berdasarkan login_key
    $user = User::where('login_key', $request->login_key)->first();

    if (! $user) {
        return back()->withErrors([
            'login_key' => 'Key not Found!.',
        ]);
    }

    // Login tanpa password
    Auth::login($user);

    $request->session()->regenerate();

    return redirect()->intended('/dashboard');
}

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
