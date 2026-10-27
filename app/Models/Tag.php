<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

#[Fillable(['name', 'slug'])]
class Tag extends Model
{
    /**
     * @return BelongsToMany<Artwork, $this>
     */
    public function artworks(): BelongsToMany
    {
        return $this->belongsToMany(Artwork::class);
    }

    /**
     * @param  list<string>  $names
     * @return list<int>
     */
    public static function idsFor(array $names): array
    {
        $ids = [];

        foreach (array_unique(array_filter(array_map(fn ($n) => trim(mb_strtolower($n)), $names))) as $name) {
            $slug = Str::slug($name);
            if ($slug === '') {
                continue;
            }
            $ids[] = static::query()->firstOrCreate(['slug' => $slug], ['name' => mb_substr($name, 0, 50)])->id;
        }

        return $ids;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
