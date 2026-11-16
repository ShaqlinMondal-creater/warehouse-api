<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Laravel') }}</title>
    <style>
        html, body { height: 100%; margin: 0; font-family: ui-sans-serif, system-ui, sans-serif; background: #FDFDFC; color: #1b1b18; }
        body { display: flex; align-items: center; justify-content: center; }
        .box { text-align: center; padding: 2rem; }
        h1 { font-size: 2rem; font-weight: 600; margin: 0 0 .5rem; }
        p { color: #706f6c; margin: 0 0 1.25rem; }
        a { color: #f53003; text-decoration: none; font-weight: 600; }
        a:hover { text-decoration: underline; }
        .badge { display: inline-block; margin-top: 1rem; padding: .35rem .75rem; border: 1px solid #e3e3e0; border-radius: 999px; font-size: .85rem; color: #706f6c; }
    </style>
</head>
<body>
    <div class="box">
        <h1>Laravel</h1>
        <p>{{ config('app.name', 'Warehouse API') }}</p>
        <p><a href="{{ url('/api/health') }}">API Health</a></p>
        <div class="badge">Laravel v{{ Illuminate\Foundation\Application::VERSION }} (PHP v{{ PHP_VERSION }})</div>
    </div>
</body>
</html>
