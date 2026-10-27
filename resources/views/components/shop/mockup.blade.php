@props([
    'product',
    'color' => null,
    'version' => null,
    'placements' => [],
    'view' => 'front',
    'label' => null,
])
@php
    use App\Support\MockupGeometry;

    $uid = 'm'.\Illuminate\Support\Str::random(6);
    $fill = $color?->hex ?? '#FFFFFF';
    $line = MockupGeometry::contrast($fill);
    $template = $product->mockup_template;
    $placements = collect($placements)->keyBy(fn ($p) => is_array($p) ? $p['print_area_id'] : $p->print_area_id);

    $mockups = $product->mockups->filter(fn ($m) => $m->view === $view || ($view === 'front' && $m->view === 'wrap'));
    $mockups = $mockups->groupBy('print_area_id')->map(fn ($group) => $group->firstWhere('product_color_id', $color?->id) ?? $group->firstWhere('product_color_id', null))->filter();
    $photo = $mockups->first(fn ($m) => $m->image_path && $m->product_color_id === $color?->id);

    $layers = [];
    if ($version && $version->width && $version->height) {
        foreach ($mockups as $mockup) {
            $placement = $placements->get($mockup->print_area_id);
            $area = $product->printAreas->firstWhere('id', $mockup->print_area_id);
            if (! $placement || ! $area) {
                continue;
            }
            $p = is_array($placement) ? (object) $placement : $placement;
            $layers[] = MockupGeometry::place($mockup, $area, (float) $p->offset_x, (float) $p->offset_y, (float) $p->scale, (int) ($p->rotation ?? 0), $version->width, $version->height) + ['area_id' => $area->id];
        }
    }
    $imageUrl = $version?->previewUrl();
@endphp
<svg viewBox="0 0 1000 1000" role="img" aria-label="{{ $label ?? $product->translate('name') }}" {{ $attributes->merge(['class' => 'block h-full w-full']) }}>
    <defs>
        <linearGradient id="shade-{{ $uid }}" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0" stop-color="#fff" stop-opacity="0.10"/>
            <stop offset="0.45" stop-color="#fff" stop-opacity="0"/>
            <stop offset="1" stop-color="#000" stop-opacity="0.14"/>
        </linearGradient>
        <linearGradient id="cylinder-{{ $uid }}" x1="0" y1="0" x2="1" y2="0">
            <stop offset="0" stop-color="#000" stop-opacity="0.20"/>
            <stop offset="0.25" stop-color="#fff" stop-opacity="0.18"/>
            <stop offset="0.55" stop-color="#fff" stop-opacity="0"/>
            <stop offset="1" stop-color="#000" stop-opacity="0.24"/>
        </linearGradient>
        @foreach ($layers as $layer)
            <clipPath id="clip-{{ $uid }}-{{ $layer['area_id'] }}">
                <rect x="{{ $layer['area']['x'] }}" y="{{ $layer['area']['y'] }}" width="{{ $layer['area']['w'] }}" height="{{ $layer['area']['h'] }}"/>
            </clipPath>
        @endforeach
    </defs>

    @if ($photo)
        <image href="{{ $photo->imageUrl() }}" x="0" y="0" width="1000" height="1000" preserveAspectRatio="xMidYMid slice"/>
    @elseif (view()->exists('mockups.'.$template))
        @include('mockups.'.$template, ['fill' => $fill, 'line' => $line, 'view' => $view, 'uid' => $uid])
    @endif

    @foreach ($layers as $layer)
        <g clip-path="url(#clip-{{ $uid }}-{{ $layer['area_id'] }})">
            <image href="{{ $imageUrl }}" x="{{ round($layer['image']['x'], 2) }}" y="{{ round($layer['image']['y'], 2) }}"
                width="{{ round($layer['image']['w'], 2) }}" height="{{ round($layer['image']['h'], 2) }}" preserveAspectRatio="none"
                @if ($layer['rotation']) transform="rotate({{ $layer['rotation'] }} {{ round($layer['cx'], 2) }} {{ round($layer['cy'], 2) }})" @endif/>
        </g>
    @endforeach
</svg>
