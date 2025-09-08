<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ config('app.name', 'LaLune by NE') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-[url('{{ asset('images/collections-hero.jpg') }}')] bg-cover bg-center bg-fixed">
    {{ $slot }}
</body>

</html>
