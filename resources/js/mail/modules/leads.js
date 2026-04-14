/* =========================
   LEADS MODULE (SSE VERSION)
========================= */

let source = null;

/* =========================
   EXTRACTION
========================= */

export async function handleExtract(){

    const TOKEN_ID = window.ACTIVE_TOKEN_ID;

    const btn = document.getElementById('extractBtn');
    if(!btn || btn.disabled) return;

    btn.disabled = true;
    btn.innerText = '⏳ Starting...';

    const res = await fetch(`/leads/start?token_id=${TOKEN_ID}`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    });

    const data = await res.json();

    if(data.status === 'locked'){
        btn.innerText = '⏳ Running...';
        startSSE();
        return;
    }

    startSSE();
}

/* =========================
   SSE STREAM
========================= */


let sseRetryTimer = null;
let sseClosedByApp = false;

function startSSE() {
    const TOKEN_ID = window.ACTIVE_TOKEN_ID;

    if (source) {
        source.close();
    }

    if (sseRetryTimer) {
        clearTimeout(sseRetryTimer);
        sseRetryTimer = null;
    }

    sseClosedByApp = false;

    source = new EventSource(`/leads/stream?token_id=${TOKEN_ID}`);

    source.onmessage = function(event) {
        const data = JSON.parse(event.data);

        const btn = document.getElementById('extractBtn');
        if (!btn) return;

        document.getElementById('statusText').innerText =
            data.status + (data.total ? ` (${data.total})` : '');

        document.getElementById('progressMsg').innerText =
            data.message || '';

        if (data.total) {
            document.getElementById('totalLeads').innerText =
                `📊 ${data.total} leads collected`;

            let percent = Math.min(95, Math.log10(data.total + 1) * 30);
            document.getElementById('progressBar').style.width = percent + '%';
        }

        if (data.status === 'processing') {
            btn.innerText = '⏳ Extracting...';
            btn.disabled = true;
        }

        if (data.status === 'done') {
            document.getElementById('progressBar').style.width = '100%';
            document.getElementById('statusText').innerText = '✅ Ready to download';

            btn.innerText = '✅ Done';
            btn.disabled = true;

            document.getElementById('csvBtn').disabled = false;
            document.getElementById('txtBtn').disabled = false;

            sseClosedByApp = true;
            source.close();
        }

        if (data.status === 'failed') {
            document.getElementById('statusText').innerText =
                '❌ Error: ' + (data.message || 'Unknown error');

            btn.innerText = 'Retry';
            btn.disabled = false;

            sseClosedByApp = true;
            source.close();
        }
    };

    source.onerror = function(e) {
        console.error("SSE ERROR", e);

        if (source) {
            source.close();
        }

        if (!sseClosedByApp) {
            sseRetryTimer = setTimeout(() => {
                startSSE();
            }, 3000);
        }
    };
}

/* =========================
   DOWNLOAD
========================= */

let currentDownloadPage = 1;
let currentType = null;

export async function startDownload(type){

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

    const TOKEN_ID = window.ACTIVE_TOKEN_ID;

    const nextBtn = document.getElementById('nextBtn');
    nextBtn.disabled = true;

    const res = await fetch(
        `/leads/export/${currentType}?page=${currentDownloadPage}&token_id=${TOKEN_ID}`
    );

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

export async function downloadNext(){
    currentDownloadPage++;
    await downloadPage();
}

/* =========================
   AUTO RESUME (SSE)
========================= */

export async function initLeads(){

    const TOKEN_ID = window.ACTIVE_TOKEN_ID;

    const res = await fetch(`/leads/status?token_id=${TOKEN_ID}`);
    const data = await res.json();

    const btn = document.getElementById('extractBtn');
    if(!btn) return;

    if(data.status === 'processing'){
        btn.innerText = '⏳ Extracting...';
        btn.disabled = true;

        startSSE(); // 🔥 langsung stream
    }

    if(data.status === 'idle' || !data.status){
        document.getElementById('statusText').innerText = 'idle';
        document.getElementById('progressBar').style.width = '0%';
    }

    if(data.status === 'done'){
        btn.innerText = '✅ Done';
        btn.disabled = true;

        document.getElementById('progressBar').style.width = '100%';
        document.getElementById('statusText').innerText = '✅ Ready to download';

        document.getElementById('csvBtn').disabled = false;
        document.getElementById('txtBtn').disabled = false;
    }
}