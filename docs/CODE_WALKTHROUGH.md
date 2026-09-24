# RFA Monitoring System — Code Walkthrough (Phases 7–11)

Written for: the developer maintaining this project. It explains every file
added or changed in Phases 7 through 11, what each part does, and why it is
built that way.

Phases 1–6G are unchanged except where noted in §7.

---

## 0. The shape of the thing

Before Phase 7 the system was a read-only monitoring tool: import a CSV, look
at listings, PCT figures and reports. Phases 7–11 turn it into an operational
system:

```
  Phase 7   Who you are        →  authentication, roles, permissions
  Phase 8   What you can do    →  editable cases, workflow, timeline
  Phase 9   What you should do →  notifications; what you did → audit
  Phase 10  Is the data sound  →  governance, settings, backup
  Phase 11  Is it ready to go  →  readiness checks, UAT, sign-off
```

Three patterns repeat across all five phases. Recognising them makes the rest
obvious:

**1. Catalogue in code, state in the database.**
`Permissions`, `Workflow`, `SystemSettings`, `UatPlan` are PHP classes listing
what exists. The database stores only which ones are *granted*, *current*, or
*passed*. Adding an item is a code change with no data migration, and removing
one never orphans a row.

**2. Derived, not typed.**
`monitoring_bucket` is recalculated from the record's dates on every write.
Data-quality findings are live queries. Readiness checks read the running
system. Nothing that can be computed is stored and trusted.

**3. Refuse to make it worse; report what is already wrong.**
Imported data is imperfect. Rather than blocking all editing until it is
perfect, the system refuses changes that add *new* problems and surfaces
existing ones where they are visible.

---

## 1. Phase 7 — Authentication, Users, RBAC

### 1.1 The permission catalogue

**`app/Support/Permissions.php`**

A `final class` of constants — `dashboard.view`, `rfa.manage`, `users.manage`,
and so on — plus `groups()`, which arranges them with a label, a description
and an accent colour for the role-editing UI.

Three helpers matter:

- `all()` — flattens the groups into a list of keys. Gates are registered from
  this, so a new constant becomes enforceable the moment it is listed.
- `describe($key)` — the human sentence for a key.
- `onlyKnown($array)` — intersects submitted keys with the catalogue. This is
  the choke point that makes it impossible to store a permission the code does
  not understand, whatever a form posts.

### 1.2 Roles

**`database/migrations/2026_09_14_090000_create_roles_tables.php`**

- `roles` — `name`, `slug` (unique), `description`, `is_system`
- `role_permissions` — `role_id` + `permission` string, unique together

Only *granted* permissions are stored. Revoking is a row deletion.

**`app/Models/Role.php`**

- `permissionKeys()` — the granted keys as a flat array
- `grants($permission)` — **returns `true` unconditionally for the
  administrator slug.** This is the single line that makes the administrator
  role un-narrowable. The database can hold zero permission rows for it and it
  still holds everything.
- `syncPermissions($keys)` — filters through `Permissions::onlyKnown()`,
  deletes what is no longer granted, inserts what is new, reloads the relation.

**`app/Models/RolePermission.php`** — a thin pivot model.

### 1.3 Users

**`database/migrations/2026_09_14_090100_add_access_control_to_users_table.php`**

Adds to `users`: `role_id` (FK, null on delete), `office`, `position`,
`status`, `must_change_password`, `deactivated_at`, `last_login_at`,
`last_login_ip`.

`office` deliberately mirrors `rfas.office` so operational scoping stays
possible later.

**`app/Models/User.php`**

- `hasPermission($key)` — **returns `false` if the account is inactive or has
  no role, before consulting the role at all.** Deactivation therefore removes
  access instantly and everywhere, without touching the role.
- `isAdministrator()`, `isActive()`, `roleName()`, `statusLabel()`
- `initials()` — for the avatar chip
- `scopeActive()` — used throughout
- `auditEntries()` / `appNotifications()` — added in Phase 9. The second is
  named `appNotifications` on purpose: `notifications()` already exists on the
  `Notifiable` trait and overriding it would shadow Laravel's own relation.

