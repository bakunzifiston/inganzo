<?php

namespace App\Services\Catalog;

use App\Models\ArtistProduct;
use App\Models\FinishedProduct;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Storefront and API product search over listed artist products.
 *
 * Keyword search uses the PostgreSQL full-text index on artworks (title + description),
 * plus case-insensitive matches on tags and artist names.
 */
class CatalogSearch
{
    public const SORTS = ['trending', 'newest', 'price_asc', 'price_desc'];

    public const PER_PAGE = 24;

    /**
     * Pseudo product type that limits a search to ready-made products.
     */
    public const READY_MADE = 'ready-made';

    /**
     * @param  array{q?: string|null, type?: string|null, category?: string|null, color?: string|null, size?: string|null, min?: int|string|null, max?: int|string|null, sort?: string|null, artist?: int|null, collection?: int|null}  $filters
     * @return Builder<ArtistProduct>
     */
    public function query(array $filters): Builder
    {
        $query = ArtistProduct::query()
            ->listed()
            ->select('artist_products.*')
            ->join('artworks', 'artworks.id', '=', 'artist_products.artwork_id')
            ->with(self::eagerLoads());

        if (filled($filters['q'] ?? null)) {
            $term = mb_substr(trim((string) $filters['q']), 0, 100);
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

            $query->where(function (Builder $q) use ($term, $like) {
                $q->whereRaw("artworks.search_vector @@ plainto_tsquery('simple', ?)", [$term])
                    ->orWhere('artworks.title', 'ilike', $like)
                    ->orWhereExists(fn ($sub) => $sub->selectRaw('1')->from('artwork_tag')
                        ->join('tags', 'tags.id', '=', 'artwork_tag.tag_id')
                        ->whereColumn('artwork_tag.artwork_id', 'artworks.id')
                        ->where('tags.name', 'ilike', $like))
                    ->orWhereExists(fn ($sub) => $sub->selectRaw('1')->from('artist_profiles')
                        ->whereColumn('artist_profiles.id', 'artworks.artist_profile_id')
                        ->where('artist_profiles.display_name', 'ilike', $like));
            });
        }

        if (filled($filters['type'] ?? null) && $filters['type'] !== self::READY_MADE) {
            $query->whereHas('product.type', fn (Builder $q) => $q->where('slug', $filters['type']));
        }

        if (filled($filters['category'] ?? null)) {
            $query->whereExists(fn ($sub) => $sub->selectRaw('1')->from('categories')
                ->whereColumn('categories.id', 'artworks.category_id')
                ->where(fn ($c) => $c->where('categories.slug', $filters['category'])
                    ->orWhereIn('categories.parent_id', fn ($p) => $p->select('id')->from('categories')->where('slug', $filters['category']))));
        }

        if (filled($filters['color'] ?? null)) {
            $query->whereHas('colors', fn (Builder $q) => $q->where('product_colors.name', 'ilike', '%'.$filters['color'].'%'));
        }

        if (filled($filters['size'] ?? null)) {
            $query->whereHas('product.sizes', fn (Builder $q) => $q->where('label', $filters['size'])->where('is_active', true));
        }

        if (is_numeric($filters['min'] ?? null)) {
            $query->where('artist_products.price_from_amount', '>=', (int) $filters['min']);
        }

        if (is_numeric($filters['max'] ?? null)) {
            $query->where('artist_products.price_from_amount', '<=', (int) $filters['max']);
        }

        if (! empty($filters['artist'])) {
            $query->where('artworks.artist_profile_id', $filters['artist']);
        }

        if (! empty($filters['collection'])) {
            $query->whereIn('artworks.id', fn ($sub) => $sub->select('artwork_id')->from('collection_items')->where('collection_id', $filters['collection']));
        }

        match ($filters['sort'] ?? 'trending') {
            'newest' => $query->orderByDesc('artworks.published_at'),
            'price_asc' => $query->orderBy('artist_products.price_from_amount'),
            'price_desc' => $query->orderByDesc('artist_products.price_from_amount'),
            default => $query->orderByDesc('artist_products.sales_count')->orderByDesc('artworks.sales_count')->orderByDesc('artworks.published_at'),
        };

        return $query->orderByDesc('artist_products.id');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, ArtistProduct>
     */
    public function paginate(array $filters, int $perPage = self::PER_PAGE): LengthAwarePaginator
    {
        return $this->query($filters)->paginate($perPage)->withQueryString();
    }

    /**
     * Printed designs and ready-made products in one ranked, paginated list. Both kinds are
     * reduced to (kind, id, popularity, published_at, price), combined with UNION ALL, sorted and
     * paginated by the database, then hydrated into models. Filters that only make sense for
     * one kind (product type, collection) exclude the other kind.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, ArtistProduct|FinishedProduct>
     */
    public function paginateMixed(array $filters, int $perPage = self::PER_PAGE): LengthAwarePaginator
    {
        $type = (string) ($filters['type'] ?? '');
        $onlyFinished = $type === self::READY_MADE;
        $onlyDesigns = ($type !== '' && ! $onlyFinished) || ! empty($filters['collection']);

        $designs = $this->query($filters)->reorder()->toBase()
            ->select([])
            ->selectRaw("'design' AS kind, artist_products.id AS id, artist_products.sales_count AS popularity, artworks.published_at AS published_at, artist_products.price_from_amount AS price");
        $finished = $this->finishedQuery($filters)->toBase()
            ->select([])
            ->selectRaw("'finished' AS kind, finished_products.id AS id, finished_products.sales_count AS popularity, finished_products.published_at AS published_at, finished_products.price_from_amount AS price");

        $combined = match (true) {
            $onlyFinished => $finished,
            $onlyDesigns => $designs,
            default => $designs->unionAll($finished),
        };

        $sorted = DB::query()->fromSub($combined, 'c');
        match ($filters['sort'] ?? 'trending') {
            'newest' => $sorted->orderByRaw('published_at DESC NULLS LAST'),
            'price_asc' => $sorted->orderByRaw('price ASC NULLS LAST'),
            'price_desc' => $sorted->orderByRaw('price DESC NULLS LAST'),
            default => $sorted->orderByDesc('popularity')->orderByRaw('published_at DESC NULLS LAST'),
        };
        $page = $sorted->orderBy('kind')->orderByDesc('id')->paginate($perPage)->withQueryString();

        $artistProducts = ArtistProduct::query()->whereIn('id', $page->where('kind', 'design')->pluck('id'))->with(self::eagerLoads())->get()->keyBy('id');
        $finishedProducts = FinishedProduct::query()->whereIn('id', $page->where('kind', 'finished')->pluck('id'))->with(['artist', 'images', 'variants'])->get()->keyBy('id');

        $page->setCollection($page->getCollection()
            ->map(fn ($row) => $row->kind === 'finished' ? $finishedProducts->get($row->id) : $artistProducts->get($row->id))
            ->filter()->values());

        return $page;
    }

    /**
     * Listed ready-made products matching the shared catalogue filters.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<FinishedProduct>
     */
    public function finishedQuery(array $filters): Builder
    {
        $query = FinishedProduct::query()->listed()->select('finished_products.*');

        if (filled($filters['q'] ?? null)) {
            $term = mb_substr(trim((string) $filters['q']), 0, 100);
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

            $query->where(fn (Builder $q) => $q->where('finished_products.title', 'ilike', $like)
                ->orWhere('finished_products.description', 'ilike', $like)
                ->orWhereExists(fn ($sub) => $sub->selectRaw('1')->from('artist_profiles')
                    ->whereColumn('artist_profiles.id', 'finished_products.artist_profile_id')
                    ->where('artist_profiles.display_name', 'ilike', $like)));
        }

        if (filled($filters['category'] ?? null)) {
            $query->whereExists(fn ($sub) => $sub->selectRaw('1')->from('categories')
                ->whereColumn('categories.id', 'finished_products.category_id')
                ->where(fn ($c) => $c->where('categories.slug', $filters['category'])
                    ->orWhereIn('categories.parent_id', fn ($p) => $p->select('id')->from('categories')->where('slug', $filters['category']))));
        }

        if (filled($filters['color'] ?? null)) {
            $query->whereHas('variants', fn (Builder $q) => $q->where('is_active', true)->where('color_name', 'ilike', '%'.$filters['color'].'%'));
        }

        if (filled($filters['size'] ?? null)) {
            $query->whereHas('variants', fn (Builder $q) => $q->where('is_active', true)->where('size_label', $filters['size']));
        }

        if (is_numeric($filters['min'] ?? null)) {
            $query->where('finished_products.price_from_amount', '>=', (int) $filters['min']);
        }
        if (is_numeric($filters['max'] ?? null)) {
            $query->where('finished_products.price_from_amount', '<=', (int) $filters['max']);
        }
        if (! empty($filters['artist'])) {
            $query->where('finished_products.artist_profile_id', $filters['artist']);
        }

        return $query;
    }

    /**
     * @return list<string>
     */
    public static function eagerLoads(): array
    {
        return [
            'artwork.artist',
            'artwork.currentVersion',
            'product.printAreas',
            'product.mockups',
            'colors',
            'defaultColor',
            'placements',
        ];
    }
}
