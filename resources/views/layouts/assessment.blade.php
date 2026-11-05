<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Assessment - Zapmed' }}</title>

    @include('partials.fonts')

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-gray-50">

    @include('partials.public-nav')

    <main class="pt-24 pb-16 px-4 sm:px-6 lg:px-8">
        <div class="max-w-2xl mx-auto">
            {{ $slot }}
        </div>
    </main>

    @include('partials.public-footer')

</body>
</html>
