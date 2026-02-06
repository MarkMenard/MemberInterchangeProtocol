@extends('layout')

@section('content')
<div class="card">
    <div class="card-header">
        Certificates of Good Standing
        <a href="{{ route('cogs.create') }}" class="btn btn-primary btn-sm">Request COGS</a>
    </div>
</div>

@if(count($inbound_requests) > 0)
<div class="card">
    <div class="card-header">Inbound COGS Requests</div>
    <div class="card-body" style="padding: 0;">
        <table>
            <thead>
                <tr>
                    <th>From</th>
                    <th>Member #</th>
                    <th>Status</th>
                    <th>Received</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($inbound_requests as $request)
                <tr>
                    <td>{{ $request['source_organization'] }}</td>
                    <td><code class="mono">{{ $request['member_number'] }}</code></td>
                    <td>
                        <span class="badge badge-{{ $request['status'] === 'PENDING' ? 'warning' : ($request['status'] === 'APPROVED' ? 'success' : 'danger') }}">
                            {{ $request['status'] }}
                        </span>
                    </td>
                    <td>{{ \Carbon\Carbon::parse($request['created_at'])->diffForHumans() }}</td>
                    <td>
                        @if($request['status'] === 'PENDING')
                        <form action="{{ route('cogs.approve', $request['id']) }}" method="POST" style="display: inline;">
                            @csrf
                            <button type="submit" class="btn btn-success btn-sm">Approve</button>
                        </form>
                        <form action="{{ route('cogs.decline', $request['id']) }}" method="POST" style="display: inline;">
                            @csrf
                            <button type="submit" class="btn btn-danger btn-sm">Decline</button>
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

<div class="card">
    <div class="card-header">Outbound COGS Requests</div>
    <div class="card-body" style="padding: 0;">
        @if(count($outbound_requests) > 0)
        <table>
            <thead>
                <tr>
                    <th>To</th>
                    <th>Member #</th>
                    <th>Status</th>
                    <th>Sent</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($outbound_requests as $request)
                <tr>
                    <td>{{ $request['target_organization'] }}</td>
                    <td><code class="mono">{{ $request['member_number'] }}</code></td>
                    <td>
                        <span class="badge badge-{{ $request['status'] === 'PENDING' ? 'warning' : ($request['status'] === 'APPROVED' ? 'success' : 'danger') }}">
                            {{ $request['status'] }}
                        </span>
                    </td>
                    <td>{{ \Carbon\Carbon::parse($request['created_at'])->diffForHumans() }}</td>
                    <td>
                        @if($request['status'] === 'APPROVED' && $request['member_profile'])
                            <a href="{{ route('cogs.show', $request['id']) }}" class="btn btn-primary btn-sm">View COGS</a>
                        @elseif($request['status'] === 'PENDING')
                            Awaiting...
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <div class="empty-state">No COGS requests sent</div>
        @endif
    </div>
</div>
@endsection
