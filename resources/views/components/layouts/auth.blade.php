<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

    <title>{{ $title ?? 'Poof' }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    @stack('head')
</head>
<body class="bg-black overflow-hidden antialiased">
    {{ $slot }}

    @livewireScriptConfig
    @stack('scripts')
</body>
</html>
