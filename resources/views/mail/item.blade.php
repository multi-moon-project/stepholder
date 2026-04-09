@php
$from = $mail['from']['emailAddress']['name'] ?? 'Unknown';
$initial = strtoupper(substr($from,0,1));

// 🔥 SAFE DATE
$date = $mail['receivedDateTime'] ?? $mail['sentDateTime'] ?? null;

$time = $date
    ? \Carbon\Carbon::parse($date)->format('H:i')
    : '';
@endphp


<div draggable="true"
     mail-id="{{ $mail['id'] }}"
     class="mail-item {{ !$mail['isRead'] ? 'unread' : '' }}"
     onclick="handleMailClick(event,this,'{{ $mail['id'] }}')">

<!-- CHECKBOX -->

<input type="checkbox"
       class="mail-checkbox"
       onclick="event.stopPropagation()">


<!-- AVATAR -->

<div class="mail-avatar" data-color="{{ rand(1,6) }}">
{{ $initial }}
</div>


<!-- CONTENT -->

<div class="mail-content">


<div class="mail-header">


<div class="mail-sender">
{{ $from }}
</div>


<div class="mail-right">


{{-- ATTACHMENT --}}

@if($mail['hasAttachments'])

<span class="mail-icon">
<i class="fa-solid fa-paperclip"></i>
</span>

@endif



{{-- FLAG --}}

@if(($mail['flag']['flagStatus'] ?? '') === 'flagged')

<span class="mail-icon"
onclick="event.stopPropagation(); toggleFlag('{{ $mail['id'] }}')">

<i class="fa-solid fa-flag"></i>

</span>

@else

<span class="mail-icon"
onclick="event.stopPropagation(); toggleFlag('{{ $mail['id'] }}')">

<i class="fa-regular fa-flag"></i>

</span>

@endif



{{-- READ / UNREAD --}}

@if($mail['isRead'])

<span class="mail-action"
onclick="event.stopPropagation(); markUnread('{{ $mail['id'] }}')">

<i class="fa-regular fa-envelope"></i>

</span>

@else

<span class="mail-action"
onclick="event.stopPropagation(); markRead('{{ $mail['id'] }}')">

<i class="fa-solid fa-envelope-open"></i>

</span>

@endif



{{-- DELETE --}}

<span class="mail-action delete"
onclick="event.stopPropagation(); deleteMail('{{ $mail['id'] }}')">

<i class="fa-regular fa-trash-can"></i>

</span>



{{-- TIME --}}

<span class="mail-time">
{{ $time }}
</span>


</div>

</div>


<div class="mail-subject">
{{ $mail['subject'] }}
</div>


<div class="mail-preview-text">
{{ $mail['bodyPreview'] }}
</div>


</div>

</div>