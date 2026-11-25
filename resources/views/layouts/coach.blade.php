<!DOCTYPE html>
<html lang="en-ZA">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    <meta name="theme-color" content="#F4F0E7">
    <link rel="manifest" href="/coach.webmanifest">
    <link rel="apple-touch-icon" href="/images/icons/icon-192x192.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Zapmed Coach">
    <title>{{ $title ?? 'My coach' }} - Zapmed</title>
    @include('partials.fonts')
    @vite(['resources/css/zapmed-coach.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="zc" x-data="{ toast: null, sheet: null, flash(m) { this.toast = m; setTimeout(() => this.toast = null, 2600) } }"
      @if (session('coach_toast')) x-init="flash(@js(session('coach_toast')))" @endif
      @coach-toast.window="flash($event.detail.message)">
    <div class="zc-shell">
        {{ $slot }}
    </div>

    @unless ($hideTabs ?? false)
        @include('coach.partials.tabs')
    @endunless

    <div class="zc-toast" x-show="toast" x-cloak x-text="toast" role="status"></div>
    @livewireScripts
    <script>if ('serviceWorker' in navigator) { window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {})); }</script>
</body>
</html>