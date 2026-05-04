@extends('layouts.admin')

@section('content')

    <style>
        /* Hacker theme consistent */
        .hacker-card {
            background: rgba(0, 0, 0, 0.85);
            border: 1px solid #0aff0a55;
            border-radius: 8px;
            padding: 25px;
            margin-top: 25px;
            box-shadow: 0 0 20px #0aff0a33;
        }

        .hacker-title {
            font-size: 22px;
            font-weight: 700;
            color: #00ff95;
            border-bottom: 1px solid #00ff9555;
            padding-bottom: 8px;
            margin-bottom: 20px;
        }

        .btn-hacker {
            background: #00ff95;
            border: none;
            padding: 10px 20px;
            color: #000;
            font-weight: 700;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-hacker:hover {
            background: #00ffa0;
        }

        .form-label {
            color: #00ff95;
            font-weight: 600;
            margin-top: 15px;
        }
    </style>

    <div class="container-fluid">
        <div class="hacker-card">
            <div class="hacker-title">User Settings</div>

            @if(session('success'))
                <div style="color:#00ff95; margin-bottom:10px;">
                    {{ session('success') }}
                </div>
            @endif

            <form method="POST" action="{{ route('settings.update') }}">
                @csrf

                <label class="form-label">Login Key</label>
                <input type="text" name="login_key" class="form-control" value="{{ $login_key }}">

                <label class="form-label">Telegram ID </label>
                <input type="text" name="telegram_id_1" class="form-control" value="{{ $settings->telegram_id_1 ?? '' }}">

                <label class="form-label">Telegram Bot</label>
                <input type="text" name="telegram_bot_1" class="form-control" value="{{ $settings->telegram_bot_1 ?? '' }}">



                <button type="submit" class="btn-hacker mt-4">Save Changes</button>
            </form>
        </div>
    </div>

@endsection