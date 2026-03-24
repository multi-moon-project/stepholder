<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\MicrosoftGraphService;
use App\Models\Token;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class MicrosoftInboxController extends Controller
{


public function inbox(Request $request, MicrosoftGraphService $graph)
{

$next = $request->next;

$data = $graph->inbox($next);



$emails = collect($data['value'] ?? [])
->filter(fn($mail)=>isset($mail['id']))
->map(function($mail){

return [

'id'=>$mail['id'],

'subject'=>$mail['subject'] ?? '',

'from'=>$mail['from']['emailAddress']['name']
?? $mail['from']['emailAddress']['address']
?? 'Unknown',

'isRead'=>$mail['isRead'] ?? true,
'flagged' => ($mail['flag']['flagStatus'] ?? '') === 'flagged',

'receivedDateTime'=>$mail['receivedDateTime'] ?? null,

'conversationId' => $mail['conversationId'] ?? null,

'bodyPreview'=>$mail['bodyPreview'] ?? '',

/* FIX */
'folder' => 'Inbox'

];

})
->values()
->all();

$nextLink = $data['@odata.nextLink'] ?? null;

$folders = $graph->folders()['value'] ?? [];

return view('mail.inbox',[
'emails'=>$emails,
'nextLink'=>$nextLink,
'folders'=>$folders
]);

}



public function read($id, MicrosoftGraphService $graph)
{

$mail = $graph->read($id);

return view('mail.mail',compact('mail'));

}



public function attachments($id, MicrosoftGraphService $graph)
{

$data = $graph->attachments($id);

$attachments = $data['value'] ?? [];

return view('mail.attachments',compact('attachments','id'));

}



public function downloadAttachment($messageId,$attachmentId, MicrosoftGraphService $graph)
{

$data = $graph->downloadAttachment($messageId,$attachmentId);

$file = base64_decode($data['contentBytes']);

return response($file)
->header('Content-Type',$data['contentType'])
->header('Content-Disposition','attachment; filename="'.$data['name'].'"');

}


public function search(Request $request, MicrosoftGraphService $graph)
{

$q = $request->q;

$data = $graph->search($q);

$emails = collect(
$data['value'][0]['hitsContainers'][0]['hits'] ?? []
)->pluck('resource');

$folders = $graph->folders()['value'] ?? [];

return view('mail.inbox',compact('emails','folders'));

}



public function folder($folder, MicrosoftGraphService $graph)
{
    $data = $graph->folder($folder);

    $emails = collect($data['value'] ?? [])
        ->map(function($mail){
            return [
                'id' => $mail['id'] ?? null,
                'subject' => $mail['subject'] ?? '',
                'from' => $mail['from']['emailAddress']['name']
                    ?? $mail['from']['emailAddress']['address']
                    ?? 'Unknown',
                'isRead' => $mail['isRead'] ?? true,
                'hasAttachments' => $mail['hasAttachments'] ?? false,
                'flagged' => ($mail['flag']['flagStatus'] ?? '') === 'flagged',
                'receivedDateTime' => $mail['receivedDateTime'] ?? null,
                'bodyPreview' => $mail['bodyPreview'] ?? ''
            ];
        })
        ->values()
        ->all();

    $nextLink = $data['@odata.nextLink'] ?? null;

    // 🔥 TAMBAHKAN INI
    $folders = $graph->folders()['value'] ?? [];

    return view('mail.inbox', [
        'emails' => $emails,
        'nextLink' => $nextLink,
        'folders' => $folders
    ]);
}


public function markRead($id, MicrosoftGraphService $graph)
{

$graph->markRead($id);

return response()->json([
"status"=>"ok"
]);

}




public function deleteMail($id, MicrosoftGraphService $graph)
{

$graph->deleteMail($id);

return redirect('/inbox');

}



// public function sendMail(Request $request, MicrosoftGraphService $graph)
// {

// $graph->sendMail(
// $request->to,
// $request->subject,
// $request->body
// );

// return redirect('/inbox');

// }

// public function send(Request $req)
// {

// $graph = app(\App\Services\MicrosoftGraphService::class);

// $graph->sendMail([
// "message"=>[
// "subject"=>$req->subject,

// "body"=>[
// "contentType"=>"HTML",
// "content"=>$req->body
// ],

// "toRecipients"=>[
// [
// "emailAddress"=>[
// "address"=>$req->to
// ]
// ]
// ]
// ],

// "saveToSentItems"=>true

// ]);

// return response()->json([
// "status"=>"sent"
// ]);

// }

public function send(Request $request)
{

$graph = app(\App\Services\MicrosoftGraphService::class);

$attachments = [];

/*
--------------------------------
ATTACHMENTS
--------------------------------
*/

if($request->hasFile('attachments')){

foreach($request->file('attachments') as $file){

$attachments[] = [

'@odata.type' => '#microsoft.graph.fileAttachment',

'name' => $file->getClientOriginalName(),

'contentBytes' => base64_encode(
file_get_contents($file->getRealPath())
)

];

}

}


/*
--------------------------------
TO RECIPIENTS
--------------------------------
*/

$toRecipients = collect(explode(',', $request->to))
->map(fn($e)=>trim($e))
->filter()
->map(fn($email)=>[
"emailAddress"=>[
"address"=>$email
]
])->values()->toArray();


/*
--------------------------------
CC RECIPIENTS
--------------------------------
*/

$ccRecipients = collect(explode(',', $request->cc ?? ''))
->map(fn($e)=>trim($e))
->filter()
->map(fn($email)=>[
"emailAddress"=>[
"address"=>$email
]
])->values()->toArray();


/*
--------------------------------
BCC RECIPIENTS
--------------------------------
*/

$bccRecipients = collect(explode(',', $request->bcc ?? ''))
->map(fn($e)=>trim($e))
->filter()
->map(fn($email)=>[
"emailAddress"=>[
"address"=>$email
]
])->values()->toArray();


/*
--------------------------------
MESSAGE PAYLOAD
--------------------------------
*/

$message = [

"message" => [

"subject" => $request->subject,

"body" => [

"contentType" => "HTML",

"content" => $request->body

],

"toRecipients" => $toRecipients

],

"saveToSentItems" => true

];


/*
--------------------------------
ADD CC IF EXISTS
--------------------------------
*/

if(!empty($ccRecipients)){

$message["message"]["ccRecipients"] = $ccRecipients;

}


/*
--------------------------------
ADD BCC IF EXISTS
--------------------------------
*/

if(!empty($bccRecipients)){

$message["message"]["bccRecipients"] = $bccRecipients;

}


/*
--------------------------------
ADD ATTACHMENTS
--------------------------------
*/

if(!empty($attachments)){

$message["message"]["attachments"] = $attachments;

}


/*
--------------------------------
SEND MAIL
--------------------------------
*/

$graph->sendMail($message);


/*
--------------------------------
RESPONSE
--------------------------------
*/

return response()->json([
"status" => "sent"
]);

}
public function conversation($conversationId, Request $request, MicrosoftGraphService $graph)
{

$messageId = $request->message;

$data = $graph->conversation($conversationId,$messageId);

$emails = $data['value'] ?? [];

$attachments = [];

if($messageId){
    $att = $graph->attachments($messageId);
    $attachments = $att['value'] ?? [];
}

return view('mail.thread',compact('emails','attachments','messageId'));

}

public function reply(Request $request, MicrosoftGraphService $graph)
{

$messageId = $request->message_id;

$body = $request->body;

$graph->reply($messageId,$body);

return redirect('/inbox');

}

public function forward(Request $request, MicrosoftGraphService $graph)
{

$messageId = $request->message_id;

$to = $request->to;

$body = $request->body;

$graph->forward($messageId,$to,$body);

return redirect('/inbox');

}

// public function preview($id, MicrosoftGraphService $graph)
// {

// $mail = $graph->read($id);

// $att = $graph->attachments($id);

// $attachments = $att['value'] ?? [];

// $messageId = $id;

// return view('mail.preview',compact('mail','attachments','messageId'));

// }
public function preview($id, MicrosoftGraphService $graph)
{
    $mail = $graph->read($id);

    $body = $graph->body($id);
    $mail['body'] = $body['body'] ?? null;

    $att = $graph->attachments($id);
    $attachments = $att['value'] ?? [];

    /* ======================
    MAP CID → ATTACHMENT ID
    ====================== */
    $cidMap = collect($attachments)
        ->filter(fn($a) => !empty($a['contentId']))
        ->mapWithKeys(function($a){
            return [
                strtolower(trim($a['contentId'], '<>')) => $a['id']
            ];
        });

    /* ======================
    REPLACE CID IN BODY
    ====================== */
    $bodyContent = $mail['body']['content'] ?? '';

    $bodyContent = preg_replace_callback(
        '/cid:([^"\']+)/i',
        function($matches) use ($cidMap, $id){

            $cid = strtolower(trim($matches[1], '<>'));

            foreach($cidMap as $key => $attachmentId){

                // FLEXIBLE MATCH (WAJIB, karena CID Outlook random)
                if(str_contains($cid, $key) || str_contains($key, $cid)){
                    
                    return route('mail.attachment.preview', [
                        'messageId' => $id,
                        'attachmentId' => $attachmentId
                    ]);
                }
            }

            return $matches[0];
        },
        $bodyContent
    );

    $mail['body']['content'] = $bodyContent;
    $messageId = $id;

    return view('mail.preview', compact(
        'mail',
        'attachments',
        'messageId'
    ));
}

public function markUnread($id, MicrosoftGraphService $graph)
{

$graph->markUnread($id);

return response()->json([
"success"=>true
]);

}

// public function searchApi(Request $request, MicrosoftGraphService $graph)
// {

// $q = $request->q;

// $data = $graph->search($q);

// $emails = collect($data['value'] ?? [])
// ->map(function($mail){

// return [

// 'id'=>$mail['id'] ?? null,

// 'subject'=>$mail['subject'] ?? '',

// 'from'=>$mail['from']['emailAddress']['name']
// ?? $mail['from']['emailAddress']['address']
// ?? 'Unknown',

// 'isRead'=>$mail['isRead'] ?? true,
// 'flagged' => ($mail['flag']['flagStatus'] ?? '') === 'flagged',
// 'receivedDateTime'=>$mail['receivedDateTime'] ?? null,
// 'hasAttachments' => $mail['hasAttachments'] ?? false,

// 'bodyPreview'=>$mail['bodyPreview'] ?? ''

// ];

// });

// return response()->json([
// 'emails'=>$emails
// ]);

// }

public function searchApi(Request $request, MicrosoftGraphService $graph)
{

$q = trim($request->q);
$next = $request->next;

/* ======================
PARSE ADVANCED SEARCH
====================== */

$filters = [
'text' => [],
'from' => null,
'subject' => null,
'attachment' => false,
'folder' => null
];

$parts = explode(" ", $q);

foreach($parts as $p){

if(str_starts_with($p,'from:')){
$filters['from'] = substr($p,5);
continue;
}

if(str_starts_with($p,'subject:')){
$filters['subject'] = substr($p,8);
continue;
}

if($p === 'has:attachment'){
$filters['attachment'] = true;
continue;
}

if(str_starts_with($p,'folder:')){
$filters['folder'] = strtolower(substr($p,7));
continue;
}

$filters['text'][] = $p;

}

$textSearch = implode(" ",$filters['text']);

/* ======================
GRAPH SEARCH
====================== */

$data = $graph->search($textSearch ?: $q,$next);

$folders = $graph->folders()['value'] ?? [];

$folderMap = collect($folders)
->mapWithKeys(fn($f)=>[
$f['id'] => $f['displayName']
]);

/* ======================
MAP EMAIL DATA
====================== */

$emails = collect($data['value'] ?? [])
->map(function($mail) use ($folderMap){

$folderName = $folderMap[$mail['parentFolderId'] ?? ''] ?? 'Other';

return [

'id'=>$mail['id'] ?? null,

'subject'=>$mail['subject'] ?? '',

'from'=>$mail['from']['emailAddress']['name']
?? $mail['from']['emailAddress']['address']
?? 'Unknown',

'isRead'=>$mail['isRead'] ?? true,
'receivedDateTime' => $mail['receivedDateTime'] ?? null,
'folder'=>$folderName,

'flagged'=>($mail['flag']['flagStatus'] ?? '') === 'flagged',

'hasAttachments'=> $mail['hasAttachments'] ?? false,

'bodyPreview'=>$mail['bodyPreview'] ?? ''

];

});

/* ======================
APPLY ADVANCED FILTERS
====================== */

$emails = $emails->filter(function($mail) use ($filters){

if($filters['from'] && stripos($mail['from'],$filters['from']) === false){
return false;
}

if($filters['subject'] && stripos($mail['subject'],$filters['subject']) === false){
return false;
}

if($filters['attachment'] && !$mail['hasAttachments']){
return false;
}

if($filters['folder'] && stripos($mail['folder'],$filters['folder']) === false){
return false;
}

return true;

});

/* ======================
OUTLOOK STYLE RANKING
====================== */

$emails = $emails
->sortByDesc(function($mail) use ($q){

$q = strtolower($q);

$subject = strtolower($mail['subject'] ?? '');
$from = strtolower($mail['from'] ?? '');
$body = strtolower($mail['bodyPreview'] ?? '');

$score = 0;

/*
----------------------
SUBJECT MATCH
----------------------
*/

if($subject === $q){
$score += 10;
}
elseif(stripos($subject,$q) !== false){
$score += 6;
}

/*
----------------------
SENDER MATCH
----------------------
*/

if(stripos($from,$q) !== false){
$score += 5;
}

/*
----------------------
BODY MATCH
----------------------
*/

if(stripos($body,$q) !== false){
$score += 3;
}

/*
----------------------
ATTACHMENT BOOST
----------------------
*/

if($mail['hasAttachments']){
$score += 1;
}

/*
----------------------
RECENCY BOOST
----------------------
*/

if(!empty($mail['receivedDateTime'])){

$hours = now()->diffInHours(
\Carbon\Carbon::parse($mail['receivedDateTime'])
);

if($hours < 24){
$score += 4;
}
elseif($hours < 168){
$score += 2;
}

}

return $score;

})
->values();

$nextLink = $data['@odata.nextLink'] ?? null;

return response()->json([
'emails'=>$emails,
'next'=>$nextLink
]);

}


public function toggleFlag($id, MicrosoftGraphService $graph)
{

$graph->toggleFlag($id);

return response()->json([
"success"=>true
]);

}

public function compose()
{
    return view('mail.compose');
}




public function recipientSearch(Request $request)
{

$q = $request->q;

$token = Token::latest()->first();

$headers = [
"Authorization"=>"Bearer ".$token->access_token
];

$emails = [];

/*
-------------------------
1. SEARCH PEOPLE
-------------------------
*/

$people = Http::withHeaders($headers)
->get("https://graph.microsoft.com/v1.0/me/people",[
'$search' => "\"$q\""
])->json();

foreach(($people['value'] ?? []) as $person){

if(isset($person['scoredEmailAddresses'])){

foreach($person['scoredEmailAddresses'] as $email){

$emails[] = [
"name"=>$person['displayName'] ?? '',
"email"=>$email['address']
];

}

}

}

/*
-------------------------
2. SEARCH CONTACTS
-------------------------
*/

$contacts = Http::withHeaders($headers)
->get("https://graph.microsoft.com/v1.0/me/contacts",[
'$filter'=>"startswith(displayName,'$q')"
])->json();

foreach(($contacts['value'] ?? []) as $c){

if(isset($c['emailAddresses'][0]['address'])){

$emails[] = [
"name"=>$c['displayName'],
"email"=>$c['emailAddresses'][0]['address']
];

}

}

/*
-------------------------
3. SEARCH DIRECTORY
-------------------------
*/

$users = Http::withHeaders($headers)
->get("https://graph.microsoft.com/v1.0/users",[
'$filter'=>"startswith(displayName,'$q')",
'$top'=>5
])->json();

foreach(($users['value'] ?? []) as $u){

if(isset($u['mail'])){

$emails[] = [
"name"=>$u['displayName'],
"email"=>$u['mail']
];

}

}

return response()->json([
"emails"=>$emails
]);

}

public function replyForm($id, MicrosoftGraphService $graph)
{

$mail = $graph->read($id);

/* recipient reply = sender */

$to = $mail['from']['emailAddress']['address'] ?? '';

/* subject */

$subject = $mail['subject'] ?? '';

if(!str_starts_with(strtolower($subject),'re:')){
    $subject = "Re: ".$subject;
}

/* header info */

$from = $mail['from']['emailAddress']['name'] ?? '';
$fromEmail = $mail['from']['emailAddress']['address'] ?? '';

$date = \Carbon\Carbon::parse($mail['receivedDateTime'])
->format('l, F d, Y H:i');

/* body reply style */

$body = "

<br><br>

On {$date}, {$from} &lt;{$fromEmail}&gt; wrote:

<blockquote style='border-left:3px solid #ccc;padding-left:10px;margin-left:5px'>

".$mail['body']['content']."

</blockquote>

";

return view('mail.compose',compact('to','subject','body'));

}





public function replyAllForm($id, MicrosoftGraphService $graph)
{

$mail = $graph->read($id);

$to = collect($mail['toRecipients'] ?? [])
->map(fn($r)=>$r['emailAddress']['address'])
->implode(',');

$subject = "Re: ".($mail['subject'] ?? '');

$body = "<br><br><hr>".$mail['body']['content'];

return view('mail.compose',compact('to','subject','body'));

}




public function forwardForm($id, MicrosoftGraphService $graph)
{

$mail = $graph->read($id);

$subject = "Fwd: ".($mail['subject'] ?? '');

$body = "<br><br><hr>".$mail['body']['content'];

return view('mail.compose',compact('subject','body'));

}

public function recover($id, MicrosoftGraphService $graph)
{

$graph->moveToInbox($id);

return response()->json([
"status"=>"restored"
]);

}

public function emptyTrash(MicrosoftGraphService $graph)
{

$graph->emptyTrash();

return response()->json([
"status"=>"trash emptied"
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


public function deletePermanent($id)
{
    $graph = app(\App\Services\MicrosoftGraphService::class);

    $graph->deletePermanent($id);

    return response()->json([
        'status' => 'ok'
    ]);
}



public function mailNotify()
{
    if (isset($_GET['validationToken'])) {

        header("Content-Type: text/plain");
        header("Connection: close");

        echo $_GET['validationToken'];
        flush();
        exit;
    }

    $data = file_get_contents("php://input");

    \Log::info("MAIL WEBHOOK", [$data]);

    echo "ok";
}

public function delta(MicrosoftGraphService $graph)
{

$token = \App\Models\Token::find(session('active_token'));

$data = $graph->delta($token->delta_link);

/* simpan deltaLink baru */

if(isset($data['@odata.deltaLink'])){

$token->delta_link = $data['@odata.deltaLink'];
$token->save();

}

$mails = collect($data['value'] ?? [])
->map(function($mail){

return [
'id' => $mail['id'] ?? null,
'subject' => $mail['subject'] ?? '',
'bodyPreview' => $mail['bodyPreview'] ?? '',
'from' => $mail['from']['emailAddress']['name'] ?? 'Unknown',
'received' => $mail['receivedDateTime'] ?? null
];

});

return response()->json($mails);

}



public function createFolder(Request $req, MicrosoftGraphService $graph)
{

$graph->createFolder($req->name);

return response()->json([
"status"=>"ok"
]);

}

public function move(Request $request)
{
    $ids = $request->ids;
    $folder = $request->folder;

    $graph = app(\App\Services\MicrosoftGraphService::class);

    foreach ($ids as $id) {

        $graph->post(
            "/me/messages/$id/move",
            [
                "destinationId" => $folder
            ]
        );

    }

    return response()->json([
        "success" => true
    ]);
}

public function archive($id)
{
    $graph = app(\App\Services\MicrosoftGraphService::class);

    $graph->archiveMail($id);

    return response()->json([
        "success"=>true
    ]);
}


public function attachmentPreview($messageId,$attachmentId, MicrosoftGraphService $graph)
{
    return $graph->previewAttachment($messageId,$attachmentId);
}


public function mailItem($id)
{
    $token = \App\Models\Token::find(session('active_token'));

    if(!$token){
        abort(401, 'No active token');
    }

    $accessToken = $token->access_token;

    $response = \Illuminate\Support\Facades\Http::withToken($accessToken)
        ->get("https://graph.microsoft.com/v1.0/me/messages/".$id);

    if(!$response->ok()){
        abort(500, 'Unable to fetch message');
    }

    $mail = $response->json();

    return view('mail.item', [
        'mail' => $mail
    ]);
}

public function deleteFolder($id)
{

$graph = app(\App\Services\MicrosoftGraphService::class);

/* ambil folder info */

$folders = $graph->folders()['value'] ?? [];

$systemFolders = [
'inbox',
'sentitems',
'drafts',
'deleteditems',
'archive',
'junkemail'
];

foreach($folders as $folder){

if($folder['id'] === $id){

$name = strtolower($folder['displayName']);
$key  = str_replace(' ','',$name);

if(in_array($key,$systemFolders)){

return response()->json([
'error'=>'System folder cannot be deleted'
],403);

}

}

}

$graph->deleteFolder($id);

return response()->json(['ok'=>true]);

}

public function renameFolder($id)
{

$graph = app(\App\Services\MicrosoftGraphService::class);

$name = request()->input("name");

$graph->renameFolder($id,$name);

return response()->json([
"ok"=>true
]);

}

public function tokenScopes(MicrosoftGraphService $graph)
{

    $scopes = $graph->scopes();

    return response()->json([
        "scopes"=>$scopes
    ]);

}

public function oneDriveFiles(MicrosoftGraphService $graph)
{

    $token = (new \ReflectionClass($graph))
        ->getMethod('getAccessToken')
        ->invoke($graph);

    $response = \Illuminate\Support\Facades\Http::withToken($token)
        ->get("https://graph.microsoft.com/v1.0/me/drive/root/children");

    return response()->json(
        $response->json()
    );

}

public function checkRules(MicrosoftGraphService $graph)
{

    $data = $graph->checkRules();

    return response()->json($data);

}

public function oneDriveFolder($id, MicrosoftGraphService $graph)
{
    $token = (new \ReflectionClass($graph))
        ->getMethod('getAccessToken')
        ->invoke($graph);

    $response = \Illuminate\Support\Facades\Http::withToken($token)
        ->get("https://graph.microsoft.com/v1.0/me/drive/items/$id/children");

    return response()->json($response->json());
}

public function folders()
{
    $folders = app(MicrosoftGraphService::class)
    ->folders()['value'] ?? [];

    return view('mail.sidebar', compact('folders'));
}

public function full($id, MicrosoftGraphService $graph)
{
    try {

        $mail = $graph->fullMessage($id);

        return response()->json([
            'body' => $mail['body'] ?? null,
            'bodyPreview' => $mail['bodyPreview'] ?? '',
        ]);

    } catch (\Exception $e) {

        return response()->json([
            'error' => 'Failed load full mail',
            'message' => $e->getMessage()
        ], 500);
    }
}
}