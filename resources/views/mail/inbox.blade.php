@extends('mail.layout')

@section('list')

@foreach($emails as $mail)

@php

$sender = $mail['from'] ?? 'Unknown';
$letter = strtoupper(substr($sender ?? '?',0,1));

$time = '';

if(isset($mail['receivedDateTime'])){
    $time = \Carbon\Carbon::parse($mail['receivedDateTime'])->format('H:i');
}

@endphp


<div class="mail-item {{ !$mail['isRead'] ? 'unread' : '' }}"
mail-id="{{$mail['id']}}"
onclick="openMail('{{$mail['id']}}', this)">


<!-- CHECKBOX -->

<input
type="checkbox"
class="mail-checkbox"
onclick="event.stopPropagation()">


<!-- AVATAR -->

<div class="mail-avatar" data-color="{{ rand(1,6) }}">
{{$letter}}
</div>


<!-- CONTENT -->

<div class="mail-content">


<div class="mail-header">


<div class="mail-sender">
{{$sender}}
</div>


<div class="mail-right">

{{-- ATTACHMENT --}}
@if($mail['hasAttachments'] ?? false)
<span class="mail-icon">
<i class="fa-solid fa-paperclip"></i>
</span>
@endif


{{-- FLAG --}}
@if($mail['flagged'] ?? false)

<span class="mail-icon"
onclick="event.stopPropagation(); toggleFlag('{{$mail['id']}}')">
<i class="fa-solid fa-flag"></i>
</span>

@else

<span class="mail-icon"
onclick="event.stopPropagation(); toggleFlag('{{$mail['id']}}')">
<i class="fa-regular fa-flag"></i>
</span>

@endif


{{-- READ / UNREAD --}}

@if($mail['isRead'])

<span class="mail-action"
onclick="event.stopPropagation(); markUnread('{{$mail['id']}}')">

<i class="fa-regular fa-envelope"></i>

</span>

@else

<span class="mail-action"
onclick="event.stopPropagation(); markRead('{{$mail['id']}}')">

<i class="fa-solid fa-envelope-open"></i>

</span>

@endif


{{-- DELETE --}}
<span class="mail-action delete"
onclick="event.stopPropagation(); deleteMail('{{$mail['id']}}')">

<i class="fa-regular fa-trash-can"></i>

</span>


{{-- TIME --}}
<span class="mail-time">
{{$time}}
</span>

</div>

</div>


<div class="mail-subject">
{{$mail['subject']}}
</div>


<div class="mail-preview-text">
{{$mail['bodyPreview']}}
</div>


</div>

</div>

@endforeach



@if($nextLink)

<div id="nextPageLink"
data-next="{{ $nextLink }}"
style="display:none">
</div>

@endif

@endsection



@section('preview')

@endsection