<?php

namespace App\Providers;

use App\Enums\Ability;
use App\Models\Category;
use App\Models\ProductType;
use App\Models\User;
use App\Services\Cart\CartManager;
use App\Services\Pricing\PriceCalculator;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(PriceCalculator::class);
    }

    public function boot(): void
    {
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
        Model::automaticallyEagerLoadRelationships();

        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(10)->letters()->numbers()->uncompromised()
            : Password::min(8));

        $this->registerAbilities();
        $this->registerRateLimiters();

        View::composer('components.layouts.shop', function ($view) {
            $view->with('navProductTypes', ProductType::query()->active()->orderBy('position')->get());
            $view->with('navCategories', Category::query()->active()->whereNull('parent_id')->orderBy('position')->get());
            $view->with('cartCount', app(CartManager::class)->current()?->itemCount() ?? 0);
        });
    }

    private function registerAbilities(): void
    {
        foreach (Ability::cases() as $ability) {
            Gate::define($ability->value, fn (User $user) => $user->isSuperAdmin() || $user->hasPermission($ability));
        }
    }

    private function registerRateLimiters(): void
    {
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(20)->by($request->ip()),
        ]);

        RateLimiter::for('checkout', fn (Request $request) => Limit::perMinute(10)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('uploads', fn (Request $request) => Limit::perMinute(20)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('design-saves', fn (Request $request) => Limit::perMinute(40)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('forms', fn (Request $request) => Limit::perMinute(6)->by($request->ip()));
        RateLimiter::for('webhooks', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));
    }
}
