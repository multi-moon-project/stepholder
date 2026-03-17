<h2>Conversation</h2>

<a href="/inbox">← Back to inbox</a>

@foreach($emails as $mail)

<div style="border:1px solid #ddd;margin:10px;padding:15px">

<h3>{{ $mail['subject'] ?? '' }}</h3>

<p>
<b>From:</b>
{{ $mail['from']['emailAddress']['address'] ?? '' }}
</p>

<p>
<b>Date:</b>
{{ $mail['receivedDateTime'] ?? '' }}
</p>

<hr>

<div>
{!! $mail['body']['content'] ?? '' !!}
</div>

</div>

@endforeach


@if(isset($attachments) && count($attachments) > 0)

<h3>Attachments</h3>

@foreach($attachments as $file)

<div style="border:1px solid #ccc;padding:10px;margin:10px">

📎 <b>{{$file['name']}}</b>

<br>

Size: {{$file['size']}} bytes

<br><br>

<a href="/mail/{{$messageId}}/attachment/{{$file['id']}}">
⬇ Download
</a>

</div>

@endforeach

@endif

<hr>

<h3>Reply</h3>

<form method="POST" action="/mail/reply">

@csrf

<input type="hidden" name="message_id" value="{{$messageId}}">

<textarea name="body" style="width:100%;height:150px"></textarea>

<br><br>

<button type="submit">

Send Reply

</button>

</form>

<hr>

<h3>Forward</h3>

<form method="POST" action="/mail/forward">

@csrf

<input type="hidden" name="message_id" value="{{$messageId}}">

<input type="email" name="to" placeholder="Forward to email" style="width:100%">

<br><br>

<textarea name="body" style="width:100%;height:150px"></textarea>

<br><br>

<button type="submit">

Forward Mail

</button>

</form>