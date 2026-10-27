<?php

namespace Tests\Feature\Studio;

use App\Enums\ArtworkStatus;
use App\Enums\PrintCheckStatus;
use App\Models\ArtistProfile;
use App\Models\Artwork;
use App\Models\DesignAsset;
use App\Models\DesignDocument;
use App\Models\PrintArea;
use App\Models\User;
use App\Services\Artwork\PlacementValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DesignStudioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('artwork');
    }

    private function artistUser(): User
    {
        return ArtistProfile::factory()->create()->user->fresh();
    }

    private function area(string $product = 'classic-tee'): PrintArea
    {
        return $this->product($product)->primaryPrintArea();
    }

    private function newDesign(User $user, string $product = 'classic-tee'): DesignDocument
    {
        $this->actingAs($user)->post(route('studio.design.store'), [
            'title' => 'Ishema',
            'print_area_id' => $this->area($product)->id,
            'rights_confirmed' => '1',
        ])->assertRedirect();

        return DesignDocument::query()->latest('id')->firstOrFail();
    }

    private function asset(User $user, int $width = 1200, int $height = 900): DesignAsset
    {
        $this->actingAs($user)->post(route('studio.design-assets.store'), [
            'file' => UploadedFile::fake()->image('photo.png', $width, $height),
        ], ['Accept' => 'application/json'])->assertCreated();

        return DesignAsset::query()->latest('id')->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function image(DesignAsset $asset, float $scale, array $overrides = []): array
    {
        return [
            'type' => 'Image', 'assetRef' => 'asset:'.$asset->id, 'left' => 500, 'top' => 500,
            'width' => $asset->width, 'height' => $asset->height, 'cropX' => 0, 'cropY' => 0,
            'scaleX' => $scale, 'scaleY' => $scale, 'originX' => 'center', 'originY' => 'center',
            ...$overrides,
        ];
    }

    private function save(DesignDocument $document, array $objects, ?int $revision = null)
    {
        return $this->putJson(route('studio.artworks.design.save', $document->artwork), [
            'revision' => $revision ?? $document->fresh()->revision,
            'data' => ['objects' => $objects, 'background' => null],
        ]);
    }

    #[Test]
    public function an_artist_creates_a_design_on_a_print_area_artboard(): void
    {
        $user = $this->artistUser();
        $document = $this->newDesign($user);
        $area = $this->area();

        $this->assertSame(ArtworkStatus::Draft, $document->artwork->status);
        $this->assertNull($document->artwork->current_version_id);
        $this->assertSame([$area->width_mm, $area->height_mm, 300], [$document->width_mm, $document->height_mm, $document->design_dpi]);
        // 300 × 400 mm at 300 DPI.
        $this->assertSame([3543, 4724], $document->exportPixels());

        $this->get(route('studio.artworks.design', $document->artwork))
            ->assertOk()
            ->assertSee('designStudio', false)
            ->assertSee('Export to products');

        // The artwork page offers the studio and does not wait for a file that does not exist.
        $this->get(route('studio.artworks.show', $document->artwork))
            ->assertOk()
            ->assertSee('Open in Design Studio')
            ->assertSee('No print file yet');
    }

    #[Test]
    public function artboard_resolution_respects_upload_minimum_and_canvas_limits(): void
    {
        // Mug wrap: the short side still reaches the 1000 px upload minimum.
        $this->assertGreaterThanOrEqual(1000, (int) round(85 / 25.4 * DesignDocument::dpiFor(200, 85)));
        // Very large posters are rendered below 300 DPI rather than beyond what browsers can draw.
        $dpi = DesignDocument::dpiFor(600, 900);
        $this->assertLessThan(300, $dpi);
        $this->assertLessThanOrEqual(DesignDocument::MAX_EXPORT_SIDE, (int) round(900 / 25.4 * $dpi));
    }

    #[Test]
    public function saving_validates_sanitises_and_versions_the_document(): void
    {
        $user = $this->artistUser();
        $document = $this->newDesign($user);
        $asset = $this->asset($user);

        $this->save($document, [
            ['type' => 'Textbox', 'text' => 'Ishema', 'fontFamily' => 'Pacifico', 'fontSize' => 200, 'fill' => '#a16207', 'left' => 100, 'top' => 100, 'width' => 900, 'curve' => 30, 'onclick' => 'alert(1)', 'styles' => ['x' => 1]],
            $this->image($asset, 1.5, ['src' => 'data:image/png;base64,AAAA', 'filters' => [['type' => 'Blur']]]),
            ['type' => 'Group', 'left' => 10, 'top' => 10, 'objects' => [
                ['type' => 'Rect', 'width' => 100, 'height' => 50, 'rx' => 8, 'fill' => 'rgba(0,0,0,0.5)'],
                ['type' => 'Polygon', 'points' => [['x' => 0, 'y' => 0], ['x' => 10, 'y' => 0], ['x' => 5, 'y' => 8]]],
            ]],
        ])->assertOk()->assertJson(['revision' => 2, 'min_raster_dpi' => 200]);

        $document->refresh();
        [$text, $image, $group] = $document->data['objects'];

        $this->assertSame(2, $document->revision);
        $this->assertSame('textbox', $text['type']);
        $this->assertEquals(30, $text['curve']);
        $this->assertArrayNotHasKey('onclick', $text);
        $this->assertArrayNotHasKey('styles', $text);
        // Images are stored by reference only: no src, no data URI, no filters.
        $this->assertSame('asset:'.$asset->id, $image['assetRef']);
        $this->assertArrayNotHasKey('src', $image);
        $this->assertArrayNotHasKey('filters', $image);
        $this->assertStringNotContainsString('base64', json_encode($document->data));
        $this->assertCount(2, $group['objects']);
        // 300 DPI artboard ÷ 1.5 enlargement = 200 real DPI.
        $this->assertSame(200, $document->min_raster_dpi);

        // The saved document reloads into the editor.
        $this->get(route('studio.artworks.design', $document->artwork))->assertOk()->assertSee('asset:'.$asset->id);
    }

    #[Test]
    public function a_stale_revision_is_refused_instead_of_overwriting_newer_work(): void
    {
        $user = $this->artistUser();
        $document = $this->newDesign($user);
        $text = ['type' => 'Textbox', 'text' => 'One', 'fontFamily' => 'Inter', 'width' => 300];

        $this->save($document, [$text], 1)->assertOk();
        $this->save($document, [[...$text, 'text' => 'From an old tab']], 1)
            ->assertStatus(409)
            ->assertJson(['revision' => 2]);

        $this->assertSame('One', $document->fresh()->data['objects'][0]['text']);
    }

    #[Test]
    public function unsafe_or_invalid_documents_are_rejected(): void
    {
        $user = $this->artistUser();
        $document = $this->newDesign($user);
        $asset = $this->asset($user);
        $otherAsset = $this->asset($this->artistUser());
        $this->actingAs($user);

        $invalid = [
            'unknown type' => [['type' => 'Path', 'path' => [['M', 0, 0]]]],
            'script colour' => [['type' => 'Rect', 'width' => 10, 'height' => 10, 'fill' => 'url(javascript:alert(1))']],
            'gradient fill' => [['type' => 'Rect', 'width' => 10, 'height' => 10, 'fill' => ['type' => 'linear']]],
            'unlisted font' => [['type' => 'Textbox', 'text' => 'x', 'fontFamily' => 'Comic Sans']],
            'no asset' => [['type' => 'Image', 'src' => 'https://evil.example/x.png', 'width' => 10, 'height' => 10]],
            'someone else’s asset' => [$this->image($otherAsset, 1)],
            'crop outside the image' => [$this->image($asset, 1, ['cropX' => 1000, 'width' => 900])],
            'non-numeric geometry' => [['type' => 'Rect', 'width' => '10; drop', 'height' => 10]],
            'zero scale' => [['type' => 'Rect', 'width' => 10, 'height' => 10, 'scaleX' => 0]],
            'too many layers' => array_fill(0, 301, ['type' => 'Rect', 'width' => 10, 'height' => 10]),
        ];

        foreach ($invalid as $case => $objects) {
            $this->save($document, $objects)->assertUnprocessable()->assertJsonValidationErrors('data');
            $this->assertSame(1, $document->fresh()->revision, $case);
        }
    }

    #[Test]
    public function studio_images_are_validated_by_content_stored_privately_and_shown_only_to_their_owner(): void
    {
        $user = $this->artistUser();

        $this->actingAs($user)->post(route('studio.design-assets.store'), [
            'file' => UploadedFile::fake()->createWithContent('photo.png', '<?php echo 1;'),
        ], ['Accept' => 'application/json'])->assertUnprocessable();

        $asset = $this->asset($user, 640, 480);
        Storage::disk('artwork')->assertExists($asset->path);
        $this->assertSame([640, 480], [$asset->width, $asset->height]);

        // The same file is stored once.
        $this->asset($user, 640, 480);
        $this->assertSame(1, $user->artistProfile->designAssets()->count());

        $this->get(route('studio.design-assets.show', $asset))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->getJson(route('studio.design-assets.index'))->assertOk()->assertJsonPath('uploads.0.ref', 'asset:'.$asset->id);

        $this->actingAs($this->artistUser())->get(route('studio.design-assets.show', $asset))->assertNotFound();
        auth()->logout();
        $this->get(route('studio.design-assets.show', $asset))->assertRedirect(route('login'));
    }

    #[Test]
    public function only_the_owning_artist_can_open_save_or_export_a_design(): void
    {
        $document = $this->newDesign($this->artistUser());
        $artwork = $document->artwork;

        $this->actingAs($this->artistUser());
        $this->get(route('studio.artworks.design', $artwork))->assertNotFound();
        $this->putJson(route('studio.artworks.design.save', $artwork), ['revision' => 1, 'data' => ['objects' => []]])->assertNotFound();
        $this->postJson(route('studio.artworks.design.export', $artwork), ['revision' => 1])->assertNotFound();

        $customer = User::factory()->create();
        $this->actingAs($customer)->get(route('studio.artworks.design', $artwork))->assertForbidden();
    }

    #[Test]
    public function designs_under_review_cannot_be_edited(): void
    {
        $user = $this->artistUser();
        $document = $this->newDesign($user);
        $document->artwork->forceFill(['status' => ArtworkStatus::PendingReview])->save();

        $this->get(route('studio.artworks.design', $document->artwork))->assertForbidden();
        $this->save($document, [])->assertForbidden();
    }

    #[Test]
    public function exporting_creates_a_new_version_that_carries_the_real_photo_resolution(): void
    {
        $user = $this->artistUser();
        $document = $this->newDesign($user, 'ceramic-mug');
        $asset = $this->asset($user, 1000, 400);
        [$width, $height] = $document->exportPixels();

        // A 1000 px photo stretched 2.5× on a 300 DPI artboard has only 120 DPI of real detail.
        $this->save($document, [$this->image($asset, 2.5)])->assertOk()->assertJson(['min_raster_dpi' => 120]);

        $this->post(route('studio.artworks.design.export', $document->artwork), [
            'revision' => 2,
            'file' => UploadedFile::fake()->image('design.png', $width, $height),
        ], ['Accept' => 'application/json'])->assertCreated()->assertJson(['version' => 1]);

        $artwork = $document->artwork->fresh(['currentVersion', 'designDocument', 'artistProducts.placements']);
        $version = $artwork->currentVersion;

        $this->assertSame([$width, $height, 120, 300], [$version->width, $version->height, $version->source_dpi_limit, $version->design_dpi]);
        $this->assertSame($version->id, $artwork->designDocument->last_exported_version_id);
        // The editable document is kept alongside the flattened file.
        $this->assertSame('asset:'.$asset->id, $artwork->designDocument->data['objects'][0]['assetRef']);

        // Product checks use the real resolution, not the export's pixel count.
        $mug = $artwork->artistProducts->firstWhere('product_id', $this->product('ceramic-mug')->id);
        $placement = $mug->placements->first();
        $raw = PlacementValidator::dpi($width, $placement->scale * $this->area('ceramic-mug')->width_mm);
        $this->assertLessThan($raw, $placement->effective_dpi);
        $this->assertSame((int) floor($raw * 120 / 300), $placement->effective_dpi, 'effective DPI is scaled by 120/300');
    }

    #[Test]
    public function export_is_refused_for_stale_revisions_and_wrong_sizes(): void
    {
        $user = $this->artistUser();
        $document = $this->newDesign($user, 'ceramic-mug');
        [$width, $height] = $document->exportPixels();
        $this->save($document, [['type' => 'Rect', 'width' => 100, 'height' => 100, 'fill' => '#f5b027']])->assertOk();

        $this->post(route('studio.artworks.design.export', $document->artwork), [
            'revision' => 1, 'file' => UploadedFile::fake()->image('design.png', $width, $height),
        ], ['Accept' => 'application/json'])->assertStatus(409);

        $this->post(route('studio.artworks.design.export', $document->artwork), [
            'revision' => 2, 'file' => UploadedFile::fake()->image('design.png', 1200, 1200),
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('file');

        $this->assertNull($document->artwork->fresh()->current_version_id);
    }

    #[Test]
    public function exporting_a_published_design_sends_it_back_for_review(): void
    {
        $user = $this->artistUser();
        $document = $this->newDesign($user, 'ceramic-mug');
        [$width, $height] = $document->exportPixels();
        $document->artwork->forceFill(['status' => ArtworkStatus::Approved, 'published_at' => now()])->save();
        $this->save($document, [['type' => 'Ellipse', 'rx' => 50, 'ry' => 50, 'fill' => '#171923']])->assertOk();

        $this->post(route('studio.artworks.design.export', $document->artwork), [
            'revision' => 2, 'file' => UploadedFile::fake()->image('design.png', $width, $height),
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->assertSame(ArtworkStatus::Draft, $document->artwork->fresh()->status);
    }

    #[Test]
    public function an_uploaded_artwork_opens_in_the_studio_without_changing_its_file(): void
    {
        $user = $this->artistUser();
        $artwork = Artwork::factory()->withVersion(3000, 3000)->create(['artist_profile_id' => $user->artistProfile->id]);
        $original = $artwork->current_version_id;

        $this->actingAs($user)->get(route('studio.artworks.design', $artwork))->assertRedirect(route('studio.artworks.design.start', $artwork));
        $this->get(route('studio.artworks.design.start', $artwork))->assertOk()->assertSee('Open Design Studio');
        $this->post(route('studio.artworks.design.start', $artwork), ['print_area_id' => $this->area()->id])
            ->assertRedirect(route('studio.artworks.design', $artwork));

        $document = $artwork->fresh()->designDocument;
        $layer = $document->data['objects'][0];

        $this->assertSame('version:'.$original, $layer['assetRef']);
        // Placed no larger than its sharp size (250 DPI) so the composition starts print-ready.
        $this->assertGreaterThanOrEqual(250, $document->min_raster_dpi);
        $this->assertSame($original, $artwork->fresh()->current_version_id);

        // The full-resolution file is served inline to its artist only.
        Storage::disk('artwork')->put($artwork->currentVersion->original_path, 'png-bytes');
        $this->get(route('studio.design-sources.show', $original))->assertOk();
        $this->actingAs($this->artistUser())->get(route('studio.design-sources.show', $original))->assertNotFound();
    }

    #[Test]
    public function the_source_resolution_limit_lowers_print_checks_for_studio_exports(): void
    {
        $artwork = Artwork::factory()->withVersion(3543, 4724)->create();
        $version = $artwork->currentVersion;
        $area = $this->area();
        $validator = app(PlacementValidator::class);

        $this->assertSame(PrintCheckStatus::Pass, $validator->validate($version, $area, 0, 0, 1.0)->status);

        $version->forceFill(['source_dpi_limit' => 120, 'design_dpi' => 300])->save();
        $result = $validator->validate($version, $area, 0, 0, 1.0);

        // 3543 px across 300 mm is 299.97 DPI; 120/300 of that is 119.99, rounded down.
        $this->assertSame(PrintCheckStatus::Fail, $result->status);
        $this->assertSame(119, $result->effectiveDpi);
        // Best print size shrinks the design until the photo is sharp again.
        $best = $validator->bestPrintScale($version, $area);
        $this->assertSame(250, $best['target_dpi']);
        $this->assertEqualsWithDelta(120 / 250, $best['scale'], 0.01);
    }
}
