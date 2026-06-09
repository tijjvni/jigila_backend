<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; color: #374151; line-height: 1.6; background: #f3f4f6; }
        .wrapper { padding: 32px 16px; }
        .container { max-width: 560px; margin: 0 auto; border-radius: 10px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.08); }
        .header { background: @yield('header-bg', '#1d4ed8'); color: #ffffff; padding: 28px 32px; text-align: center; }
        .header h1 { font-size: 20px; font-weight: 700; letter-spacing: -0.3px; }
        .header p { font-size: 13px; margin-top: 4px; opacity: .85; }
        .body-section { background: #ffffff; padding: 28px 32px; }
        .body-section p { font-size: 14px; color: #374151; margin-bottom: 12px; }
        .ticket-box { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px 20px; margin: 18px 0; }
        .label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.07em; color: #9ca3af; font-weight: 600; margin-bottom: 3px; }
        .value { font-size: 14px; color: #111827; font-weight: 600; margin-bottom: 12px; }
        .value:last-child { margin-bottom: 0; }
        .message-body { background: #eff6ff; border-left: 3px solid #1d4ed8; padding: 12px 16px; border-radius: 0 6px 6px 0; margin-top: 8px; font-size: 14px; color: #374151; }
        .btn { display: inline-block; background: @yield('btn-bg', '#1d4ed8'); color: #ffffff !important; padding: 11px 24px; border-radius: 7px; text-decoration: none; font-size: 13px; font-weight: 700; margin-top: 8px; }
        .divider { height: 1px; background: #f3f4f6; margin: 20px 0; }
        .footer { background: #f9fafb; padding: 16px 32px; text-align: center; border-top: 1px solid #e5e7eb; }
        .footer p { font-size: 11px; color: #9ca3af; }
        .footer a { color: #6b7280; text-decoration: none; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="container">

        {{-- Header --}}
        <div class="header">
            <h1>@yield('header-title')</h1>
            @hasSection('header-subtitle')
            <p>@yield('header-subtitle')</p>
            @endif
        </div>

        {{-- Body --}}
        <div class="body-section">
            @yield('body')
        </div>

        {{-- Footer --}}
        <div class="footer">
            <p>&copy; {{ date('Y') }} <strong>Jigila</strong>. All rights reserved.</p>
            <p style="margin-top:4px;">Questions? <a href="mailto:support@jigila.com">support@jigila.com</a></p>
        </div>

    </div>
</div>
</body>
</html>
