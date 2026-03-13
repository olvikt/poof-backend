<!DOCTYPE html>
<html lang="uk">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

    <title>{{ $title ?? 'POOF' }}</title>

    @vite(['resources/css/app.css','resources/js/app.js'])
</head>

<body class="bg-black overflow-x-hidden overflow-y-auto">
    <div class="min-h-[100dvh] flex flex-col items-center px-4 pt-12 pb-12">
        <div class="mx-auto w-full max-w-md">
            {{ $slot }}
        </div>
    </div>
</body>

</html>
