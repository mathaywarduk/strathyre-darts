{{-- The finance pages' own menu. $current is one of: overview, received, expenses, owed --}}
<nav class="subnav" aria-label="Finance">
    @foreach ([
        'overview' => ['Overview', route('finance.index')],
        'received' => ['Subs received', route('finance.received')],
        'owed' => ['Subs owed', route('finance.owed')],
        'expenses' => ['Expenses', route('finance.expenses')],
    ] as $key => [$label, $url])
        <a href="{{ $url }}" @if ($key === $current) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>
