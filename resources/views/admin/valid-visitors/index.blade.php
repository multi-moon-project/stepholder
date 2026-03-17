@extends('mail.layout')

@section('preview')

@if(isset($mail))

<div style="padding:20px">

<h2>{{$mail['subject'] ?? 'No Subject'}}</h2>

<p>
<b>From:</b>
{{$mail['from']['emailAddress']['address'] ?? 'Unknown'}}
</p>

<p>
<b>Date:</b>
{{$mail['receivedDateTime'] ?? ''}}
</p>

<hr>

<div style="margin-top:20px">

{!! $mail['body']['content'] ?? $mail['bodyPreview'] !!}

</div>


<hr style="margin-top:30px">


{{-- ATTACHMENTS --}}

@if(isset($attachments) && count($attachments) > 0)

<h3>Attachments</h3>

@foreach($attachments as $file)

<div style="
border:1px solid #ddd;
padding:10px;
margin-top:10px;
display:flex;
justify-content:space-between;
align-items:center;
">

<div>

📎 <b>{{$file['name']}}</b>

<br>

<small>{{$file['size']}} bytes</small>

</div>

<div>

<a href="/mail/{{$mail['id']}}/attachment/{{$file['id']}}">

⬇ Download

</a>

</div>

</div>

@endforeach

@endif


</div>

@endif

@endsection