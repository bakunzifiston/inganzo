@php
    $tools = [
        ['addText()', 'type', __('Text'), 'T'],
        ['openLibrary()', 'image', __('Image'), 'I'],
        ["addShape('rect')", 'square', __('Rectangle'), 'R'],
        ["addShape('ellipse')", 'circle', __('Ellipse'), 'E'],
        ["addShape('triangle')", 'triangle', __('Triangle'), ''],
        ["addShape('star')", 'star', __('Star'), ''],
        ["addShape('line')", 'line', __('Line'), 'L'],
    ];
    $shortcuts = [
        [__('Undo / redo'), '⌘/Ctrl Z · ⇧⌘/Ctrl Z'],
        [__('Save'), '⌘/Ctrl S'],
        [__('Copy / paste / cut'), '⌘/Ctrl C · V · X'],
        [__('Duplicate'), '⌘/Ctrl D'],
        [__('Select all'), '⌘/Ctrl A'],
        [__('Group / ungroup'), '⌘/Ctrl G · ⇧⌘/Ctrl G'],
        [__('Delete'), 'Delete / Backspace'],
        [__('Move 1 mm / 10 mm'), '← → ↑ ↓ · ⇧ + arrows'],
        [__('Bring forward / send backward'), '] · [ (⇧ for front / back)'],
        [__('Zoom in / out / fit / actual size'), '⌘/Ctrl + · − · 0 · 1'],
        [__('Pan'), __('Space + drag, or scroll')],
        [__('Zoom at pointer'), __('⌘/Ctrl + scroll')],
        [__('Add text / rectangle / ellipse / line'), 'T · R · E · L'],
        [__('Image library'), 'I'],
        [__('Preview'), 'P'],
        [__('Grid'), "'"],
        [__('Rotate in 15° steps'), __('Hold ⇧ while rotating')],
        [__('Add to selection'), __('⇧ or ⌘/Ctrl + click a layer')],
    ];
