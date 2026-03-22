@extends('layouts.admin')

@section('content')

<div class="card p-4">

    <div class="d-flex justify-content-between mb-3">
        <h3>Sub Users</h3>

        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
            + Add Sub User
        </button>
    </div>

    <table class="table">
        <tr>
            <th>#</th>
            <th>Login Key</th>
            <th>Token Access</th>
            <th>Action</th>
        </tr>

        @foreach($subUsers as $i => $user)
        <tr>
            <td>#{{ $i+1 }}</td>

            <td>
                {{ $user->login_key }}

                <!-- 🔥 EDIT KEY -->
                <button class="btn btn-sm btn-info ms-2"
                    onclick="openKeyModal({{ $user->id }}, '{{ $user->login_key }}')">
                    🔑
                </button>
            </td>

            <td>
                @foreach($user->accessibleTokens as $token)
                    <span class="badge bg-success me-1">
                        {{ $token->email }}
                    </span>
                @endforeach
            </td>

            <td>
                <!-- EDIT TOKEN -->
                <button class="btn btn-sm btn-warning"
                    onclick="openEditModal({{ $user->id }})">
                    ✏️
                </button>

                <!-- DELETE -->
                <form method="POST" action="/sub-users/{{ $user->id }}" style="display:inline;">
                    @csrf
                    @method('DELETE')

                    <button class="btn btn-sm btn-danger"
                        onclick="return confirm('Delete this sub user?')">
                        🗑
                    </button>
                </form>
            </td>
        </tr>
        @endforeach
    </table>

</div>

{{-- ================= ADD MODAL ================= --}}
<div class="modal fade" id="addModal">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="/sub-users">
            @csrf

            <div class="modal-content bg-dark text-light">

                <div class="modal-header">
                    <h5>Add Sub User</h5>
                </div>

                <div class="modal-body">

                    <input type="text" name="login_key"
                        placeholder="Login Key"
                        class="form-control mb-4"
                        required>

                    <h6>Assign Tokens</h6>

                    <div class="row">
                        @foreach($tokens as $token)
                        <div class="col-md-6 mb-3">
                            <label class="token-card">
                                <input type="checkbox" name="token_ids[]" value="{{ $token->id }}" hidden>

                                <div class="card-inner">
                                    <div class="fw-bold">{{ $token->email }}</div>
                                    <div class="text-muted small">{{ $token->name }}</div>

                                    <span class="badge 
                                        {{ $token->status == 'active' ? 'bg-success' : 'bg-secondary' }}">
                                        {{ $token->status }}
                                    </span>
                                </div>
                            </label>
                        </div>
                        @endforeach
                    </div>

                </div>

                <div class="modal-footer">
                    <button class="btn btn-primary">Save</button>
                </div>

            </div>
        </form>
    </div>
</div>

{{-- ================= EDIT TOKEN MODAL ================= --}}
<div class="modal fade" id="editModal">
    <div class="modal-dialog modal-lg">
        <form method="POST" id="editForm">
            @csrf

            <div class="modal-content bg-dark text-light">

                <div class="modal-header">
                    <h5>Edit Token Access</h5>
                </div>

                <div class="modal-body">

                    <div class="row">
                        @foreach($tokens as $token)
                        <div class="col-md-6 mb-3">
                            <label class="token-card">
                                <input type="checkbox"
                                    class="edit-checkbox"
                                    name="token_ids[]"
                                    value="{{ $token->id }}"
                                    hidden>

                                <div class="card-inner">
                                    <div class="fw-bold">{{ $token->email }}</div>
                                    <div class="text-muted small">{{ $token->name }}</div>

                                    <span class="badge 
                                        {{ $token->status == 'active' ? 'bg-success' : 'bg-secondary' }}">
                                        {{ $token->status }}
                                    </span>
                                </div>
                            </label>
                        </div>
                        @endforeach
                    </div>

                </div>

                <div class="modal-footer">
                    <button class="btn btn-primary">Save</button>
                </div>

            </div>
        </form>
    </div>
</div>

{{-- ================= 🔑 EDIT LOGIN KEY MODAL ================= --}}
<div class="modal fade" id="keyModal">
    <div class="modal-dialog">
        <form method="POST" id="keyForm">
            @csrf

            <div class="modal-content bg-dark text-light">

                <div class="modal-header">
                    <h5>Edit Login Key</h5>
                </div>

                <div class="modal-body">
                    <input type="text" name="login_key" id="keyInput"
                        class="form-control" required>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-primary">Save</button>
                </div>

            </div>
        </form>
    </div>
</div>

{{-- ================= STYLE ================= --}}
<style>
.token-card {
    display: block;
    cursor: pointer;
}

.card-inner {
    background: #111;
    border: 1px solid #00ff7755;
    padding: 15px;
    border-radius: 8px;
    transition: 0.2s;
}

.token-card:hover .card-inner {
    box-shadow: 0 0 10px #00ffaa88;
    transform: scale(1.02);
}

.token-card input:checked + .card-inner {
    border: 2px solid #00ffaa;
    background: #002f2f;
}
</style>

{{-- ================= SCRIPT ================= --}}
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
function openEditModal(userId) {
    let form = document.getElementById('editForm');
    form.action = `/sub-users/${userId}/tokens`;

    document.querySelectorAll('.edit-checkbox').forEach(cb => cb.checked = false);

    fetch(`/api/sub-user/${userId}`)
        .then(res => res.json())
        .then(data => {

            data.tokens.forEach(id => {
                let checkbox = document.querySelector(`.edit-checkbox[value="${id}"]`);
                if (checkbox) checkbox.checked = true;
            });

            new bootstrap.Modal(document.getElementById('editModal')).show();
        });
}

function openKeyModal(id, key) {
    let form = document.getElementById('keyForm');
    form.action = `/sub-users/${id}/update-key`;

    document.getElementById('keyInput').value = key;

    new bootstrap.Modal(document.getElementById('keyModal')).show();
}
</script>

@endsection