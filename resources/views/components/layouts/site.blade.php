@props(['title' => null, 'theme' => 'dark', 'section' => 'league', 'noindex' => false])
<!doctype html>
<html lang="en-GB">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @if ($noindex)
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
        @php
            $links = array_filter([
                ['League', route('home'), 'league'],
                $leagueLive ? ['Results and fixtures', route('results.index'), 'results'] : null,
                $leagueLive ? ['Finance', route('finance.index'), 'finance'] : null,
                $hasNotices ? ['Notices', route('notices'), 'notices'] : null,
                $hasRules ? ['Rules', route('rules'), 'rules'] : null,
                auth()->check() ? ['Admin', route('admin.dashboard'), 'admin'] : null,
            ]);
        @endphp
        @if (count($links) > 1)
            <nav class="nav" aria-label="Main">
                @foreach ($links as [$label, $url, $key])
                    <a href="{{ $url }}" @if ($key === $section) aria-current="page" @endif>{{ $label }}</a>
                @endforeach
            </nav>
        @endif
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
