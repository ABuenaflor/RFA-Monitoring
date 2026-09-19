<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps a signed-in session honest:
 *
 * - a deactivated account is signed out immediately, even mid-session
 * - an account flagged for a password change cannot browse the system
 *   until the password has actually been changed
 */
class EnforceAccountState
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $user = Auth::user();

        if ($user === null) {
            return $next($request);
        }

        /*
        |--------------------------------------------------------------------------
        | Deactivated account
        |--------------------------------------------------------------------------
        */

        if (! $user->isActive()) {
            Auth::logout();

            $request->session()->invalidate();

            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors([
                    'email' =>
                        'This account has been deactivated. Contact a system administrator.',
                ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Forced password change
        |--------------------------------------------------------------------------
        */

        if (
            $user->must_change_password
            && ! $request->routeIs(
                'account.*',
                'logout'
            )
        ) {
            return redirect()
                ->route('account.edit')
                ->with(
                    'warning',
                    'You must set a new password before continuing.'
                );
        }

        return $next($request);
    }
}
