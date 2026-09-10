<!doctype html>
<html lang="en" data-theme="dark" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#000000">
    @php
        $appBase = rtrim(parse_url(config('app.url'), PHP_URL_PATH) ?? '', '/');
        $brandName = config('telemetry.brand_name', 'Lido Telemetry');
    @endphp
    <meta name="app-base" content="{{ $appBase }}">
    <script>window.__TELEMETRY_APP_BASE__ = @json($appBase);</script>
    <script>window.__TELEMETRY_BRAND_NAME__ = @json($brandName);</script>
    <title>{{ $brandName }}</title>
    @if (file_exists(public_path('hot')))
        @viteReactRefresh
    @endif
    @vite(['resources/css/app.css', 'resources/js/src/styles/telemetry-app.css', 'resources/js/app.jsx'])
</head>
<body>
    <div id="app"></div>
    <noscript>
        <div style="padding:1rem;font-family:system-ui,sans-serif;background:#1a1a1a;color:#e5e7eb;min-height:100vh">
            JavaScript is required for {{ $brandName }}.
        </div>
    </noscript>
</body>
</html>
