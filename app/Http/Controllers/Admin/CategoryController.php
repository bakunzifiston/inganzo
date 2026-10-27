<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index(): View
    {
        return view('admin.categories.index', ['categories' => Category::query()->with('parent')->withCount('artworks')->orderBy('parent_id')->orderBy('position')->get()]);
    }

    public function create(): View
    {
        return view('admin.categories.form', ['category' => new Category(['is_active' => true]), 'parents' => $this->parents()]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $category = Category::query()->create($this->validated($request));
        $audit->log('category.created', $category);

        return redirect()->route('admin.categories.index')->with('success', __('Category created.'));
    }

    public function edit(Category $category): View
    {
        return view('admin.categories.form', ['category' => $category, 'parents' => $this->parents($category)]);
    }

    public function update(Request $request, Category $category, AuditLogger $audit): RedirectResponse
    {
        $category->fill($this->validated($request, $category));
        $audit->logChanges('category.updated', $category);
        $category->save();

        return redirect()->route('admin.categories.index')->with('success', __('Category saved.'));
    }

    private function parents(?Category $except = null)
    {
        return Category::query()->whereNull('parent_id')->when($except, fn ($q) => $q->whereKeyNot($except->id))->orderBy('position')->get()->mapWithKeys(fn ($c) => [$c->id => $c->translate('name')]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Category $category = null): array
    {
        $v = $request->validate([
            'slug' => ['required', 'alpha_dash', 'max:120', Rule::unique('categories', 'slug')->ignore($category?->id)],
            'parent_id' => ['nullable', 'exists:categories,id', Rule::notIn([$category?->id])],
            'name_en' => ['required', 'string', 'max:80'],
            'name_rw' => ['nullable', 'string', 'max:80'],
            'description_en' => ['nullable', 'string', 'max:500'],
            'description_rw' => ['nullable', 'string', 'max:500'],
            'position' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
        ]);

        return [
            'slug' => strtolower($v['slug']),
            'parent_id' => $v['parent_id'] ?? null,
            'name' => array_filter(['en' => $v['name_en'], 'rw' => $v['name_rw'] ?? null]),
            'description' => array_filter(['en' => $v['description_en'] ?? null, 'rw' => $v['description_rw'] ?? null]) ?: null,
            'position' => $v['position'] ?? 0,
            'is_active' => $request->boolean('is_active'),
            'is_featured' => $request->boolean('is_featured'),
        ];
    }
}
