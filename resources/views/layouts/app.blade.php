<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>@yield('title')</title>
</head>
<body>
    <div id="app">
        <main id="main-container">
            @yield('content')
        </main>
    </div>
</body>
</html>
