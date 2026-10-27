<?php

namespace Tests\Feature\Storefront;

use App\Enums\ArtistStatus;
use App\Http\Controllers\Shop\PageController;
use App\Livewire\CatalogBrowser;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function public_pages_render_for_guests(): void
    {
        $artistProduct = $this->listedArtistProduct();

        $this->get(route('home'))->assertOk()->assertSee($artistProduct->artwork->title);
        $this->get(route('shop.index'))->assertOk();
        $this->get(route('products.show', [$artistProduct->artwork, $artistProduct->product]))
            ->assertOk()->assertSee($artistProduct->artwork->artist->display_name)->assertSee(__('Add to cart'));
        $this->get(route('artists.show', $artistProduct->artwork->artist))->assertOk();
        $this->get(route('designs.show', $artistProduct->artwork))->assertRedirect(route('products.show', [$artistProduct->artwork, $artistProduct->product]));
        foreach (PageController::PAGES as $page) {
            $this->get(route('pages.show', $page))->assertOk();
        }
        $this->get(route('pages.show', 'unknown'))->assertNotFound();
    }

    #[Test]
    public function designs_from_unapproved_or_suspended_artists_are_hidden(): void
    {
        $artistProduct = $this->listedArtistProduct();
        $artistProduct->artwork->artist->forceFill(['status' => ArtistStatus::Suspended])->save();

        $this->get(route('products.show', [$artistProduct->artwork, $artistProduct->product]))->assertNotFound();
        $this->get(route('artists.show', $artistProduct->artwork->artist))->assertNotFound();
        $this->get(route('media.artwork', [$artistProduct->artwork->current_version_id, 'preview']))->assertForbidden();
        Livewire::test(CatalogBrowser::class)->assertDontSee($artistProduct->artwork->title);
    }

    #[Test]
    public function search_matches_titles_tags_and_artist_names(): void
    {
        $hills = $this->listedArtistProduct('classic-tee');
        $hills->artwork->forceFill(['title' => 'Thousand Hills'])->save();
        $mug = $this->listedArtistProduct('ceramic-mug');
        $mug->artwork->forceFill(['title' => 'Coffee Ritual'])->save();
        $mug->artwork->tags()->sync(Tag::idsFor(['imigongo']));

        Livewire::test(CatalogBrowser::class)->set('q', 'hills')->assertSee('Thousand Hills')->assertDontSee('Coffee Ritual');
        Livewire::test(CatalogBrowser::class)->set('q', 'imigongo')->assertSee('Coffee Ritual')->assertDontSee('Thousand Hills');
        Livewire::test(CatalogBrowser::class)->set('q', $mug->artwork->artist->display_name)->assertSee('Coffee Ritual');
    }

    #[Test]
    public function filters_narrow_by_product_type_colour_size_and_price(): void
    {
        $tee = $this->listedArtistProduct('classic-tee');
        $mug = $this->listedArtistProduct('ceramic-mug');

        Livewire::test(CatalogBrowser::class)->set('type', 'mugs')->assertSee($mug->artwork->title)->assertDontSee($tee->artwork->title);
        Livewire::test(CatalogBrowser::class)->set('size', 'XL')->assertSee($tee->artwork->title)->assertDontSee($mug->artwork->title);
        Livewire::test(CatalogBrowser::class)->set('color', 'Clay red')->assertSee($tee->artwork->title)->assertDontSee($mug->artwork->title);
        Livewire::test(CatalogBrowser::class)->set('max', '6000')->assertSee($mug->artwork->title)->assertDontSee($tee->artwork->title);
    }

    #[Test]
    public function the_api_lists_only_listed_products(): void
    {
        $listed = $this->listedArtistProduct();
        $hidden = $this->listedArtistProduct('canvas-tote');
        $hidden->forceFill(['is_enabled' => false])->save();

        $this->getJson(route('api.v1.products.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $listed->id);

        $this->getJson(route('api.v1.products.show', $listed))->assertOk()->assertJsonPath('variants.0.price', 9500);
        $this->getJson(route('api.v1.products.show', $hidden))->assertNotFound();
        $this->getJson(route('api.v1.me.orders.index'))->assertUnauthorized();
    }
}
