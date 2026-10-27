@props(['name'])
@php
    // Two-tone 32×32 icons for the category strip: ink outline over a soft accent fill.
    $icons = [
        // Design categories
        'culture' => '<rect x="8" y="9" width="16" height="17" rx="3" fill="#F2B8A2"/><ellipse cx="16" cy="9" rx="8" ry="3" fill="#FBF1ED"/><path d="M8 9v14c0 1.7 3.6 3 8 3s8-1.3 8-3V9M8 9c0 1.7 3.6 3 8 3s8-1.3 8-3M8 9c0-1.7 3.6-3 8-3s8 1.3 8 3M11 14l2.5 3 2.5-3 2.5 3 2.5-3"/>',
        'nature' => '<path d="M3 25l8-12 5 7 4-5 9 10z" fill="#9FD3B4"/><circle cx="23" cy="8" r="3.5" fill="#F7C86A"/><path d="M3 25l8-12 5 7m0 0l4-5 9 10H3"/>',
        'typography' => '<rect x="4" y="5" width="24" height="22" rx="4" fill="#F8E3A3"/><path d="M8 22l5-12 5 12M9.7 18h6.6M20 16.5c0-1.4 1.1-2.5 2.5-2.5S25 15.1 25 16.5V22m0-3.2c0 1.8-1.3 3.2-2.8 3.2-1.3 0-2.2-.8-2.2-2s1-2 2.5-2H25"/>',
        'abstract' => '<circle cx="11" cy="12" r="6" fill="#B7C6F2"/><rect x="15" y="15" width="12" height="12" rx="2" fill="#F2B8A2"/><path d="M5 26l6-9 6 9z" fill="#F7C86A"/><circle cx="11" cy="12" r="6"/><rect x="15" y="15" width="12" height="12" rx="2"/><path d="M5 26l6-9 6 9z"/>',
        'city-life' => '<rect x="5" y="12" width="8" height="15" fill="#B7C6F2"/><rect x="13" y="6" width="9" height="21" fill="#F8E3A3"/><rect x="22" y="15" width="6" height="12" fill="#9FD3B4"/><path d="M5 27V12h8v15M13 27V6h9v21M22 27V15h6v12M3 27h27M16 10h3M16 14h3M16 18h3M8 16h2M8 20h2"/>',
        'music' => '<circle cx="10" cy="23" r="4" fill="#F2B8A2"/><circle cx="23" cy="20" r="4" fill="#F2B8A2"/><path d="M14 23V8l13-3v15M14 12l13-3"/><circle cx="10" cy="23" r="4"/><circle cx="23" cy="20" r="4"/>',

        // Product types
        't-shirts' => '<path d="M11 5l-7 4 2.5 5.5L9 13v14h14V13l2.5 1.5L28 9l-7-4c-.8 2-2.7 3-5 3s-4.2-1-5-3z" fill="#9FC6F2"/>',
        'oversized-t-shirts' => '<path d="M11 5L2 10l3 6 3.5-2V27h15V14l3.5 2 3-6-9-5c-.8 2-2.7 3-5 3s-4.2-1-5-3z" fill="#C9B6F2"/>',
        'hoodies-sweatshirts' => '<path d="M11 6c0-2 2.2-3 5-3s5 1 5 3l5 3 2 16h-4l-1-9v11H9V16l-1 9H4l2-16z" fill="#8ED1C6"/><path d="M12.5 6.5c1 2 2.2 3 3.5 3s2.5-1 3.5-3M14 10v4M18 10v4M11 21h10"/>',
        'tote-bags' => '<path d="M6 12h20l-1.5 15h-17z" fill="#F7D08A"/><path d="M11 12V9a5 5 0 0110 0v3"/>',
        'mugs' => '<path d="M6 8h15v15a3 3 0 01-3 3H9a3 3 0 01-3-3z" fill="#F2B8A2"/><path d="M21 12h2.5a3.5 3.5 0 010 7H21"/><path d="M10 3c0 1.5 1.5 1.5 1.5 3M15 3c0 1.5 1.5 1.5 1.5 3"/>',
        'stickers' => '<path d="M6 6h20v13l-7 7H6z" fill="#F5A3C7"/><path d="M26 19h-5a2 2 0 00-2 2v5"/><path d="M12 13h.01M20 13h.01M12 17c1 1.5 2.4 2 4 2s3-.5 4-2"/>',
    ];
    $paths = $icons[$name] ?? '<path d="M16 4l3 9h9l-7 5.5 2.7 9L16 22l-7.7 5.5 2.7-9L4 13h9z" fill="#F7C86A"/>';
@endphp
<svg {{ $attributes->merge(['class' => 'size-8']) }} viewBox="0 0 32 32" fill="none" stroke="#17140f" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    {!! $paths !!}
</svg>
