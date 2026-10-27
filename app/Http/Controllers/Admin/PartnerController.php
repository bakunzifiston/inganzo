<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\PartnerMember;
use App\Models\PartnerServiceArea;
use App\Models\PrintingPartner;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Rules\ValidPhone;
use App\Services\AuditLogger;
use App\Support\Money;
use App\Support\PhoneNumber;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PartnerController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(): View
    {
        return view('admin.partners.index', [
            'partners' => PrintingPartner::query()->with('country')
                ->withCount(['services', 'members', 'productionJobs as open_jobs_count' => fn ($q) => $q->active(), 'productionJobs as delayed_jobs_count' => fn ($q) => $q->delayed()])
                ->orderBy('priority')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.partners.form', ['partner' => new PrintingPartner(['is_active' => true, 'daily_capacity' => 50, 'priority' => 100]), 'countries' => $this->countries()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $partner = PrintingPartner::query()->create($this->validated($request));
        $this->audit->log('partner.created', $partner);

        return redirect()->route('admin.partners.show', $partner)->with('success', __('Partner created. Add the products they print and the areas they serve.'));
    }

    public function show(PrintingPartner $partner): View
    {
        $partner->load(['services.product', 'serviceAreas.country', 'serviceAreas.deliveryZone', 'members.user', 'country']);

        return view('admin.partners.show', [
            'partner' => $partner,
            'products' => Product::query()->orderBy('position')->get(),
            'countries' => Country::query()->with('deliveryZones')->orderBy('name')->get(),
            'jobs' => $partner->productionJobs()->with('orderItem.order')->latest('id')->limit(15)->get(),
            'stats' => [
                'open' => $partner->productionJobs()->active()->count(),
                'delayed' => $partner->productionJobs()->delayed()->count(),
                'dispatched_30d' => $partner->productionJobs()->where('dispatched_at', '>=', now()->subDays(30))->count(),
                'failed_30d' => $partner->productionJobs()->where('failed_at', '>=', now()->subDays(30))->count(),
            ],
        ]);
    }

    public function edit(PrintingPartner $partner): View
    {
        return view('admin.partners.form', ['partner' => $partner, 'countries' => $this->countries()]);
    }

    public function update(Request $request, PrintingPartner $partner): RedirectResponse
    {
        $partner->fill($this->validated($request, $partner));
        $this->audit->logChanges('partner.updated', $partner);
        $partner->save();

        return redirect()->route('admin.partners.show', $partner)->with('success', __('Partner saved.'));
    }

    public function updateServices(Request $request, PrintingPartner $partner): RedirectResponse
    {
        $validated = $request->validate([
            'services' => ['array'],
            'services.*.enabled' => ['sometimes', 'boolean'],
            'services.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'services.*.production_days' => ['nullable', 'integer', 'min:1', 'max:60'],
        ]);

        foreach (Product::query()->get() as $product) {
            $input = $validated['services'][$product->id] ?? [];
            $service = $partner->services()->firstWhere('product_id', $product->id);
            $enabled = (bool) ($input['enabled'] ?? false);

            if (! $service && ! $enabled) {
                continue;
            }

            if (! $service && ! filled($input['unit_cost'] ?? null)) {
                return back()->withErrors(['services' => __('Enter a unit cost for :product.', ['product' => $product->translate('name')])]);
            }

            $service ??= $partner->services()->make(['product_id' => $product->id]);
            $service->fill([
                'is_active' => $enabled,
                'unit_cost_amount' => filled($input['unit_cost'] ?? null) ? Money::toMinor($input['unit_cost'], $product->currency) : $service->unit_cost_amount,
                'production_days' => $input['production_days'] ?? $service->production_days ?? $product->production_days,
            ]);
            if ($service->isDirty()) {
                $this->audit->log('partner.service_updated', $partner, ['product' => $product->slug, 'changes' => $service->getDirty()]);
                $service->save();
            }
        }

        return back()->with('success', __('Products and costs saved.'));
    }

    public function storeArea(Request $request, PrintingPartner $partner): RedirectResponse
    {
        $validated = $request->validate([
            'country_id' => ['required', 'exists:countries,id'],
            'delivery_zone_id' => ['nullable', Rule::exists('delivery_zones', 'id')->where('country_id', $request->integer('country_id'))],
        ]);

        $partner->serviceAreas()->firstOrCreate(['country_id' => $validated['country_id'], 'delivery_zone_id' => $validated['delivery_zone_id'] ?? null]);
        $this->audit->log('partner.area_added', $partner, $validated);

        return back()->with('success', __('Service area added.'));
    }

    public function destroyArea(PrintingPartner $partner, PartnerServiceArea $area): RedirectResponse
    {
        abort_unless($area->printing_partner_id === $partner->id, 404);
        $area->delete();
        $this->audit->log('partner.area_removed', $partner, ['area_id' => $area->id]);

        return back()->with('success', __('Service area removed.'));
    }

    public function storeMember(Request $request, PrintingPartner $partner): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', Rule::exists('users', 'email')],
            'role' => ['required', Rule::in(['manager', 'operator'])],
        ], ['email.exists' => __('No account uses that e-mail. Ask the staff member to register first.')]);

        $user = User::query()->where('email', mb_strtolower($validated['email']))->firstOrFail();
        $existing = PartnerMember::query()->where('user_id', $user->id)->first();

        if ($existing && $existing->printing_partner_id !== $partner->id) {
            return back()->withErrors(['email' => __('This user already belongs to another printing partner.')]);
        }

        PartnerMember::query()->updateOrCreate(['user_id' => $user->id], ['printing_partner_id' => $partner->id, 'role' => $validated['role']]);
        $user->assignRole(RoleName::PartnerStaff, $request->user());
        $this->audit->log('partner.member_added', $partner, ['user_id' => $user->id, 'role' => $validated['role']]);

        return back()->with('success', __(':name can now use the production portal.', ['name' => $user->name]));
    }

    public function destroyMember(Request $request, PrintingPartner $partner, PartnerMember $member): RedirectResponse
    {
        abort_unless($member->printing_partner_id === $partner->id, 404);
        $user = $member->user;
        $member->delete();
        $role = Role::query()->where('slug', RoleName::PartnerStaff->value)->first();
        if ($role) {
            $user->roles()->detach($role->id);
        }
        $this->audit->log('partner.member_removed', $partner, ['user_id' => $user->id]);

        return back()->with('success', __('Access removed.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?PrintingPartner $partner = null): array
    {
        $country = Country::query()->find($request->integer('country_id'));
        $v = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'legal_name' => ['nullable', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', new ValidPhone($country)],
            'country_id' => ['required', 'exists:countries,id'],
            'city' => ['required', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:255'],
            'daily_capacity' => ['required', 'integer', 'min:1', 'max:10000'],
            'priority' => ['required', 'integer', 'min:1', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return [...$v, 'phone' => PhoneNumber::normalize($v['phone'], $country), 'slug' => $partner?->slug ?? $this->slug($v['name']), 'is_active' => $request->boolean('is_active')];
    }

    private function slug(string $name): string
    {
        $base = Str::slug($name) ?: 'partner';
        $slug = $base;
        for ($i = 2; PrintingPartner::query()->where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }

    /**
     * @return Collection<int, string>
     */
    private function countries()
    {
        return Country::query()->orderBy('name')->pluck('name', 'id');
    }
}