@endphp
<x-layouts.base :title="__('Design Studio · :title', ['title' => $artwork->title])" body-class="overflow-hidden bg-paper">
    <x-slot:head>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link rel="stylesheet" href="https://fonts.bunny.net/css?family=bebas-neue:400|caveat:400,700|montserrat:400,700|oswald:400,700|pacifico:400|playfair-display:400,700|space-mono:400,700&display=swap">
        @vite('resources/js/studio.js')
    </x-slot:head>

    <div x-data="designStudio(@js($config))" class="flex h-dvh flex-col text-ink" id="main">
        {{-- Top toolbar --}}
        <header class="flex h-14 shrink-0 items-center gap-1 border-b border-line bg-white px-2 sm:gap-2 sm:px-3">
            <a href="{{ route('studio.artworks.show', $artwork) }}" class="studio-btn" title="{{ __('Back to design') }}" aria-label="{{ __('Back to design') }}"><x-ui.icon name="arrow-left" class="size-4" /></a>
            <div class="mr-1 hidden min-w-0 sm:block">
                <p class="max-w-48 truncate text-sm font-semibold leading-tight">{{ $artwork->title }}</p>
                <p class="truncate text-[11px] text-muted">{{ $config['artboard']['label'] }} · {{ __(':w × :h mm', ['w' => $document->width_mm, 'h' => $document->height_mm]) }}</p>
            </div>

            <div class="flex items-center border-l border-line pl-1 sm:pl-2">
                <button type="button" class="studio-btn" @click="undo()" :disabled="! canUndo" title="{{ __('Undo') }} (⌘Z)" aria-label="{{ __('Undo') }}"><x-ui.icon name="undo" class="size-4" /></button>
                <button type="button" class="studio-btn" @click="redo()" :disabled="! canRedo" title="{{ __('Redo') }} (⇧⌘Z)" aria-label="{{ __('Redo') }}"><x-ui.icon name="redo" class="size-4" /></button>
            </div>

            <div class="hidden items-center border-l border-line pl-2 md:flex">
                <button type="button" class="studio-btn" @click="zoomBy(0.8)" title="{{ __('Zoom out') }}" aria-label="{{ __('Zoom out') }}"><x-ui.icon name="zoom-out" class="size-4" /></button>
                <button type="button" class="h-8 w-14 rounded-md text-xs font-medium tabular-nums hover:bg-sand" @click="actualSize()" x-text="zoomLabel" title="{{ __('Actual size') }} (⌘1)"></button>
                <button type="button" class="studio-btn" @click="zoomBy(1.25)" title="{{ __('Zoom in') }}" aria-label="{{ __('Zoom in') }}"><x-ui.icon name="zoom-in" class="size-4" /></button>
                <button type="button" class="studio-btn" @click="fit()" title="{{ __('Fit to screen') }} (⌘0)" aria-label="{{ __('Fit to screen') }}"><x-ui.icon name="maximize" class="size-4" /></button>
            </div>

            <div class="hidden items-center border-l border-line pl-2 md:flex">
                <button type="button" class="studio-btn" :class="showGrid && 'studio-btn-on'" @click="toggleGrid()" :aria-pressed="showGrid" title="{{ __('Grid (10 mm)') }}" aria-label="{{ __('Grid') }}"><x-ui.icon name="grid-lines" class="size-4" /></button>
                <button type="button" class="studio-btn" :class="snap && 'studio-btn-on'" @click="snap = ! snap" :aria-pressed="snap" title="{{ __('Snap to guides') }}" aria-label="{{ __('Snap to guides') }}"><x-ui.icon name="magnet" class="size-4" /></button>
                <button type="button" class="studio-btn" :class="preview && 'studio-btn-on'" @click="togglePreview()" :aria-pressed="preview" title="{{ __('Preview') }} (P)" aria-label="{{ __('Preview') }}"><x-ui.icon name="eye" class="size-4" /></button>
                <button type="button" class="studio-btn" @click="showShortcuts = true" title="{{ __('Keyboard shortcuts') }} (?)" aria-label="{{ __('Keyboard shortcuts') }}"><x-ui.icon name="keyboard" class="size-4" /></button>
            </div>

            <div class="ml-auto flex items-center gap-1.5 sm:gap-2">
                <span class="hidden items-center gap-1.5 text-xs whitespace-nowrap xl:flex" role="status" aria-live="polite">
                    <span class="size-2 rounded-full" :class="{ 'bg-success-600': status === 'saved', 'bg-warning-500': status === 'dirty' || status === 'saving', 'bg-danger-700': ['error', 'conflict', 'offline'].includes(status) }"></span>
                    <span class="text-muted" x-text="statusLabel"></span>
                </span>
                <span class="size-2 rounded-full xl:hidden" :title="statusLabel" :class="{ 'bg-success-600': status === 'saved', 'bg-warning-500': status === 'dirty' || status === 'saving', 'bg-danger-700': ['error', 'conflict', 'offline'].includes(status) }"></span>
                <button type="button" class="studio-btn" @click="save(true)" :disabled="status === 'saved' || status === 'saving' || status === 'conflict'" title="{{ __('Save') }} (⌘S)" aria-label="{{ __('Save') }}"><x-ui.icon name="save" class="size-4" /></button>
                <button type="button" class="studio-btn lg:hidden" @click="mobilePanel = mobilePanel === 'left' ? null : 'left'" aria-label="{{ __('Layers') }}"><x-ui.icon name="layers" class="size-4" /></button>
                <button type="button" class="studio-btn lg:hidden" @click="mobilePanel = mobilePanel === 'right' ? null : 'right'" aria-label="{{ __('Properties') }}"><x-ui.icon name="settings" class="size-4" /></button>
                <button type="button" class="hidden h-8 items-center gap-1.5 rounded-full border border-line-strong px-3 text-xs font-semibold hover:border-brand-700 sm:inline-flex" @click="download()" :disabled="exportState !== ''" title="{{ __('Download a transparent PNG at full print resolution') }}">
                    <x-ui.icon name="download" class="size-4" /> {{ __('PNG') }}
                </button>
                <button type="button" class="inline-flex h-8 items-center gap-1.5 rounded-full bg-brand-600 px-3.5 text-xs font-semibold whitespace-nowrap text-ink hover:bg-brand-500 disabled:opacity-60" @click="exportToProducts()" :disabled="exportState !== ''" title="{{ __('Export to products') }}">
                    <x-ui.icon name="upload" class="size-4" />
                    <span x-show="exportState === ''"><span class="sm:hidden">{{ __('Export') }}</span><span class="hidden sm:inline">{{ __('Export to products') }}</span></span>
                    <span x-show="exportState === 'saving'" x-cloak>{{ __('Saving…') }}</span>
                    <span x-show="exportState === 'rendering'" x-cloak>{{ __('Rendering…') }}</span>
                    <span x-show="exportState === 'uploading'" x-cloak>{{ __('Uploading…') }}</span>
                </button>
            </div>
        </header>

        {{-- Banners --}}
        <div x-show="recovery" x-cloak class="flex flex-wrap items-center gap-3 border-b border-warning-100 bg-warning-50 px-4 py-2 text-sm">
            <x-ui.icon name="info" class="size-4 text-warning-700" />
            <span>{{ __('Unsaved changes from') }} <span x-text="recovery?.at"></span> {{ __('were found on this device.') }}</span>
            <button type="button" class="font-semibold text-brand-800 underline" @click="recover()">{{ __('Restore them') }}</button>
            <button type="button" class="text-muted underline" @click="discardRecovery()">{{ __('Discard') }}</button>
        </div>
        <div x-show="status === 'conflict'" x-cloak class="flex flex-wrap items-center gap-3 border-b border-danger-100 bg-danger-50 px-4 py-2 text-sm text-danger-700">
            <x-ui.icon name="alert" class="size-4" />
            <span>{{ __('This design was saved from another tab or device. Your copy is kept on this device; reload to continue from the latest version and restore it if you need to.') }}</span>
            <button type="button" class="font-semibold underline" @click="writeDraft(); location.reload()">{{ __('Reload') }}</button>
        </div>
        <div x-show="status === 'error' || exportError" x-cloak class="flex items-center gap-3 border-b border-danger-100 bg-danger-50 px-4 py-2 text-sm text-danger-700">
            <x-ui.icon name="alert" class="size-4" />
            <span x-text="exportError || statusMessage"></span>
            <button type="button" class="ml-auto text-xs underline" @click="exportError = ''">{{ __('Dismiss') }}</button>
        </div>

        <div class="relative flex min-h-0 flex-1">
            {{-- Tool rail --}}
            <nav class="flex w-12 shrink-0 flex-col items-center gap-1 border-r border-line bg-white py-2" aria-label="{{ __('Tools') }}">
                <button type="button" class="studio-btn studio-btn-on" @click="deselect()" title="{{ __('Select') }} (Esc)" aria-label="{{ __('Select') }}"><x-ui.icon name="cursor" class="size-4" /></button>
                <span class="my-1 h-px w-6 bg-line"></span>
                @foreach ($tools as [$action, $icon, $label, $key])
                    <button type="button" class="studio-btn" @click="{{ $action }}" title="{{ $label }}{{ $key ? ' ('.$key.')' : '' }}" aria-label="{{ $label }}"><x-ui.icon :name="$icon" class="size-4" /></button>
                @endforeach
            </nav>

            {{-- Left panel: layers & images --}}
            <aside class="w-64 shrink-0 flex-col border-r border-line bg-white lg:static lg:flex lg:shadow-none"
                :class="mobilePanel === 'left' ? 'absolute inset-y-0 left-12 z-30 flex shadow-xl' : 'hidden'">
                <div class="flex border-b border-line text-xs font-semibold" role="tablist">
                    <button type="button" role="tab" class="flex-1 py-2.5" :class="leftPanel === 'layers' ? 'border-b-2 border-brand-600 text-ink' : 'text-muted'" @click="leftPanel = 'layers'" :aria-selected="leftPanel === 'layers'">{{ __('Layers') }}</button>
                    <button type="button" role="tab" class="flex-1 py-2.5" :class="leftPanel === 'images' ? 'border-b-2 border-brand-600 text-ink' : 'text-muted'" @click="openLibrary(replaceMode)" :aria-selected="leftPanel === 'images'">{{ __('Images') }}</button>
                </div>

                <div x-show="leftPanel === 'layers'" class="min-h-0 flex-1 overflow-y-auto p-2">
                    <p x-show="! layers.length" class="px-2 py-6 text-center text-xs text-muted">{{ __('No layers yet. Add text, an image or a shape from the toolbar.') }}</p>
                    <ul class="space-y-0.5" x-data="{ renaming: null }">
                        <template x-for="(layer, i) in layers" :key="layer.id">
                            <li draggable="true"
                                @dragstart="dragIndex = i; $event.dataTransfer.effectAllowed = 'move'"
                                @dragover.prevent="dropIndex = i"
                                @drop.prevent="layerDrop()"
                                @dragend="layerDrop()"
                                @click="pickLayer(layer.id, $event)"
                                class="group flex h-9 cursor-pointer items-center gap-1.5 rounded-md px-1.5 text-xs"
                                :class="[layer.selected ? 'bg-brand-50 ring-1 ring-brand-200' : 'hover:bg-paper', dropIndex === i && dragIndex !== null && dragIndex !== i ? 'outline-2 outline-dashed outline-brand-600' : '', ! layer.visible ? 'opacity-50' : '']">
                                <x-ui.icon name="grip" class="size-3.5 shrink-0 cursor-grab text-subtle" />
                                <span class="shrink-0 text-muted">
                                    <span x-show="layer.type === 'text'"><x-ui.icon name="type" class="size-3.5" /></span>
                                    <span x-show="layer.type === 'image'"><x-ui.icon name="image" class="size-3.5" /></span>
                                    <span x-show="layer.type === 'shape'"><x-ui.icon name="square" class="size-3.5" /></span>
                                    <span x-show="layer.type === 'group'"><x-ui.icon name="group" class="size-3.5" /></span>
                                </span>
                                <span x-show="renaming !== layer.id" class="min-w-0 flex-1 truncate" x-text="layer.name" @dblclick.stop="renaming = layer.id; $nextTick(() => $el.nextElementSibling.select())"></span>
                                <input x-show="renaming === layer.id" type="text" class="studio-input h-6 flex-1" :value="layer.name" maxlength="80"
                                    @click.stop @keydown.enter="renameLayer(layer.id, $event.target.value); renaming = null" @keydown.escape="renaming = null" @blur="renaming === layer.id && renameLayer(layer.id, $event.target.value); renaming = null">
                                <span x-show="layer.issue === 'fail'" class="size-2 shrink-0 rounded-full bg-danger-700" title="{{ __('Resolution too low') }}"></span>
                                <span x-show="layer.issue === 'warn'" class="size-2 shrink-0 rounded-full bg-warning-500" title="{{ __('Check print warnings') }}"></span>
                                <span x-show="layer.issue === 'edge'" class="size-2 shrink-0 rounded-full bg-info-600" title="{{ __('Outside the safe area') }}"></span>
                                <button type="button" class="studio-btn size-6" :class="layer.visible && 'opacity-0 group-hover:opacity-100 focus:opacity-100'" @click.stop="toggleVisible(layer.id)" :aria-label="layer.visible ? '{{ __('Hide') }}' : '{{ __('Show') }}'">
                                    <span x-show="layer.visible"><x-ui.icon name="eye" class="size-3.5" /></span>
                                    <span x-show="! layer.visible"><x-ui.icon name="eye-off" class="size-3.5" /></span>
                                </button>
                                <button type="button" class="studio-btn size-6" :class="! layer.locked && 'opacity-0 group-hover:opacity-100 focus:opacity-100'" @click.stop="toggleLock(layer.id)" :aria-label="layer.locked ? '{{ __('Unlock') }}' : '{{ __('Lock') }}'">
                                    <span x-show="layer.locked"><x-ui.icon name="lock" class="size-3.5" /></span>
                                    <span x-show="! layer.locked"><x-ui.icon name="unlock" class="size-3.5" /></span>
                                </button>
                            </li>
                        </template>
                    </ul>
                    <p x-show="layers.length > 1" class="mt-3 px-2 text-[11px] text-subtle">{{ __('Drag to reorder. Double-click to rename. ⇧-click to select several.') }}</p>
                </div>

                <div x-show="leftPanel === 'images'" x-cloak class="min-h-0 flex-1 overflow-y-auto p-3">
                    <div x-show="replaceMode" class="mb-3 flex items-center justify-between rounded-md bg-info-50 px-2.5 py-2 text-xs text-info-700">
                        <span>{{ __('Choose an image to replace the selected one.') }}</span>
                        <button type="button" class="underline" @click="replaceMode = false">{{ __('Cancel') }}</button>
                    </div>
                    <label class="flex cursor-pointer flex-col items-center rounded-lg border-2 border-dashed border-line-strong px-3 py-4 text-center text-xs transition hover:border-brand-600" :class="uploading && 'pointer-events-none opacity-60'">
                        <x-ui.icon name="upload" class="size-5 text-brand-700" />
                        <span class="mt-1.5 font-semibold" x-text="uploading ? '{{ __('Uploading…') }}' : '{{ __('Upload an image') }}'"></span>
                        <span class="mt-0.5 text-muted">{{ __('PNG, JPEG or WebP · up to :mb MB', ['mb' => intdiv(config('marketplace.design.max_asset_kb'), 1024)]) }}</span>
                        <input type="file" accept="image/png,image/jpeg,image/webp" class="sr-only" @change="upload($event)">
                    </label>
                    <p x-show="uploadError" x-text="uploadError" class="mt-2 text-xs font-medium text-danger-700"></p>
                    <p class="mt-2 text-[11px] text-subtle">{{ __('Original files are kept unchanged. Cropping and resizing in the studio never alter them.') }}</p>

                    <p x-show="library.loading" class="mt-4 text-xs text-muted">{{ __('Loading…') }}</p>
                    <template x-if="library.uploads.length">
                        <div class="mt-4">
                            <p class="studio-heading mb-2">{{ __('Your uploads') }}</p>
                            <div class="grid grid-cols-3 gap-1.5">
                                <template x-for="item in library.uploads" :key="item.ref">
                                    <button type="button" class="aspect-square overflow-hidden rounded-md border border-line bg-checker p-1 hover:border-brand-600" @click="addImage(item)" :title="`${item.name} · ${item.width} × ${item.height} px`">
                                        <img :src="item.url" alt="" class="h-full w-full object-contain" loading="lazy">
                                    </button>
                                </template>
                            </div>
                        </div>
                    </template>
                    <template x-if="library.artworks.length">
                        <div class="mt-4">
                            <p class="studio-heading mb-2">{{ __('Your designs') }}</p>
                            <div class="grid grid-cols-3 gap-1.5">
                                <template x-for="item in library.artworks" :key="item.ref">
                                    <button type="button" class="aspect-square overflow-hidden rounded-md border border-line bg-checker p-1 hover:border-brand-600" @click="addImage(item)" :title="`${item.name} · ${item.width} × ${item.height} px`">
                                        <img :src="item.thumb" alt="" class="h-full w-full object-contain" loading="lazy">
                                    </button>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </aside>

            {{-- Canvas --}}
            <main class="relative min-w-0 flex-1 overflow-hidden" @click.self="mobilePanel = null">
                <div x-ref="stage" class="absolute inset-0" @pointerdown="mobilePanel = null"><canvas x-ref="canvas"></canvas></div>
                <div class="pointer-events-none absolute inset-x-0 bottom-0 flex flex-wrap items-center gap-x-4 gap-y-1 bg-gradient-to-t from-white/95 to-white/0 px-4 pt-6 pb-2 text-[11px] text-muted">
                    <span>{{ __(':w × :h mm · :pw × :ph px at :dpi DPI', ['w' => $document->width_mm, 'h' => $document->height_mm, 'pw' => $config['artboard']['width'], 'ph' => $config['artboard']['height'], 'dpi' => $config['artboard']['dpi']]) }}</span>
                    <span class="inline-flex items-center gap-1"><span class="inline-block h-0 w-4 border-t border-dashed border-info-600"></span>{{ __('Safe area (:mm mm)', ['mm' => $config['artboard']['safeMm']]) }}</span>
                    <span x-show="lowestDpi !== null" :class="lowestDpi < {{ $config['thresholds']['min'] }} ? 'font-semibold text-danger-700' : (lowestDpi < {{ $config['thresholds']['sharp'] }} ? 'text-warning-700' : 'text-success-700')">
                        {{ __('Lowest image resolution:') }} <span x-text="lowestDpi"></span> DPI
                    </span>
                    <span x-show="preview" class="font-semibold text-ink">{{ __('Preview — press Esc or P to edit') }}</span>
                </div>
            </main>

            {{-- Right panel: properties --}}
            <aside class="w-72 shrink-0 overflow-y-auto border-l border-line bg-white lg:static lg:block"
                :class="mobilePanel === 'right' ? 'absolute inset-y-0 right-0 z-30 block shadow-xl' : 'hidden'"
                aria-label="{{ __('Properties') }}">

                {{-- Nothing selected: artboard and print check --}}
                <template x-if="! sel">
                    <div>
                        <section class="studio-section">
                            <h2 class="studio-heading">{{ __('Print area') }}</h2>
                            <p class="text-sm font-semibold">{{ $config['artboard']['label'] }}</p>
                            <dl class="grid grid-cols-2 gap-y-1 text-xs">
                                <dt class="text-muted">{{ __('Printed size') }}</dt><dd class="text-right">{{ __(':w × :h mm', ['w' => $document->width_mm, 'h' => $document->height_mm]) }}</dd>
                                <dt class="text-muted">{{ __('Print file') }}</dt><dd class="text-right">{{ $config['artboard']['width'] }} × {{ $config['artboard']['height'] }} px</dd>
                                <dt class="text-muted">{{ __('Resolution') }}</dt><dd class="text-right">{{ $config['artboard']['dpi'] }} DPI</dd>
                                <dt class="text-muted">{{ __('Minimum / sharp') }}</dt><dd class="text-right">{{ $config['thresholds']['min'] }} / {{ $config['thresholds']['sharp'] }} DPI</dd>
                            </dl>
                        </section>
                        <section class="studio-section">
                            <h2 class="studio-heading">{{ __('Background') }}</h2>
                            <div class="flex items-center gap-2">
                                <button type="button" class="h-8 flex-1 rounded-md border text-xs font-medium" :class="! background ? 'border-brand-600 bg-brand-50' : 'border-line-strong'" @click="setBackground(null)">{{ __('Transparent') }}</button>
                                <input type="color" class="h-8 w-12 cursor-pointer rounded-md border border-line-strong" :value="background || '#ffffff'" @input="setBackground($event.target.value)" aria-label="{{ __('Background colour') }}">
                            </div>
                            <p class="text-[11px] text-subtle">{{ __('Transparent is best for apparel: only your artwork is printed, not a box around it.') }}</p>
                        </section>
                        <section class="studio-section">
                            <h2 class="studio-heading">{{ __('Print check') }}</h2>
                            <p x-show="! warnings.length && layers.length" class="flex items-center gap-1.5 text-xs text-success-700"><x-ui.icon name="check-circle" class="size-4" /> {{ __('Everything is inside the print area and sharp enough.') }}</p>
                            <p x-show="! layers.length" class="text-xs text-muted">{{ __('Start by adding text, an image or a shape.') }}</p>
                            <ul class="space-y-1.5">
                                <template x-for="(w, i) in warnings" :key="i">
                                    <li>
                                        <button type="button" class="flex w-full gap-2 rounded-md p-2 text-left text-xs" @click="focusWarning(w.id)"
                                            :class="{ 'bg-danger-50 text-danger-700': w.level === 'fail', 'bg-warning-50 text-warning-700': w.level === 'warn', 'bg-info-50 text-info-700': w.level === 'info' }">
                                            <x-ui.icon name="alert" class="mt-0.5 size-3.5 shrink-0" /><span x-text="w.text"></span>
                                        </button>
                                    </li>
                                </template>
                            </ul>
                            <p x-show="blocking" class="text-[11px] text-danger-700">{{ __('You can still export, but products using this design will fail the print check and cannot be sold until the image is made smaller or replaced.') }}</p>
                        </section>
                        <section class="studio-section border-b-0">
                            <button type="button" class="inline-flex items-center gap-1.5 text-xs font-medium text-brand-800 underline" @click="showShortcuts = true"><x-ui.icon name="keyboard" class="size-4" /> {{ __('Keyboard shortcuts') }}</button>
                            @if ($document->exported_at)
                                <p class="text-[11px] text-subtle">{{ __('Last exported :time.', ['time' => $document->exported_at->diffForHumans()]) }}</p>
                            @endif
                        </section>
                    </div>
                </template>

                <template x-if="sel">
                    <div>
                        {{-- Multiple selection --}}
                        <template x-if="sel.kind === 'multiple'">
                            <section class="studio-section">
                                <h2 class="studio-heading"><span x-text="sel.count"></span> {{ __('layers selected') }}</h2>
                                <div class="flex flex-wrap gap-1.5">
                                    <button type="button" class="inline-flex h-8 items-center gap-1.5 rounded-md border border-line-strong px-2.5 text-xs font-medium hover:bg-sand" @click="group()"><x-ui.icon name="group" class="size-4" /> {{ __('Group') }}</button>
                                    <button type="button" class="inline-flex h-8 items-center gap-1.5 rounded-md border border-line-strong px-2.5 text-xs font-medium hover:bg-sand" @click="duplicate()"><x-ui.icon name="copy" class="size-4" /> {{ __('Duplicate') }}</button>
                                    <button type="button" class="inline-flex h-8 items-center gap-1.5 rounded-md border border-line-strong px-2.5 text-xs font-medium text-danger-700 hover:bg-danger-50" @click="remove()"><x-ui.icon name="trash" class="size-4" /> {{ __('Delete') }}</button>
                                </div>
                                <p class="studio-label pt-1">{{ __('Distribute') }}</p>
                                <div class="flex gap-1">
                                    <button type="button" class="studio-btn border border-line" @click="distribute('x')" :disabled="sel.count < 3" title="{{ __('Distribute horizontally') }}" aria-label="{{ __('Distribute horizontally') }}"><x-ui.icon name="distribute-x" class="size-4" /></button>
                                    <button type="button" class="studio-btn border border-line" @click="distribute('y')" :disabled="sel.count < 3" title="{{ __('Distribute vertically') }}" aria-label="{{ __('Distribute vertically') }}"><x-ui.icon name="distribute-y" class="size-4" /></button>
                                </div>
                            </section>
                        </template>

                        {{-- Layer name and actions --}}
                        <template x-if="sel.kind !== 'multiple'">
                            <section class="studio-section">
                                <div class="flex items-center gap-1">
                                    <input type="text" class="studio-input font-semibold" :value="sel.name" @change="setName($event.target.value)" maxlength="80" aria-label="{{ __('Layer name') }}" placeholder="{{ __('Layer name') }}">
                                    <button type="button" class="studio-btn" @click="duplicate()" title="{{ __('Duplicate') }} (⌘D)" aria-label="{{ __('Duplicate') }}"><x-ui.icon name="copy" class="size-4" /></button>
                                    <button type="button" class="studio-btn" :class="sel.locked && 'studio-btn-on'" @click="toggleLock()" :title="sel.locked ? '{{ __('Unlock') }}' : '{{ __('Lock') }}'" :aria-label="sel.locked ? '{{ __('Unlock') }}' : '{{ __('Lock') }}'">
                                        <span x-show="sel.locked"><x-ui.icon name="lock" class="size-4" /></span><span x-show="! sel.locked"><x-ui.icon name="unlock" class="size-4" /></span>
                                    </button>
                                    <button type="button" class="studio-btn text-danger-700" @click="remove()" :disabled="sel.locked" title="{{ __('Delete') }}" aria-label="{{ __('Delete') }}"><x-ui.icon name="trash" class="size-4" /></button>
                                </div>
                                <p x-show="sel.locked" class="text-[11px] text-muted">{{ __('Locked layers cannot be moved, resized or deleted.') }}</p>
                                <button x-show="sel.kind === 'group'" type="button" class="inline-flex h-8 items-center gap-1.5 rounded-md border border-line-strong px-2.5 text-xs font-medium hover:bg-sand" @click="ungroup()"><x-ui.icon name="ungroup" class="size-4" /> {{ __('Ungroup') }}</button>
                            </section>
                        </template>

                        {{-- Position & size --}}
                        <section class="studio-section" :class="sel.locked && 'pointer-events-none opacity-50'">
                            <h2 class="studio-heading">{{ __('Position & size (mm)') }}</h2>
                            <div class="grid grid-cols-2 gap-2">
                                <label><span class="studio-label">X</span><input type="number" step="0.5" class="studio-input" :value="sel.x" @change="setGeometry('x', $event.target.value)"></label>
                                <label><span class="studio-label">Y</span><input type="number" step="0.5" class="studio-input" :value="sel.y" @change="setGeometry('y', $event.target.value)"></label>
                                <template x-if="sel.kind !== 'multiple'">
                                    <label><span class="studio-label">{{ __('W') }}</span><input type="number" step="0.5" min="1" class="studio-input" :value="sel.w" @change="setGeometry('w', $event.target.value)"></label>
                                </template>
                                <template x-if="sel.kind !== 'multiple'">
                                    <div class="flex items-end gap-1">
                                        <label class="flex-1"><span class="studio-label">{{ __('H') }}</span><input type="number" step="0.5" min="1" class="studio-input" :value="sel.h" @change="setGeometry('h', $event.target.value)" :disabled="sel.kind === 'text'"></label>
                                        <button type="button" x-show="sel.kind !== 'text'" class="studio-btn" :class="sel.lockAspect && 'studio-btn-on'" @click="toggleAspect()" :title="sel.lockAspect ? '{{ __('Proportions locked — click to resize freely') }}' : '{{ __('Free resize — click to keep proportions') }}'" aria-label="{{ __('Keep proportions') }}">
                                            <span x-show="sel.lockAspect"><x-ui.icon name="lock" class="size-3.5" /></span><span x-show="! sel.lockAspect"><x-ui.icon name="unlock" class="size-3.5" /></span>
                                        </button>
                                    </div>
                                </template>
                                <template x-if="sel.kind !== 'multiple'">
                                    <label><span class="studio-label">{{ __('Rotation °') }}</span><input type="number" step="1" class="studio-input" :value="sel.angle" @change="setGeometry('angle', $event.target.value)"></label>
                                </template>
                                <template x-if="sel.kind !== 'multiple'">
                                    <div class="flex items-end gap-1">
                                        <button type="button" class="studio-btn border border-line" @click="rotateBy(-90)" title="{{ __('Rotate left 90°') }}" aria-label="{{ __('Rotate left 90°') }}"><x-ui.icon name="rotate" class="size-4 -scale-x-100" /></button>
                                        <button type="button" class="studio-btn border border-line" @click="rotateBy(90)" title="{{ __('Rotate right 90°') }}" aria-label="{{ __('Rotate right 90°') }}"><x-ui.icon name="rotate" class="size-4" /></button>
                                        <button type="button" class="studio-btn border border-line" :class="sel.flipX && 'studio-btn-on'" @click="flip('flipX')" title="{{ __('Flip horizontally') }}" aria-label="{{ __('Flip horizontally') }}"><x-ui.icon name="flip-x" class="size-4" /></button>
                                        <button type="button" class="studio-btn border border-line" :class="sel.flipY && 'studio-btn-on'" @click="flip('flipY')" title="{{ __('Flip vertically') }}" aria-label="{{ __('Flip vertically') }}"><x-ui.icon name="flip-y" class="size-4" /></button>
                                    </div>
                                </template>
                            </div>
                            <template x-if="sel.kind !== 'multiple'">
                                <label class="block"><span class="studio-label">{{ __('Opacity') }} · <span x-text="sel.opacity + '%'"></span></span>
                                    <input type="range" min="0" max="100" class="w-full accent-brand-700" :value="sel.opacity" @input="setOpacity($event.target.value)">
                                </label>
                            </template>
                        </section>

                        {{-- Align & arrange --}}
                        <section class="studio-section">
                            <h2 class="studio-heading" x-text="sel.kind === 'multiple' ? '{{ __('Align to selection') }}' : '{{ __('Align to print area') }}'"></h2>
                            <div class="flex flex-wrap gap-1">
                                @foreach (['left' => __('Align left'), 'center' => __('Align centre'), 'right' => __('Align right'), 'top' => __('Align top'), 'middle' => __('Align middle'), 'bottom' => __('Align bottom')] as $where => $label)
                                    <button type="button" class="studio-btn border border-line" @click="align('{{ $where }}')" title="{{ $label }}" aria-label="{{ $label }}"><x-ui.icon :name="'align-'.$where" class="size-4" /></button>
                                @endforeach
                            </div>
                            <template x-if="sel.kind !== 'multiple'">
                                <div class="flex gap-1">
                                    <button type="button" class="studio-btn border border-line" @click="order('front')" title="{{ __('Bring to front') }}" aria-label="{{ __('Bring to front') }}"><x-ui.icon name="bring-front" class="size-4" /></button>
                                    <button type="button" class="h-8 rounded-md border border-line px-2 text-xs hover:bg-sand" @click="order('forward')">{{ __('Forward') }}</button>
                                    <button type="button" class="h-8 rounded-md border border-line px-2 text-xs hover:bg-sand" @click="order('backward')">{{ __('Backward') }}</button>
                                    <button type="button" class="studio-btn border border-line" @click="order('back')" title="{{ __('Send to back') }}" aria-label="{{ __('Send to back') }}"><x-ui.icon name="send-back" class="size-4" /></button>
                                </div>
                            </template>
                        </section>

                        {{-- Text --}}
                        <template x-if="sel.kind === 'text'">
                            <section class="studio-section">
                                <h2 class="studio-heading">{{ __('Text') }}</h2>
                                <textarea class="studio-input h-auto py-1.5 leading-snug" rows="3" maxlength="5000" :value="sel.text" @input="setText($event.target.value)" aria-label="{{ __('Text') }}"></textarea>
                                <div class="grid grid-cols-[1fr_4.5rem] gap-2">
                                    <select class="studio-input" @change="setFont($event.target.value)" aria-label="{{ __('Font') }}">
                                        <template x-for="font in fonts" :key="font"><option :value="font" x-text="font" :selected="font === sel.fontFamily" :style="`font-family: '${font}'`"></option></template>
                                    </select>
                                    <label class="relative"><input type="number" min="4" max="1000" step="1" class="studio-input pr-6" :value="sel.fontSize" @change="setFontSize($event.target.value)" aria-label="{{ __('Font size (pt)') }}"><span class="pointer-events-none absolute top-2 right-1.5 text-[10px] text-subtle">pt</span></label>
                                </div>
                                <div class="flex items-center gap-1">
                                    <input type="color" class="h-8 w-10 shrink-0 cursor-pointer rounded-md border border-line-strong" :value="hex(sel.fill, '#171923')" @input="set('fill', $event.target.value)" aria-label="{{ __('Text colour') }}">
                                    <button type="button" class="studio-btn border border-line font-bold" :class="sel.bold && 'studio-btn-on'" @click="toggleStyle('bold')" aria-label="{{ __('Bold') }}">B</button>
                                    <button type="button" class="studio-btn border border-line italic" :class="sel.italic && 'studio-btn-on'" @click="toggleStyle('italic')" aria-label="{{ __('Italic') }}">I</button>
                                    <button type="button" class="studio-btn border border-line underline" :class="sel.underline && 'studio-btn-on'" @click="toggleStyle('underline')" aria-label="{{ __('Underline') }}">U</button>
                                </div>
                                <div class="flex gap-1">
                                    @foreach (['left' => __('Left'), 'center' => __('Centre'), 'right' => __('Right'), 'justify' => __('Justify')] as $align => $label)
                                        <button type="button" class="h-8 flex-1 rounded-md border border-line text-xs hover:bg-sand" :class="sel.textAlign === '{{ $align }}' && 'studio-btn-on'" @click="set('textAlign', '{{ $align }}')">{{ $label }}</button>
                                    @endforeach
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <label><span class="studio-label">{{ __('Letter spacing') }}</span><input type="number" step="10" min="-200" max="2000" class="studio-input" :value="sel.charSpacing" @change="set('charSpacing', Number($event.target.value)); applyCurve(active())"></label>
                                    <label><span class="studio-label">{{ __('Line height') }}</span><input type="number" step="0.05" min="0.5" max="4" class="studio-input" :value="sel.lineHeight" @change="set('lineHeight', Number($event.target.value))"></label>
                                </div>
                                <label class="block"><span class="studio-label">{{ __('Curve') }} · <span x-text="sel.curve"></span></span>
                                    <input type="range" min="-100" max="100" step="1" class="w-full accent-brand-700" :value="sel.curve" @input="setCurve($event.target.value)">
                                </label>
                                <p class="text-[11px] text-subtle">{{ __('Text is printed as sharp vector shapes at any size. Box width controls wrapping.') }}</p>
                            </section>
                        </template>

                        {{-- Image --}}
                        <template x-if="sel.kind === 'image'">
                            <section class="studio-section">
                                <h2 class="studio-heading">{{ __('Image') }}</h2>
                                <div class="rounded-md p-2 text-xs" :class="sel.dpi < {{ $config['thresholds']['min'] }} ? 'bg-danger-50 text-danger-700' : (sel.dpi < {{ $config['thresholds']['sharp'] }} ? 'bg-warning-50 text-warning-700' : 'bg-success-50 text-success-700')">
                                    <p class="font-semibold"><span x-text="sel.dpi"></span> DPI ·
                                        <span x-show="sel.dpi >= {{ $config['thresholds']['sharp'] }}">{{ __('sharp') }}</span>
                                        <span x-show="sel.dpi < {{ $config['thresholds']['sharp'] }} && sel.dpi >= {{ $config['thresholds']['min'] }}">{{ __('printable, softer detail') }}</span>
                                        <span x-show="sel.dpi < {{ $config['thresholds']['min'] }}">{{ __('too low to print') }}</span>
                                    </p>
                                    <p class="mt-0.5"><span x-text="sel.sourceSize"></span> · {{ __('sharp up to') }} <span x-text="sel.maxSharpMm"></span> {{ __('mm wide') }}</p>
                                </div>
                                <p x-show="sel.dpi < {{ $config['thresholds']['sharp'] }}" class="text-[11px] text-muted">{{ __('Make the image smaller or replace it with a higher-resolution file. Enlarging a small image, even with upscaling tools, cannot restore detail that is not in the file.') }}</p>
                                <div class="grid grid-cols-2 gap-1.5">
                                    <button type="button" class="h-8 rounded-md border border-line text-xs font-medium hover:bg-sand" @click="sharpSize()">{{ __('Largest sharp size') }}</button>
                                    <button type="button" class="h-8 rounded-md border border-line text-xs font-medium hover:bg-sand" @click="fitTo('safe')">{{ __('Fit safe area') }}</button>
                                    <button type="button" class="h-8 rounded-md border border-line text-xs font-medium hover:bg-sand" @click="fitTo('area')">{{ __('Fit print area') }}</button>
                                    <button type="button" class="h-8 rounded-md border border-line text-xs font-medium hover:bg-sand" @click="fitTo('fill')">{{ __('Fill print area') }}</button>
                                </div>
                                <p class="studio-label flex items-center gap-1 pt-1"><x-ui.icon name="crop" class="size-3.5" /> {{ __('Crop (% of original)') }}</p>
                                <div class="grid grid-cols-4 gap-1.5">
                                    @foreach (['L' => __('Left'), 'T' => __('Top'), 'R' => __('Right'), 'B' => __('Bottom')] as $side => $label)
                                        <label><span class="studio-label">{{ $label }}</span><input type="number" min="0" max="95" step="1" class="studio-input px-1.5" :value="sel.crop{{ $side }}" @change="setCrop('{{ $side }}', $event.target.value)"></label>
                                    @endforeach
                                </div>
                                <div class="flex flex-wrap gap-1.5">
                                    <button type="button" class="h-8 rounded-md border border-line px-2.5 text-xs hover:bg-sand" @click="resetCrop()">{{ __('Reset crop') }}</button>
                                    <button type="button" class="h-8 rounded-md border border-line px-2.5 text-xs hover:bg-sand" @click="openLibrary(true)">{{ __('Replace…') }}</button>
                                    <button type="button" class="h-8 rounded-md border border-line px-2.5 text-xs hover:bg-sand" @click="resetImage()">{{ __('Reset image') }}</button>
                                </div>
                            </section>
                        </template>

                        {{-- Fill & outline (shapes and text) --}}
                        <template x-if="sel.kind === 'shape' || sel.kind === 'text'">
                            <section class="studio-section">
                                <h2 class="studio-heading" x-text="sel.kind === 'text' ? '{{ __('Outline') }}' : '{{ __('Fill & outline') }}'"></h2>
                                <div class="grid grid-cols-2 gap-2">
                                    <label x-show="sel.kind === 'shape' && sel.type !== 'Line' && sel.type !== 'line'"><span class="studio-label">{{ __('Fill') }}</span><input type="color" class="h-8 w-full cursor-pointer rounded-md border border-line-strong" :value="hex(sel.fill, '#f5b027')" @input="set('fill', $event.target.value)"></label>
                                    <label><span class="studio-label">{{ __('Outline colour') }}</span><input type="color" class="h-8 w-full cursor-pointer rounded-md border border-line-strong" :value="hex(sel.stroke, '#171923')" @input="set('stroke', $event.target.value)"></label>
                                    <label><span class="studio-label">{{ __('Outline (mm)') }}</span><input type="number" min="0" max="50" step="0.1" class="studio-input" :value="sel.strokeWidth" @change="setStrokeWidth($event.target.value)"></label>
                                    <label x-show="sel.type === 'Rect' || sel.type === 'rect'"><span class="studio-label">{{ __('Corner radius (mm)') }}</span><input type="number" min="0" step="0.5" class="studio-input" :value="sel.radius" @change="setRadius($event.target.value)"></label>
                                </div>
                            </section>
                        </template>

                        {{-- Shadow --}}
                        <template x-if="sel.kind !== 'multiple'">
                            <section class="studio-section border-b-0">
                                <label class="flex items-center justify-between">
                                    <span class="studio-heading">{{ __('Shadow') }}</span>
                                    <input type="checkbox" class="size-4 accent-brand-700" :checked="sel.shadow" @change="toggleShadow($event.target.checked)">
                                </label>
                                <div x-show="sel.shadow" class="grid grid-cols-2 gap-2">
                                    <label><span class="studio-label">{{ __('Colour') }}</span><input type="color" class="h-8 w-full cursor-pointer rounded-md border border-line-strong" :value="hex(sel.shadowColor, '#000000')" @input="setShadow('color', $event.target.value)"></label>
                                    <label><span class="studio-label">{{ __('Blur (mm)') }}</span><input type="number" min="0" step="0.5" class="studio-input" :value="sel.shadowBlur" @change="setShadow('blur', $event.target.value)"></label>
                                    <label><span class="studio-label">{{ __('Offset X (mm)') }}</span><input type="number" step="0.5" class="studio-input" :value="sel.shadowX" @change="setShadow('offsetX', $event.target.value)"></label>
                                    <label><span class="studio-label">{{ __('Offset Y (mm)') }}</span><input type="number" step="0.5" class="studio-input" :value="sel.shadowY" @change="setShadow('offsetY', $event.target.value)"></label>
                                </div>
                            </section>
                        </template>
                    </div>
                </template>
            </aside>
        </div>

        {{-- Shortcuts --}}
        <div x-show="showShortcuts" x-cloak class="fixed inset-0 z-50 grid place-items-center bg-ink/40 p-4" @click.self="showShortcuts = false" @keydown.escape.window="showShortcuts = false" role="dialog" aria-modal="true" aria-labelledby="shortcuts-title">
            <div class="max-h-[85vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
                <div class="mb-4 flex items-center justify-between">
                    <h2 id="shortcuts-title" class="font-display text-lg font-semibold">{{ __('Keyboard shortcuts') }}</h2>
                    <button type="button" class="studio-btn" @click="showShortcuts = false" aria-label="{{ __('Close') }}"><x-ui.icon name="x" class="size-4" /></button>
                </div>
                <dl class="divide-y divide-line text-sm">
                    @foreach ($shortcuts as [$label, $keys])
                        <div class="flex justify-between gap-4 py-2"><dt class="text-muted">{{ $label }}</dt><dd class="text-right font-mono text-xs">{{ $keys }}</dd></div>
                    @endforeach
                </dl>
                <p class="mt-4 text-xs text-subtle">{{ __('Shortcuts are paused while you type in a field or edit text on the canvas.') }}</p>
            </div>
        </div>
    </div>
</x-layouts.base>
