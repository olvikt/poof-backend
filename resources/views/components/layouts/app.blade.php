<!DOCTYPE html>
<html lang="uk" data-user-id="{{ auth()->id() ?? "" }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
    <meta name="theme-color" content="#18191f">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

    <title>{{ $title ?? 'Poof' }}</title>

    {{-- Tailwind / Vite --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Livewire styles --}}
    @livewireStyles

	<link
		rel="stylesheet"
		href="https://unpkg.com/cropperjs@1.6.2/dist/cropper.min.css"
	/>

    {{-- ✅ Leaflet (ОДИН РАЗ НА ВСЁ ПРИЛОЖЕНИЕ) --}}
	{{-- Leaflet --}}
	<link
	  rel="stylesheet"
	  href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
	/>

	<script
	  src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
	  defer
	></script>
<style>
.poof-input {
    padding: 12px 16px;
    border-radius: 12px;
    background: #0a0a0a;
    border: 1px solid #333;
    color: #fff;
}
</style>
    @stack('head')
</head>

<body class="bg-gray-900 min-h-screen antialiased">

    {{ $slot }}

    {{-- Livewire scripts --}}
    @livewireScriptConfig

    @stack('scripts')
</body>
</html>
