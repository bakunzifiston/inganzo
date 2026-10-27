<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\CustomerAddress;
use App\Rules\ValidPhone;
use App\Support\PhoneNumber;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AddressController extends Controller
{
    public function index(Request $request): View
    {
        return view('account.addresses.index', [
            'addresses' => $request->user()->addresses()->with(['district', 'country'])->orderByDesc('is_default')->latest()->get(),
        ]);
    }

    public function create(): View
    {
        return view('account.addresses.form', ['address' => new CustomerAddress, 'districts' => $this->districts()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $user = $request->user();

        DB::transaction(function () use ($user, $data) {
            $isDefault = $data['is_default'] || ! $user->addresses()->exists();
            if ($isDefault) {
                $user->addresses()->update(['is_default' => false]);
            }
            $user->addresses()->create(['is_default' => $isDefault] + $data);
        });

        return redirect()->route('account.addresses.index')->with('success', __('Address saved.'));
    }

    public function edit(Request $request, CustomerAddress $address): View
    {
        $this->authorizeOwner($request, $address);

        return view('account.addresses.form', ['address' => $address, 'districts' => $this->districts()]);
    }

    public function update(Request $request, CustomerAddress $address): RedirectResponse
    {
        $this->authorizeOwner($request, $address);
        $data = $this->validated($request);

        DB::transaction(function () use ($request, $address, $data) {
            if ($data['is_default']) {
                $request->user()->addresses()->whereKeyNot($address->id)->update(['is_default' => false]);
            } elseif ($address->is_default) {
                $data['is_default'] = true; // keep one default; choose another address to change it
            }
            $address->update($data);
        });

        return redirect()->route('account.addresses.index')->with('success', __('Address updated.'));
    }

    public function destroy(Request $request, CustomerAddress $address): RedirectResponse
    {
        $this->authorizeOwner($request, $address);

        DB::transaction(function () use ($request, $address) {
            $wasDefault = $address->is_default;
            $address->delete();
            if ($wasDefault) {
                $request->user()->addresses()->latest()->first()?->update(['is_default' => true]);
            }
        });

        return redirect()->route('account.addresses.index')->with('success', __('Address removed.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $country = $this->country();

        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:40'],
            'recipient_name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', new ValidPhone($country)],
            'district_id' => ['required', Rule::exists('districts', 'id')->where('country_id', $country->id)],
            'sector' => ['nullable', 'string', 'max:80'],
            'street_address' => ['required', 'string', 'max:255'],
            'landmark' => ['nullable', 'string', 'max:255'],
        ]);

        return array_merge($data, [
            'phone' => PhoneNumber::normalize($data['phone'], $country),
            'country_id' => $country->id,
            'is_default' => $request->boolean('is_default'),
        ]);
    }

    private function country(): Country
    {
        return Country::query()->where('iso2', 'RW')->firstOrFail();
    }

    private function districts()
    {
        return $this->country()->districts()->get()->groupBy('province');
    }

    private function authorizeOwner(Request $request, CustomerAddress $address): void
    {
        abort_unless($address->user_id === $request->user()->id, 404);
    }
}
