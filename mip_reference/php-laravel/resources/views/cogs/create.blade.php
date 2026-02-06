@extends('layout')

@section('content')
<div class="card">
    <div class="card-header">
        Request Certificate of Good Standing
        <a href="{{ route('cogs.index') }}" class="btn btn-primary btn-sm">Back to List</a>
    </div>
    <div class="card-body">
        @if(count($connections) === 0)
            <div class="alert alert-error">
                No active connections available. Please establish a connection first.
            </div>
        @else
        <form action="{{ route('cogs.store') }}" method="POST">
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

            <div class="form-group">
                <label for="member_number">Member Number</label>
                <input type="text" name="member_number" id="member_number" class="form-control" required placeholder="e.g., BETA-001">
                <small style="color: #666;">Enter the member number from the target organization</small>
            </div>

            <button type="submit" class="btn btn-primary">Request COGS</button>
        </form>
        @endif
    </div>
</div>
@endsection
