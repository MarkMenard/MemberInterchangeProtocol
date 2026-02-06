@extends('layout')

@section('content')
<div class="card">
    <div class="card-header">Local Members</div>
    <div class="card-body" style="padding: 0;">
        @if(count($members) > 0)
        <table>
            <thead>
                <tr>
                    <th>Member #</th>
                    <th>Name</th>
                    <th>Status</th>
                    <th>Good Standing</th>
                    <th>Years</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($members as $member)
                <tr>
                    <td><code class="mono">{{ $member['member_number'] }}</code></td>
                    <td>{{ $member['first_name'] }} {{ $member['last_name'] }}</td>
                    <td>
                        <span class="badge badge-{{ ($member['is_active'] ?? false) ? 'success' : 'warning' }}">
                            {{ $member['status'] ?? 'Unknown' }}
                        </span>
                    </td>
                    <td>
                        @if($member['good_standing'] ?? false)
                            <span class="badge badge-success">Yes</span>
                        @else
                            <span class="badge badge-danger">No</span>
                        @endif
                    </td>
                    <td>{{ $member['years_in_good_standing'] ?? 0 }}</td>
                    <td>
                        <a href="{{ route('members.show', $member['member_number']) }}" class="btn btn-primary btn-sm">View</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <div class="empty-state">No members configured</div>
        @endif
    </div>
</div>
@endsection