### 1.4 Gates

**`app/Providers/AppServiceProvider.php` → `registerGates()`**

```php
Gate::before(function (User $user) {
    if (! $user->isActive()) return false;      // hard stop
    return $user->isAdministrator() ? true : null;  // null = keep checking
});

foreach (Permissions::all() as $permission) {
    Gate::define($permission, fn (User $user) => $user->hasPermission($permission));
}
```

Returning `null` from `Gate::before` is the important detail: it means "no
opinion, run the specific gate". Returning `false` would deny everything.

Because every permission is a gate of the same name, routes are guarded with
the framework's own middleware — `->middleware('can:users.view')` — with no
custom middleware to get wrong.

`registerRateLimiters()` defines the `login` limiter: five attempts per minute,
keyed on email **and** IP, so one attacker cannot lock out a real user by
hammering their address from elsewhere.

### 1.5 Session state enforcement

**`app/Http/Middleware/EnforceAccountState.php`**

Appended to the `web` group in `bootstrap/app.php`, so it runs on every
authenticated request. It does two things:

1. If the account is no longer active — logs out, invalidates the session,
   regenerates the CSRF token, redirects to login with a message. This is what
   makes deactivation take effect *mid-session*, not at next sign-in.
2. If `must_change_password` is set — redirects everything except the
   `account.*` routes and `logout` to the account page. The exemption is what
   stops the forced change from being a lockout.

**`bootstrap/app.php`** also sets `redirectGuestsTo(fn () => route('login'))`,
so an unauthenticated request lands on the sign-in page rather than a 500 from
a missing named route.

### 1.6 Controllers

**`Auth/AuthenticatedSessionController.php`**

`store()` runs three gates in order, and the order matters:

1. `Auth::attempt()` — wrong credentials fail first, so the later messages
   never confirm that an address exists
2. account is active — a correct password is *not* enough
3. a role is assigned — an account with no role cannot do anything, so it is
   refused at the door rather than signed in to a system it cannot use

On failure at step 2 or 3 the session is torn down before the exception is
thrown. Then `session()->regenerate()` (session fixation defence),
`recordSignIn()` writes `last_login_at`/`last_login_ip` via `forceFill`, and
`must_change_password` decides the destination.

**`Account/ProfileController.php`**

`update()` allows name and email only — role, office and status stay
administrator-controlled. `updatePassword()` verifies the current password with
`Hash::check`, enforces `Password::min(10)->letters()->numbers()`, clears the
forced-change flag, and regenerates the session.

**`Admin/UserController.php`**

The interesting part is `isLastActiveAdministrator()` and how `update()` uses
it. Two situations lose administrative cover — a role change away from
administrator, and a status change to inactive — and both are checked against
the same guard before anything is saved. `destroy()` uses it too, alongside a
self-deletion check.

An optional password on `update()` always sets `must_change_password = true`:
an administrator-chosen password is a transport mechanism, not a credential.

`officeOptions()` pulls distinct offices from the RFA data so user assignment
uses real operational vocabulary rather than invented options.

**`Admin/RoleController.php`**

`update()` skips `syncPermissions()` entirely for the administrator role.
Combined with `Role::grants()`, that means the administrator's matrix is not
editable and nothing is written for it — belt and braces.

`uniqueSlug()` appends `-2`, `-3` … on collision. `destroy()` refuses system
roles and roles that still have users.

### 1.7 Views

| File | Purpose |
| --- | --- |
| `auth/login.blade.php` | Standalone page (no app layout) — brand panel plus form, with error and status regions |
| `admin/access/index.blade.php` | Directory with search/role/status filters and summary cards |
| `admin/access/_form.blade.php` | Shared create/edit form; `$isEdit` switches labels and whether the password is required |
| `admin/access/create/edit.blade.php` | Thin wrappers supplying the form action and method |
| `admin/roles/index.blade.php` | Role cards with user counts; administrator shows "All N permissions (implicit)" |
| `admin/roles/_form.blade.php` | Permission matrix grouped by `Permissions::groups()`; locked panel for administrator |
| `account/index.blade.php` | Own profile and password; the password card turns amber when a change is required |
| `components/flash.blade.php` | One place for status / warning / validation output, rendered by the layout |

