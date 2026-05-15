<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name', 'Zoho Books Report'))</title>
    @vite('resources/css/app.css')
    @stack('styles')
</head>
<body>
    <header class="topbar">
        <h1>Zoho Books · Profit &amp; Loss Comparison</h1>
        <div class="actions">
            @yield('topbar-actions')
        </div>
    </header>
    <main class="container">
        @if (session('status'))
            <div class="flash success">{{ session('status') }}</div>
        @endif
        @if (session('error') || ($error ?? null))
            <div class="flash error">{{ session('error') ?? $error }}</div>
        @endif

        @yield('content')
    </main>
    @stack('scripts')
</body>
</html>
