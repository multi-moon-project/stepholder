@extends('mail.layout')


@section('list')

<div style="padding:10px">
<a href="/inbox">← Back to inbox</a>
</div>

@endsection



@section('preview')

<hr>

<a href="/mail/{{$mail['id']}}/attachments">

📎 View Attachments

</a>

<h2>{{$mail['subject']}}</h2>

<p>

<b>From:</b>

{{$mail['from']['emailAddress']['address']}}

</p>

<hr>

{!! $mail['body']['content'] !!}

<hr>

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



@endsection