**`components/sidebar.blade.php`** was rewritten from ~620 lines of repeated
markup into a data-driven definition:

```php
['label' => 'Reports', 'route' => 'reports', 'icon' => 'reports',
 'permission' => Permissions::REPORTS_VIEW]
```

Each item renders only when `Route::has($route)` **and** the user holds the
permission. The `Route::has()` check is why Phase 9, 10 and 11 entries could be
listed before their routes existed — they simply stayed hidden until the route
appeared. Icons moved to `components/icons/*.blade.php`.

The visual output is unchanged. Hiding a link is a convenience only; the route
is separately guarded.

**`layouts/app.blade.php`** gained an account menu (avatar, name, role, My
Account, Sign Out) and wraps `@yield('content')` with `<x-flash />`.

### 1.8 Bootstrapping and seeds

**`database/seeders/RolePermissionSeeder.php`** creates the four system roles.
It is re-run safe: an existing role keeps whatever permissions an administrator
has since configured, and only `is_system` is re-asserted.

**`database/seeders/DatabaseSeeder.php`** — `RfaSeeder` was **removed** from
the default chain. It begins `Rfa::query()->delete()`, so a plain
`php artisan db:seed` would have wiped live data. It now requires
`--class=RfaSeeder` to be asked for by name.

**`app/Console/Commands/CreateAdministratorCommand.php`** — `rfa:create-admin`.
Generates a 16-character password when none is given and flags the account for
a forced change. This is the recovery path when everyone is locked out.

---

## 2. Phase 8 — Case Management

### 2.1 Workflow vocabulary

**`app/Support/Workflow.php`**

The twelve workflow statuses in processing order, the three monitoring buckets,
and `dateChain()` — the nine workflow date fields in chronological order with
their labels.

`label()` falls back to `Str::headline()` for unrecognised values, which is
exactly what the existing Phase 4–6 views do, so imported statuses outside the
catalogue still render correctly.

`dateChain()` is the single source of truth for chronology validation *and*
change labelling. Reordering the chain there changes both.

### 2.2 The activity timeline

**`database/migrations/2026_09_14_100000_create_rfa_activities_table.php`** —
`rfa_id`, `user_id`, `type`, `title`, `description`, `changes` (JSON).

This is deliberately *not* the audit trail. The timeline is case-scoped and
written for case officers; the Phase 9 audit trail is system-wide and written
for administrators. Different audiences, different tables.

**`app/Models/RfaActivity.php`** — casts `changes` to array, `actorName()`
falls back to "System" for imports and console work, `accent()` maps the type
to a colour.

### 2.3 Rfa model additions

`activities()`, `interviewer()`, `seado()` relations, plus `statusLabel()`,
`bucketLabel()`, `displayReference()` (docket number, else reference) and
`isDisposed()`. `HasFactory` was added for the test factory.

### 2.4 The service that does the work

**`app/Services/RfaWorkflowService.php`**

`apply()` is the only path by which a case is written, and it always does three
things together:

```php
$rfa->fill($attributes);
$rfa->monitoring_bucket = $this->deriveBucket($rfa);   // never typed
$changes = $this->diff($rfa);                          // from getDirty()
if ($changes === [] && $description === null) return null;  // no-op
$rfa->save();
return $this->log(...);                                // timeline entry
```

Two details worth keeping:

- **`diff()` uses Eloquent's own `getDirty()`/`getOriginal()`** rather than
  comparing raw input. That means `"1500"` against `"1500.00"`, or a date
  resubmitted unchanged, is correctly seen as *no change* — so re-saving a form
  without editing anything writes nothing to the timeline.
- **`presentField()`** renders `status` and `monitoring_bucket` through the
  `Workflow` labels, so the timeline reads "Ongoing → Disposed" rather than
  "ongoing → disposed".

`deriveStatus()` mirrors the importer's logic and is offered to the user as a
*suggestion* only. The status itself stays under explicit control, because a
case officer sometimes needs to hold a case at a stage the dates alone would
not show. The **bucket** is not negotiable — the dashboard depends on it, so it
is always recalculated.

