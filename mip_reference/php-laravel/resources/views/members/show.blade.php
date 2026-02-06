@extends('layout')

@section('content')
<div class="card">
    <div class="card-header">
        Member Details
        <a href="{{ route('members.index') }}" class="btn btn-primary btn-sm">Back to List</a>
    </div>
    <div class="card-body">
        <div class="grid grid-2">
            <div>
                <h3 style="margin-bottom: 1rem;">Personal Information</h3>
                <table>
                    <tr>
                        <th style="width: 40%;">Member Number</th>
                        <td><code class="mono">{{ $member['member_number'] }}</code></td>
                    </tr>
                    <tr>
                        <th>Name</th>
                        <td>{{ $member['first_name'] }} {{ $member['middle_name'] ?? '' }} {{ $member['last_name'] }}</td>
                    </tr>
                    <tr>
                        <th>Birthdate</th>
                        <td>{{ $member['birthdate'] ?? '-' }}</td>
                    </tr>
                    <tr>
                        <th>Email</th>
                        <td>{{ $member['email'] ?? '-' }}</td>
                    </tr>
                    <tr>
                        <th>Phone</th>
                        <td>{{ $member['phone'] ?? '-' }}</td>
                    </tr>
                    @if(isset($member['address']))
                    <tr>
                        <th>Address</th>
                        <td>
                            {{ $member['address']['line1'] ?? '' }}<br>
                            {{ $member['address']['city'] ?? '' }}, {{ $member['address']['state'] ?? '' }} {{ $member['address']['postal_code'] ?? '' }}<br>
                            {{ $member['address']['country'] ?? '' }}
                        </td>
                    </tr>
                    @endif
                </table>
            </div>
            <div>
                <h3 style="margin-bottom: 1rem;">Membership Status</h3>
                <table>
                    <tr>
                        <th style="width: 40%;">Status</th>
                        <td>
                            <span class="badge badge-{{ ($member['is_active'] ?? false) ? 'success' : 'warning' }}">
                                {{ $member['status'] ?? 'Unknown' }}
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>Good Standing</th>
                        <td>
                            @if($member['good_standing'] ?? false)
                                <span class="badge badge-success">Yes</span>
                            @else
                                <span class="badge badge-danger">No</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <th>Years in Good Standing</th>
                        <td>{{ $member['years_in_good_standing'] ?? 0 }}</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

@if(isset($member['affiliations']) && count($member['affiliations']) > 0)
<div class="card">
    <div class="card-header">Lodge Affiliations</div>
    <div class="card-body" style="padding: 0;">
        <table>
            <thead>
                <tr>
                    <th>Lodge</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Member Since</th>
                </tr>
            </thead>
            <tbody>
                @foreach($member['affiliations'] as $affiliation)
                <tr>
                    <td>{{ $affiliation['group_name'] }}</td>
                    <td>{{ $affiliation['role'] ?? '-' }}</td>
                    <td>
                        <span class="badge badge-{{ $affiliation['status'] === 'Active' ? 'success' : 'warning' }}">
                            {{ $affiliation['status'] }}
                        </span>
                    </td>
                    <td>{{ $affiliation['membership_start'] ?? '-' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

@if(isset($member['life_cycle_events']) && count($member['life_cycle_events']) > 0)
<div class="card">
    <div class="card-header">Life Cycle Events</div>
    <div class="card-body" style="padding: 0;">
        <table>
            <thead>
                <tr>
                    <th>Event</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                @foreach($member['life_cycle_events'] as $event)
                <tr>
                    <td>{{ $event['event_type'] }}</td>
                    <td>{{ $event['event_date'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
@endsection
