@extends('mail.layout')

@section('list')

<div style="font-size:11px;color:#aaa;margin:6px 10px;">
    Powered by async extraction
</div>

<style>
.leads-container {
    padding: 30px;
    background: #f6f8fb;
    height: 100%;
}
.card {
    background: white;
    border-radius: 12px;
    padding: 25px;
    max-width: 600px;
    margin: auto;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
}
.title {
    font-size: 22px;
    font-weight: 600;
}
.sub {
    font-size: 13px;
    color: #777;
    margin-top: 5px;
}
.status {
    margin-top: 15px;
    display: inline-block;
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 12px;
    background: #eef2ff;
    color: #4f46e5;
}
.progress {
    margin-top: 15px;
    height: 6px;
    background: #eee;
    border-radius: 10px;
    overflow: hidden;
}
.progress-bar {
    height: 100%;
    width: 0%;
    background: #4f46e5;
    transition: width 0.3s;
}
.actions {
    margin-top: 20px;
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}
.btn {
    padding: 10px 14px;
    border-radius: 10px;
    border: none;
    cursor: pointer;
    font-size: 13px;
}
.btn-primary {
    background: #4f46e5;
    color: white;
}
.btn-light {
    background: #f3f4f6;
}
.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
.info {
    margin-top: 10px;
    font-size: 12px;
    color: #555;
}
</style>

<div class="leads-container">
<div class="card">

    <div class="title">Leads Extractor</div>

    <div class="sub">
        Extract emails in background and download in batches
    </div>

    <div id="statusText" class="status">idle</div>

    <div class="progress">
        <div id="progressBar" class="progress-bar"></div>
    </div>

    <div id="progressMsg" class="info"></div>
    <div id="totalLeads" class="info"></div>

    <div class="actions">
        <button id="extractBtn" class="btn btn-primary" onclick="handleExtract()">
            🚀 Start Extract
        </button>

        <button id="csvBtn" class="btn btn-light" onclick="startDownload('csv')" disabled>
            ⬇ CSV
        </button>

        <button id="txtBtn" class="btn btn-light" onclick="startDownload('txt')" disabled>
            ⬇ TXT
        </button>

        <button id="nextBtn" class="btn btn-light" style="display:none;" onclick="downloadNext()">
            ➡ Next
        </button>
    </div>

</div>
</div>

@endsection


@section('preview')
@endsection


@push('scripts')
@endpush