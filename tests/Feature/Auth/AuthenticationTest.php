<?php

namespace Tests\Feature\Auth;

use App\Enums\ArtistStatus;
use App\Enums\RoleName;
use App\Models\Cart;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function customers_register_and_must_verify_their_email(): void
    {
        Notification::fake();

        $this->post('/register', [
            'name' => 'Grace Mukamana',
            'email' => 'Grace@Example.rw',
            'password' => 'secret-password-1',
            'password_confirmation' => 'secret-password-1',
            'accept_terms' => '1',
        ])->assertRedirect(route('verification.notice'));

        $user = User::query()->where('email', 'grace@example.rw')->sole();
        $this->assertTrue($user->hasRole(RoleName::Customer));
        $this->assertNull($user->artistProfile);
        Notification::assertSentTo($user, VerifyEmail::class);

        $this->get(route('account.dashboard'))->assertRedirect(route('verification.notice'));
    }

    #[Test]
    public function artists_register_with_a_pending_shop_and_must_accept_the_agreement(): void
    {
        $this->post('/register', [
            'as_artist' => '1', 'name' => 'Eric N', 'email' => 'eric@example.rw', 'display_name' => 'Eric Draws',
            'password' => 'secret-password-1', 'password_confirmation' => 'secret-password-1', 'accept_terms' => '1',
        ])->assertSessionHasErrors('accept_agreement');

        $this->post('/register', [
            'as_artist' => '1', 'name' => 'Eric N', 'email' => 'eric@example.rw', 'display_name' => 'Eric Draws',
            'password' => 'secret-password-1', 'password_confirmation' => 'secret-password-1', 'accept_terms' => '1', 'accept_agreement' => '1',
        ])->assertRedirect(route('studio.dashboard'));

        $profile = User::query()->where('email', 'eric@example.rw')->sole()->artistProfile;
        $this->assertSame(ArtistStatus::Pending, $profile->status);
        $this->assertSame('eric-draws', $profile->slug);
        $this->assertNotNull($profile->agreement_accepted_at);
        $this->get(route('artists.show', $profile))->assertNotFound();
    }

    #[Test]
    public function login_is_rate_limited(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 5) as $attempt) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        }

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertStatus(429);
    }

    #[Test]
    public function suspended_users_cannot_sign_in_and_are_signed_out(): void
    {
        $user = User::factory()->suspended()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->actingAs($user)->get('/')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    #[Test]
    public function password_reset_does_not_reveal_whether_an_account_exists(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => 'nobody@example.rw'])->assertSessionHas('status');
        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $this->post('/reset-password', [
                'token' => $notification->token, 'email' => $user->email,
                'password' => 'brand-new-password', 'password_confirmation' => 'brand-new-password',
            ])->assertRedirect(route('login'));

            return true;
        });

        $this->post('/login', ['email' => $user->email, 'password' => 'brand-new-password'])->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    #[Test]
    public function a_guest_cart_follows_the_user_after_login(): void
    {
        $artistProduct = $this->listedArtistProduct();
        $user = User::factory()->create();

        $this->post(route('cart.items.store'), ['artist_product_id' => $artistProduct->id, 'variant_id' => $this->variant()->id, 'quantity' => 2]);
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->assertSame(2, Cart::query()->where('user_id', $user->id)->sole()->itemCount());
    }

    #[Test]
    public function login_does_not_return_to_an_area_the_new_account_cannot_open(): void
    {
        $admin = $this->staff(RoleName::SuperAdmin);

        $this->get(route('studio.dashboard'))->assertRedirect(route('login'));
        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])->assertRedirect(route('admin.dashboard'));

        $this->get(route('studio.dashboard'))->assertForbidden()->assertSee('You don’t have access to this page', false);
    }
}
