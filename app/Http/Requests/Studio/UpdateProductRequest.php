<?php

namespace App\Http\Requests\Studio;

use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('artwork'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'is_enabled' => ['required', 'boolean'],
            'colors' => ['array'],
            'colors.*' => ['integer'],
            'default_color_id' => ['nullable', 'integer'],
            'markup_type' => ['required', Rule::in(['percent', 'fixed'])],
            'markup_percent' => ['required_if:markup_type,percent', 'nullable', 'numeric', 'min:0', 'max:100'],
            'markup_amount' => ['required_if:markup_type,fixed', 'nullable', 'numeric', 'min:0'],
            'placements' => ['required', 'json'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function configuration(string $currency): array
    {
        $placements = [];
        foreach ((array) json_decode($this->input('placements'), true) as $areaId => $placement) {
            if (! is_array($placement) || empty($placement['enabled'])) {
                continue;
            }
            $placements[(int) $areaId] = [
                'offset_x' => (float) ($placement['offset_x'] ?? 0),
                'offset_y' => (float) ($placement['offset_y'] ?? 0),
                'scale' => (float) ($placement['scale'] ?? 0.5),
                'rotation' => (int) ($placement['rotation'] ?? 0),
            ];
        }

        $type = $this->input('markup_type');

        return [
            'is_enabled' => $this->boolean('is_enabled'),
            'colors' => array_map('intval', $this->input('colors', [])),
            'default_color_id' => $this->integer('default_color_id') ?: null,
            'markup_type' => $type,
            'markup_bps' => $type === 'percent' ? (int) round(((float) $this->input('markup_percent')) * 100) : null,
            'markup_amount' => $type === 'fixed' ? Money::toMinor((string) $this->input('markup_amount'), $currency) : null,
            'placements' => $placements,
        ];
    }
}
