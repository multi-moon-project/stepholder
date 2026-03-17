@extends('mail.layout')

@section('preview')

<h2>Attachments</h2>

<a href="/mail/{{$id}}">← Back to Email</a>

@foreach($attachments as $file)

<div style="border:1px solid #ddd;padding:15px;margin:10px">

📎 <b>{{$file['name']}}</b>

<br>

Size: {{$file['size']}} bytes

<br><br>

<a href="/mail/{{$id}}/attachment/{{$file['id']}}">

⬇ Download

</a>

</div>

@endforeach

@endsection