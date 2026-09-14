<?php

namespace App\Providers;

use App\Models\User;
use App\Support\Permissions;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerGates();

        $this->registerRateLimiters();
    }

    /**
     * Every permission key in the catalogue becomes a gate of the same name,
     * which lets routes be guarded with the framework's own can: middleware.
     */
    private function registerGates(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Administrator short circuit
        |--------------------------------------------------------------------------
        |
        | Returning null (rather than false) lets the individual gate run when
        | the user is not an administrator.
        |
        */

        Gate::before(function (User $user) {
            if (! $user->isActive()) {
                return false;
            }

            return $user->isAdministrator()
                ? true
                : null;
        });

        foreach (Permissions::all() as $permission) {
            Gate::define(
                $permission,
                fn (User $user): bool =>
                    $user->hasPermission($permission)
            );
        }
    }

    private function registerRateLimiters(): void
    {
        RateLimiter::for(
            'login',
            fn (Request $request) => Limit::perMinute(5)
                ->by(
                    strtolower(
                        (string) $request->input('email')
                    )
                    . '|'
                    . $request->ip()
                )
        );
    }
}
