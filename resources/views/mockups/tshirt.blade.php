{{-- Classic T-shirt. Expects $fill, $line, $view ('front'|'back'), $uid. --}}
<path d="M380 110 C420 {{ $view === 'back' ? 128 : 165 }} 580 {{ $view === 'back' ? 128 : 165 }} 620 110 L770 160 L920 330 L820 420 L745 355 L750 920 Q750 935 735 935 L265 935 Q250 935 250 920 L255 355 L180 420 L80 330 L230 160 Z" fill="{{ $fill }}"/>
<path d="M380 110 C420 {{ $view === 'back' ? 128 : 165 }} 580 {{ $view === 'back' ? 128 : 165 }} 620 110 C580 96 420 96 380 110 Z" fill="rgba(0,0,0,0.22)"/>
<path d="M372 112 C418 {{ $view === 'back' ? 140 : 182 }} 582 {{ $view === 'back' ? 140 : 182 }} 628 112" fill="none" stroke="{{ $line }}" stroke-width="14"/>
<path d="M255 355 L230 160 M745 355 L770 160 M180 420 L80 330 M820 420 L920 330" fill="none" stroke="{{ $line }}" stroke-width="3"/>
<path d="M380 110 C420 {{ $view === 'back' ? 128 : 165 }} 580 {{ $view === 'back' ? 128 : 165 }} 620 110 L770 160 L920 330 L820 420 L745 355 L750 920 Q750 935 735 935 L265 935 Q250 935 250 920 L255 355 L180 420 L80 330 L230 160 Z" fill="url(#shade-{{ $uid }})"/>
