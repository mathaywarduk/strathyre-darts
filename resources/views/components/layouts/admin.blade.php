@props(['title' => null])
<!doctype html>
<html lang="en-GB">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title ? $title.' | ' : '' }}Admin | Strathyre Darts League</title>
    @include('partials.icons')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700&amp;family=Barlow:wght@400;500;600;700&amp;display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    <script src="{{ asset('js/admin.js') }}?v={{ filemtime(public_path('js/admin.js')) }}" defer></script>
    <script src="{{ asset('js/editor.js') }}?v={{ filemtime(public_path('js/editor.js')) }}" defer></script>
</head>
<body class="light">
<div class="wrap wide">
    <div class="admin-bar">
        <a class="brand" href="{{ route('admin.dashboard') }}">Strathyre Darts League <span class="admin-tag">Admin</span></a>
        @auth
            <span class="actions" style="gap: 16px">
                <a class="back" href="{{ route('home') }}">View site</a>
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button class="linkbtn" type="submit">Log out</button>
                </form>
            </span>
        @endauth
    </div>
    @auth
        <nav class="admin-nav" aria-label="Admin">
            @foreach ([
                ['Dashboard', 'admin.dashboard', 'admin.dashboard', true],
                ['Results', 'admin.results.*', 'admin.results.index', $leagueLive],
                ['Players', 'admin.players.*', 'admin.players.index', true],
                ['Fixtures', 'admin.fixtures.*', 'admin.fixtures.index', $hasSeason],
                ['Expenses', 'admin.expenses.*', 'admin.expenses.index', $hasSeason],
                ['Subs', 'admin.subs*', 'admin.subs', $leagueLive],
                ['Notices', 'admin.notices.*', 'admin.notices.index', true],
                ['Rules', 'admin.rules.*', 'admin.rules.edit', true],
                ['Settings', 'admin.settings.*', 'admin.settings.edit', true],
                ['Season', 'admin.season.*', 'admin.season.index', true],
            ] as [$label, $pattern, $route, $show])
                @if ($show)
                    <a href="{{ route($route) }}" @if (request()->routeIs($pattern)) aria-current="page" @endif>{{ $label }}</a>
                @endif
            @endforeach
        </nav>
    @endauth

    @auth
        @if (($setupNext ?? null) && ! request()->routeIs('admin.dashboard'))
            <p class="setup-bar" role="status">
                <strong>Setup {{ $setupDone }} of {{ $setupTotal }}</strong>
                <span>Next: <a href="{{ $setupNext[2] }}">{{ $setupNext[0] }}</a></span>
            </p>
        @endif
    @endauth

    @if (session('status'))
        <p class="flash" role="status">{{ session('status') }}
            @if (session('status_link'))
                <a href="{{ session('status_link')['url'] }}">{{ session('status_link')['label'] }}</a>
            @endif
        </p>
    @endif
    @if ($errors->any())
        <div class="errors" role="alert">
            <p>Please fix the following:</p>
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    @if (session('import_errors'))
        <div class="errors" role="alert">
            <p>This file can’t be used yet:</p>
            <ul>@foreach (session('import_errors') as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    {{ $slot }}
</div>
</body>
</html>
