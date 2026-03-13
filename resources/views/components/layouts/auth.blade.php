<!DOCTYPE html>
<html lang="uk">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

    <title>{{ $title ?? 'POOF' }}</title>

    @vite(['resources/css/app.css','resources/js/app.js'])
</head>

<body class="bg-black overflow-hidden">
    <div class="min-h-[100dvh] flex flex-col justify-center px-4">
        <div class="mx-auto w-full max-w-md">
            {{ $slot }}
        </div>
    </div>
</body>

</html>
