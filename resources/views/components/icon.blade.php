@props(['name' => 'grid'])
@php
    $icons = [
        'grid' =>
            '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
        'users' =>
            '<circle cx="9" cy="8" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3M16 5a3 3 0 0 1 0 6M21 21v-3a6 6 0 0 0-4-5.65"/>',
        'book' =>
            '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20M6.5 3H20v19H6.5A2.5 2.5 0 0 1 4 19.5v-14A2.5 2.5 0 0 1 6.5 3ZM8 7h8M8 11h6"/>',
        'calendar' =>
            '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18M8 15h2M14 15h2"/>',
        'checklist' => '<path d="m3 7 2 2 4-4M12 7h9m-18 8 2 2 4-4M12 15h9M12 20h9"/>',
        'file' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8ZM14 2v6h6M8 13h8M8 17h5"/>',
        'chart' => '<path d="M3 3v18h18M7 17v-5M12 17V7M17 17v-8"/>',
        'nfc' => '<path d="M8 6a8 8 0 0 1 0 12M12 3a12 12 0 0 1 0 18M4 9a4 4 0 0 1 0 6M20 7v10"/>',
        'upload' => '<path d="M12 16V3m-5 5 5-5 5 5M4 15v5h16v-5"/>',
        'bell' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 22v-2a8 8 0 0 1 16 0v2"/>',
        'logout' => '<path d="M9 5H4v14h5M10 12h11m-4-4 4 4-4 4"/>',
        'arrow' => '<path d="M5 12h14m-5-5 5 5-5 5"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'close' => '<path d="m6 6 12 12M6 18 18 6"/>',
        'lock' => '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4M12 15v2"/>',
        'search' => '<circle cx="10" cy="10" r="6"/><path d="m15 15 6 6"/>',
        'eye' => '<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7h.01"/>',
        'check' => '<path d="m5 12 4 4 10-10"/>',
    ];
@endphp
<svg {{ $attributes->class(['icon']) }} xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
    fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"
    aria-hidden="true">{!! $icons[$name] ?? $icons['grid'] !!}</svg>
