<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\MicrosoftGraphService;

class RulesController extends Controller
{

public function index(MicrosoftGraphService $graph)
{

$rules = $graph->rules()['value'] ?? [];

$folders = $graph->folders()['value'] ?? [];

return view('mail.rules',compact('rules','folders'));

}

public function store(Request $req, MicrosoftGraphService $graph)
{

$data = json_decode($req->getContent(), true);

$response = $graph->createRule($data);
dd($response);
return response()->json($response);

}

public function delete($id, MicrosoftGraphService $graph)
{

$graph->deleteRule($id);

return back();

}


}