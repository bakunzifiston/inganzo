<?php

namespace App\Livewire;

use App\Models\Category;
use App\Models\ProductColor;
use App\Models\ProductSize;
use App\Models\ProductType;
use App\Services\Catalog\CatalogSearch;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Live-filtering product grid. Filters are mirrored in the URL so results are shareable
 * and work without JavaScript through the same query parameters.
 */
class CatalogBrowser extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $q = '';

    #[Url(except: '')]
    public string $type = '';

    #[Url(except: '')]
    public string $category = '';

    #[Url(except: '')]
    public string $color = '';

    #[Url(except: '')]
    public string $size = '';

    #[Url(except: '')]
    public string $min = '';

    #[Url(except: '')]
    public string $max = '';

    #[Url(except: 'trending')]
    public string $sort = 'trending';

    public ?int $collection = null;

    public bool $lockCategory = false;

    public function mount(?string $presetCategory = null, ?int $presetCollection = null): void
    {
        if ($presetCategory) {
            $this->category = $presetCategory;
            $this->lockCategory = true;
        }
        $this->collection = $presetCollection;
        $this->sort = in_array($this->sort, CatalogSearch::SORTS, true) ? $this->sort : 'trending';
    }

    public function updated(string $property): void
    {
        if ($property !== 'page') {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['q', 'type', 'color', 'size', 'min', 'max', 'sort']);
        if (! $this->lockCategory) {
            $this->category = '';
        }
        $this->resetPage();
    }

    /**
     * One labelled swatch per colour name; "Glossy white" and "White" are shown once as White.
     *
     * @return Collection<int, array{name: string, hex: string}>
     */
    private function colorOptions(): Collection
    {
        return ProductColor::query()->where('is_active', true)->orderBy('position')->get(['name', 'hex'])
            ->reject(fn (ProductColor $color) => $color->name === 'Glossy white')
            ->unique('name')
            ->map(fn (ProductColor $color) => ['name' => $color->name, 'hex' => $color->hex])
            ->values();
    }

    /**
     * Sizes grouped so apparel sizes (S–XXL) and sticker sizes (cm) are not mixed together.
     *
     * @return array<string, list<string>>
     */
    private function sizeGroups(): array
    {
        $apparelOrder = ['XS', 'S', 'M', 'L', 'XL', 'XXL', '3XL'];
        $labels = ProductSize::query()->where('is_active', true)->pluck('label')->unique();

        $apparel = $labels->filter(fn ($label) => in_array($label, $apparelOrder, true))
            ->sortBy(fn ($label) => array_search($label, $apparelOrder, true))->values()->all();
        $other = $labels->reject(fn ($label) => in_array($label, $apparelOrder, true))
            ->sortBy(fn ($label) => (float) $label)->values()->all();

        return array_filter([__('Clothing') => $apparel, __('Stickers') => $other]);
    }

    public function render(CatalogSearch $search): View
    {
        $results = $search->paginateMixed([
            'q' => $this->q,
            'type' => $this->type,
            'category' => $this->category,
            'color' => $this->color,
            'size' => $this->size,
            'min' => $this->min,
            'max' => $this->max,
            'sort' => in_array($this->sort, CatalogSearch::SORTS, true) ? $this->sort : 'trending',
            'collection' => $this->collection,
        ]);

        return view('livewire.catalog-browser', [
            'results' => $results,
            'types' => ProductType::query()->active()->orderBy('position')->get(),
            'categories' => Category::query()->active()->whereNull('parent_id')->orderBy('position')->get(),
            'colorOptions' => $this->colorOptions(),
            'sizeGroups' => $this->sizeGroups(),
            'hasFilters' => $this->q || $this->type || (! $this->lockCategory && $this->category) || $this->color || $this->size || $this->min || $this->max,
        ]);
    }
}
