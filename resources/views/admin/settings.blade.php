@extends('layouts.admin')

@section('content')

<style>
/* pakai style hacker biar konsisten */
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

.btn-hacker {
    background: #00ff95;
    border: none;
    padding: 8px 16px;
    color: #000;
    font-weight: 700;
    border-radius: 4px;
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
        <form method="POST" action="/settings/update">
            @csrf

            <label class="form-label">Login Key</label>
            <input type="text" name="login_key" class="form-control"
                value="{{ $login_key }}">

            <button class="btn-hacker mt-3">
                Save Changes
            </button>
        </form>

    </div>

</div>

@endsection