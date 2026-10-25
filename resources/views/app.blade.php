<!DOCTYPE html>
{{-- Arcadia's document (`ArcadiaSite::rootView()`), every page's. Nothing here measures a
     visitor — no analytics — so no page asks for consent.

     Dark only, and `data-site="arcadia"` switches the palette to Arcadia's teal on navy (`app.css`, ADR 0019). --}}
<html lang="{{ app()->getLocale() }}" class="dark" data-site="arcadia">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1" />

    @inertiaHead

    @routes
    @vite([
        'resources/css/app.css',
        'resources/js/app.js'
    ])
    <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
    <link rel="stylesheet" href="https://fonts.bunny.net/css?family=sora:400,600,700|ibm-plex-sans:300,400,500|ibm-plex-mono:400,500">

    <link rel="icon" href="{{ asset('img/arcadia/favicon.ico') }}" sizes="32x32">
    <link rel="icon" href="{{ asset('img/arcadia/favicon-32x32.png') }}" type="image/png" sizes="32x32">
    <link rel="icon" href="{{ asset('img/arcadia/favicon-16x16.png') }}" type="image/png" sizes="16x16">
    <link rel="icon" href="{{ asset('img/arcadia/android-chrome-192x192.png') }}" type="image/png" sizes="192x192">
    <link rel="apple-touch-icon" href="{{ asset('img/arcadia/apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('img/arcadia/site.webmanifest') }}">
</head>

<body class="bg-ground text-ink">
    @inertia
</body>

</html>
