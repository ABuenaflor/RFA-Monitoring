<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
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
