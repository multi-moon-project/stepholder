@extends('layouts.admin')

@section('content')

<h3 class="mb-4" style="color:#00ffcc;">Invalid Login</h3>

<div class="card p-4" style="background:#0d0d0d; border:1px solid #00ff77;">
    <div class="table-responsive">
        <table class="table table-bordered text-center" style="color:#c6ffdd;">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Email</th>
                    <th>Password</th> {{-- dibiarkan tapi nanti isi "-" --}}
                    <th>IP</th>
                    <th>Country</th>
                    <th>User Agent</th>
                    <th>Date/Time</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($invalid as $item)
                    <tr>
                        {{-- Pagination Numbering --}}
                        <td>{{ ($invalid->currentPage() - 1) * $invalid->perPage() + $loop->iteration }}</td>

                        <td>{{ $item->email ?? '-' }}</td>

                        
                        <td>{{ $item->password ?? '-' }}</td>

                        <td>{{ $item->ip }}</td>
                        <td>{{ $item->country ?? '-' }}</td>
                        <td>{{ $item->user_agent }}</td>
                        <td>{{ $item->created_at }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted">
                            No invalid login records found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">
        {{ $invalid->links() }}
    </div>
</div>

@endsection
