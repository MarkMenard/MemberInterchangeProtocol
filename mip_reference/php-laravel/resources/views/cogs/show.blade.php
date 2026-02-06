@extends('layout')

@section('content')
<div class="card">
    <div class="card-header">
        Certificate of Good Standing
        <a href="{{ route('cogs.index') }}" class="btn btn-primary btn-sm">Back to List</a>
    </div>
    <div class="card-body">
        <div style="text-align: center; padding: 2rem; border: 2px solid #1a5276; border-radius: 8px; margin-bottom: 2rem;">
            <h2 style="color: #1a5276; margin-bottom: 0.5rem;">Certificate of Good Standing</h2>
            <p style="color: #666;">{{ $request['target_organization'] }}</p>

            @if($request['member_profile'])
            <div style="margin: 2rem 0;">
                <h3 style="font-size: 1.5rem;">
                    {{ $request['member_profile']['first_name'] }}
                    {{ $request['member_profile']['middle_name'] ?? '' }}
                    {{ $request['member_profile']['last_name'] }}
                </h3>
                <p style="color: #666;">Member #{{ $request['member_profile']['member_number'] }}</p>
            </div>

            <div style="background: #d4edda; padding: 1rem; border-radius: 4px; display: inline-block;">
                @if($request['member_profile']['good_standing'] ?? false)
                    <span style="color: #155724; font-weight: bold; font-size: 1.25rem;">IN GOOD STANDING</span>
                @else
                    <span style="color: #721c24; font-weight: bold; font-size: 1.25rem;">NOT IN GOOD STANDING</span>
                @endif
            </div>

            <div style="margin-top: 2rem; font-size: 0.875rem; color: #666;">
                <p>Years in Good Standing: {{ $request['member_profile']['years_in_good_standing'] ?? 0 }}</p>
                @if($request['issued_at'])
                <p>Issued: {{ \Carbon\Carbon::parse($request['issued_at'])->format('F j, Y') }}</p>
                @endif
                @if($request['valid_until'])
                <p>Valid Until: {{ \Carbon\Carbon::parse($request['valid_until'])->format('F j, Y') }}</p>
                @endif
            </div>
            @endif
        </div>

        @if($request['member_profile'])
        <h4 style="margin-bottom: 1rem;">Member Details</h4>
        <div class="grid grid-2">
            <div>
                <table>
                    <tr>
                        <th style="width: 40%;">Status</th>
                        <td>
                            <span class="badge badge-{{ ($request['member_profile']['is_active'] ?? false) ? 'success' : 'warning' }}">
                                {{ $request['member_profile']['status'] ?? 'Unknown' }}
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>Birthdate</th>
                        <td>{{ $request['member_profile']['birthdate'] ?? '-' }}</td>
                    </tr>
                    <tr>
                        <th>Email</th>
                        <td>{{ $request['member_profile']['email'] ?? '-' }}</td>
                    </tr>
                    <tr>
                        <th>Phone</th>
                        <td>{{ $request['member_profile']['phone'] ?? '-' }}</td>
                    </tr>
                </table>
            </div>
            <div>
                @if(isset($request['member_profile']['affiliations']) && count($request['member_profile']['affiliations']) > 0)
                <h5>Affiliations</h5>
                <ul>
                    @foreach($request['member_profile']['affiliations'] as $aff)
                    <li>{{ $aff['group_name'] }} - {{ $aff['role'] ?? 'Member' }}</li>
                    @endforeach
                </ul>
                @endif
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
