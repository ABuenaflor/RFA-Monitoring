<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Failed sign-ins allowed from one address within the window.
     */
    private const MAX_FAILURES_PER_IP = 20;

    private const FAILURE_WINDOW_SECONDS = 900;

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' =>
                ['required', 'string', 'email', 'max:255'],

            'password' =>
                ['required', 'string'],
        ]);

        $remember = $request->boolean('remember');

        /*
        |--------------------------------------------------------------------------
        | Password spraying guard
        |--------------------------------------------------------------------------
        |
        | throttle:login stops guessing one account's password. This stops one
        | address trying a common password against many accounts. Only failed
        | attempts count, so an office signing in together from one shared
        | address is never blocked.
        |
        */

        $failureKey = 'login-failures:' . $request->ip();

        if (RateLimiter::tooManyAttempts($failureKey, self::MAX_FAILURES_PER_IP)) {
            throw ValidationException::withMessages([
                'email' => sprintf(
                    'Too many failed sign-in attempts from this network. Try again in %d minutes.',
                    (int) ceil(RateLimiter::availableIn($failureKey) / 60)
                ),
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Credential check
        |--------------------------------------------------------------------------
        */

        if (
            ! Auth::attempt(
                [
                    'email' => $credentials['email'],
                    'password' => $credentials['password'],
                ],
                $remember
            )
        ) {
            RateLimiter::hit($failureKey, self::FAILURE_WINDOW_SECONDS);

            throw ValidationException::withMessages([
                'email' =>
                    'These credentials do not match our records.',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Account status check
        |--------------------------------------------------------------------------
        |
        | A correct password is not enough — a deactivated account may not
        | establish a session.
        |
        */

        $user = Auth::user();

        if (! $user->isActive()) {
            Auth::logout();

            $request->session()->invalidate();

            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' =>
                    'This account has been deactivated. Contact a system administrator.',
            ]);
        }

        if ($user->role_id === null) {
            Auth::logout();

            $request->session()->invalidate();

            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' =>
                    'This account has no role assigned. Contact a system administrator.',
            ]);
        }

        $request->session()->regenerate();

        /*
        | Tie the session to the password it signed in with, so auth.session
        | ends it if the password changes before its next request.
        */

        $request->session()->put(
            'password_hash_' . Auth::getDefaultDriver(),
            Auth::guard()->hashPasswordForCookie($user->getAuthPassword())
        );

        $this->recordSignIn($user, $request);

        if ($user->must_change_password) {
            return redirect()
                ->route('account.edit')
                ->with(
                    'warning',
                    'You must set a new password before continuing.'
                );
        }

        return redirect()->intended(
            route('dashboard')
        );
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->with(
                'status',
                'You have been signed out.'
            );
    }

    private function recordSignIn(
        User $user,
        Request $request
    ): void {
        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();
    }
}
