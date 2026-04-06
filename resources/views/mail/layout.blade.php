<!DOCTYPE html>
<html>
<head>
    <script>
window.ACTIVE_TOKEN_ID = "{{ $tokenId ?? request('token_id') }}";
</script>
<!-- file layout.blade.php -->
@vite('resources/js/mail/app.js')
@php



@endphp



<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Mail</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
<link rel="stylesheet" href="/css/mail.css">

<style>

</style>

</head>


<body>
    <div id="mailNotifications"></div>

    <div id="toast"></div>


<!-- TOP BAR -->
<!-- TOP BAR -->
<div class="topbar">

    <div class="logo">Outlook</div>

<div class="topbar-actions">

<button class="onedrive-btn" onclick="openOneDrive()">
<i class="fa-solid fa-cloud"></i>
OneDrive
</button>

</div>

    <div class="search-box">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input
            type="text"
            id="mailSearch"
            placeholder="Search mail"
            autocomplete="off"
        >
    </div>

    <div class="account-box">
<i class="fa-solid fa-gear settings-icon"
onclick="openSettings()"></i>
        <div class="account-current" onclick="toggleAccountMenu()">

            <div class="avatar">
                {{ strtoupper(substr($active->name,0,1)) }}
            </div>

            <div class="account-info">
                <div class="account-name">{{$active->name}}</div>
                <div class="account-email">{{$active->email}}</div>
            </div>

            <div>▼</div>

        </div>

       <div class="account-menu" id="accountMenu">

@foreach($tokens as $token)

<div class="account-item
@if($token->id == session('active_token')) active @endif"
onclick="switchAccount({{ $token->id }})">

    <div class="avatar">
        {{ strtoupper(substr($token->name,0,1)) }}
    </div>

    <div class="account-info">
        <div class="account-name">{{ $token->name }}</div>
        <div class="account-email">{{ $token->email }}</div>
    </div>

</div>

@endforeach

</div>

    </div>

</div>


<!-- SEARCH FILTER BAR -->
<div class="search-filters">

<button onclick="addSearch('from:')">
<i class="fa-solid fa-user"></i> From
</button>

<button onclick="addSearch('subject:')">
<i class="fa-solid fa-pen"></i> Subject
</button>

<button onclick="addSearch('has:attachment')">
<i class="fa-solid fa-paperclip"></i> Attachment
</button>

<button onclick="addSearch('folder:inbox')">
<i class="fa-solid fa-inbox"></i> Inbox
</button>

<button onclick="addSearch('folder:archive')">
<i class="fa-solid fa-box-archive"></i> Archive
</button>

</div>

<!-- MAIN -->

<!-- TOOLBAR -->

<div class="toolbar">

<button class="primary-btn" onclick="composeMail()">
    <i class="fa-solid fa-envelope"></i>

New mail
</button>

<button class="primary-btn" onclick="deleteSelected()">
 <i class="fa-solid fa-trash"></i>
 Delete
</button>



<button onclick="archiveSelected()">
    <i class="fa-solid fa-box-archive"></i>
Archive
</button>

<button onclick="replySelected()"><i class="fa-solid fa-reply"></i> Reply</button>

<button onclick="forwardSelected()"><i class="fa-solid fa-forward"></i> Forward</button>

<button onclick="markReadSelected()"><i class="fa-solid fa-envelope-open"></i> Mark read</button>

<button onclick="refreshCurrentFolder()">
    <i class="fa-solid fa-rotate"></i>
    Refresh
</button>

<button id="emptyTrashBtn" onclick="emptyTrash()" style="display:none">
🧹 Empty Trash
</button>


<button id="recoverBtn" onclick="recoverSelected()" style="display:none">
♻ Restore
</button>




</div>

<div class="main {{ (isset($hidePreview) && $hidePreview) ? 'full-width' : '' }}">


<!-- ICON BAR -->

<div class="iconbar"></div>


<!-- SIDEBAR -->

@include('mail.sidebar')


<!-- MAIL LIST -->

<div class="mail-list">

@yield('list')

</div>


<!-- MAIL PREVIEW -->

@if(!isset($hidePreview) || !$hidePreview)
<div class="mail-preview">

    <div class="empty-preview">
        📧
        <br>
        Select an email to read
    </div>

    

</div>
@endif

@yield('preview')

</div>

</div>

<!-- <script src="https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js"></script> -->


<div id="attachmentViewer" class="attachment-viewer">

<div class="attachment-toolbar">

<button onclick="prevAttachment()">◀</button>
<button onclick="nextAttachment()">▶</button>

<div class="attachment-title" id="attachmentTitle"></div>

<div class="attachment-actions">

<button onclick="openAttachment()">
<i class="fa-solid fa-arrow-up-right-from-square"></i>
</button>

<button onclick="downloadAttachment()">
<i class="fa-solid fa-download"></i>
</button>

<div class="attachment-close" onclick="closeAttachmentViewer()">✕</div>

</div>

</div>

<div class="attachment-body" id="attachmentBody"></div>

</div>



<div id="folderMenu" class="folder-menu">

<div onclick="menuRename()">Rename</div>

<div onclick="menuDelete()">Delete</div>

<div onclick="menuCreate()">New Subfolder</div>

</div>

<div id="settingsOverlay" class="settings-overlay">

<div class="settings-panel">

<div class="settings-header">

<div>Settings</div>

<div class="settings-close" onclick="closeSettings()">✕</div>

</div>

<div class="settings-body">

<div class="settings-sidebar">

<div class="settings-item active"
onclick="loadRules()">

<i class="fa-solid fa-filter"></i>
Rules

</div>

</div>

<div class="settings-content" id="settingsContent">

Loading...

</div>

</div>

</div>

</div>

<div id="onedrivePanel" class="onedrive-panel">

<div class="onedrive-header">

<div>
<i class="fa-solid fa-cloud"></i>
OneDrive
</div>

<div onclick="closeOneDrive()" class="onedrive-close">✕</div>

</div>

<div class="onedrive-body" id="onedriveFiles">

Loading...

</div>

</div>
<script>
  window.__MAIL_NEXT_PAGE__ = @json($nextLink ?? null);
</script>

</body>
</html>
