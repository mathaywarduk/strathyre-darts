@props(['title' => null, 'theme' => 'dark', 'section' => 'league'])
<!doctype html>
<html lang="en-GB">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @if ($theme === 'light')
        <meta name="robots" content="noindex">
    @endif
    <title>{{ $title ? $title.' | ' : '' }}Strathyre Darts League</title>
    @include('partials.icons')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700&amp;family=Barlow:wght@400;500;600;700&amp;display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body class="{{ $theme === 'light' ? 'light' : '' }}">
<div class="wrap">
    <header class="site-head">
        <a class="brand" href="{{ route('home') }}">Strathyre Darts League</a>
        @auth
            <nav class="nav" aria-label="Main">
                <a href="{{ route('home') }}" @if ($section === 'league') aria-current="page" @endif>League</a>
                <a href="{{ route('finance.index') }}" @if ($section === 'finance') aria-current="page" @endif>Finance</a>
                <a href="{{ route('admin.dashboard') }}">Admin</a>
            </nav>
        @endauth
    </header>

    @if (session('status'))
        <p class="flash" role="status">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <div class="errors" role="alert">
            <p>Please fix the following:</p>
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    {{ $slot }}
</div>
</body>
</html>
