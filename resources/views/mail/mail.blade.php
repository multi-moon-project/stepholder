@extends('mail.layout')

@section('list')

<div style="padding:10px">
<a href="/inbox">← Back to inbox</a>
</div>

@endsection


@section('preview')

<hr>

@if(!empty($mail['id']))
<a href="/mail/{{$mail['id']}}/attachments">
📎 View Attachments
</a>
@endif

<h2>{{ $mail['subject'] ?? '(No subject)' }}</h2>

<p>
<b>From:</b>

{{ 
    $mail['from']['emailAddress']['address']
    ?? $mail['from']['emailAddress']['name']
    ?? 'Unknown'
}}
</p>

<hr>

{!! $mail['body']['content'] ?? '<i>No content</i>' !!}

<hr>

@if(!empty($mail['id']))
<a href="/mail/{{$mail['id']}}/attachments">
View Attachments
</a>

<form method="POST" action="/mail/{{$mail['id']}}/read">
@csrf
<button>Mark Read</button>
</form>

<form method="POST" action="/mail/{{$mail['id']}}">
@csrf
@method('DELETE')
<button>Delete</button>
</form>
@endif

@endsection