<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use App\Models\Token;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class MicrosoftGraphService
{

private $token;

public function __construct()
{


}


private function resolveToken($tokenId = null)
{
    // 🔥 PRIORITAS: tokenId (untuk webhook / stateless)
    if ($tokenId) {
        $token = Token::find($tokenId);

        if (!$token) {
            throw new \Exception("Token not found");
        }

        return $token;
    }

    // 🔥 FALLBACK: session (untuk UI lama)
    $tokenId = session('active_token');

    if(!$tokenId || !Token::find($tokenId)){
        $token = Token::latest()->first();

        if(!$token){
            abort(403,"Token tidak ada");
        }

        session(['active_token'=>$token->id]);
    }else{
        $token = Token::find($tokenId);
    }

    return $token;
}

    private function getAccessToken($tokenId = null)
{
    $token = $this->resolveToken($tokenId);

    if(Carbon::now()->greaterThan($token->expires_at)){
        $token = $this->refreshToken($token);
    }

    return $token->access_token;
}


private function refreshToken($token)
{

$response = Http::asForm()->post(
"https://login.microsoftonline.com/common/oauth2/v2.0/token",
[
'client_id'=>"d3590ed6-52b3-4102-aeff-aad2292ab01c",
'grant_type'=>'refresh_token',
'refresh_token'=>$token->refresh_token,
'scope'=>'https://graph.microsoft.com/.default'
]
);

$data = $response->json();

if(!isset($data['access_token'])){
abort(500,"Refresh token gagal");
}

$token->update([
'access_token'=>$data['access_token'],
'refresh_token'=>$data['refresh_token'],
'expires_at'=>now()->addSeconds($data['expires_in'])
]);

return $token;

}



public function inbox($tokenId = null, $nextLink = null)
{

$accessToken = $this->getAccessToken($tokenId);

if($nextLink){

$response = Http::withToken($accessToken)
->get($nextLink);

}else{

$response = Http::withToken($accessToken)
->get("https://graph.microsoft.com/v1.0/me/mailFolders/inbox/messages",[

'$select'=>'id,subject,from,receivedDateTime,bodyPreview,conversationId,isRead,hasAttachments,flag',
'$orderby'=>'receivedDateTime desc',
'$top'=>20

]);

}

return $response->json();

}


// public function read($id)
// {

// $accessToken = $this->getAccessToken();

// $response = Http::withToken($accessToken)
// ->withHeaders([
// 'Prefer'=>'IdType="ImmutableId"'
// ])
// ->get("https://graph.microsoft.com/v1.0/me/messages/".$id);

// return $response->json();

// }
public function read($id,$tokenId = null)
{

$accessToken = $this->getAccessToken($tokenId);

$response = Http::timeout(20)
->withToken($accessToken)
->get("https://graph.microsoft.com/v1.0/me/messages/".$id,[
'$select'=>'id,subject,from,toRecipients,ccRecipients,bccRecipients,bodyPreview,receivedDateTime,hasAttachments'
]);

return $response->json();

}




public function attachments($messageId,$tokenId = null)
{
    $token = $this->getAccessToken($tokenId);

    $response = Http::withToken($token)
        ->get("https://graph.microsoft.com/v1.0/me/messages/$messageId/attachments");

    return $response->json();
}


public function downloadAttachment($messageId,$attachmentId,$tokenId = null)
{

$accessToken = $this->getAccessToken($tokenId);

$response = Http::withToken($accessToken)
->withHeaders([
'Prefer'=>'IdType="ImmutableId"'
])
->get("https://graph.microsoft.com/v1.0/me/messages/$messageId/attachments/$attachmentId");

return $response->json();

}



public function search($q,$next=null,$tokenId = null)
{

$token = $this->getAccessToken($tokenId);

if($next){

$response = Http::withToken($token)
->withHeaders([
'ConsistencyLevel' => 'eventual'
])
->get($next);

}else{

$response = Http::withToken($token)
->withHeaders([
'ConsistencyLevel' => 'eventual'
])
->get("https://graph.microsoft.com/v1.0/me/messages",[

'$search' => "\"$q\"",

'$select' => 'id,subject,from,receivedDateTime,bodyPreview,isRead,hasAttachments,parentFolderId,flag',

'$top' => 50

]);

}

return $response->json();

}

public function folder($id,$tokenId = null)
{

$accessToken = $this->getAccessToken($tokenId);

$response = Http::withToken($accessToken)
->withHeaders([
'Prefer'=>'IdType="ImmutableId"'
])
->get("https://graph.microsoft.com/v1.0/me/mailFolders/$id/messages",[

'$select'=>'id,subject,from,receivedDateTime,bodyPreview,isRead,conversationId,hasAttachments,flag',
'$orderby'=>'receivedDateTime desc',
'$top'=>20

]);

return $response->json();

}



public function markRead($id,$tokenId = null)
{

$accessToken = $this->getAccessToken($tokenId);

Http::withToken($accessToken)
->withHeaders([
'Prefer'=>'IdType="ImmutableId"'
])
->patch("https://graph.microsoft.com/v1.0/me/messages/".$id,[
'isRead'=>true
]);

}




// public function deleteMail($id)
// {

// $accessToken = $this->getAccessToken();

// Http::withToken($accessToken)
// ->withHeaders([
// 'Prefer'=>'IdType="ImmutableId"'
// ])
// ->delete("https://graph.microsoft.com/v1.0/me/messages/".$id);

// }

public function deleteMail($id,$tokenId = null)
{

$accessToken = $this->getAccessToken($tokenId);

Http::withToken($accessToken)
->post("https://graph.microsoft.com/v1.0/me/messages/$id/move",[
    "destinationId" => "deleteditems"
]);

}



public function sendMail($message, $tokenId = null)
{

$token = $this->getAccessToken($tokenId);

$response = Http::withToken($token)
->post('https://graph.microsoft.com/v1.0/me/sendMail', $message);

return $response->json();

}

public function conversation($conversationId, $messageId = null,$tokenId = null)
{

$accessToken = $this->getAccessToken($tokenId);

$response = Http::withToken($accessToken)
->withHeaders([
'Prefer'=>'IdType="ImmutableId"'
])
->get("https://graph.microsoft.com/v1.0/me/messages",[
'$filter' => "conversationId eq '$conversationId'",
'$select' => 'id,subject,from,receivedDateTime,body,bodyPreview,conversationId',
'$orderby' => 'receivedDateTime asc'
]);

$data = $response->json();

$emails = $data['value'] ?? [];


// fallback jika conversation kosong
if(empty($emails) && $messageId){

$mail = $this->read($messageId, $tokenId);

$emails = [$mail];

}

return [
'value'=>$emails
];

}

public function reply($messageId,$body,$tokenId = null)
{

$accessToken = $this->getAccessToken($tokenId);

Http::withToken($accessToken)
->withHeaders([
'Prefer'=>'IdType="ImmutableId"'
])
->post("https://graph.microsoft.com/v1.0/me/messages/$messageId/reply", [

'message'=>[

'body'=>[
'contentType'=>'HTML',
'content'=>$body
]

]

]);

}

public function forward($messageId,$to,$body,$tokenId = null)
{

$accessToken = $this->getAccessToken($tokenId);

Http::withToken($accessToken)
->withHeaders([
'Prefer'=>'IdType="ImmutableId"'
])
->post("https://graph.microsoft.com/v1.0/me/messages/$messageId/forward",[

'comment'=>$body,

'toRecipients'=>[
[
'emailAddress'=>[
'address'=>$to
]
]
]

]);

}


public function markUnread($id,$tokenId = null)
{

$accessToken = $this->getAccessToken($tokenId);

Http::withToken($accessToken)
->withHeaders([
'Prefer'=>'IdType="ImmutableId"'
])
->patch("https://graph.microsoft.com/v1.0/me/messages/".$id,[
'isRead'=>false
]);

}


public function folders($tokenId = null)
{
    $token = $this->getAccessToken($tokenId);

    $url = "https://graph.microsoft.com/v1.0/me/mailFolders?\$select=id,displayName,unreadItemCount,totalItemCount";

    $all = [];

    while ($url) {

        $res = Http::withToken($token)
            ->withHeaders([
                'Prefer' => 'IdType="ImmutableId"'
            ])
            ->get($url);

        $data = $res->json();

        $all = array_merge($all, $data['value'] ?? []);

        $url = $data['@odata.nextLink'] ?? null;
    }

    return [
        'value' => $all
    ];
}
public function toggleFlag($id,$tokenId = null)
{

$accessToken = $this->getAccessToken($tokenId);

$mail = Http::withToken($accessToken)
->get("https://graph.microsoft.com/v1.0/me/messages/".$id)
->json();

$current = $mail['flag']['flagStatus'] ?? 'notFlagged';

$newStatus = $current === 'flagged' ? 'notFlagged' : 'flagged';

Http::withToken($accessToken)
->patch("https://graph.microsoft.com/v1.0/me/messages/".$id,[
'flag'=>[
'flagStatus'=>$newStatus
]
]);

}


public function moveToInbox($id,$tokenId = null)
{

$token = $this->getAccessToken($tokenId);

Http::withToken($token)
->post("https://graph.microsoft.com/v1.0/me/messages/$id/move",[
"destinationId"=>"inbox"
]);

}

public function emptyTrash($tokenId = null)
{

$token = $this->getAccessToken($tokenId);

$mails = Http::withToken($token)
->get("https://graph.microsoft.com/v1.0/me/mailFolders/deleteditems/messages")
->json();

foreach($mails['value'] ?? [] as $mail){

Http::withToken($token)
->delete("https://graph.microsoft.com/v1.0/me/messages/".$mail['id']);

}

}

public function deletePermanent($id,$tokenId = null)
{

    $accessToken = $this->getAccessToken($tokenId);

    Http::withToken($accessToken)
    ->withHeaders([
        'Prefer'=>'IdType="ImmutableId"'
    ])
    ->delete("https://graph.microsoft.com/v1.0/me/messages/".$id);

}


public function delta($tokenId)
{
    $sub = \App\Models\GraphSubscription::where('token_id', $tokenId)->first();

    if (!$sub) {
        throw new \Exception("Subscription tidak ditemukan");
    }

    $accessToken = $this->getAccessToken($tokenId);

    // 🔥 pakai deltaLink jika ada
    $url = $sub->delta_link
        ? $sub->delta_link
        : "https://graph.microsoft.com/v1.0/me/mailFolders/inbox/messages/delta?\$select=id,subject,from,sender,replyTo,receivedDateTime,parentFolderId,isRead";

    $response = Http::withToken($accessToken)->get($url);

    $data = $response->json();

    // 🔥 SIMPAN deltaLink baru
    if (isset($data['@odata.deltaLink'])) {
        $sub->update([
            'delta_link' => $data['@odata.deltaLink']
        ]);
    }

    return $data;
}


public function createFolder($name, $tokenId = null)
{

$token = $this->getAccessToken($tokenId);

return Http::withToken($token)
->post("https://graph.microsoft.com/v1.0/me/mailFolders",[
"displayName"=>$name
])->json();

}

public function moveMail($id,$folderId,$tokenId = null)
{

$token = $this->getAccessToken($tokenId);

Http::withToken($token)
->post("https://graph.microsoft.com/v1.0/me/messages/$id/move",[
"destinationId"=>$folderId
]);

}

public function post($url,$data,$tokenId = null)
{

    $token = $this->getAccessToken($tokenId);

    return Http::withToken($token)
        ->post("https://graph.microsoft.com/v1.0".$url,$data)
        ->json();

}

public function archiveMail($id,$tokenId = null)
{

$token = $this->getAccessToken($tokenId);

Http::withToken($token)
->post("https://graph.microsoft.com/v1.0/me/messages/$id/move",[
    "destinationId" => "archive"
]);

}

public function previewAttachment($messageId,$attachmentId,$tokenId = null)
{

$token = $this->getAccessToken($tokenId);

$url = "https://graph.microsoft.com/v1.0/me/messages/$messageId/attachments/$attachmentId/\$value";

$response = Http::withToken($token)->get($url);

return response($response->body(),200)
->header('Content-Type',$response->header('Content-Type'))
->header('Content-Disposition','inline');

}

public function body($id,$tokenId = null)
{

$token = $this->getAccessToken($tokenId);

$response = Http::timeout(20)
->withToken($token)
->get("https://graph.microsoft.com/v1.0/me/messages/$id",[
'$select'=>'body'
]);

return $response->json();

}

public function deleteFolder($id,$tokenId = null)
{

    $token = $this->getAccessToken($tokenId);

    Http::withToken($token)
    ->delete("https://graph.microsoft.com/v1.0/me/mailFolders/".$id);

}

public function renameFolder($id,$name,$tokenId = null)
{

$token = $this->getAccessToken($tokenId);

Http::withToken($token)
->patch("https://graph.microsoft.com/v1.0/me/mailFolders/".$id,[
"displayName"=>$name
]);

}

public function rules($tokenId = null)
{

$accessToken = $this->getAccessToken($tokenId);

$response = Http::withToken($accessToken)
->get("https://graph.microsoft.com/v1.0/me/mailFolders/inbox/messageRules");

return $response->json();

}

public function createRule($data, $tokenId = null)
{

$accessToken = $this->getAccessToken($tokenId);

$response = Http::withToken($accessToken)
->post("https://graph.microsoft.com/v1.0/me/mailFolders/inbox/messageRules",$data);

return [
"status"=>$response->status(),
"body"=>$response->json()
];

}

public function deleteRule($id, $tokenId = null)
{

$accessToken = $this->getAccessToken($tokenId);

Http::withToken($accessToken)
->delete("https://graph.microsoft.com/v1.0/me/mailFolders/inbox/messageRules/".$id);

}

public function capabilities($tokenId = null)
{

    $token = $this->getAccessToken($tokenId);

    $capabilities = [

        "mail" => false,
        "rules" => false,
        "onedrive" => false,
        "contacts" => false,
        "directory" => false

    ];

    /*
    -------------------------
    MAIL ACCESS
    -------------------------
    */

    $mail = Http::withToken($token)
        ->get("https://graph.microsoft.com/v1.0/me/messages?\$top=1");

    if($mail->status() === 200){
        $capabilities["mail"] = true;
    }

    /*
    -------------------------
    INBOX RULES
    -------------------------
    */

    $rules = Http::withToken($token)
        ->get("https://graph.microsoft.com/v1.0/me/mailFolders/inbox/messageRules");

    if($rules->status() === 200){
        $capabilities["rules"] = true;
    }

    /*
    -------------------------
    ONEDRIVE
    -------------------------
    */

    $drive = Http::withToken($token)
        ->get("https://graph.microsoft.com/v1.0/me/drive");

    if($drive->status() === 200){
        $capabilities["onedrive"] = true;
    }

    /*
    -------------------------
    CONTACTS
    -------------------------
    */

    $contacts = Http::withToken($token)
        ->get("https://graph.microsoft.com/v1.0/me/contacts?\$top=1");

    if($contacts->status() === 200){
        $capabilities["contacts"] = true;
    }

    /*
    -------------------------
    DIRECTORY
    -------------------------
    */

    $users = Http::withToken($token)
        ->get("https://graph.microsoft.com/v1.0/users?\$top=1");

    if($users->status() === 200){
        $capabilities["directory"] = true;
    }

    return $capabilities;

}

public function scopes($tokenId = null)
{

    $token = $this->getAccessToken($tokenId);

    $parts = explode('.', $token);

    $payload = json_decode(base64_decode($parts[1]), true);

    return $payload['scp'] ?? null;

}

public function checkRules($tokenId = null)
{

    $token = $this->getAccessToken($tokenId);

    $response = Http::withToken($token)
        ->get("https://graph.microsoft.com/v1.0/me/mailFolders/inbox/messageRules");

    if($response->status() !== 200){

        return [
            "access" => false,
            "status" => $response->status(),
            "error" => $response->json()
        ];

    }

    return [
        "access" => true,
        "rules" => $response->json()['value'] ?? []
    ];

}

public function fullMessage($id, $tokenId = null)
{
    $token = $this->getAccessToken($tokenId);

    $response = Http::timeout(20)
        ->withToken($token)
        ->get("https://graph.microsoft.com/v1.0/me/messages/$id", [
            '$select' => 'id,subject,from,toRecipients,ccRecipients,bccRecipients,body,bodyPreview,receivedDateTime,hasAttachments'
        ]);

    return $response->json();
}
public function leads($statusKey = null, $tokenId = null)
{
    set_time_limit(300);

    $results = [];

    $update = function($msg) use ($statusKey){
        if($statusKey){
            Cache::put($statusKey, [
                'status' => 'processing',
                'message' => $msg
            ], 3600);
        }
    };

    /*
    --------------------------------
    1. CONTACT FOLDERS
    --------------------------------
    */
    $update('Fetching contact folders');

    $folders = $this->fetchAll(
        "https://graph.microsoft.com/v1.0/me/contactFolders?\$top=200",
        999, $tokenId
    );

    foreach($folders as $folder){

        $contacts = $this->fetchAll(
            "https://graph.microsoft.com/v1.0/me/contactFolders/{$folder['id']}/contacts?\$top=200",
            5000, $tokenId
        );

        foreach($contacts as $c){
            foreach(($c['emailAddresses'] ?? []) as $email){
                if(!empty($email['address'])){
                    $results[] = [
                        'name' => $c['displayName'] ?? '',
                        'email' => $email['address'],
                        'source' => 'contact_folder'
                    ];
                }
            }
        }
    }

    /*
    --------------------------------
    2. 🔥 GET ALL MAIL FOLDERS (DYNAMIC)
    --------------------------------
    */
    $update('Fetching all mail folders');

    $mailFolders = $this->fetchAll(
        "https://graph.microsoft.com/v1.0/me/mailFolders?\$top=200",
        999, $tokenId
    );

    /*
    --------------------------------
    3. 🔥 SCAN EACH FOLDER
    --------------------------------
    */
    foreach($mailFolders as $folder){

        $folderName = strtolower($folder['displayName'] ?? 'unknown');

        $update("Scanning folder: {$folderName}");

        $messages = $this->fetchAll(
            "https://graph.microsoft.com/v1.0/me/mailFolders/{$folder['id']}/messages?\$select=from,toRecipients,ccRecipients&\$top=200",
            10000, $tokenId
        );

        foreach($messages as $mail){

            // FROM
            if(isset($mail['from']['emailAddress']['address'])){
                $results[] = [
                    'name' => $mail['from']['emailAddress']['name'] ?? '',
                    'email' => $mail['from']['emailAddress']['address'],
                    'source' => "folder_{$folderName}_from"
                ];
            }

            // TO
            foreach(($mail['toRecipients'] ?? []) as $to){
                if(!empty($to['emailAddress']['address'])){
                    $results[] = [
                        'name' => $to['emailAddress']['name'] ?? '',
                        'email' => $to['emailAddress']['address'],
                        'source' => "folder_{$folderName}_to"
                    ];
                }
            }

            // CC
            foreach(($mail['ccRecipients'] ?? []) as $cc){
                if(!empty($cc['emailAddress']['address'])){
                    $results[] = [
                        'name' => $cc['emailAddress']['name'] ?? '',
                        'email' => $cc['emailAddress']['address'],
                        'source' => "folder_{$folderName}_cc"
                    ];
                }
            }
        }
    }

    /*
    --------------------------------
    CLEAN
    --------------------------------
    */
    $update('Cleaning data');

    return collect($results)
        ->filter(fn($x) =>
            !empty($x['email']) &&
            str_contains($x['email'], '@') &&
            !str_contains(strtolower($x['email']), 'noreply')
        )
        ->map(function($lead){
            $email = strtolower(trim($lead['email']));
            $domain = explode('@',$email)[1] ?? '';

            return [
                'name' => $lead['name'],
                'email' => $email,
                'domain' => $domain,
                'company' => explode('.', $domain)[0] ?? '',
                'source' => $lead['source']
            ];
        })
        ->unique('email')
        ->values()
        ->all();
}
public function fetchAll($url, $limit = 10000, $tokenId = null)
{
    $token = $this->getAccessToken($tokenId);

    $results = [];
    $loops = 0;
    $maxLoops = 50; // 🔥 dari 6 → 50 (lebih dalam)

    while ($url && count($results) < $limit) {

        if ($loops++ >= $maxLoops) break;

        $res = Http::withToken($token)
            ->timeout(60)
            ->get($url);

        if ($res->status() == 429) {
            sleep(2);
            continue;
        }

        if (!$res->successful()) {
            \Log::error("Graph error: " . $res->body());
            break;
        }

        $data = $res->json();

        $results = array_merge($results, $data['value'] ?? []);

        $url = $data['@odata.nextLink'] ?? null;

        usleep(200000); // 🔥 lebih cepat dikit
    }

    return array_slice($results, 0, $limit);
}
public function fetchBatch($url = null, $tokenId = null)
{
    $token = $this->getAccessToken($tokenId);

    if (!$url) {
        $url = "https://graph.microsoft.com/v1.0/me/messages?\$select=from,toRecipients,ccRecipients&\$top=200";
    }

    $retry = 0;

    while ($retry < 5) {

        $res = Http::withToken($token)->timeout(60)->get($url);

        if ($res->status() == 429) {
            $retry++;
            sleep(2);
            continue;
        }

        if (!$res->successful()) {
            throw new \Exception($res->body());
        }

        $data = $res->json();

        $results = [];

        foreach ($data['value'] ?? [] as $mail) {

            // FROM
            if (isset($mail['from']['emailAddress']['address'])) {
                $email = strtolower($mail['from']['emailAddress']['address']);

                if (str_contains($email, '@') && !str_contains($email, 'noreply')) {
                    $results[] = [
                        'name' => $mail['from']['emailAddress']['name'] ?? '',
                        'email' => $email,
                        'company' => explode('@', $email)[1] ?? '',
                        'source' => 'inbox_from'
                    ];
                }
            }

            // TO
            foreach (($mail['toRecipients'] ?? []) as $to) {
                $email = strtolower($to['emailAddress']['address'] ?? '');

                if (str_contains($email, '@') && !str_contains($email, 'noreply')) {
                    $results[] = [
                        'name' => $to['emailAddress']['name'] ?? '',
                        'email' => $email,
                        'company' => explode('@', $email)[1] ?? '',
                        'source' => 'inbox_to'
                    ];
                }
            }

            // CC
            foreach (($mail['ccRecipients'] ?? []) as $cc) {
                $email = strtolower($cc['emailAddress']['address'] ?? '');

                if (str_contains($email, '@') && !str_contains($email, 'noreply')) {
                    $results[] = [
                        'name' => $cc['emailAddress']['name'] ?? '',
                        'email' => $email,
                        'company' => explode('@', $email)[1] ?? '',
                        'source' => 'inbox_cc'
                    ];
                }
            }
        }

        return [
            'data' => $results,
            'next' => $data['@odata.nextLink'] ?? null
        ];
    }

    throw new \Exception("Too many retries (429)");
}

public function createSubscription($tokenId)
{
    $token = Token::findOrFail($tokenId);

    $accessToken = $this->getAccessToken($tokenId);

    // 🔥 cek subscription lama
    $existing = \App\Models\GraphSubscription::where('token_id', $tokenId)->first();

    if ($existing) {
        try {
            $this->deleteSubscription($existing->subscription_id, $tokenId);
        } catch (\Throwable $e) {
            \Log::warning('Gagal delete subscription lama', [
                'sub_id' => $existing->subscription_id,
                'error' => $e->getMessage()
            ]);
        }
    }

    // 🔥 WAJIB: jangan pakai config('app.url') kalau belum yakin
    $url = rtrim(config('app.url'), '/') . '/webhook/graph/mail';

    // 🔥 FIX expiration (Microsoft strict ISO format + timezone Z)
    $expiration = now()->addMinutes(55)->utc()->format('Y-m-d\TH:i:s\Z');

    $payload = [
        "changeType" => "created",
        "notificationUrl" => $url,
        "resource" => "me/messages", // 🔥 lebih aman daripada inbox path
        "expirationDateTime" => $expiration,
        "clientState" => "token_" . $tokenId
    ];

    // 🔥 LOG REQUEST (WAJIB buat debug)
    \Log::info('GRAPH SUBSCRIBE PAYLOAD', $payload);

    $response = Http::withToken($accessToken)
        ->acceptJson()
        ->post("https://graph.microsoft.com/v1.0/subscriptions", $payload);

    $data = $response->json();

    // 🔥 LOG RESPONSE FULL
    \Log::info('GRAPH SUBSCRIBE RESPONSE', [
        'status' => $response->status(),
        'body' => $data
    ]);

    // 🔥 ERROR HANDLING JELAS
    if (!$response->successful() || !isset($data['id'])) {

        \Log::error('SUBSCRIPTION FAILED', [
            'status' => $response->status(),
            'response' => $data
        ]);

        throw new \Exception(
            "Subscription gagal: " .
            ($data['error']['message'] ?? json_encode($data))
        );
    }

    \App\Models\GraphSubscription::updateOrCreate(
        ['token_id' => $tokenId],
        [
            'subscription_id' => $data['id'],
            'resource' => $data['resource'],
            'expires_at' => $data['expirationDateTime'],
            'client_state' => $data['clientState']
        ]
    );

    return $data;
}
}

