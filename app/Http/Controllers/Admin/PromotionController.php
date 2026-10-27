<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use App\Services\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PromotionController extends Controller
{
    public function index(): View
    {
        return view('admin.promotions.index', ['promotions' => Promotion::query()->orderBy('placement')->orderBy('position')->get()]);
    }

    public function create(): View
    {
        return view('admin.promotions.form', ['promotion' => new Promotion(['placement' => 'home_banner', 'theme' => 'ink', 'is_active' => true])]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $promotion = Promotion::query()->create($this->validated($request));
        $audit->log('promotion.created', $promotion);

        return redirect()->route('admin.promotions.index')->with('success', __('Promotion created.'));
    }

    public function edit(Promotion $promotion): View
    {
        return view('admin.promotions.form', ['promotion' => $promotion]);
    }

    public function update(Request $request, Promotion $promotion, AuditLogger $audit): RedirectResponse
    {
        $promotion->fill($this->validated($request));
        $audit->logChanges('promotion.updated', $promotion);
        $promotion->save();

        return redirect()->route('admin.promotions.index')->with('success', __('Promotion saved.'));
    }

    public function destroy(Promotion $promotion, AuditLogger $audit): RedirectResponse
    {
        $audit->log('promotion.deleted', $promotion, ['title' => $promotion->title]);
        $promotion->delete();

        return redirect()->route('admin.promotions.index')->with('success', __('Promotion deleted.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $v = $request->validate([
            'placement' => ['required', Rule::in(['home_banner', 'home_hero'])],
            'title_en' => ['required', 'string', 'max:160'],
            'title_rw' => ['nullable', 'string', 'max:160'],
            'subtitle_en' => ['nullable', 'string', 'max:255'],
            'subtitle_rw' => ['nullable', 'string', 'max:255'],
            'cta_label_en' => ['nullable', 'string', 'max:60'],
            'cta_url' => ['nullable', 'string', 'max:255', 'regex:/^(\/|https:\/\/)/'],
            'theme' => ['required', Rule::in(['ink', 'clay', 'leaf'])],
            'image' => ['nullable', 'image', 'max:4096'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'position' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return array_filter([
            'placement' => $v['placement'],
            'title' => array_filter(['en' => $v['title_en'], 'rw' => $v['title_rw'] ?? null]),
            'subtitle' => array_filter(['en' => $v['subtitle_en'] ?? null, 'rw' => $v['subtitle_rw'] ?? null]) ?: null,
            'cta_label' => filled($v['cta_label_en'] ?? null) ? ['en' => $v['cta_label_en']] : null,
            'cta_url' => $v['cta_url'] ?? null,
            'theme' => $v['theme'],
            'image_path' => $request->file('image')?->store('promotions', 'public'),
            'starts_at' => $v['starts_at'] ?? null,
            'ends_at' => $v['ends_at'] ?? null,
            'position' => $v['position'] ?? 0,
            'is_active' => $request->boolean('is_active'),
        ], fn ($value, $key) => $key !== 'image_path' || $value !== null, ARRAY_FILTER_USE_BOTH);
    }
}
