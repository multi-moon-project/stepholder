<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\MicrosoftGraphService;
use App\Models\MailRule;

class RulesController extends Controller
{

public function index()
{
    $tokenId = session('active_token');

    $rules = MailRule::where('token_id', $tokenId)
        ->where('is_active', true)
        ->orderBy('priority')
        ->get();

    return view('mail.rules', [
        'rules' => $rules,
        'folders' => app(\App\Services\MicrosoftGraphService::class)->folders()['value'] ?? []
    ]);
}

public function store(Request $request)
{
    $tokenId = session('active_token');

    $rule = MailRule::create([
        'token_id' => $tokenId,
        'name' => $request->displayName,
        'condition_type' => $request->conditionType,
        'condition_value' => $request->conditionValue,
        'action_delete' => $request->delete ?? false,
        'action_read' => $request->read ?? false,
        'action_folder' => $request->folder ?: null,
        'priority' => 0
    ]);

    return response()->json([
        'status' => 'ok',
        'rule' => $rule
    ]);
}

public function delete($id)
{
    $tokenId = session('active_token');

    MailRule::where('id', $id)
        ->where('token_id', $tokenId)
        ->delete();

    return response()->json([
        'status' => 'deleted'
    ]);
}


public function json()
{
    $tokenId = session('active_token');

    $rules = MailRule::where('token_id', $tokenId)
        ->where('is_active', true)
        ->orderBy('priority')
        ->get();

    return response()->json([
        'rules' => $rules
    ]);
}

public function update(Request $request, $id)
{
    $tokenId = session('active_token');

    $rule = MailRule::where('id', $id)
        ->where('token_id', $tokenId)
        ->firstOrFail();

    $rule->update([
        'name' => $request->displayName,
        'condition_type' => $request->conditionType,
        'condition_value' => $request->conditionValue,
        'action_delete' => $request->delete ?? false,
        'action_read' => $request->read ?? false,
        'action_folder' => $request->folder ?: null,
    ]);

    return response()->json(['status' => 'updated']);
}

}