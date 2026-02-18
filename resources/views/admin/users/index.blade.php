@extends('admin.layout')

@section('title', 'Users')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="mb-0">Users</h3>
            <p class="text-muted">Manage admin access for your team.</p>
        </div>
        <a href="{{ route('admin.users.create') }}" class="btn btn-primary">Add User</a>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Joined</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $user)
                            <tr>
                                <td class="fw-semibold">{{ $user->name }}</td>
                                <td>{{ $user->email }}</td>
                                <td>
                                    @if ($user->is_admin)
                                        <span class="badge bg-success">Admin</span>
                                    @else
                                        <span class="badge bg-secondary">Customer</span>
                                    @endif
                                </td>
                                <td>{{ $user->created_at?->format('Y-m-d') }}</td>
                                <td class="text-end">
                                    <form method="POST" action="{{ route('admin.users.toggle-admin', $user) }}" class="d-inline">
                                        @csrf
                                        @method('PATCH')
                                        @if (auth()->id() === $user->id)
                                            <button type="button" class="btn btn-outline-secondary btn-sm" disabled>Current User</button>
                                        @else
                                            <button type="submit" class="btn btn-outline-primary btn-sm">
                                                {{ $user->is_admin ? 'Remove Admin' : 'Make Admin' }}
                                            </button>
                                        @endif
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted">No users found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $users->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>
@endsection
