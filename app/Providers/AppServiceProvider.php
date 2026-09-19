<?php

namespace App\Providers;

use App\Models\User;
use App\Observers\AuditableObserver;
use App\Services\AuditLogger;
use App\Support\Permissions;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
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

        $this->registerAuditTrail();
    }

    /**
     * Watch the models and authentication events that the audit trail covers.
     */
    private function registerAuditTrail(): void
    {
        foreach (AuditableObserver::auditedModels() as $model) {
            $model::observe(AuditableObserver::class);
        }

        Event::listen(
            Login::class,
            function (Login $event): void {
                app(AuditLogger::class)->record(
                    event: 'login',
                    subject: $event->user,
                    recordLabel: $event->user->email,
                    summary: 'Signed in',
                    actor: $event->user
                );
            }
        );

        Event::listen(
            Logout::class,
            function (Logout $event): void {
                if ($event->user === null) {
                    return;
                }

                app(AuditLogger::class)->record(
                    event: 'logout',
                    subject: $event->user,
                    recordLabel: $event->user->email,
                    summary: 'Signed out',
                    actor: $event->user
                );
            }
        );

        Event::listen(
            Failed::class,
            function (Failed $event): void {
                app(AuditLogger::class)->record(
                    event: 'login_failed',

                    subject: $event->user instanceof User
                        ? $event->user
                        : null,

                    recordLabel: (string) ($event->credentials['email'] ?? 'unknown'),

                    summary: 'Failed sign-in attempt'
                );
            }
        );
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
