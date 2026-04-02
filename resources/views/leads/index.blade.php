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


<script>

let monitor = null;

/* =========================
   EXTRACTION
========================= */

async function handleExtract(){

    const btn = document.getElementById('extractBtn');

    if(btn.disabled) return;

    btn.disabled = true;
    btn.innerText = '⏳ Starting...';

    const res = await fetch('/leads/start', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    });

    const data = await res.json();

    if(data.status === 'locked' || data.status === 'already_running'){
        btn.innerText = '⏳ Running...';
        monitorProgress();
        return;
    }

    monitorProgress();
}

function monitorProgress(){

    if(monitor) clearInterval(monitor);

    monitor = setInterval(async () => {

        const res = await fetch('/leads/status');
        const data = await res.json();

        const btn = document.getElementById('extractBtn');

        // STATUS TEXT
        document.getElementById('statusText').innerText =
            data.status + (data.total ? ` (${data.total})` : '');

        // MESSAGE
        document.getElementById('progressMsg').innerText =
            data.message || '';

        // TOTAL LEADS
        if(data.total){
            document.getElementById('totalLeads').innerText =
                `📊 ${data.total} leads collected`;
        }

        // PROGRESS BAR (smooth fake but stable)
        if(data.total){
            let percent = Math.min(95, Math.log10(data.total + 1) * 30);
            document.getElementById('progressBar').style.width = percent + '%';
        }

        // STATES
        if(data.status === 'processing'){
            btn.innerText = '⏳ Extracting...';
            btn.disabled = true;
        }

        if(data.status === 'continue'){
            btn.innerText = '➕ Continue Extract';
            btn.disabled = false;
        }

        if(data.status === 'done'){
            document.getElementById('progressBar').style.width = '100%';

            document.getElementById('statusText').innerText =
                '✅ Ready to download';

            btn.innerText = '✅ Done';
            btn.disabled = true;

            document.getElementById('csvBtn').disabled = false;
            document.getElementById('txtBtn').disabled = false;

            clearInterval(monitor);
        }

        if(data.status === 'failed'){
            document.getElementById('statusText').innerText =
                '❌ Error: ' + data.message;

            btn.innerText = 'Retry';
            btn.disabled = false;

            clearInterval(monitor);
        }

    }, 1500);
}


/* =========================
   DOWNLOAD PAGINATION
========================= */

let currentDownloadPage = 1;
let currentType = null;

async function startDownload(type){

    currentDownloadPage = 1;
    currentType = type;

    const btn = event.target;
    btn.innerText = '⏳ Preparing...';
    btn.disabled = true;

    document.getElementById('nextBtn').style.display = 'none';

    await downloadPage();

    btn.innerText = type.toUpperCase();
    btn.disabled = false;
}

async function downloadPage(){

    const nextBtn = document.getElementById('nextBtn');
    nextBtn.disabled = true;

    const res = await fetch(`/leads/export/${currentType}?page=${currentDownloadPage}`);

    if(res.status === 204){
        document.getElementById('statusText').innerText = '⚠ No data';
        return;
    }

    const blob = await res.blob();
    const url = URL.createObjectURL(blob);

    const a = document.createElement('a');
    a.href = url;
    a.download = `leads_page_${currentDownloadPage}.${currentType}`;
    a.click();

    const hasMore = res.headers.get('X-Has-More');
    const total = parseInt(res.headers.get('X-Total') || 0);
    const batch = parseInt(res.headers.get('X-Batch-Size') || 1);

    nextBtn.disabled = false;

    if(hasMore === '1'){
        const totalPage = Math.ceil(total / batch);

        nextBtn.style.display = 'inline-block';
        nextBtn.innerText = `➡ Next (${currentDownloadPage + 1}/${totalPage})`;
    } else {
        nextBtn.style.display = 'none';

        document.getElementById('statusText').innerText =
            '✅ All files downloaded';
    }
}

async function downloadNext(){
    currentDownloadPage++;
    await downloadPage();
}


/* =========================
   AUTO RESUME
========================= */

document.addEventListener("DOMContentLoaded", async () => {

    const res = await fetch('/leads/status');
    const data = await res.json();

    const btn = document.getElementById('extractBtn');

    if(data.status === 'processing'){
        btn.innerText = '⏳ Extracting...';
        btn.disabled = true;
        monitorProgress();
    }
    if(data.status === 'idle' || !data.status){
    document.getElementById('statusText').innerText = 'idle';
    document.getElementById('progressBar').style.width = '0%';
}

    if(data.status === 'continue'){
        btn.innerText = '➕ Continue Extract';
        btn.disabled = false;
        monitorProgress();
    }

    if(data.status === 'done'){
        btn.innerText = '✅ Done';
        btn.disabled = true;

        document.getElementById('progressBar').style.width = '100%';
        document.getElementById('statusText').innerText = '✅ Ready to download';

        document.getElementById('csvBtn').disabled = false;
        document.getElementById('txtBtn').disabled = false;
    }

});
</script>