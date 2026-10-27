<?php

namespace Tests\Feature\Studio;

use App\Enums\ArtworkStatus;
use App\Enums\ArtworkVersionStatus;
use App\Enums\RoleName;
use App\Models\ArtistProfile;
use App\Models\Artwork;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ArtworkUploadTest extends TestCase
{
    use RefreshDatabase;

    private function artistUser(): User
    {
        return ArtistProfile::factory()->create()->user->fresh();
    }

    private function png(int $width = 3000, int $height = 3000): UploadedFile
    {
        return UploadedFile::fake()->image('design.png', $width, $height);
    }

    #[Test]
    public function an_artist_uploads_artwork_which_is_processed_privately_and_offered_on_products(): void
    {
        Storage::fake('artwork');
        $user = $this->artistUser();

        $this->actingAs($user)->post(route('studio.artworks.store'), [
            'title' => 'Kigali Sunrise',
            'tags' => 'kigali, sunrise',
            'file' => $this->png(),
            'rights_confirmed' => '1',
        ])->assertRedirect();

        $artwork = Artwork::query()->sole();
        $version = $artwork->currentVersion;

        $this->assertSame(ArtworkStatus::Draft, $artwork->status);
        $this->assertSame(ArtworkVersionStatus::Ready, $version->status);
        $this->assertSame([3000, 3000], [$version->width, $version->height]);
        Storage::disk('artwork')->assertExists([$version->original_path, $version->preview_path, $version->thumbnail_path]);
        $this->assertGreaterThan(0, $artwork->artistProducts()->where('is_enabled', true)->count());
        $this->assertEqualsCanonicalizing(['kigali', 'sunrise'], $artwork->tags->pluck('name')->all());

        // Unpublished artwork is not publicly reachable.
        auth()->logout();
        $this->get(route('media.artwork', [$version, 'preview']))->assertForbidden();
    }

    #[Test]
    public function the_upload_endpoint_answers_json_for_the_progress_uploader(): void
    {
        Storage::fake('artwork');

        $this->actingAs($this->artistUser())
            ->postJson(route('studio.artworks.store'), ['title' => 'Kivu Blue', 'file' => $this->png(), 'rights_confirmed' => '1'])
            ->assertCreated()
            ->assertJsonStructure(['redirect']);

        $this->actingAs($this->artistUser())
            ->postJson(route('studio.artworks.store'), ['title' => 'No rights', 'file' => $this->png()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('rights_confirmed');
    }

    #[Test]
    public function files_are_validated_by_content_and_dimensions(): void
    {
        Storage::fake('artwork');
        $user = $this->artistUser();

        $spoofed = UploadedFile::fake()->createWithContent('design.png', '<?php echo "not an image";');
        $this->actingAs($user)->post(route('studio.artworks.store'), ['title' => 'Bad', 'file' => $spoofed, 'rights_confirmed' => '1'])
            ->assertSessionHasErrors('file');

        $this->actingAs($user)->post(route('studio.artworks.store'), ['title' => 'Small', 'file' => $this->png(400, 400), 'rights_confirmed' => '1'])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, Artwork::query()->count());
    }

    #[Test]
    public function customers_and_other_artists_cannot_reach_someone_elses_studio(): void
    {
        $artwork = Artwork::factory()->withVersion()->create();
        $customer = User::factory()->withRole(RoleName::Customer)->create();

        $this->actingAs($customer)->get(route('studio.dashboard'))->assertForbidden();
        $this->actingAs($this->artistUser())->get(route('studio.artworks.show', $artwork))->assertNotFound();
        $this->actingAs($this->artistUser())->put(route('studio.artworks.update', $artwork), ['title' => 'Hijack'])->assertForbidden();
    }

    #[Test]
    public function suspended_artists_cannot_upload(): void
    {
        $user = ArtistProfile::factory()->suspended()->create()->user;

        $this->actingAs($user)->get(route('studio.artworks.create'))->assertForbidden();
    }
}
