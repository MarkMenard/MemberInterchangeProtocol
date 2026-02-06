@extends('layout')

@section('content')
<div class="card">
    <div class="card-header">
        New Member Search
        <a href="{{ route('searches.index') }}" class="btn btn-primary btn-sm">Back to List</a>
    </div>
    <div class="card-body">
        @if(count($connections) === 0)
            <div class="alert alert-error">
                No active connections available. Please establish a connection first.
            </div>
        @else
        <form action="{{ route('searches.store') }}" method="POST">
            @csrf
            <div class="form-group">
                <label for="target_mip_id">Target Organization</label>
                <select name="target_mip_id" id="target_mip_id" class="form-control" required>
                    <option value="">Select an organization...</option>
                    @foreach($connections as $conn)
                        <option value="{{ $conn['mip_identifier'] }}">{{ $conn['organization_name'] }}</option>
                    @endforeach
                </select>
            </div>

            <h4 style="margin: 1.5rem 0 1rem;">Search Criteria</h4>
            <p style="color: #666; margin-bottom: 1rem;">Enter at least one search criterion:</p>

            <div class="grid grid-3">
                <div class="form-group">
                    <label for="first_name">First Name</label>
                    <input type="text" name="first_name" id="first_name" class="form-control">
                </div>
                <div class="form-group">
                    <label for="last_name">Last Name</label>
                    <input type="text" name="last_name" id="last_name" class="form-control">
                </div>
                <div class="form-group">
                    <label for="member_number">Member Number</label>
                    <input type="text" name="member_number" id="member_number" class="form-control">
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Send Search Request</button>
        </form>
        @endif
    </div>
</div>
@endsection