`chronologyIssues()` walks `dateChain()` and reports every date that precedes
an earlier one, plus three business-specific problems: a 2nd conference with no
1st, a case marked disposed with no date, and a disposal date with no official
disposition.

### 2.5 The controller

**`app/Http/Controllers/RfaCaseController.php`**

Five independent save actions rather than one giant form, so permissions can be
split: `updateDetails` (`rfa.manage`), `updateAssignment` (`rfa.assign`),
`updateWorkflow` (`rfa.manage`), `updateConference` (`rfa.manage`),
`updateDisposition` (`rfa.dispose`). Plus `reopen`, `storeNote` and `show`.

**`guardChronology()` is the heart of it:**

```php
$existing   = chronologyIssues($rfa);          // problems now
$candidate  = clone $rfa; $candidate->fill($attributes);
$introduced = array_diff(chronologyIssues($candidate), $existing);
if ($introduced !== []) throw ValidationException::withMessages([...]);
```

It compares problem *sets*, not absolutes. A save is refused only if it would
add something new. An imported record whose interview date already precedes its
filing date can still have its requesting party corrected — the existing
problem is shown as a warning on the page instead of blocking all work.

Other rules encoded here:

- `updateWorkflow()` refuses `status = disposed` when there is no
  `date_disposed`, because that is precisely the state that makes the 30-day
  PCT indeterminate. Disposal has its own action.
- `updateConference()` refuses a 2nd conference with no 1st.
- `updateDisposition()` requires status and date **together**, in both
  directions, and sets `status = disposed` when a date is present.
- `reopen()` requires a reason, refuses a case that is not disposed, and clears
  the disposition through `apply()` — so the old values land in the timeline's
  `changes` rather than vanishing.

`resolveAssignee()` reconciles the two ways a person can be named: pick a
system account (sets both `*_id` and `*_name`) or type a name (clears the id).
Imported records only ever carry a name, so the free-text field has to stay.

### 2.6 The case screen

**`resources/views/rfas/show.blade.php`** — header with badges, a data
consistency panel when `$issues` is non-empty, five PCT checkpoint cards (one per
`PctService::definitions()` rule, colour-keyed by rating), the five editable
sections, and the timeline with a note form.

Permission gating is per section: `$canManage`, `$canAssign`, `$canDispose` are
computed once at the top, inputs get `@disabled(! $can…)`, and submit buttons
are hidden entirely. The server enforces the same split through route
middleware — the UI only avoids offering what will be refused.

**`resources/views/rfas/index.blade.php`** gained a `Case` column with an
**Open** link. That is the only change to the Phase 4 listing.

---

## 3. Phase 9 — Notifications and Audit

### 3.1 Audit trail

**`database/migrations/2026_09_14_110000_create_audit_logs_table.php`**

`user_id` **and** `user_name`. The denormalised name is not redundancy — it is
what keeps the trail readable after an account is deleted and the foreign key
nulls out.

**`app/Services/AuditLogger.php`**

- `REDACTED` — `password`, `remember_token`, `import_payload` are replaced with
  `••••••`. The field still appears in the trail (so you can see a password was
  changed) without the value.
- `NOT_WORTH_AUDITING` — `created_at`, `updated_at`, `last_login_at`,
  `last_login_ip`. Without this, every sign-in would write an "updated" entry
  for the login timestamp and drown the trail.
- `pause()` / `resume()` — static, for bulk work.
- `changesFor($model)` and `summarize($changes)`.

**`app/Observers/AuditableObserver.php`**

Registered in `AppServiceProvider` for `Rfa`, `User` and `Role`. Explicitly
*not* registered for `AuditLog`, `RfaActivity` or `AppNotification` — those are
already history.

The subtle part: it hooks **`updating`**, not `updated`. `getDirty()` and
`getOriginal()` only hold the before/after pair while the save is still
pending; after it completes, the original is gone.

