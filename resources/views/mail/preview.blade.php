@php

$from = $mail['from']['emailAddress']['address'] ?? 'Unknown';
$name = $mail['from']['emailAddress']['name'] ?? $from;

$initial = strtoupper(substr($name,0,1));

$to = collect($mail['toRecipients'] ?? [])
    ->map(fn($r)=>$r['emailAddress']['address'])
    ->implode(', ');

$cc = collect($mail['ccRecipients'] ?? [])
    ->map(fn($r)=>$r['emailAddress']['address'])
    ->implode(', ');

$bcc = collect($mail['bccRecipients'] ?? [])
    ->map(fn($r)=>$r['emailAddress']['address'])
    ->implode(', ');

$date = isset($mail['receivedDateTime'])
    ? \Carbon\Carbon::parse($mail['receivedDateTime'])->format('d M Y H:i')
    : '';

@endphp


<div style="max-width:900px;margin:auto;font-family:Segoe UI, Arial">

{{-- SUBJECT --}}
<h1 style="
font-size:26px;
margin-bottom:20px;
font-weight:600;
color:#323130
">
{{ $mail['subject'] ?? '(No subject)' }}
</h1>


{{-- HEADER --}}
<div style="
display:flex;
align-items:flex-start;
gap:12px;
margin-bottom:20px
">

    {{-- AVATAR --}}
    <div style="
    width:42px;
    height:42px;
    border-radius:50%;
    background:#106ebe;
    color:white;
    display:flex;
    align-items:center;
    justify-content:center;
    font-weight:600;
    font-size:16px
    ">
        {{$initial}}
    </div>


    {{-- INFO --}}
    <div style="flex:1">

        <div style="font-weight:600;font-size:15px">
            {{$name}}
        </div>

        <div style="font-size:13px;color:#605e5c">
            <b>From:</b> {{$from}}
        </div>

        @if($to)
        <div style="font-size:13px;color:#605e5c;margin-top:4px">
            <b>To:</b> {{$to}}
        </div>
        @endif

        @if($cc)
        <div style="font-size:13px;color:#605e5c">
            <b>Cc:</b> {{$cc}}
        </div>
        @endif

        @if($bcc)
        <div style="font-size:13px;color:#605e5c">
            <b>Bcc:</b> {{$bcc}}
        </div>
        @endif

        <div style="font-size:12px;color:#8a8886;margin-top:4px">
            {{$date}}
        </div>

    </div>

</div>


<hr style="border:none;border-top:1px solid #eee;margin:20px 0">


{{-- ATTACHMENTS --}}
@if(!empty($attachments))

<div class="attachment-list" style="margin-bottom:20px">

@foreach($attachments as $file)

<div
style="
display:inline-flex;
align-items:center;
gap:8px;
border:1px solid #e1e1e1;
padding:8px 12px;
border-radius:6px;
margin-right:8px;
margin-bottom:8px;
background:#faf9f8;
">

    📎

    <a 
        href="{{ route('mail.attachment.preview', [
            'messageId'=>$messageId,
            'attachmentId'=>$file['id']
        ]) }}"
        target="_blank"
        style="
        color:#106ebe;
        font-size:14px;
        text-decoration:none;
        max-width:220px;
        white-space:nowrap;
        overflow:hidden;
        text-overflow:ellipsis;
        display:inline-block;
        "
    >
        {{ $file['name'] ?? 'attachment' }}
    </a>

</div>

@endforeach

</div>

@endif



{{-- BODY EMAIL --}}
<div style="
font-size:15px;
line-height:1.7;
color:#323130;
word-break:break-word;
overflow:hidden
">

{!! $mail['body']['content'] ?? '' !!}

</div>



{{-- ACTION BUTTONS --}}
<div style="margin-top:40px;display:flex;gap:10px">
  <button
    class="mail-preview-action"
    data-action="reply"
    data-id="{{ $mail['id'] }}"
    style="padding:8px 16px;border:1px solid #d2d0ce;border-radius:6px;background:white;cursor:pointer"
  >
    ↩ Reply
  </button>

  <button
    class="mail-preview-action"
    data-action="reply-all"
    data-id="{{ $mail['id'] }}"
    style="padding:8px 16px;border:1px solid #d2d0ce;border-radius:6px;background:white;cursor:pointer"
  >
    ↩ Reply all
  </button>

  <button
    class="mail-preview-action"
    data-action="forward"
    data-id="{{ $mail['id'] }}"
    style="padding:8px 16px;border:1px solid #d2d0ce;border-radius:6px;background:white;cursor:pointer"
  >
    ➡ Forward
  </button>
</div>

</div>