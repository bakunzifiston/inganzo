<?php

namespace App\Http\Requests\Studio;

use App\Models\FinishedProduct;
use App\Models\FinishedProductVariant;
use App\Services\Inventory\FinishedProductService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a ready-made product form (create and update). Authorisation is done by the controller
 * through FinishedProductPolicy; this class only checks the data, including the rules that span
 * several fields (sale price below price, no duplicate options, unique SKUs).
 */
class StoreFinishedProductRequest extends FormRequest
{
    public const MAX_PRICE = 100_000_000;

    public function authorize(): bool
    {
        return $this->user()?->isArtist() === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $price = ['integer', 'min:1', 'max:'.self::MAX_PRICE];

        return [
            'title' => ['required', 'string', 'min:2', 'max:120'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category_id' => ['nullable', Rule::exists('categories', 'id')],
            'price_amount' => ['required', ...$price],
            'sale_price_amount' => ['nullable', ...$price],
            'sku' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/'],
            'is_handmade' => ['sometimes', 'boolean'],
            'weight_grams' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'processing_days' => ['required', 'integer', 'min:0', 'max:60'],
            'ships_from' => ['nullable', 'string', 'max:80'],
            'shipping_note' => ['nullable', 'string', 'max:255'],
            'low_stock_threshold' => ['required', 'integer', 'min:0', 'max:1000'],
            'variants' => ['required', 'array', 'min:1', 'max:60'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.color_name' => ['nullable', 'string', 'max:50'],
            'variants.*.color_hex' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'variants.*.size_label' => ['nullable', 'string', 'max:20'],
            'variants.*.sku' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/'],
            'variants.*.price_amount' => ['nullable', ...$price],
            'variants.*.sale_price_amount' => ['nullable', ...$price],
            'variants.*.stock_quantity' => ['required', 'integer', 'min:0', 'max:100000'],
            'variants.*.is_active' => ['sometimes', 'boolean'],
            'images' => ['sometimes', 'array', 'max:'.FinishedProductService::MAX_IMAGES],
            'images.*' => ['file', 'mimetypes:image/png,image/jpeg,image/webp', 'max:10240'],
            'remove_images' => ['sometimes', 'array'],
            'remove_images.*' => ['integer'],
            'cover_image_id' => ['nullable', 'integer'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $productPrice = (int) $this->input('price_amount');
            $productSale = $this->filled('sale_price_amount') ? (int) $this->input('sale_price_amount') : null;
            if ($productSale !== null && $productSale >= $productPrice) {
                $validator->errors()->add('sale_price_amount', __('The sale price must be lower than the regular price.'));
            }

            /** @var FinishedProduct|null $product */
            $product = $this->route('finished_product');
            $ownIds = $product ? $product->variants()->pluck('id')->all() : [];
            $seenCombos = [];
            $seenSkus = [];
            $anyActive = false;

            foreach ($this->input('variants', []) as $index => $row) {
                $n = $index + 1;
                $regular = filled($row['price_amount'] ?? null) ? (int) $row['price_amount'] : $productPrice;
                $sale = filled($row['sale_price_amount'] ?? null) ? (int) $row['sale_price_amount'] : (filled($row['price_amount'] ?? null) ? null : $productSale);
                if ($sale !== null && $sale >= $regular) {
                    $validator->errors()->add("variants.{$index}.sale_price_amount", __('Option :n: the sale price must be lower than its regular price.', ['n' => $n]));
                }

                $combo = mb_strtolower(trim((string) ($row['color_name'] ?? ''))).'|'.mb_strtolower(trim((string) ($row['size_label'] ?? '')));
                if (isset($seenCombos[$combo])) {
                    $validator->errors()->add("variants.{$index}.size_label", __('Option :n repeats the same colour and size as another option.', ['n' => $n]));
                }
                $seenCombos[$combo] = true;

                if (filled($row['sku'] ?? null)) {
                    $sku = mb_strtoupper(trim($row['sku']));
                    $taken = isset($seenSkus[$sku]) || FinishedProductVariant::query()->where('sku', $sku)->whereNotIn('id', $ownIds)->exists();
                    if ($taken) {
                        $validator->errors()->add("variants.{$index}.sku", __('The SKU :sku is already used.', ['sku' => $sku]));
                    }
                    $seenSkus[$sku] = true;
                }

                $anyActive = $anyActive || (bool) ($row['is_active'] ?? true);
            }

            if (! $anyActive) {
                $validator->errors()->add('variants', __('Keep at least one option available.'));
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'price_amount' => __('price'),
            'sale_price_amount' => __('sale price'),
            'stock_quantity' => __('stock'),
        ];
    }
}
