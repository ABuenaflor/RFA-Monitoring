<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('account.index', [
            'user' => $request->user()->load('role'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' =>
                ['required', 'string', 'max:150'],

            'email' =>
                [
                    'required',
                    'string',
                    'email',
                    'max:255',
                    Rule::unique('users', 'email')
                        ->ignore($user->id),
                ],
        ]);

        $user->fill($data)->save();

        return redirect()
            ->route('account.edit')
            ->with(
                'status',
                'Your profile has been updated.'
            );
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' =>
                ['required', 'string'],

            'password' =>
                [
                    'required',
                    'string',
                    'confirmed',
                    Password::min(10)
                        ->letters()
                        ->numbers(),
                ],
        ]);

        if (
            ! Hash::check(
                $validated['current_password'],
                $user->password
            )
        ) {
            return redirect()
                ->route('account.edit')
                ->withErrors([
                    'current_password' =>
                        'The current password is incorrect.',
                ]);
        }

        $user->forceFill([
            'password' => $validated['password'],
            'must_change_password' => false,
        ])->save();

        /*
        |--------------------------------------------------------------------------
        | Invalidate other sessions
        |--------------------------------------------------------------------------
        */

        $request->session()->regenerate();

        return redirect()
            ->route('account.edit')
            ->with(
                'status',
                'Your password has been changed.'
            );
    }
}
