<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Token;

class SubUserController extends Controller
{
    /**
     * 🔹 LIST PAGE
     */
    public function index()
    {
        $owner = auth()->user();

        // ambil sub-user + token yang di-assign
        $subUsers = User::where('owner_id', $owner->id)
            ->with('accessibleTokens')
            ->get();

        // ambil semua token milik owner
        $tokens = Token::where('user_id', $owner->id)->get();

        return view('admin.sub-user', compact('subUsers', 'tokens'));
    }

    /**
     * 🔹 CREATE SUB USER (pakai login_key + assign token)
     */
    public function store(Request $request)
    {
        $request->validate([
            'login_key' => 'required|string|unique:users,login_key',
            'token_ids' => 'nullable|array'
        ]);

        // buat sub-user
        $subUser = User::create([
            'login_key' => $request->login_key,
            'owner_id' => auth()->id(),
        ]);

        // assign token (bisa multiple)
        if ($request->token_ids) {
            $subUser->accessibleTokens()->sync($request->token_ids);
        }

        return back()->with('success', 'Sub user created');
    }

    /**
     * 🔹 UPDATE TOKEN ACCESS (optional, kalau mau edit nanti)
     */
    public function assignTokens(Request $request, $id)
    {
        $subUser = User::findOrFail($id);

        // 🔒 pastikan milik owner
        if ($subUser->owner_id !== auth()->id()) {
            abort(403, 'Unauthorized');
        }

        $subUser->accessibleTokens()->sync($request->token_ids ?? []);

        return back()->with('success', 'Token updated');
    }

    /**
     * 🔹 DELETE SUB USER (optional tapi penting)
     */
    public function destroy($id)
    {
        $subUser = User::findOrFail($id);

        if ($subUser->owner_id !== auth()->id()) {
            abort(403);
        }

        $subUser->delete();

        return back()->with('success', 'Sub user deleted');
    }
    public function updateKey(Request $request, $id)
{
    $request->validate([
        'login_key' => 'required|string|unique:users,login_key,' . $id
    ]);

    $subUser = User::findOrFail($id);

    if ($subUser->owner_id !== auth()->id()) {
        abort(403);
    }

    $subUser->update([
        'login_key' => $request->login_key
    ]);

    return back()->with('success', 'Login key updated');
}
}
