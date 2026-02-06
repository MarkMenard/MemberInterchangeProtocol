<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $identity['organization_name'] ?? 'MIP Node' }} - MIP Admin</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            color: #333;
            line-height: 1.6;
        }

        .header {
            background: linear-gradient(135deg, #1a5276, #2980b9);
            color: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .header h1 {
            font-size: 1.5rem;
            font-weight: 600;
        }

        .header .mip-id {
            font-size: 0.75rem;
            opacity: 0.8;
            font-family: monospace;
        }

        .nav {
            background: white;
            padding: 0 2rem;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            gap: 0;
        }

        .nav a {
            padding: 1rem 1.5rem;
            text-decoration: none;
            color: #666;
            font-weight: 500;
            border-bottom: 3px solid transparent;
            transition: all 0.2s;
        }

        .nav a:hover {
            color: #1a5276;
            background: #f8f9fa;
        }

        .nav a.active {
            color: #1a5276;
            border-bottom-color: #1a5276;
        }

        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
            overflow: hidden;
        }

        .card-header {
            padding: 1rem 1.5rem;
            background: #f8f9fa;
            border-bottom: 1px solid #e0e0e0;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-body {
            padding: 1.5rem;
        }

        .btn {
            display: inline-block;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-primary {
            background: #1a5276;
            color: white;
        }

        .btn-primary:hover {
            background: #154360;
        }

        .btn-success {
            background: #27ae60;
            color: white;
        }

        .btn-success:hover {
            background: #219a52;
        }

        .btn-danger {
            background: #e74c3c;
            color: white;
        }

        .btn-danger:hover {
            background: #c0392b;
        }

        .btn-warning {
            background: #f39c12;
            color: white;
        }

        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #555;
        }

        .form-control {
            width: 100%;
            padding: 0.5rem 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }

        .form-control:focus {
            outline: none;
            border-color: #1a5276;
            box-shadow: 0 0 0 3px rgba(26, 82, 118, 0.1);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 0.75rem 1rem;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }

        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #555;
            font-size: 0.875rem;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge-success {
            background: #d4edda;
            color: #155724;
        }

        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }

        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }

        .badge-info {
            background: #d1ecf1;
            color: #0c5460;
        }

        .alert {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .grid {
            display: grid;
            gap: 1.5rem;
        }

        .grid-2 {
            grid-template-columns: repeat(2, 1fr);
        }

        .grid-3 {
            grid-template-columns: repeat(3, 1fr);
        }

        .grid-4 {
            grid-template-columns: repeat(4, 1fr);
        }

        .stat-card {
            text-align: center;
            padding: 1.5rem;
        }

        .stat-card .number {
            font-size: 2rem;
            font-weight: 700;
            color: #1a5276;
        }

        .stat-card .label {
            color: #666;
            font-size: 0.875rem;
        }

        .activity-item {
            padding: 0.75rem 0;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            gap: 1rem;
            align-items: flex-start;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-time {
            color: #999;
            font-size: 0.75rem;
            white-space: nowrap;
        }

        .activity-message {
            flex: 1;
        }

        .mono {
            font-family: monospace;
            font-size: 0.875rem;
            background: #f5f5f5;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #999;
        }

        @media (max-width: 768px) {
            .grid-2, .grid-3, .grid-4 {
                grid-template-columns: 1fr;
            }

            .nav {
                flex-wrap: wrap;
            }

            .container {
                padding: 0 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $identity['organization_name'] ?? 'MIP Node' }}</h1>
        <div class="mip-id">MIP ID: {{ $identity['mip_identifier'] ?? 'Not initialized' }}</div>
    </div>

    <nav class="nav">
        <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">Dashboard</a>
        <a href="{{ route('connections.index') }}" class="{{ request()->routeIs('connections.*') ? 'active' : '' }}">Connections</a>
        <a href="{{ route('members.index') }}" class="{{ request()->routeIs('members.*') ? 'active' : '' }}">Members</a>
        <a href="{{ route('searches.index') }}" class="{{ request()->routeIs('searches.*') ? 'active' : '' }}">Searches</a>
        <a href="{{ route('cogs.index') }}" class="{{ request()->routeIs('cogs.*') ? 'active' : '' }}">COGS</a>
    </nav>

    <div class="container">
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if (session('error'))
            <div class="alert alert-error">{{ session('error') }}</div>
        @endif

        @yield('content')
    </div>
</body>
</html>
