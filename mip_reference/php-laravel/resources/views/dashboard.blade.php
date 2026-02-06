@extends('layout')

@section('content')
<div class="grid grid-2">
    <div class="card">
        <div class="card-header">Node Information</div>
        <div class="card-body">
            <table>
                <tr>
                    <th style="width: 40%;">Organization</th>
                    <td>{{ $identity['organization_name'] }}</td>
                </tr>
                <tr>
                    <th>MIP Identifier</th>
                    <td><code class="mono">{{ $identity['mip_identifier'] }}</code></td>
                </tr>
                <tr>
                    <th>MIP URL</th>
                    <td><code class="mono">{{ $identity['mip_url'] }}</code></td>
                </tr>
                <tr>
                    <th>Contact Person</th>
                    <td>{{ $identity['contact_person'] }}</td>
                </tr>
                <tr>
                    <th>Contact Phone</th>
                    <td>{{ $identity['contact_phone'] }}</td>
                </tr>
                <tr>
                    <th>Trust Threshold</th>
                    <td>{{ $identity['trust_threshold'] }} endorsement(s)</td>
                </tr>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Statistics</div>
        <div class="card-body">
            <div class="grid grid-2">
                <div class="stat-card">
                    <div class="number">{{ $stats['active_connections'] }}</div>
                    <div class="label">Active Connections</div>
                </div>
                <div class="stat-card">
                    <div class="number">{{ $stats['pending_inbound'] }}</div>
                    <div class="label">Pending Inbound</div>
                </div>
                <div class="stat-card">
                    <div class="number">{{ $stats['total_members'] }}</div>
                    <div class="label">Local Members</div>
                </div>
                <div class="stat-card">
                    <div class="number">{{ $stats['total_searches'] }}</div>
                    <div class="label">Search Requests</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">Recent Activity</div>
    <div class="card-body">
        @if(count($activity_log) > 0)
            @foreach($activity_log as $activity)
                <div class="activity-item">
                    <span class="activity-time">{{ \Carbon\Carbon::parse($activity['timestamp'])->format('H:i:s') }}</span>
                    <span class="activity-message">{{ $activity['message'] }}</span>
                </div>
            @endforeach
        @else
            <div class="empty-state">No recent activity</div>
        @endif
    </div>
</div>

<div class="card">
    <div class="card-header">Quick Actions</div>
    <div class="card-body">
        <div style="display: flex; gap: 1rem;">
            <a href="{{ route('connections.index') }}" class="btn btn-primary">Manage Connections</a>
            <a href="{{ route('searches.create') }}" class="btn btn-primary">New Search</a>
            <a href="{{ route('cogs.create') }}" class="btn btn-primary">Request COGS</a>
        </div>
    </div>
</div>
@endsection
