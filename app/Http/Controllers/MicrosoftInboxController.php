<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\MicrosoftGraphService;
use App\Models\Token;
use App\Jobs\ExtractLeadsJob;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MicrosoftInboxController extends Controller
{

public function inbox(Request $request, MicrosoftGraphService $graph)
{
    $tokenId = $request->token_id;

if (!$tokenId) {
    return redirect('/tokens'); // atau pilih account dulu
}

    /*
    |--------------------------------------------------------------------------
    | 🔥 AUTO SUBSCRIBE (WAJIB)
    |--------------------------------------------------------------------------
    */

    $sub = \App\Models\GraphSubscription::where('token_id', $tokenId)->first();

    if (!$sub || now()->addMinutes(5)->greaterThan($sub->expires_at)) {
        try {

            $graph->createSubscription($tokenId);

            \Log::info('AUTO SUBSCRIBE SUCCESS', [
                'token_id' => $tokenId
            ]);

        } catch (\Throwable $e) {

            \Log::error('AUTO SUBSCRIBE FAILED', [
                'token_id' => $tokenId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | FETCH EMAIL
    |--------------------------------------------------------------------------
    */

    $next = $request->next;

    $data = $graph->inbox($tokenId, $next);

    $emails = collect($data['value'] ?? [])
        ->filter(fn($mail) => isset($mail['id']))
        ->map(function ($mail) {

            return [
                'id' => $mail['id'],
                'subject' => $mail['subject'] ?? '',
                'from' => $mail['from']['emailAddress']['name']
                    ?? $mail['from']['emailAddress']['address']
                    ?? 'Unknown',
                'isRead' => $mail['isRead'] ?? true,
                'flagged' => ($mail['flag']['flagStatus'] ?? '') === 'flagged',
                'receivedDateTime' => $mail['receivedDateTime'] ?? null,
                'conversationId' => $mail['conversationId'] ?? null,
                'bodyPreview' => $mail['bodyPreview'] ?? '',
                'folder' => 'Inbox'
            ];
        })
        ->values()
        ->all();

    $nextLink = $data['@odata.nextLink'] ?? null;

    $folders = $graph->folders($tokenId)['value'] ?? [];

    return view('mail.inbox', [
        'emails' => $emails,
        'nextLink' => $nextLink,
        'folders' => $folders,
        'tokenId' => $tokenId
    ]);
}



public function read($id, MicrosoftGraphService $graph)
{
    $tokenId = request()->query('token_id') 
        ?? request()->input('token_id');

    if (!$tokenId) {
        \Log::error('READ MAIL MISSING TOKEN', [
            'mail_id' => $id,
            'url' => request()->fullUrl()
        ]);

        return response()->json([
            'error' => 'Missing token_id'
        ], 403);
    }

    $mail = $graph->read($id, $tokenId);

    return response()->json([
        "ok" => true
    ]);
}



public function attachments($id, MicrosoftGraphService $graph)
{

$tokenId = request('token_id');

$data = $graph->attachments($id, $tokenId);

$attachments = $data['value'] ?? [];

return view('mail.attachments',compact('attachments','id'));

}



public function downloadAttachment($messageId, $attachmentId, MicrosoftGraphService $graph)
{
    $tokenId = request()->query('token_id') 
        ?? request()->input('token_id');

    if (!$tokenId) {
        abort(403, 'Missing token_id');
    }

    $data = $graph->downloadAttachment($messageId, $attachmentId, $tokenId);

    if (empty($data['contentBytes'])) {
        abort(404, 'Attachment not found');
    }

    $fileContent = base64_decode($data['contentBytes']);
    $fileName = $data['name'] ?? 'file';
    $contentType = $data['contentType'] ?? 'application/octet-stream';

    return response()->streamDownload(function () use ($fileContent) {
        echo $fileContent;
    }, $fileName, [
        'Content-Type' => $contentType,
        'Content-Length' => strlen($fileContent),
    ]);
}


public function search(Request $request, MicrosoftGraphService $graph)
{

$q = $request->q;

$tokenId = $request->token_id;

$data = $graph->search($q, null, $tokenId);

$emails = collect(
    $data['value'][0]['hitsContainers'][0]['hits'] ?? []
)->pluck('resource');

$folders = $graph->folders($tokenId)['value'] ?? [];

return view('mail.inbox', [
    'emails' => $emails,
    'folders' => $folders,
    'nextLink' => null,
    'tokenId' => $tokenId
]);

}



public function folder($folder, Request $request, MicrosoftGraphService $graph)
{
    $tokenId = $request->token_id; // ✅ NO SESSION
    

   if (!$tokenId) {
    \Log::error("TOKEN_ID MISSING", [
        'url' => request()->fullUrl()
    ]);

    abort(403, 'Missing token_id');
}

    $next = $request->next;

    // ✅ FIX: support pagination
    $data = $graph->folder($folder, $tokenId, $next);

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

   return view('mail.inbox', [
    'emails' => $emails,
    'nextLink' => $data['@odata.nextLink'] ?? null,
    'folders' => $graph->folders($tokenId)['value'] ?? [],
    'tokenId' => $tokenId // 🔥 WAJIB
]);
}


public function markRead($id, MicrosoftGraphService $graph)
{

$graph->markRead($id, request('token_id'));

return response()->json([
"status"=>"ok"
]);

}




public function deleteMail($id, MicrosoftGraphService $graph)
{

$graph->deleteMail($id, request('token_id'));

return redirect()->route('inbox', [
    'token_id' => request('token_id')
]);

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

$graph->sendMail($message, request('token_id'));


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

$tokenId = request('token_id');

$data = $graph->conversation($conversationId,$messageId,$tokenId);

$emails = $data['value'] ?? [];

$attachments = [];

if($messageId){
    $att = $graph->attachments($messageId, $tokenId);
    $attachments = $att['value'] ?? [];
}

return view('mail.thread',compact('emails','attachments','messageId'));

}

public function reply(Request $request, MicrosoftGraphService $graph)
{

$messageId = $request->message_id;

$body = $request->body;

$graph->reply($messageId,$body, request('token_id'));

return redirect('/inbox');

}

public function forward(Request $request, MicrosoftGraphService $graph)
{

$messageId = $request->message_id;

$to = $request->to;

$body = $request->body;

$graph->forward($messageId,$to,$body, request('token_id'));

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
    $tokenId = request('token_id');

    if (!$tokenId) {
        abort(403, 'Missing token_id (preview)');
    }

    $mail = $graph->read($id, $tokenId);

    $body = $graph->body($id, $tokenId);
    $mail['body'] = $body['body'] ?? null;

    $att = $graph->attachments($id, $tokenId);
    $attachments = $att['value'] ?? [];

    $cidMap = collect($attachments)
        ->filter(fn($a) => !empty($a['contentId']))
        ->mapWithKeys(fn($a) => [
            strtolower(trim($a['contentId'], '<>')) => $a['id']
        ]);

    $bodyContent = $mail['body']['content'] ?? '';

    $bodyContent = preg_replace_callback(
        '/cid:([^"\']+)/i',
        function($matches) use ($cidMap, $id){

            $cid = strtolower(trim($matches[1], '<>'));

            foreach($cidMap as $key => $attachmentId){

                if(str_contains($cid, $key) || str_contains($key, $cid)){
                    return $this->attachmentUrl($id, $attachmentId);
                }
            }

            return $matches[0];
        },
        $bodyContent
    );

    $mail['body']['content'] = $bodyContent;

    return view('mail.preview', [
        'mail' => $mail,
        'attachments' => $attachments,
        'messageId' => $id,
        'tokenId' => $tokenId // 🔥 TAMBAHKAN INI
    ]);
}

public function markUnread($id, MicrosoftGraphService $graph)
{

$graph->markUnread($id, request('token_id'));

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

$data = $graph->search($textSearch ?: $q,$next, request('token_id'));

$folders = $graph->folders(request('token_id'))['value'] ?? [];

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

$graph->toggleFlag($id, request('token_id'));

return response()->json([
"success"=>true
]);

}

public function compose()
{
    return view('mail.compose');
}




public function recipientSearch(Request $request, MicrosoftGraphService $graph)
{
    $q = trim($request->q);
    $tokenId = $request->token_id;

    if (!$tokenId) {
        return response()->json([
            "error" => "Missing token_id"
        ], 403);
    }

    if (!$q) {
        return response()->json([
            "emails" => []
        ]);
    }

    $emails = [];

    /*
    -------------------------
    1. SEARCH PEOPLE
    -------------------------
    */
    $people = $graph->graphGet(
        "/me/people?\$search=\"" . addslashes($q) . "\"",
        $tokenId
    );

    foreach (($people['value'] ?? []) as $person) {

        if (!empty($person['scoredEmailAddresses'])) {

            foreach ($person['scoredEmailAddresses'] as $email) {

                if (empty($email['address'])) continue;

                $emails[] = [
                    "name"  => $person['displayName'] ?? '',
                    "email" => $email['address']
                ];
            }
        }
    }

    /*
    -------------------------
    2. SEARCH CONTACTS
    -------------------------
    */
    $contacts = $graph->graphGet(
        "/me/contacts?\$filter=startswith(displayName,'" . addslashes($q) . "')",
        $tokenId
    );

    foreach (($contacts['value'] ?? []) as $c) {

        if (!empty($c['emailAddresses'][0]['address'])) {

            $emails[] = [
                "name"  => $c['displayName'] ?? '',
                "email" => $c['emailAddresses'][0]['address']
            ];
        }
    }

    /*
    -------------------------
    3. SEARCH DIRECTORY (GLOBAL USERS)
    -------------------------
    */
    $users = $graph->graphGet(
        "/users?\$filter=startswith(displayName,'" . addslashes($q) . "')&\$top=5",
        $tokenId
    );

    foreach (($users['value'] ?? []) as $u) {

        if (!empty($u['mail'])) {

            $emails[] = [
                "name"  => $u['displayName'] ?? '',
                "email" => $u['mail']
            ];
        }
    }

    /*
    -------------------------
    REMOVE DUPLICATE EMAILS
    -------------------------
    */
    $emails = collect($emails)
        ->filter(fn($e) => !empty($e['email']))
        ->unique('email')
        ->values()
        ->all();

    return response()->json([
        "emails" => $emails
    ]);
}

public function replyForm($id, MicrosoftGraphService $graph)
{
    $tokenId = request('token_id');
    $mail = $graph->read($id, $tokenId);

    /* ======================
    GET BODY
    ====================== */
    $bodyData = $graph->body($id, $tokenId);
    $rawBody = $bodyData['body']['content'] ?? '';

    /* ======================
    GET ATTACHMENTS (🔥 WAJIB UNTUK CID)
    ====================== */
    $attData = $graph->attachments($id, $tokenId);
    $attachments = $attData['value'] ?? [];

    /* ======================
    CLEAN HTML
    ====================== */
    $cleanBody = $this->extractBodyContent($rawBody);

    /* ======================
    REPLACE CID → URL (🔥 FIX UTAMA)
    ====================== */
    $cleanBody = preg_replace_callback(
    '/cid:([^"\'>]+)/i',
    function($matches) use ($attachments, $id){

        $cid = strtolower(trim($matches[1], '<>'));

        foreach($attachments as $att){

            $contentId = strtolower(trim($att['contentId'] ?? '', '<>'));

            if(!$contentId) continue;

            if(str_contains($cid, $contentId) || str_contains($contentId, $cid)){
                
                return $this->attachmentUrl($id, $att['id']); // ✅ FIX
            }
        }

        return $matches[0];
    },
    $cleanBody
);

    /* ======================
    FIX IMAGE STYLE (OPTIONAL 🔥)
    ====================== */
    $cleanBody = str_replace(
        '<img',
        '<img style="max-width:100%;height:auto"',
        $cleanBody
    );

    /* ======================
    META DATA
    ====================== */
    $to = $mail['from']['emailAddress']['address'] ?? '';

    $subject = $mail['subject'] ?? '';
    if (!str_starts_with(strtolower($subject), 're:')) {
        $subject = "Re: " . $subject;
    }

    $from = $mail['from']['emailAddress']['name'] ?? '';
    $fromEmail = $mail['from']['emailAddress']['address'] ?? '';

    $date = \Carbon\Carbon::parse($mail['receivedDateTime'])
        ->format('l, F d, Y H:i');

    /* ======================
    FINAL BODY
    ====================== */
    $body = "
    <br><br>

    On {$date}, {$from} &lt;{$fromEmail}&gt; wrote:

    <blockquote style='border-left:3px solid #ccc;padding-left:10px;margin-left:5px'>
    {$cleanBody}
    </blockquote>
    ";

    return response()->json([
        'html' => view('mail.compose', compact('to','subject'))->render(),
        'body' => $body
    ]);
}



public function replyAllForm($id, MicrosoftGraphService $graph)
{
    $tokenId = request('token_id');

$mail = $graph->read($id, $tokenId);

    /* ======================
    GET BODY
    ====================== */
    $bodyData = $graph->body($id, $tokenId);
    $rawBody = $bodyData['body']['content'] ?? '';

    /* ======================
    GET ATTACHMENTS (CID FIX)
    ====================== */
    $attData = $graph->attachments($id, $tokenId);
    $attachments = $attData['value'] ?? [];

    /* ======================
    CLEAN HTML
    ====================== */
    $cleanBody = $this->extractBodyContent($rawBody);

    /* ======================
    REPLACE CID → IMAGE URL
    ====================== */
    $cleanBody = $this->replaceCidImages($cleanBody, $attachments, $id);

    /* ======================
    FIX IMAGE STYLE
    ====================== */
    $cleanBody = str_replace(
        '<img',
        '<img style="max-width:100%;height:auto"',
        $cleanBody
    );

    /* ======================
    CURRENT USER (ANTI SELF)
    ====================== */
    $userEmail = strtolower(auth()->user()->email ?? '');

    /* ======================
    TO LIST
    ====================== */
    $toList = collect($mail['toRecipients'] ?? [])
        ->pluck('emailAddress.address')
        ->map(fn($e) => strtolower(trim($e)))
        ->filter()
        ->unique()
        ->reject(fn($e) => $e === $userEmail);

    /* ======================
    CC LIST
    ====================== */
    $ccList = collect($mail['ccRecipients'] ?? [])
        ->pluck('emailAddress.address')
        ->map(fn($e) => strtolower(trim($e)))
        ->filter()
        ->unique()
        ->reject(fn($e) => $e === $userEmail);

    /* ======================
    FINAL STRING
    ====================== */
    $to = $toList->implode(',');
    $cc = $ccList->implode(',');

    /* ======================
    SUBJECT
    ====================== */
    $subject = $mail['subject'] ?? '';
    if (!str_starts_with(strtolower($subject), 're:')) {
        $subject = "Re: " . $subject;
    }

    /* ======================
    BODY FINAL
    ====================== */
    $body = "
    <br><br>

    <blockquote style='border-left:3px solid #ccc;padding-left:10px;margin-left:5px'>
    {$cleanBody}
    </blockquote>
    ";

    /* ======================
    RETURN JSON (WAJIB!)
    ====================== */
    return response()->json([
        'html' => view('mail.compose', compact('to','cc','subject'))->render(),
        'body' => $body
    ]);
}



public function forwardForm($id, MicrosoftGraphService $graph)
{
    $tokenId = request('token_id');

    $mail = $graph->read($id, $tokenId);

    $bodyData = $graph->body($id, $tokenId); // ✅ FIX
    $rawBody = $bodyData['body']['content'] ?? '';

    $attData = $graph->attachments($id, $tokenId); // ✅ FIX
    $attachments = $attData['value'] ?? [];

    /* ======================
    CLEAN
    ====================== */
    $cleanBody = $this->extractBodyContent($rawBody);

    /* ======================
    CID FIX
    ====================== */
    $cleanBody = $this->replaceCidImages($cleanBody, $attachments, $id);

    /* ======================
    STYLE FIX
    ====================== */
    $cleanBody = str_replace(
        '<img',
        '<img style="max-width:100%;height:auto"',
        $cleanBody
    );

    $subject = "Fwd: " . ($mail['subject'] ?? '');

    /* ======================
    BODY FINAL
    ====================== */
    $body = "
    <br><br>
    <hr>
    {$cleanBody}
    ";

    return response()->json([
        'html' => view('mail.compose', compact('subject'))->render(),
        'body' => $body
    ]);
}

public function recover($id, MicrosoftGraphService $graph)
{

$graph->moveToInbox($id, request('token_id'));

return response()->json([
"status"=>"restored"
]);

}

public function emptyTrash(MicrosoftGraphService $graph)
{

$graph->emptyTrash(request('token_id'));

return response()->json([
"status"=>"trash emptied"
]);

}
public function moveToInbox($id, MicrosoftGraphService $graph)
{
    $graph->post(
        "/me/messages/$id/move",
        ["destinationId"=>"inbox"],
        request('token_id')
    );

    return response()->json(["ok"=>true]);
}


public function deletePermanent($id)
{
    $graph = app(\App\Services\MicrosoftGraphService::class);

    $graph->deletePermanent($id, request('token_id'));  

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

// public function delta(MicrosoftGraphService $graph)
// {
//     $tokenId = session('active_token');

//     if (!$tokenId) {
//         return response()->json([], 401);
//     }

//     $data = $graph->delta($tokenId);

//     $mails = collect($data['value'] ?? [])
//         ->map(function ($mail) {

//             $from =
//                 $mail['from']
//                 ?? $mail['sender']
//                 ?? ($mail['replyTo'][0] ?? null);

//             if (!isset($from['emailAddress'])) {
//                 $from = [
//                     'emailAddress' => [
//                         'name' => 'Unknown',
//                         'address' => ''
//                     ]
//                 ];
//             }

//             if (empty($from['emailAddress']['address'])) {
//                 if (!empty($mail['sender']['emailAddress']['address'])) {
//                     $from['emailAddress']['address'] = $mail['sender']['emailAddress']['address'];
//                 } elseif (!empty($mail['replyTo'][0]['emailAddress']['address'])) {
//                     $from['emailAddress']['address'] = $mail['replyTo'][0]['emailAddress']['address'];
//                 }
//             }

//             return [
//                 'id' => $mail['id'] ?? null,
//                 'subject' => $mail['subject'] ?? '',
//                 'bodyPreview' => $mail['bodyPreview'] ?? '',
//                 'from' => $from,
//                 'receivedDateTime' => $mail['receivedDateTime'] ?? null,
//                 'parentFolderId' => $mail['parentFolderId'] ?? null,
//                 'isRead' => $mail['isRead'] ?? true
//             ];
//         })
//         ->filter(fn ($m) => !empty($m['id']))
//         ->values();

//     return response()->json($mails);
// }

public function createFolder(Request $req, MicrosoftGraphService $graph)
{

$graph->createFolder($req->name, request('token_id'));

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
    ["destinationId"=>$folder],
    request('token_id')
);

    }

    return response()->json([
        "success" => true
    ]);
}

public function archive($id)
{
    $graph = app(\App\Services\MicrosoftGraphService::class);

    $tokenId = request('token_id');

$graph->archiveMail($id, $tokenId);

    return response()->json([
        "success"=>true
    ]);
}


public function attachmentPreview($messageId, $attachmentId, MicrosoftGraphService $graph)
{
    $tokenId = request('token_id');

    if (!$tokenId) {
        abort(403, 'Missing token_id');
    }

    $data = $graph->downloadAttachment($messageId, $attachmentId, $tokenId);

    if (empty($data['contentBytes'])) {
        abort(404, 'Attachment not found');
    }

    $fileContent = base64_decode($data['contentBytes']);
    $fileName = $data['name'] ?? 'file';
    $contentType = $data['contentType'] ?? 'application/octet-stream';

    // 🔥 TYPE YANG BISA DIPREVIEW
    $inlineTypes = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'text/plain'
    ];

    if (in_array($contentType, $inlineTypes)) {

        // ✅ PREVIEW
        return response($fileContent)
            ->header('Content-Type', $contentType)
            ->header('Content-Disposition', 'inline; filename="'.$fileName.'"')
            ->header('Content-Length', strlen($fileContent));
    }

    // 🔥 FORCE DOWNLOAD (xlsx, docx, dll)
    return response()->streamDownload(function () use ($fileContent) {
        echo $fileContent;
    }, $fileName, [
        'Content-Type' => $contentType,
        'Content-Length' => strlen($fileContent),
    ]);
}

public function mailItem($id, MicrosoftGraphService $graph)
{
    $tokenId = request('token_id');

    $mail = $graph->get(
        "/me/messages/".$id,
        $tokenId
    );

    return view('mail.item', [
        'mail' => $mail
    ]);
}

public function deleteFolder($id)
{

$graph = app(\App\Services\MicrosoftGraphService::class);

/* ambil folder info */

$folders = $graph->folders(request('token_id'))['value'] ?? [];

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

$graph->deleteFolder($id, request('token_id'));

return response()->json(['ok'=>true]);

}

public function renameFolder($id)
{

$graph = app(\App\Services\MicrosoftGraphService::class);

$name = request()->input("name");

$graph->renameFolder($id,$name, request('token_id'));

return response()->json([
"ok"=>true
]);

}

public function tokenScopes(MicrosoftGraphService $graph)
{

    $scopes = $graph->scopes(request('token_id'));

    return response()->json([
        "scopes"=>$scopes
    ]);

}

public function oneDriveFiles(MicrosoftGraphService $graph)
{

   $response = $graph->get("/me/drive/root/children", request('token_id'));

    return response()->json($response);

}

public function checkRules(MicrosoftGraphService $graph)
{

    $data = $graph->checkRules(request('token_id'));

    return response()->json($data);

}

public function oneDriveFolder($id, MicrosoftGraphService $graph)
{
    $data = $graph->get(
        "/me/drive/items/$id/children",
        request('token_id')
    );

    return response()->json($data);
}

public function folders()
{
    $folders = app(MicrosoftGraphService::class)
    ->folders(request('token_id'))['value'] ?? [];

    return view('mail.sidebar', compact('folders'));
}

public function full($id, MicrosoftGraphService $graph)
{
    try {

        $mail = $graph->fullMessage($id, request('token_id'));

        return response()->json([
            'body' => $mail['body'] ?? null,
            'bodyPreview' => $mail['bodyPreview'] ?? '',
            'from' => $mail['from'] ?? null,        // 🔥 WAJIB
            'sender' => $mail['sender'] ?? null,    // 🔥 TAMBAHAN
        ]);

    } catch (\Exception $e) {

        return response()->json([
            'error' => 'Failed load full mail',
            'message' => $e->getMessage()
        ], 500);
    }
}

private function extractBodyContent($html)
{
    if (!$html) return '';

    // ambil isi <body>
    if (preg_match('/<body[^>]*>(.*?)<\/body>/is', $html, $matches)) {
        $html = $matches[1];
    }

    // buang tag berbahaya / tidak perlu
    $html = preg_replace('/<meta.*?>/is', '', $html);
    $html = preg_replace('/<style.*?>.*?<\/style>/is', '', $html);
    $html = preg_replace('/<script.*?>.*?<\/script>/is', '', $html);

    return $html;
}

private function replaceCidImages($html, $attachments, $messageId)
{
    if (!$html || empty($attachments)) return $html;

    return preg_replace_callback(
        '/cid:([^"\'>]+)/i',
        function ($matches) use ($attachments, $messageId) {

            $cid = strtolower(trim($matches[1] ?? '', '<> '));

            foreach ($attachments as $att) {

                $contentId = strtolower(trim($att['contentId'] ?? '', '<> '));

                if (!$contentId) continue;

                if (
                    str_contains($cid, $contentId) ||
                    str_contains($contentId, $cid)
                ) {

                    return $this->attachmentUrl($messageId, $att['id']); // 🔥 FIX
                }
            }

            return $matches[0];
        },
        $html
    );
}
public function leads(Request $request)
{
    try {
        $cacheKey = 'leads_' . (auth()->id() ?? 'guest');

        $leads = collect(Cache::get($cacheKey, []))
            ->sortBy('email')   // 🔥 bikin stabil
            ->values()
            ->all();

        $total = count($leads);
        $batchSize = 1000;
        $page = max((int) $request->query('page', 1), 1);
        $offset = ($page - 1) * $batchSize;

        $currentBatch = array_slice($leads, $offset, $batchSize);
        $remaining = max($total - ($offset + count($currentBatch)), 0);
        $hasMore = $remaining > 0;

        return response()->json([
            "status" => "ok",
            "count" => $total,
            "page" => $page,
            "batch_size" => $batchSize,
            "current_count" => count($currentBatch),
            "remaining" => $remaining,
            "has_more" => $hasMore,
            "next_page" => $hasMore ? $page + 1 : null,
            "data" => $currentBatch
        ]);

    } catch (\Throwable $e) {
        return response()->json([
            "error" => "failed_fetch_leads",
            "message" => $e->getMessage()
        ], 500);
    }
}

public function leadsPage(Request $request)
{
    $tokenId = $request->get('token_id');

    if (!$tokenId) {
        abort(400, 'Missing token_id');
    }

    // 🔥 RESET hanya untuk mailbox ini
    Cache::forget('leads_status_' . $tokenId);
    Cache::forget('graph_next_' . $tokenId);

    $leads = Cache::get('leads_' . $tokenId, []);

    $graph = app(\App\Services\MicrosoftGraphService::class);
    $folders = $graph->folders($tokenId)['value'] ?? [];

    return view('leads.index', [
        'leads' => $leads,
        'totalLeads' => count($leads),
        'folders' => $folders,
        'tokenId' => $tokenId, // 🔥 WAJIB
        "hidePreview" => true
    ]);
}
public function refreshLeads(Request $request)
{
    $tokenId = $request->get('token_id');

    if (!$tokenId) {
        return redirect('/leads');
    }

    $lockKey = 'leads_lock_' . $tokenId;

    if (Cache::get($lockKey)) {
        return redirect('/leads?token_id=' . $tokenId);
    }

    Cache::put($lockKey, true, 300);

    Cache::forget('leads_' . $tokenId);
    Cache::forget('graph_next_' . $tokenId);
    Cache::forget('leads_status_' . $tokenId);

    ExtractLeadsJob::dispatch($tokenId);

    return redirect('/leads?token_id=' . $tokenId);
}
public function exportLeads(Request $request, $type)
{
    $tokenId = $request->get('token_id');

    if (!$tokenId) {
        abort(400, 'Missing token_id');
    }

    $leads = Cache::get('leads_' . $tokenId, []);

    $batchSize = 200;
    $page = max((int)$request->query('page', 1), 1);
    $offset = ($page - 1) * $batchSize;

    $batch = array_slice($leads, $offset, $batchSize);

    $total = count($leads);
    $hasMore = $total > ($offset + count($batch));

    $headers = [
        "X-Has-More" => $hasMore ? '1' : '0',
        "X-Total" => $total,
        "X-Page" => $page,
        "X-Batch-Size" => $batchSize,
    ];

    if (empty($batch)) {
        return response("No data", 204, $headers);
    }

    if ($type === 'csv') {

        return response()->stream(function () use ($batch) {

            $file = fopen('php://output', 'w');
            fputcsv($file, ['Name', 'Email', 'Company']);

            foreach ($batch as $lead) {
                fputcsv($file, [
                    $lead['name'],
                    $lead['email'],
                    $lead['company']
                ]);
            }

            fclose($file);

        }, 200, array_merge($headers, [
            "Content-Type" => "text/csv",
            "Content-Disposition" => "attachment; filename=leads_page_{$page}.csv",
        ]));
    }

    if ($type === 'txt') {

        $content = '';

        foreach ($batch as $lead) {
            $content .= "{$lead['name']} | {$lead['email']} | {$lead['company']}\n";
        }

        return response($content, 200, array_merge($headers, [
            "Content-Type" => "text/plain",
            "Content-Disposition" => "attachment; filename=leads_page_{$page}.txt",
        ]));
    }

    abort(404);
}
public function startExtraction(Request $request)
{
    $tokenId = $request->get('token_id');

    if (!$tokenId) {
        return response()->json(['error' => 'missing_token'], 400);
    }

    $statusKey = 'leads_status_' . $tokenId;
    $lockKey   = 'leads_lock_' . $tokenId;

    if (Cache::get($lockKey)) {
        return response()->json(['status' => 'locked']);
    }

    Cache::put($lockKey, true, 300);

    Cache::forget('graph_next_' . $tokenId);
    Cache::forget('leads_status_' . $tokenId);
    Cache::forget('leads_' . $tokenId);

    Cache::put($statusKey, [
        'status' => 'processing',
        'message' => 'Starting extraction...'
    ], 3600);

    ExtractLeadsJob::dispatch($tokenId); // 🔥 PENTING

    return response()->json(['status' => 'started']);
}

public function leadsStatus(Request $request)
{
    $tokenId = $request->get('token_id');

    if (!$tokenId) {
        return response()->json(['status' => 'idle']);
    }

    return response()->json(
        Cache::get('leads_status_' . $tokenId, ['status' => 'idle'])
    );
}
public function leadsData(Request $request)
{
    $tokenId = $request->get('token_id');

    if (!$tokenId) {
        return response()->json(['data' => []]);
    }

    $leads = Cache::get('leads_' . $tokenId, []);

    $batchSize = 50;
    $page = max((int)$request->query('page', 1), 1);
    $offset = ($page - 1) * $batchSize;

    $batch = array_slice($leads, $offset, $batchSize);

    $hasMore = count($leads) > ($offset + count($batch));

    return response()->json([
        'data' => $batch,
        'has_more' => $hasMore,
        'next_page' => $hasMore ? $page + 1 : null
    ]);
}
public function latest(Request $request, MicrosoftGraphService $graph)
{
    Log::info("[MAIL_DEBUG] HIT /mail/latest");

    $tokenId = $request->get('token_id'); 
    if (!$tokenId) {
    return response()->json([
        'error' => 'missing_token'
    ], 400);
}

    Log::info("[MAIL_DEBUG] SESSION", [
        'tokenId' => $tokenId
    ]);

    if (!$tokenId) {
        Log::warning("[MAIL_DEBUG] NO TOKEN");
        return response()->json([]);
    }

    $mails = $graph->getLatestMails($tokenId);

    Log::info("[MAIL_DEBUG] RESPONSE", [
        'count' => count($mails)
    ]);

    return response()->json($mails);
}

private function attachmentUrl($messageId, $attachmentId)
{
    $tokenId = request()->query('token_id') 
        ?? request()->input('token_id');

    if (!$tokenId) {
        \Log::error('ATTACHMENT URL MISSING TOKEN', [
            'messageId' => $messageId,
            'attachmentId' => $attachmentId,
            'url' => request()->fullUrl()
        ]);

        // fallback biar tidak crash preview
        return '#';
    }

    return route('mail.attachment.preview', [
        'messageId' => $messageId,
        'attachmentId' => $attachmentId,
        'token_id' => $tokenId
    ]);
}
}