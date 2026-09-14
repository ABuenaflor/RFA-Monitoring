<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Services\AuditLogger;
use App\Services\SettingsService;
use App\Support\SystemSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly AuditLogger $auditLogger
    ) {
    }

    public function index(): View
    {
        return view('admin.settings.index', [
            'groups' => SystemSettings::grouped(),

            'values' => $this->settings->values(),

            'lastChanged' => SystemSetting::query()
                ->with('editor')
                ->latest('updated_at')
                ->first(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        /*
        |--------------------------------------------------------------------------
        | Validation comes from the catalogue
        |--------------------------------------------------------------------------
        |
        | Each setting declares its own rules, so an unknown key can never be
        | submitted and a known key can never be saved out of range.
        |
        */

        $rules = [];

        foreach (SystemSettings::definitions() as $key => $definition) {
            $rules['settings.' . $key] = $definition['rules'];
        }

        $validated = $request->validate($rules);

        $changed = $this->settings->put(
            $validated['settings'] ?? [],
            $request->user()
        );

        if ($changed !== []) {
            $this->auditLogger->record(
                event: 'updated',
                recordLabel: 'System settings',
                summary: 'Changed: ' . implode(', ', $changed),
                changes: array_map(
                    fn (string $key) => [
                        'field' => $key,
                        'to' => (string) $this->settings->get($key),
                    ],
                    $changed
                )
            );
        }

        return redirect()
            ->route('admin.settings.index')
            ->with(
                'status',
                $changed === []
                    ? 'No settings were changed.'
                    : count($changed) . ' setting(s) updated.'
            );
    }
}
