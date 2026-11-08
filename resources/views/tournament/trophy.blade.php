@php($k = \Illuminate\Support\Str::random(6))
<svg class="trophy" viewBox="0 0 120 170" role="img" aria-label="Trophy" xmlns="http://www.w3.org/2000/svg">
    <defs>
        <linearGradient id="gold-{{ $k }}" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0" stop-color="#fbe9a0"/>
            <stop offset=".45" stop-color="#e4aa2c"/>
            <stop offset="1" stop-color="#a86d10"/>
        </linearGradient>
        <linearGradient id="goldside-{{ $k }}" x1="0" y1="0" x2="1" y2="0">
            <stop offset="0" stop-color="#b97a14"/>
            <stop offset=".5" stop-color="#f7dc7e"/>
            <stop offset="1" stop-color="#b97a14"/>
        </linearGradient>
    </defs>
    <!-- handles -->
    <path d="M30 26 H17 C9 26 7 34 10 43 C14 57 25 65 36 67" fill="none" stroke="url(#gold-{{ $k }})" stroke-width="7" stroke-linecap="round"/>
    <path d="M90 26 H103 C111 26 113 34 110 43 C106 57 95 65 84 67" fill="none" stroke="url(#gold-{{ $k }})" stroke-width="7" stroke-linecap="round"/>
    <!-- cup -->
    <path d="M26 14 H94 V52 C94 80 79 98 60 103 C41 98 26 80 26 52 Z" fill="url(#goldside-{{ $k }})"/>
    <path d="M26 14 H94" stroke="#fff3c4" stroke-width="3" stroke-linecap="round"/>
    <!-- a dartboard on the cup -->
    <circle cx="60" cy="48" r="20" fill="#1b1b1b"/>
    <circle cx="60" cy="48" r="16" fill="#f1e4c3"/>
    <circle cx="60" cy="48" r="12" fill="#c8342a"/>
    <circle cx="60" cy="48" r="8" fill="#f1e4c3"/>
    <circle cx="60" cy="48" r="4.5" fill="#1b9a52"/>
    <circle cx="60" cy="48" r="1.8" fill="#c8342a"/>
    <!-- stem and base -->
    <path d="M52 102 H68 L66 126 H54 Z" fill="url(#goldside-{{ $k }})"/>
    <ellipse cx="60" cy="127" rx="13" ry="3.5" fill="#d99b21"/>
    <path d="M36 128 H84 L89 148 H31 Z" fill="url(#gold-{{ $k }})"/>
    <rect x="26" y="148" width="68" height="14" rx="3" fill="#2a2a2a"/>
    <rect x="30" y="151" width="60" height="8" rx="2" fill="url(#gold-{{ $k }})"/>
</svg>
