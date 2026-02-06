@extends('layout')

@section('content')
<div class="card">
    <div class="card-header">
        Member Searches
        <a href="{{ route('searches.create') }}" class="btn btn-primary btn-sm">New Search</a>
    </div>
</div>

@if(count($inbound_requests) > 0)
<div class="card">
    <div class="card-header">Inbound Search Requests</div>
    <div class="card-body" style="padding: 0;">
        <table>
            <thead>
                <tr>
                    <th>From</th>
                    <th>Criteria</th>
                    <th>Status</th>
                    <th>Received</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($inbound_requests as $request)
                <tr>
                    <td>{{ $request['source_organization'] }}</td>
                    <td>
                        @if(!empty($request['criteria']['first_name']))
                            First: {{ $request['criteria']['first_name'] }}
                        @endif
                        @if(!empty($request['criteria']['last_name']))
                            Last: {{ $request['criteria']['last_name'] }}
                        @endif
                        @if(!empty($request['criteria']['member_number']))
                            #: {{ $request['criteria']['member_number'] }}
                        @endif
                    </td>
                    <td>
                        <span class="badge badge-{{ $request['status'] === 'PENDING' ? 'warning' : ($request['status'] === 'APPROVED' ? 'success' : 'danger') }}">
                            {{ $request['status'] }}
                        </span>
                    </td>
                    <td>{{ \Carbon\Carbon::parse($request['created_at'])->diffForHumans() }}</td>
                    <td>
                        @if($request['status'] === 'PENDING')
                        <form action="{{ route('searches.approve', $request['id']) }}" method="POST" style="display: inline;">
                            @csrf
                            <button type="submit" class="btn btn-success btn-sm">Approve</button>
                        </form>
                        <form action="{{ route('searches.decline', $request['id']) }}" method="POST" style="display: inline;">
                            @csrf
                            <button type="submit" class="btn btn-danger btn-sm">Decline</button>
                        </form>
                        @elseif($request['status'] === 'APPROVED')
                            {{ count($request['results'] ?? []) }} result(s) sent
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
    <div class="card-header">Outbound Search Requests</div>
    <div class="card-body" style="padding: 0;">
        @if(count($outbound_requests) > 0)
        <table>
            <thead>
                <tr>
                    <th>To</th>
                    <th>Criteria</th>
                    <th>Status</th>
                    <th>Sent</th>
                    <th>Results</th>
                </tr>
            </thead>
            <tbody>
                @foreach($outbound_requests as $request)
                <tr>
                    <td>{{ $request['target_organization'] }}</td>
                    <td>
                        @if(!empty($request['criteria']['first_name']))
                            First: {{ $request['criteria']['first_name'] }}
                        @endif
                        @if(!empty($request['criteria']['last_name']))
                            Last: {{ $request['criteria']['last_name'] }}
                        @endif
                        @if(!empty($request['criteria']['member_number']))
                            #: {{ $request['criteria']['member_number'] }}
                        @endif
                    </td>
                    <td>
                        <span class="badge badge-{{ $request['status'] === 'PENDING' ? 'warning' : ($request['status'] === 'COMPLETED' ? 'success' : 'danger') }}">
                            {{ $request['status'] }}
                        </span>
                    </td>
                    <td>{{ \Carbon\Carbon::parse($request['created_at'])->diffForHumans() }}</td>
                    <td>
                        @if($request['status'] === 'COMPLETED' && isset($request['results']))
                            @if(count($request['results']) > 0)
                                <ul style="margin: 0; padding-left: 1rem;">
                                @foreach($request['results'] as $result)
                                    <li>
                                        {{ $result['first_name'] }} {{ $result['last_name'] }}
                                        ({{ $result['member_number'] }})
                                        @if($result['good_standing'] ?? false)
                                            <span class="badge badge-success">Good Standing</span>
                                        @endif
                                    </li>
                                @endforeach
                                </ul>
                            @else
                                No matches
                            @endif
                        @elseif($request['status'] === 'PENDING')
                            Awaiting response...
                        @else
                            -
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <div class="empty-state">No search requests sent</div>
        @endif
    </div>
</div>
@endsection
