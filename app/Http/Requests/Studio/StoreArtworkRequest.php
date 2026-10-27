<?php

namespace App\Http\Requests\Studio;

use App\Models\Artwork;
use Illuminate\Foundation\Http\FormRequest;

class StoreArtworkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Artwork::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:2', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'tags' => ['nullable', 'string', 'max:300'],
            'is_mature' => ['sometimes', 'boolean'],
            // Extension and MIME are re-verified from file contents in ArtworkUploader::inspect().
            'file' => ['required', 'file', 'mimetypes:image/png,image/jpeg,image/webp', 'max:'.config('marketplace.artwork.max_upload_kb')],
            'rights_confirmed' => ['accepted'],
        ];
    }

    /**
     * @return list<string>
     */
    public function tagList(): array
    {
        return array_slice(array_values(array_filter(array_map('trim', explode(',', (string) $this->input('tags'))))), 0, 15);
    }
}
