<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The missing intake dates reminder raised at sign-in. Dismissing it hides
 * it for the rest of the session; the next sign-in shows it again.
 */
class IntakeReminderController extends Controller
{
    public function dismiss(Request $request): Response
    {
        $request->session()->forget('intake_reminder');

        return response()->noContent();
    }
}
