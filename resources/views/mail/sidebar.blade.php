<div class="sidebar">

<a href="/leads?token_id={{ request('token_id') }}" 
   class="folder" 
   style="background:#f3f6fb;">

    <i class="fa-solid fa-users"></i>
    <span class="folder-name">Leads</span>

</a>
<h3>Folders</h3>

<div class="folder-create" onclick="createFolder()">
  <svg width="16" height="16" viewBox="0 0 24 24">
    <path fill="currentColor"
    d="M19 13H13V19H11V13H5V11H11V5H13V11H19V13Z"/>
  </svg>
  <span>New folder</span>
</div>



@foreach($folders ?? [] as $folder)
<a href="#"
class="folder"
draggable="false"
data-id="{{$folder['id']}}"
data-name="{{ strtolower($folder['displayName']) }}"
onclick="loadFolder('{{$folder['id']}}','{{$folder['displayName']}}', this)">

@php
$icon = match(strtolower($folder['displayName'])) {
    'inbox' => 'fa-inbox',
    'sent items' => 'fa-paper-plane',
    'deleted items' => 'fa-trash',
    'drafts' => 'fa-file',
    'archive' => 'fa-box-archive',
    'junk email' => 'fa-shield',
    default => 'fa-folder'
};
@endphp

<i class="fa-solid {{ $icon }}"></i>


<span class="folder-name">
{{$folder['displayName']}}
</span>

<span class="folder-count">
{{$folder['unreadItemCount'] ?? 0}}
</span>

<span class="folder-delete"
onclick="event.stopPropagation(); deleteFolder('{{$folder['id']}}')">
<i class="fa-solid fa-trash"></i>
</span>

</a>
@endforeach

</div>