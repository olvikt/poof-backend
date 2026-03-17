<!DOCTYPE html>
<html lang="uk">

<head>
    <meta charset="utf-8">
    <meta name="viewport"
        content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">

    <title>{{ $title ?? 'POOF' }}</title>

    @vite(['resources/css/app.css','resources/js/auth.js'])
</head>

<body class="bg-black overflow-x-hidden overflow-y-auto">
    <div class="mx-auto w-full max-w-md">
        {{ $slot }}
    </div>
</body>

</html>
