<hr>

<a href="/mail/{{$mail['id']}}/attachments">

📎 View Attachments

</a>

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

{!! $mail['body']['content'] ?? $mail['bodyPreview'] !!}

</div>

</div>

@endforeach