<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Jigila Backend</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .card {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 16px;
            padding: 2.5rem;
            width: 100%;
            max-width: 560px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
        }

        .header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #334155;
        }

        .logo {
            width: 44px;
            height: 44px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 1.1rem;
            color: white;
            letter-spacing: -0.5px;
            flex-shrink: 0;
        }

        .header-text h1 {
            font-size: 1.25rem;
            font-weight: 700;
            color: #f1f5f9;
            line-height: 1.2;
        }

        .header-text p {
            font-size: 0.8rem;
            color: #64748b;
            margin-top: 2px;
        }

        .overall-status {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            background: #052e16;
            border: 1px solid #166534;
            border-radius: 10px;
            padding: 0.875rem 1.25rem;
            margin-bottom: 1.5rem;
        }

        .pulse {
            width: 10px;
            height: 10px;
            background: #22c55e;
            border-radius: 50%;
            flex-shrink: 0;
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }

        .overall-status span {
            font-size: 0.875rem;
            font-weight: 600;
            color: #86efac;
        }

        .overall-status .ts {
            margin-left: auto;
            font-size: 0.75rem;
            color: #4ade80;
            opacity: 0.7;
        }

        .rows {
            display: flex;
            flex-direction: column;
            gap: 0.625rem;
        }

        .row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem 1rem;
            background: #0f172a;
            border: 1px solid #1e293b;
            border-radius: 8px;
        }

        .row-label {
            display: flex;
            align-items: center;
            gap: 0.625rem;
            font-size: 0.8rem;
            color: #94a3b8;
        }

        .row-label .icon {
            font-size: 0.9rem;
            width: 18px;
            text-align: center;
        }

        .row-value {
            font-size: 0.8rem;
            font-weight: 600;
            color: #cbd5e1;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.2rem 0.625rem;
            border-radius: 999px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .badge-green {
            background: #052e16;
            color: #4ade80;
            border: 1px solid #166534;
        }

        .badge-yellow {
            background: #422006;
            color: #fbbf24;
            border: 1px solid #92400e;
        }

        .badge-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: currentColor;
        }

        .footer {
            margin-top: 2rem;
            text-align: center;
            font-size: 0.72rem;
            color: #334155;
        }
    </style>
</head>
<body>

<div class="card">
    <div class="header">
        <div class="logo">JG</div>
        <div class="header-text">
            <h1>Jigila Backend</h1>
            <p>API Status &amp; Health Check</p>
        </div>
    </div>

    <div class="overall-status">
        <div class="pulse"></div>
        <span>All Systems Operational</span>
        <span class="ts">{{ now()->format('D, d M Y H:i:s') }} UTC</span>
    </div>

    <div class="rows">

        {{-- Application --}}
        <div class="row">
            <div class="row-label">
                <span class="icon">⚙️</span> Application
            </div>
            <span class="badge badge-green">
                <span class="badge-dot"></span>
                Laravel {{ app()->version() }}
            </span>
        </div>

        {{-- PHP --}}
        <div class="row">
            <div class="row-label">
                <span class="icon">🐘</span> PHP
            </div>
            <span class="row-value">{{ PHP_VERSION }}</span>
        </div>

        {{-- Environment --}}
        <div class="row">
            <div class="row-label">
                <span class="icon">🌍</span> Environment
            </div>
            <span class="badge {{ app()->isProduction() ? 'badge-green' : 'badge-yellow' }}">
                <span class="badge-dot"></span>
                {{ ucfirst(app()->environment()) }}
            </span>
        </div>

        {{-- Database --}}
        @php
            try {
                \Illuminate\Support\Facades\DB::connection()->getPdo();
                $dbOk = true;
                $driver = strtoupper(config('database.default'));
            } catch (\Exception $e) {
                $dbOk = false;
                $driver = strtoupper(config('database.default'));
            }
        @endphp
        <div class="row">
            <div class="row-label">
                <span class="icon">🗄️</span> Database ({{ $driver }})
            </div>
            <span class="badge {{ $dbOk ? 'badge-green' : 'badge-red' }}">
                <span class="badge-dot"></span>
                {{ $dbOk ? 'Connected' : 'Unavailable' }}
            </span>
        </div>

        {{-- Queue --}}
        <div class="row">
            <div class="row-label">
                <span class="icon">📬</span> Queue Driver
            </div>
            <span class="row-value">{{ ucfirst(config('queue.default')) }}</span>
        </div>

        {{-- Cache --}}
        <div class="row">
            <div class="row-label">
                <span class="icon">⚡</span> Cache Driver
            </div>
            <span class="row-value">{{ ucfirst(config('cache.default')) }}</span>
        </div>

        {{-- Debug mode --}}
        <div class="row">
            <div class="row-label">
                <span class="icon">🔍</span> Debug Mode
            </div>
            <span class="badge {{ config('app.debug') ? 'badge-yellow' : 'badge-green' }}">
                <span class="badge-dot"></span>
                {{ config('app.debug') ? 'Enabled' : 'Disabled' }}
            </span>
        </div>

    </div>

    <div class="footer">Jigila &copy; {{ date('Y') }} &mdash; Backend API v1</div>
</div>

</body>
</html>