Auth events are wired in `AppServiceProvider::registerAuditTrail()` —
`Login`, `Logout`, `Failed`. The `Failed` listener records the attempted email
even when no user matches, and only passes `$event->user` as a subject when it
is actually a `Model`.

### 3.2 Notifications

**`database/migrations/2026_09_14_110100_create_app_notifications_table.php`**

Named `app_notifications` so it never collides with Laravel's own
`notifications` table. The key column is `dedupe_key` with
`unique(user_id, dedupe_key)`.

**`app/Services/NotificationService.php`**

`push()` is `updateOrCreate` with one rule worth understanding:

```php
$escalated = $notification->severity !== CRITICAL && $severity === CRITICAL;
if ($resetRead || $escalated) $attributes['read_at'] = null;
```

An unchanged alert already read stays read. An alert that *worsens* — a warning
becoming critical — comes back unread. Severity changes are news; repetition
is not.

`scanPct()`:

1. Chunks through undisposed RFAs, evaluating `PctService` on each.
2. `breaches()` turns a PCT result into alertable conditions: an **active**
   stage classified `on` (warning) or `beyond` (critical), and a disposition
   that is `due_today` (warning) or `active_beyond` (critical). Completed
   checkpoints are never alerted — they are history.
3. `recipientFor()` routes stage alerts to the interviewer and disposition
   alerts to the SEADO, each falling back to the other.
4. **Cases with no assigned account are counted, not alerted individually**,
   and become one aggregate alert per watcher. On the live data this is the
   difference between 3 alerts and 374: every imported record carries only a
   text name, so nothing is individually addressable yet.
5. Anything keyed `pct:*` that was not regenerated is **deleted** — the queue
   cleans itself, and re-running is idempotent.

`notifyAssignment()` is called from `RfaCaseController::announceAssignment()`,
which compares the previous and new `interviewer_id` / `seado_id` and only
fires on a genuine change of account. Re-saving the same assignment does not
re-notify.

**`app/Console/Commands/ScanPctCommand.php`** — `rfa:scan-pct`.
**`routes/console.php`** — schedules it daily at 07:00,
`withoutOverlapping()->onOneServer()`.

### 3.3 The screen

**`app/Http/Controllers/OperationsController.php`**

Notifications are **always** scoped to `$request->user()->id`. A notification
is a work item addressed to a person, not a public record —
`authorizeOwnership()` enforces this on the read and dismiss actions with a
403, and `markAllRead()` is scoped by user id in the query itself.

The audit section is loaded only when `audit.view` is held; the page itself
needs `notifications.view`. That is why the two are checked at different
levels — a SEADO gets their queue without the trail.

**`resources/views/operations/index.blade.php`** — summary cards, the
notification list, an Alert Rules panel documenting exactly what triggers what,
and the filterable audit table.

### 3.4 Import integration

**`RfaImportController::store()`** now calls `AuditLogger::pause()` before the
row loop and `resume()` after (including on the rollback path), then writes one
`import` audit entry with the batch totals. Without this, importing 534 rows
would write 534 audit entries.

---

## 4. Phase 10 — Governance, Settings, Backup

### 4.1 Tables

**`database/migrations/2026_09_14_120000_create_governance_tables.php`** —
`import_batches`, `system_settings` (string primary key), `database_backups`.

### 4.2 Data quality

**`app/Services/DataQualityService.php`**

Twelve checks, each a closure over an Eloquent builder:

```php
'missing_date_filed' => [
    'label' => '…', 'description' => '…',
    'impact' => 'No PCT stage can be measured…',
    'severity' => self::SEVERITY_CRITICAL,
    'query' => fn (Builder $q) => $q->whereNull('date_filed'),
],
```

Because a finding is a query and not a stored flag, **correcting a record
clears the finding on the next page load** with nothing to mark resolved and no
state to drift.

Severity encodes consequence, not tidiness:

- **critical** — a PCT figure cannot be computed at all
- **warning** — a figure or report will be wrong or incomplete
- **informational** — expected in this data (missing dockets on TA/NORES rows,
  repeated docket numbers, which are explicitly not unique)

