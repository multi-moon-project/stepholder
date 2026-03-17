<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Token;

class TokenController extends Controller
{
     public function index()
    {
        $user = auth()->user();

        $tokens = Token::where('user_id', $user->id)
                        ->latest()
                        ->get();

        return view('admin.tokens', compact('tokens'));
    }

    public function destroy($id)
{
    $token = Token::where('user_id', auth()->id())
                  ->findOrFail($id);

    $token->delete(); // soft delete

    return back()->with('success', 'Token deleted');
}
}
