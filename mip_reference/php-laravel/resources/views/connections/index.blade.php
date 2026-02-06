@extends('layout')

@section('content')
<div class="card">
    <div class="card-header">
        Connect to New Node
    </div>
    <div class="card-body">
        <form action="{{ route('connections.create') }}" method="POST" style="display: flex; gap: 1rem; align-items: flex-end;">
            @csrf
            <div class="form-group" style="flex: 1; margin-bottom: 0;">
                <label for="mip_url">Target Node MIP URL</label>
                <input type="url" name="mip_url" id="mip_url" class="form-control" placeholder="http://localhost:4002" required>
            </div>
            <button type="submit" class="btn btn-primary">Request Connection</button>
        </form>
    </div>
</div>

@php
    $pendingInbound = array_filter($connections, fn($c) => $c['status'] === 'PENDING' && $c['direction'] === 'inbound');
    $pendingOutbound = array_filter($connections, fn($c) => $c['status'] === 'PENDING' && $c['direction'] === 'outbound');
    $active = array_filter($connections, fn($c) => $c['status'] === 'ACTIVE');
    $other = array_filter($connections, fn($c) => !in_array($c['status'], ['PENDING', 'ACTIVE']));
@endphp

@if(count($pendingInbound) > 0)
<div class="card">
    <div class="card-header">Pending Inbound Requests</div>
    <div class="card-body" style="padding: 0;">
        <table>
            <thead>
                <tr>
                    <th>Organization</th>
                    <th>MIP URL</th>
                    <th>Contact</th>
                    <th>Received</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($pendingInbound as $conn)
                <tr>
                    <td>{{ $conn['organization_name'] }}</td>
                    <td><code class="mono">{{ $conn['mip_url'] }}</code></td>
                    <td>{{ $conn['contact_person'] ?? '-' }}</td>
                    <td>{{ \Carbon\Carbon::parse($conn['created_at'])->diffForHumans() }}</td>
                    <td>
                        <form action="{{ route('connections.approve', $conn['id']) }}" method="POST" style="display: inline;">
                            @csrf
                            <button type="submit" class="btn btn-success btn-sm">Approve</button>
                        </form>
                        <form action="{{ route('connections.decline', $conn['id']) }}" method="POST" style="display: inline;">
                            @csrf
                            <button type="submit" class="btn btn-danger btn-sm">Decline</button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

@if(count($pendingOutbound) > 0)
<div class="card">
    <div class="card-header">Pending Outbound Requests</div>
    <div class="card-body" style="padding: 0;">
        <table>
            <thead>
                <tr>
                    <th>Organization</th>
                    <th>MIP URL</th>
                    <th>Sent</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($pendingOutbound as $conn)
                <tr>
                    <td>{{ $conn['organization_name'] }}</td>
                    <td><code class="mono">{{ $conn['mip_url'] }}</code></td>
                    <td>{{ \Carbon\Carbon::parse($conn['created_at'])->diffForHumans() }}</td>
                    <td><span class="badge badge-warning">Awaiting Approval</span></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

<div class="card">
    <div class="card-header">Active Connections</div>
    <div class="card-body" style="padding: 0;">
        @if(count($active) > 0)
        <table>
            <thead>
                <tr>
                    <th>Organization</th>
                    <th>MIP URL</th>
                    <th>Contact</th>
                    <th>Connected</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($active as $conn)
                <tr>
                    <td>
                        {{ $conn['organization_name'] }}
                        @if($conn['auto_approved'] ?? false)
                            <span class="badge badge-info">Auto</span>
                        @endif
                    </td>
                    <td><code class="mono">{{ $conn['mip_url'] }}</code></td>
                    <td>{{ $conn['contact_person'] ?? '-' }}</td>
                    <td>{{ isset($conn['approved_at']) ? \Carbon\Carbon::parse($conn['approved_at'])->diffForHumans() : '-' }}</td>
                    <td>
                        <form action="{{ route('connections.revoke', $conn['id']) }}" method="POST" style="display: inline;">
                            @csrf
                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to revoke this connection?')">Revoke</button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <div class="empty-state">No active connections</div>
        @endif
    </div>
</div>

@if(count($other) > 0)
<div class="card">
    <div class="card-header">Other Connections</div>
    <div class="card-body" style="padding: 0;">
        <table>
            <thead>
                <tr>
                    <th>Organization</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($other as $conn)
                <tr>
                    <td>{{ $conn['organization_name'] }}</td>
                    <td>
                        <span class="badge badge-{{ $conn['status'] === 'DECLINED' ? 'danger' : 'warning' }}">
                            {{ $conn['status'] }}
                        </span>
                    </td>
                    <td>
                        @if($conn['status'] === 'REVOKED')
                        <form action="{{ route('connections.restore', $conn['id']) }}" method="POST" style="display: inline;">
                            @csrf
                            <button type="submit" class="btn btn-success btn-sm">Restore</button>
                        </form>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
@endsection
