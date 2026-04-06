<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\MicrosoftGraphService;
use App\Models\MailRule;

class RulesController extends Controller
{

/* ======================
LOAD RULES PAGE
====================== */
public function index()
{
    $tokenId = session('active_token');

    if (!$tokenId) {
        abort(400, 'No active token');
    }

    $rules = MailRule::where('token_id', $tokenId)
        ->where('is_active', true)
        ->orderBy('priority')
        ->get();

    $graph = app(\App\Services\MicrosoftGraphService::class);

    $folders = $graph->folders($tokenId)['value'] ?? [];

    return view('mail.rules', [
        'rules' => $rules,
        'folders' => $folders
    ]);
}

/* ======================
CREATE RULE
====================== */
public function store(Request $request)
{
    $tokenId = $request->get('token_id');

    if (!$tokenId) {
        return response()->json(['error' => 'No token'], 400);
    }

    $rule = MailRule::create([
        'token_id' => $tokenId,
        'name' => $request->displayName,
        'condition_type' => $request->conditionType,
        'condition_value' => $request->conditionValue,
        'action_delete' => $request->delete ?? false,
        'action_read' => $request->read ?? false,
        'action_folder' => $request->folder ?: null,
        'priority' => 0,
        'is_active' => true
    ]);

    return response()->json([
        'status' => 'ok',
        'rule' => $rule
    ]);
}

/* ======================
DELETE RULE
====================== */
public function delete(Request $request, $id)
{
    $tokenId = $request->get('token_id');

    MailRule::where('id', $id)
        ->where('token_id', $tokenId)
        ->delete();

    return response()->json([
        'status' => 'deleted'
    ]);
}

/* ======================
GET RULES JSON (REALTIME)
====================== */
public function json(Request $request)
{
    $tokenId = $request->get('token_id') ?? session('active_token');

    $rules = MailRule::where('token_id', $tokenId)
        ->where('is_active', true)
        ->orderBy('priority')
        ->get();

    return response()->json([
        'rules' => $rules
    ]);
}

/* ======================
UPDATE RULE
====================== */
public function update(Request $request, $id)
{
    $tokenId = $request->get('token_id');

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

    return response()->json([
        'status' => 'updated'
    ]);
}

}