Every check carries an `impact` sentence explaining what it breaks. `samples`
returns up to five records, linked straight to the case screen.

### 4.3 Settings

**`app/Support/SystemSettings.php`** — the closed catalogue. Each key declares
its label, help text, type, default, validation rules and panel group.

Keys deliberately contain **no dots** (`organization_name`, not
`organization.name`): Laravel's validator reads `settings.organization.name` as
a three-level nested path, which would never match a flat key called
`organization.name`.

PCT limits are absent on purpose and shown on the page as fixed business rules.

**`app/Services/SettingsService.php`** — `get()` falls back to the declared
default, `put()` ignores unknown keys entirely and returns the list of keys
that actually changed. Values are cached forever and flushed on write.

**`Admin/SettingsController.php`** builds its validation rules *from the
catalogue*, so a key cannot be submitted that the catalogue does not define,
and a defined key cannot be saved out of range.

Settings drive real behaviour, not decoration:

| Key | Where it takes effect |
| --- | --- |
| `organization_name`, `organization_unit`, `reports_footer_note` | `reports/print.blade.php` |
| `listing_per_page` | `RfaController::index()` default page size |
| `audit_retention_days` | `rfa:prune-audit` |
| `backup_retention_count` | `BackupService::pruneOldBackups()` |

### 4.4 Backups

**`app/Services/BackupService.php`**

A MySQL dump written in **pure PHP** — `SHOW TABLES`, `SHOW CREATE TABLE`, then
chunked `INSERT` statements — rather than shelling out to `mysqldump`. The
reason is the target environment: on a Windows workstation the MySQL client
tools are frequently not on `PATH`, and a backup feature that silently fails is
worse than none.

- `isSupported()` returns false on any non-MySQL driver, and `create()` throws
  rather than writing a file that cannot be restored. (The test suite runs on
  SQLite, so this path is exercised by the tests.)
- Rows are written in chunks of 500, so a large table never has to fit in
  memory.
- `quote()` delegates to `PDO::quote()` for strings.
- On any failure the partial file is deleted and the exception re-thrown.
- `pruneOldBackups()` applies the retention count.
- **There is no restore action.** `restoreCommand()` returns the command as
  text and the view documents the procedure. Overwriting a live database is a
  decision for a console, not a button.

**`app/Models/DatabaseBackup.php`** — `fileExists()` checks the disk, because a
register entry whose file has been deleted is worse than no entry. The
readiness page and the backup list both surface that as "File Missing".

### 4.5 Import history

**`Admin/GovernanceController::importHistory()`** groups `rfas` by
`import_batch_uuid` and left-joins `import_batches` for metadata. Grouping the
records rather than reading the register is what makes batches imported *before
the register existed* still appear — their file and user simply show as "Not
recorded".

**`RfaImportController`** now writes an `ImportBatch` row after a successful
import.

### 4.6 Views

`admin/governance/index.blade.php` (findings, import history, and a Governance
Position panel restating what the system will and will not infer),
`admin/backups/index.blade.php` (register plus a five-step restore procedure),
`admin/settings/index.blade.php` (grouped form plus the fixed-rules panel).

---

## 5. Phase 11 — Readiness, UAT, Handover

### 5.1 Live checks

**`app/Services/ReadinessService.php`**

Nineteen checks across four areas, each returning `pass` / `warn` / `fail`, a
`detail` describing what was actually found, and a `recommendation` explaining
the consequence.

Every one inspects the running system:

- **Environment** — `APP_ENV`, `APP_KEY`, PHP version, `storage/` and
  `bootstrap/cache` writability
- **Security** — `APP_DEBUG`, HTTPS on `APP_URL`, administrator count,
  role-less accounts, outstanding temporary passwords, accounts never used
- **Data** — live PDO connection, pending migrations (migration files diffed
  against the `migrations` table), backup age, audit trail activity
- **Deployment** — `public/build/manifest.json` present, `.env` untracked,
  queue driver, whether the PCT scan has ever produced notifications

`security.administrators` returns `warn` at one administrator and `pass` at two
or more — one lost password should not be able to lock an organisation out of
its own access control.

### 5.2 UAT

