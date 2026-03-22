<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use App\Models\Token;
use Carbon\Carbon;

class MicrosoftGraphService
{

private $token;

public function __construct()
{

$token = Token::latest()->first();

$this->token = $token->access_token;

}


    private function getAccessToken()
{

    $tokenId = session('active_token');

    // jika session kosong atau token tidak ditemukan
    if(!$tokenId || !Token::find($tokenId)){

        $token = Token::latest()->first();

        if(!$token){
            abort(403,"Token tidak ada");
        }

        // set session otomatis
        session(['active_token'=>$token->id]);

    }else{

        $token = Token::find($tokenId);

    }

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



public function inbox($nextLink = null)
{

$accessToken = $this->getAccessToken();

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
public function read($id)
{

$accessToken = $this->getAccessToken();

$response = Http::timeout(20)
->withToken($accessToken)
->get("https://graph.microsoft.com/v1.0/me/messages/".$id,[
'$select'=>'id,subject,from,toRecipients,ccRecipients,bccRecipients,bodyPreview,receivedDateTime,hasAttachments'
]);

return $response->json();

}




public function attachments($messageId)
{

$token = $this->getAccessToken();

$response = Http::withToken($token)
->get("https://graph.microsoft.com/v1.0/me/messages/$messageId/attachments",[
'$select' => 'id,name,contentType,size'
]);

return $response->json();

}



public function downloadAttachment($messageId,$attachmentId)
{

$accessToken = $this->getAccessToken();

$response = Http::withToken($accessToken)
->withHeaders([
'Prefer'=>'IdType="ImmutableId"'
])
->get("https://graph.microsoft.com/v1.0/me/messages/$messageId/attachments/$attachmentId");

return $response->json();

}



public function search($q,$next=null)
{

$token = $this->getAccessToken();

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

public function folder($id)
{

$accessToken = $this->getAccessToken();

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



public function markRead($id)
{

$accessToken = $this->getAccessToken();

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

public function deleteMail($id)
{

$accessToken = $this->getAccessToken();

Http::withToken($accessToken)
->post("https://graph.microsoft.com/v1.0/me/messages/$id/move",[
    "destinationId" => "deleteditems"
]);

}



public function sendMail($message)
{

$token = $this->getAccessToken();

$response = Http::withToken($token)
->post('https://graph.microsoft.com/v1.0/me/sendMail', $message);

return $response->json();

}

public function conversation($conversationId, $messageId = null)
{

$accessToken = $this->getAccessToken();

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

$mail = $this->read($messageId);

$emails = [$mail];

}

return [
'value'=>$emails
];

}

public function reply($messageId,$body)
{

$accessToken = $this->getAccessToken();

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

public function forward($messageId,$to,$body)
{

$accessToken = $this->getAccessToken();

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


public function markUnread($id)
{

$accessToken = $this->getAccessToken();

Http::withToken($accessToken)
->withHeaders([
'Prefer'=>'IdType="ImmutableId"'
])
->patch("https://graph.microsoft.com/v1.0/me/messages/".$id,[
'isRead'=>false
]);

}


public function folders()
{
    $token = $this->getAccessToken();

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
public function toggleFlag($id)
{

$accessToken = $this->getAccessToken();

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


public function moveToInbox($id)
{

$token = $this->getAccessToken();

Http::withToken($token)
->post("https://graph.microsoft.com/v1.0/me/messages/$id/move",[
"destinationId"=>"inbox"
]);

}

public function emptyTrash()
{

$token = $this->getAccessToken();

$mails = Http::withToken($token)
->get("https://graph.microsoft.com/v1.0/me/mailFolders/deleteditems/messages")
->json();

foreach($mails['value'] ?? [] as $mail){

Http::withToken($token)
->delete("https://graph.microsoft.com/v1.0/me/messages/".$mail['id']);

}

}

public function deletePermanent($id)
{

    $accessToken = $this->getAccessToken();

    Http::withToken($accessToken)
    ->withHeaders([
        'Prefer'=>'IdType="ImmutableId"'
    ])
    ->delete("https://graph.microsoft.com/v1.0/me/messages/".$id);

}


public function delta($deltaLink = null)
{

$token = $this->getAccessToken();

if($deltaLink){

$response = Http::withToken($token)
->get($deltaLink);

}else{

$response = Http::withToken($token)
->get("https://graph.microsoft.com/v1.0/me/mailFolders/inbox/messages/delta",[
'$select'=>'id,subject,from,receivedDateTime,parentFolderId,isRead'
]);


}

return $response->json();

}


public function createFolder($name)
{

$token = $this->getAccessToken();

return Http::withToken($token)
->post("https://graph.microsoft.com/v1.0/me/mailFolders",[
"displayName"=>$name
])->json();

}

public function moveMail($id,$folderId)
{

$token = $this->getAccessToken();

Http::withToken($token)
->post("https://graph.microsoft.com/v1.0/me/messages/$id/move",[
"destinationId"=>$folderId
]);

}

public function post($url,$data)
{

    $token = $this->getAccessToken();

    return Http::withToken($token)
        ->post("https://graph.microsoft.com/v1.0".$url,$data)
        ->json();

}

public function archiveMail($id)
{

$token = $this->getAccessToken();

Http::withToken($token)
->post("https://graph.microsoft.com/v1.0/me/messages/$id/move",[
    "destinationId" => "archive"
]);

}

public function previewAttachment($messageId,$attachmentId)
{

$token = $this->getAccessToken();

$url = "https://graph.microsoft.com/v1.0/me/messages/$messageId/attachments/$attachmentId/\$value";

$response = Http::withToken($token)->get($url);

return response($response->body(),200)
->header('Content-Type',$response->header('Content-Type'))
->header('Content-Disposition','inline');

}

public function body($id)
{

$token = $this->getAccessToken();

$response = Http::timeout(20)
->withToken($token)
->get("https://graph.microsoft.com/v1.0/me/messages/$id",[
'$select'=>'body'
]);

return $response->json();

}

public function deleteFolder($id)
{

    $token = $this->getAccessToken();

    Http::withToken($token)
    ->delete("https://graph.microsoft.com/v1.0/me/mailFolders/".$id);

}

public function renameFolder($id,$name)
{

$token = $this->getAccessToken();

Http::withToken($token)
->patch("https://graph.microsoft.com/v1.0/me/mailFolders/".$id,[
"displayName"=>$name
]);

}

public function rules()
{

$accessToken = $this->getAccessToken();

$response = Http::withToken($accessToken)
->get("https://graph.microsoft.com/v1.0/me/mailFolders/inbox/messageRules");

return $response->json();

}

public function createRule($data)
{

$accessToken = $this->getAccessToken();

$response = Http::withToken($accessToken)
->post("https://graph.microsoft.com/v1.0/me/mailFolders/inbox/messageRules",$data);

return [
"status"=>$response->status(),
"body"=>$response->json()
];

}

public function deleteRule($id)
{

$accessToken = $this->getAccessToken();

Http::withToken($accessToken)
->delete("https://graph.microsoft.com/v1.0/me/mailFolders/inbox/messageRules/".$id);

}

public function capabilities()
{

    $token = $this->getAccessToken();

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

public function scopes()
{

    $token = $this->getAccessToken();

    $parts = explode('.', $token);

    $payload = json_decode(base64_decode($parts[1]), true);

    return $payload['scp'] ?? null;

}

public function checkRules()
{

    $token = $this->getAccessToken();

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

}