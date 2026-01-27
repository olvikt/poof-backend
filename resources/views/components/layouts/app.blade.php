<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

    <title>{{ $title ?? 'Poof' }}</title>

    {{-- Tailwind / Vite --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
	
	<link
		rel="stylesheet"
		href="https://unpkg.com/cropperjs@1.6.2/dist/cropper.min.css"
	/>

    {{-- Livewire styles --}}
    @livewireStyles

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
    @livewireScripts

    @stack('scripts')
</body>
</html>