**`app/Support/UatPlan.php`** — 38 test cases in six areas, each with `title`,
`steps` and `expected`. In code, so the plan is versioned with the application
it tests.

**`uat_results`** stores only `case_key`, `status`, `notes`, `tested_by`,
`tested_at`. Rewording a case never orphans a result; removing one leaves a row
that is simply no longer rendered.

**`release_signoffs`** stores `cases_passed`, `cases_total` and
`checks_failing` **as they were at the moment of signing**. Recomputing them
later would let an old sign-off appear to cover a state it never saw.

**`Admin/ReadinessController`** — `recordResult()` validates `case_key` and
`status` against the catalogue with `Rule::in()`; `signOff()` freezes the
readiness snapshot. Viewing needs `readiness.view`, recording needs
`readiness.manage`.

### 5.3 Handover documents

- `docs/OPERATIONS_MANUAL.md` — running the system day to day
- `docs/DEPLOYMENT.md` — deployment, rollback, scheduler, security checklist
- `docs/CODE_WALKTHROUGH.md` — this document

---

## 6. Cross-cutting notes

### Why `can:` middleware and not a custom one

Every permission is registered as a gate of the same name, so
`->middleware('can:reports.export')` is enough. There is no bespoke
authorization middleware to keep in step with the catalogue, and
`Gate::before` handles the administrator case once for everything.

### Where authorization actually happens

Three layers, and only two of them are load-bearing:

1. **Route middleware** — the real gate. A direct URL request is refused here.
2. **Controller checks** — for things route middleware cannot express, such as
   "this notification belongs to you".
3. **Blade `@can` / `$canManage`** — convenience only. Hiding a button is not
   security, and the tests assert the 403 rather than the absence of a link.

### Testing

126 → 142 tests, all against SQLite in memory with `RefreshDatabase`.

`pdo_sqlite` and `sqlite3` were enabled in `C:\php84\php.ini` (backed up to
`php.ini.bak-rfa-2026-09-14`); they were present but commented out, and
`phpunit.xml` was already configured for SQLite.

Factories: `UserFactory` gained `withRole()`, `administrator()`, `inactive()`,
`mustChangePassword()`. `RfaFactory` is new with `ongoing()` and `disposed()`
states.

---

## 7. Changes to pre-existing (Phase 1–6G) code

| File | Change | Why |
| --- | --- | --- |
| `routes/web.php` | Rewritten — every route behind `auth` and a `can:` gate | Phase 7 |
| `bootstrap/app.php` | Guest redirect, `EnforceAccountState` appended to `web` | Phase 7 |
| `app/Models/User.php` | Role, status, permission logic, relations | Phases 7, 9 |
| `app/Models/Rfa.php` | Relations, label helpers, `HasFactory` | Phase 8 |
| `app/Providers/AppServiceProvider.php` | Gates, rate limiter, observers, auth listeners | Phases 7, 9 |
| `resources/views/layouts/app.blade.php` | Account menu, flash region | Phase 7 |
| `resources/views/components/sidebar.blade.php` | Data-driven, permission-gated, account footer | Phase 7 |
| `resources/views/rfas/index.blade.php` | `Case` column with Open link | Phase 8 |
| `app/Http/Controllers/RfaController.php` | Default page size from settings | Phase 10 |
| `app/Http/Controllers/RfaImportController.php` | Audit pause/resume, batch summary, `ImportBatch` row | Phases 9, 10 |
| `resources/views/reports/print.blade.php` | Heading, sub-heading and footer from settings | Phase 10 |
| `database/seeders/DatabaseSeeder.php` | No longer calls the destructive `RfaSeeder` | Phase 7 |
| `database/factories/UserFactory.php` | Access-control states | Phase 7 |
| `tests/Feature/ExampleTest.php` | Asserts the guest redirect instead of a 200 | Phase 7 |

**Untouched:** `PctService` and its tests, `PctProcessController`,
`DashboardController`, `ReportController`, and the Phase 6 report views apart
from the settings-driven header and footer. The PCT rules were correct and
tested; Phases 7–11 build around them rather than through them.
