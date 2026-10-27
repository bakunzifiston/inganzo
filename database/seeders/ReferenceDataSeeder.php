<?php

namespace Database\Seeders;

use App\Enums\Ability;
use App\Enums\RoleName;
use App\Models\Country;
use App\Models\Currency;
use App\Models\DeliveryZone;
use App\Models\District;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Production-safe reference data: currencies, countries, delivery geography, roles and
 * permissions. Idempotent – safe to run on every deploy.
 */
class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->currencies();
        $this->countries();
        $this->rwandaDelivery();
        $this->accessControl();
        $this->call(CatalogSeeder::class);
    }

    private function currencies(): void
    {
        foreach ([
            ['code' => 'RWF', 'name' => 'Rwandan franc', 'symbol' => 'RWF', 'minor_unit' => 0, 'symbol_first' => true],
            ['code' => 'UGX', 'name' => 'Ugandan shilling', 'symbol' => 'UGX', 'minor_unit' => 0, 'symbol_first' => true],
            ['code' => 'KES', 'name' => 'Kenyan shilling', 'symbol' => 'KSh', 'minor_unit' => 2, 'symbol_first' => true],
            ['code' => 'TZS', 'name' => 'Tanzanian shilling', 'symbol' => 'TSh', 'minor_unit' => 2, 'symbol_first' => true],
            ['code' => 'USD', 'name' => 'US dollar', 'symbol' => 'US$', 'minor_unit' => 2, 'symbol_first' => true],
        ] as $currency) {
            Currency::query()->updateOrCreate(['code' => $currency['code']], $currency + ['is_active' => $currency['code'] === 'RWF']);
        }
    }

    private function countries(): void
    {
        foreach ([
            // Rwanda: 9-digit mobile numbers starting with 7 (MTN 078/079, Airtel 072/073).
            ['iso2' => 'RW', 'name' => 'Rwanda', 'currency_code' => 'RWF', 'phone_code' => '+250', 'phone_pattern' => '7[2-9]\d{7}', 'default_locale' => 'rw', 'tax_rate_bps' => 1800, 'is_active' => true],
            ['iso2' => 'UG', 'name' => 'Uganda', 'currency_code' => 'UGX', 'phone_code' => '+256', 'phone_pattern' => '7\d{8}', 'default_locale' => 'en', 'tax_rate_bps' => 1800, 'is_active' => false],
            ['iso2' => 'KE', 'name' => 'Kenya', 'currency_code' => 'KES', 'phone_code' => '+254', 'phone_pattern' => '(7|1)\d{8}', 'default_locale' => 'en', 'tax_rate_bps' => 1600, 'is_active' => false],
            ['iso2' => 'TZ', 'name' => 'Tanzania', 'currency_code' => 'TZS', 'phone_code' => '+255', 'phone_pattern' => '(6|7)\d{8}', 'default_locale' => 'en', 'tax_rate_bps' => 1800, 'is_active' => false],
        ] as $country) {
            Country::query()->updateOrCreate(['iso2' => $country['iso2']], $country);
        }
    }

    private function rwandaDelivery(): void
    {
        $rwanda = Country::query()->where('iso2', 'RW')->firstOrFail();

        $kigali = DeliveryZone::query()->updateOrCreate(
            ['country_id' => $rwanda->id, 'name' => 'Kigali City'],
            ['fee_amount' => 2000, 'free_over_amount' => 60000, 'min_days' => 1, 'max_days' => 2, 'is_active' => true, 'position' => 1],
        );
        $provinces = DeliveryZone::query()->updateOrCreate(
            ['country_id' => $rwanda->id, 'name' => 'Provinces'],
            ['fee_amount' => 3500, 'free_over_amount' => 80000, 'min_days' => 2, 'max_days' => 4, 'is_active' => true, 'position' => 2],
        );

        $districts = [
            'Kigali City' => ['Gasabo', 'Kicukiro', 'Nyarugenge'],
            'Northern Province' => ['Burera', 'Gakenke', 'Gicumbi', 'Musanze', 'Rulindo'],
            'Southern Province' => ['Gisagara', 'Huye', 'Kamonyi', 'Muhanga', 'Nyamagabe', 'Nyanza', 'Nyaruguru', 'Ruhango'],
            'Eastern Province' => ['Bugesera', 'Gatsibo', 'Kayonza', 'Kirehe', 'Ngoma', 'Nyagatare', 'Rwamagana'],
            'Western Province' => ['Karongi', 'Ngororero', 'Nyabihu', 'Nyamasheke', 'Rubavu', 'Rusizi', 'Rutsiro'],
        ];

        foreach ($districts as $province => $names) {
            foreach ($names as $name) {
                District::query()->updateOrCreate(
                    ['country_id' => $rwanda->id, 'name' => $name],
                    ['province' => $province, 'delivery_zone_id' => $province === 'Kigali City' ? $kigali->id : $provinces->id],
                );
            }
        }
    }

    private function accessControl(): void
    {
        foreach (Ability::cases() as $ability) {
            Permission::query()->updateOrCreate(['slug' => $ability->value], ['name' => $ability->label(), 'group' => $ability->group()]);
        }

        $permissionIds = Permission::query()->pluck('id', 'slug');

        foreach (RoleName::cases() as $roleName) {
            $role = Role::query()->updateOrCreate(['slug' => $roleName->value], [
                'name' => $roleName->label(),
                'is_staff' => $roleName->isStaff(),
            ]);

            // Only seed defaults for new roles; never overwrite permissions an administrator changed.
            if ($role->wasRecentlyCreated) {
                $role->permissions()->sync(array_map(fn (Ability $a) => $permissionIds[$a->value], $roleName->defaultPermissions()));
            }
        }
    }
}
