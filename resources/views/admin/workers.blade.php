@extends('layouts.admin')

@section('content')

    <div class="card p-4">

        <div class="d-flex justify-content-between mb-3">
            <h3>☁️ URL LINK</h3>

            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
                + Create URL
            </button>
        </div>

        {{-- TABLE --}}
        <table class="table">
            <tr>
                <th>#</th>
                <th>Name</th>
                <th>URL</th>
                <th>Mode</th>
                <th>Type</th>
                <th>Action</th>
            </tr>

            @foreach($workers as $i => $w)
                <tr>
                    <td>#{{ $i + 1 }}</td>
                    <td>{{ $w->worker_name }}</td>

                    <td>
                        <a href="{{ $w->worker_url }}" target="_blank" style="color:#00ffcc;">
                            {{ $w->worker_url }}
                        </a>
                    </td>

                    {{-- MODE --}}
                    <td>
                        {{ $w->mode ?? 'token' }}
                    </td>

                    {{-- TYPE --}}
                    <td>
                        {{ $w->type }}
                    </td>

                    {{-- ACTION --}}
                    <td>
                        <form method="POST" action="/workers/{{ $w->id }}" style="display:inline;">
                            @csrf
                            @method('DELETE')

                            <button class="btn btn-sm btn-danger" style="box-shadow:0 0 10px #ff4444;">
                                Delete
                            </button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </table>

    </div>

    {{-- ================= MODAL CREATE ================= --}}
    <div class="modal fade" id="addModal">
        <div class="modal-dialog">
            <form method="POST" action="/workers">
                @csrf

                <div class="modal-content bg-dark text-light">

                    <div class="modal-header">
                        <h5>Create URL</h5>
                    </div>

                    <div class="modal-body">

                        <input id="workerName" type="text" name="name" placeholder="example-worker-name"
                            class="form-control" required />
                        <div class="mb-3">
                            <label>Mode</label>
                            <select name="mode" class="form-control" required>
                                <option value="token">Token</option>
                                <option value="cookie">Cookies</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label>Template Type</label>
                            <select name="type" class="form-control" required>
                                <option value="docusign">DocuSign</option>
                                <option value="resetpassword">Reset Password</option>
                            </select>
                        </div>

                        <small id="nameStatus" style="color:#888; display:block; margin-top:5px;">
                            Only lowercase, number, dash (-), 3-50 char
                        </small>

                    </div>

                    <div class="modal-footer">
                        <button id="createBtn" class="btn btn-primary" disabled>
                            Create
                        </button>
                    </div>

                </div>
            </form>
        </div>
    </div>


    {{-- ================= STYLE ================= --}}
    <style>
        .valid {
            color: #00ff88;
        }

        .invalid {
            color: #ff4444;
        }

        .checking {
            color: #ffaa00;
        }
    </style>

    {{-- ================= SCRIPT ================= --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        const input = document.getElementById('workerName');
        const btn = document.getElementById('createBtn');
        const statusText = document.getElementById('nameStatus');

        let debounceTimer;

        // 🔥 VALIDATE LOCAL
        function validateLocal(val) {
            const regex = /^[a-z0-9-]+$/;
            return regex.test(val) && val.length >= 3 && val.length <= 50;
        }

        // 🔥 CHECK CLOUDFLARE
        async function checkCloudflare(val) {
            try {
                const res = await fetch(`/workers/check-name?name=${val}`);
                const data = await res.json();
                return data.available;
            } catch (e) {
                return false;
            }
        }

        // 🔥 INPUT HANDLER
        input.addEventListener('input', function () {

            let val = this.value
                .toLowerCase()
                .replace(/[^a-z0-9-]/g, '')
                .replace(/\s+/g, '-');

            this.value = val;

            btn.disabled = true;

            clearTimeout(debounceTimer);

            if (!validateLocal(val)) {
                statusText.innerHTML = "❌ Invalid format";
                statusText.className = "invalid";
                return;
            }

            statusText.innerHTML = "⏳ Checking...";
            statusText.className = "checking";

            debounceTimer = setTimeout(async () => {

                const available = await checkCloudflare(val);

                if (available) {
                    statusText.innerHTML = "✅ Available";
                    statusText.className = "valid";
                    btn.disabled = false;
                } else {
                    statusText.innerHTML = "❌ Already used";
                    statusText.className = "invalid";
                    btn.disabled = true;
                }

            }, 500);

        });
    </script>

@endsection