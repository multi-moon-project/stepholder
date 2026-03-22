<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class CloudflareService
{
    protected $accountId;
    protected $token;

    public function __construct()
    {
        $this->accountId = config('services.cloudflare.account_id');
        $this->token = config('services.cloudflare.token');
    }

    public function createWorker($scriptName, $scriptContent)
    {
        $url = "https://api.cloudflare.com/client/v4/accounts/{$this->accountId}/workers/scripts/{$scriptName}";

        $response = Http::withToken($this->token)
            ->withBody($scriptContent, 'application/javascript')
            ->put($url);

        if (!$response->successful()) {

            $error = $response->json();

            if (isset($error['errors'][0]['message'])) {
                throw new \Exception($error['errors'][0]['message']);
            }

            throw new \Exception('Cloudflare unknown error');
        }

        return $response->json();
    }

    public function workerExists($scriptName)
{
    $url = "https://api.cloudflare.com/client/v4/accounts/{$this->accountId}/workers/scripts/{$scriptName}";

    $response = Http::withToken($this->token)
        ->timeout(10)
        ->get($url);

    if ($response->status() === 200) {
        return true; // worker ada
    }

    if ($response->status() === 404) {
        return false; // worker tidak ada
    }

    // 🔥 DEBUG ERROR SEBENARNYA
    throw new \Exception('Cloudflare error: ' . $response->body());
}

    public function enableWorkersDev($scriptName)
    {
        $url = "https://api.cloudflare.com/client/v4/accounts/{$this->accountId}/workers/scripts/{$scriptName}/subdomain";

        $response = Http::withToken($this->token)
            ->post($url, [
                'enabled' => true
            ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to enable workers.dev: ' . json_encode($response->json()));
        }

        return $response->json();
    }
}