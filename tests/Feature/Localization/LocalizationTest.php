<?php

namespace Tests\Feature\Localization;

use App\Models\Category;
use App\Models\User;
use App\Support\Money;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_locale_switcher_serves_kinyarwanda_and_remembers_the_choice(): void
    {
        $this->get('/')->assertOk()->assertSee('Search designs');

        $this->post(route('locale.update'), ['locale' => 'rw'])->assertRedirect();
        $this->get('/')->assertOk()->assertSee(__('Search designs', [], 'rw'))->assertSee('lang="rw"', false);

        $this->post(route('locale.update'), ['locale' => 'fr'])->assertSessionHasErrors('locale');
    }

    #[Test]
    public function every_kinyarwanda_translation_keeps_placeholders_and_plural_forms(): void
    {
        $translations = json_decode(file_get_contents(lang_path('rw.json')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertNotEmpty($translations);

        foreach ($translations as $english => $kinyarwanda) {
            preg_match_all('/:[a-z_]+/', $english, $expected);
            preg_match_all('/:[a-z_]+/', $kinyarwanda, $actual);
            $this->assertEqualsCanonicalizing(array_unique($expected[0]), array_unique($actual[0]), "Placeholder mismatch for [{$english}]");
            $this->assertSame(substr_count($english, '|'), substr_count($kinyarwanda, '|'), "Plural mismatch for [{$english}]");
        }
    }

    #[Test]
    public function signed_in_users_get_their_saved_locale_and_catalogue_content_is_translated(): void
    {
        $user = User::factory()->create(['locale' => 'rw']);
        $category = Category::query()->where('slug', 'culture')->firstOrFail();

        $this->actingAs($user)->get(route('shop.category', $category))->assertOk()->assertSee($category->translate('name', 'rw'));
        $this->assertSame("Umuco n'amateka", $category->translate('name', 'rw'));
        $this->assertSame('Culture & heritage', $category->translate('name', 'sw'), 'Unknown locales fall back to English.');
    }

    #[Test]
    public function money_and_phone_numbers_are_formatted_for_rwanda(): void
    {
        $this->assertSame('RWF 12,500', Money::format(12500, 'RWF'));
        $this->assertSame('KSh 1,250.50', Money::format(125050, 'KES'));
        $this->assertSame(1250, Money::toMinor('1,250', 'RWF'));
        $this->assertSame(125050, Money::toMinor('1250.5', 'KES'));

        $rwanda = $this->rwanda();
        $this->assertSame('+250788123456', PhoneNumber::normalize('0788 123 456', $rwanda));
        $this->assertSame('+250722123456', PhoneNumber::normalize('+250 722 123 456', $rwanda));
        $this->assertSame('+250788123456', PhoneNumber::normalize('250788123456', $rwanda));
        $this->assertNull(PhoneNumber::normalize('0612 345 678', $rwanda));
        $this->assertNull(PhoneNumber::normalize('078812345', $rwanda));
    }
}
