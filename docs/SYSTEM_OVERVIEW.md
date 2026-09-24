# RFA Monitoring System — System Overview

The RFA Monitoring System tracks Requests for Assistance (RFAs) — labor
complaints/requests filed with a DOLE regional office — from the moment they
are filed through interview, validation, turnover to Labor Relations, SEADO
assignment, conference, and final disposition. It gives case officers a
single record per case with a live timeline, automatically measures the
office's Prescribed Case Time (PCT) deadlines against that record, raises
in-app (and optionally email) alerts when a deadline is at risk or breached, and produces the
reports, exports, and audit trail that management and compliance reviews
need. This document explains every part of the system and how they fit
together, end to end.

---

## 1. Tech Stack

| Layer | Technology |
|---|---|
| Language / runtime | PHP 8.3+ |
| Framework | Laravel 13 (`laravel/framework ^13.8`) |
| Frontend | Server-rendered Blade views, Alpine.js 3 for interactivity, Tailwind CSS 4 for styling |
| Build tooling | Vite 8 (`laravel-vite-plugin`) |
| Charts | Chart.js 4 (used on the dashboard) |
| Database | MySQL (the backup service explicitly only supports the `mysql` driver) |
| Auth | Session-based (`config/auth.php` — `web` guard, `driver: session`), no API tokens or SPA layer |
| Scheduling | Laravel's task scheduler (`routes/console.php`), intended to be driven by `schedule:work` or a Windows Task Scheduler entry calling `schedule:run` every minute |

There is no SPA framework, no REST/JSON API, and no queue worker required for
normal operation (the readiness checks flag the default `sync` queue driver
as an acceptable warning at this scale).

---

## 2. Roles & Permissions

Access control is a simple two-level model: a **User** belongs to exactly one
**Role**, and a Role is a named bundle of **permission** strings
(`App\Support\Permissions`). Every protected route is gated with Laravel's
`can:<permission>` middleware — the UI hides things a role can't do, but the
route itself is what actually enforces it (`Gate::define` is registered per
permission key in `AppServiceProvider`).

The **Administrator** role is special: `Gate::before()` short-circuits to
`true` for any active administrator, so it implicitly holds every permission
and can never be locked out of its own access-control screens, even if no
row exists for it in `role_permissions`.

Four system roles are seeded by `RolePermissionSeeder` (roles are editable
afterwards, and new custom roles can be created via **Roles & Permissions**
in the UI):

| Role | Intended purpose | Key permissions granted |
|---|---|---|
| **Administrator** | Full system access | Everything, implicitly |
| **SEADO** | Conference handling, disposition, reporting | Dashboard, RFA view, PCT view, RFA manage, RFA assign, RFA dispose, reports view/export, notifications view |
| **Interviewer** | Interview-stage processing | Dashboard, RFA view, PCT view, RFA manage, reports view, notifications view |
| **Viewer** | Read-only monitoring | Dashboard, RFA view, PCT view, reports view |

The full permission catalogue (`App\Support\Permissions`), grouped as they
appear in the role-editing matrix:

| Group | Permission key | What it gates |
|---|---|---|
| Monitoring & Visibility | `dashboard.view` | The KPI dashboard, including the **RFA Filing Trend** line chart (`DashboardController::filingTrend()`): RFAs filed per day over the last 5 days ending today — reaching back into the previous month when needed — one line for On-site and one for Online (`mode_of_filing`, normalised); filings with no mode are counted in a note, not plotted |
| | `rfa.view` | The RFA master listing and case detail page. The Total / Pending / Ongoing / Disposed cards on the listing and the Dashboard link to the listing filtered to that group (`?monitoring_bucket=`); without `rfa.view` the dashboard cards are not links |
| | `pct.view` | The PCT Process Monitoring screen |
| Case Management | `rfa.manage` | Edit case information, workflow dates, conference dates, add timeline notes |
| | `rfa.assign` | Assign/reassign interviewer and SEADO |
| | `rfa.dispose` | Record official disposition, reopen a disposed case |
| Reporting | `reports.view` | The Reports screen |
| | `reports.export` | CSV export and the print report |
| Data Intake | `import.manage` | Upload and process CSV imports — **Administrator only**: cannot be granted to any other role (`Permissions::adminOnly()`) |
| Users & Access | `users.view` | View the user directory |
| | `users.manage` | Create/edit/deactivate/delete users |
| | `roles.manage` | Create/edit roles and their permission sets |
| Notifications & Audit | `notifications.view` | The Notifications & Audit screen (own notifications) |
| | `audit.view` | The system-wide audit trail section of that screen |
| Administration | `governance.view` | Data Governance (import history + data-quality findings) |
| | `governance.manage` | (Reserved for resolving data-quality findings) |
| | `backup.view` | View the database backup register |
| | `backup.manage` | Create/download/delete backups |
| | `settings.manage` | Change system settings |
| QA & Release Readiness | `readiness.view` | View live readiness checks and UAT status |
| | `readiness.manage` | Record UAT case results and release sign-offs |

**Account-level safeguards** (independent of the permission system, enforced
in `UserController` and `EnforceAccountState` middleware):

- A user can never deactivate or delete their own account.
- The system will not allow the last active Administrator to be demoted,
  deactivated, or deleted.
- A deactivated account is signed out immediately on its very next request,
  even mid-session — the middleware checks `is_active` on every request.
- An account created (or password-reset) by an administrator carries
  `must_change_password = true`; every page redirects such a user to
  **My Account** until they set their own password.
- Login is rate-limited to 5 attempts/minute per email+IP.

---

## 3. Domain Model — What an RFA Record Contains

An RFA (`App\Models\Rfa`, table `rfas`) is one wide record per case. Fields
fall into these groups:

- **Identification**: `reference_no` (unique internal ID; auto-generated as
  `RFA-SRC-<hash>` for imported rows with none supplied), `docket_no`,
  `office`.
- **Parties**: `requesting_party`, `responding_party`, `company_address`,
  `contact_no`, `industry`, `industry_code`, `size_of_enterprise`,
  `total_employment`, `filer_class`, `issues` (free text).
- **Filing**: `mode_of_filing` (online/onsite, derived from the docket
  suffix or source status if not given explicitly), `date_filed`,
  `date_ta_nores`.
- **Interviewer stage**: `interviewer_id` (FK to `users`, optional),
  `interviewer_name` (free text — always kept in sync with the linked
  account when one is chosen), `date_assigned_interviewer`, `date_interview`,
  `date_validated`.
- **Labor Relations handoff**: `date_turned_over_lr`.
- **SEADO stage**: `seado_id`, `seado_name`, `date_assigned_seado`.
- **Conference**: `date_initial_conference`, `date_second_conference`,
  `date_both_parties_appeared`.
- **Workforce/impact figures**: `workers_involved`, `male_workers`,
  `female_workers`, `workers_benefited`, `monetary_benefit`.
- **Disposition**: `disposition_status` (the system's own official
  disposition), `disposition_mode` (the *raw* mode/status imported from the
  source spreadsheet — deliberately kept separate and never substituted for
  the official one), `date_disposed`.
- **System bookkeeping**: `status` (the internal 12-step workflow status,
  see §4), `source_case_status` (whatever the originating spreadsheet said —
  kept verbatim, distinct from `status`), `monitoring_bucket` (`pending` /
  `ongoing` / `disposed` — the coarse dashboard grouping, always derived, never
  hand-set), `import_batch_uuid` and `import_payload` (the original CSV row,
  as JSON, for full traceability), `source_row_key` (a SHA-256 identity hash
  used to detect the "same" record across repeated imports).

Two related tables round out the case record:

- **`rfa_activities`** (`App\Models\RfaActivity`) — a human-readable,
  per-case timeline: every save through the case controller writes an entry
  (`type`: created / details / assignment / workflow / conference /
  disposition / reopened / note) with a `changes` JSON blob of field-level
  before/after values. This is explicitly *not* the same thing as the
  system-wide audit trail (§7) — the timeline is written for the case
  officer working that file; the audit trail is written for administrators
  reviewing the whole system.
- **`import_batches`** (`App\Models\ImportBatch`) — one row per CSV upload
  (file name, uploader, rows processed/created/updated/duplicates skipped).

---

## 4. The RFA Case Lifecycle

### 4.1 Workflow statuses

The system's internal vocabulary for where a case stands lives in
`App\Support\Workflow` and is stored in `rfas.status`. In processing order:

1. **Newly Filed** (`newly_filed`)
2. **For Interviewer Assignment** (`for_interviewer_assignment`)
3. **For Validation** (`for_validation`)
4. **Validated** (`validated`)
5. **For Turnover to Labor Relations** (`for_turnover`)
6. **For SEADO Assignment** (`for_seado_assignment`)
7. **Assigned to SEADO** (`assigned_to_seado`)
8. **For Notice Preparation** (`for_notice_preparation`)
9. **For Conference** (`for_conference`)
10. **Ongoing** (`ongoing`)
11. **For Disposition** (`for_disposition`)
12. **Disposed** (`disposed`)

A case officer sets `status` explicitly through the case's **Workflow
Progress** form; it is never silently overwritten. However, the system does
compute a **suggested status** from whichever dates are actually filled in
(`RfaWorkflowService::deriveStatus()` — disposed if `date_disposed` is set,
else "For Conference" if a conference date exists, else "Assigned to SEADO"
if `date_assigned_seado` is set, and so on backwards to "For Interviewer
Assignment" as the default) and shows it on the case page as a hint, without
forcing the officer to accept it.

Separately, every case also carries a **monitoring bucket**
(`pending` / `ongoing` / `disposed`) — a coarse, *always re-derived* grouping
used for dashboard counts and colour-coding. It is computed every time a case
is saved (`RfaWorkflowService::deriveBucket()`): disposed if
`date_disposed` is set or status is `disposed`; ongoing if any workflow date
beyond filing exists (assignment, interview, validation, turnover, SEADO
assignment, either conference date); pending otherwise. It is never hand-typed,
so it cannot drift out of sync with the underlying dates.

### 4.2 Stage by stage

| Stage | Who acts | What's captured | Route / permission |
|---|---|---|---|
| **1. Filing** | Front-line intake (often via CSV import from another office system, or manual entry) | `reference_no`, `docket_no`, parties, `date_filed`, `mode_of_filing`, workforce figures, issues | `rfas.details` (`rfa.manage`) |
| **2. Interviewer Assignment** | Supervisor/SEADO with `rfa.assign` | Links `interviewer_id` (system account) or a free-text `interviewer_name`, sets `date_assigned_interviewer` | `rfas.assignment` (`rfa.assign`) — **triggers a notification** to the newly assigned interviewer |
| **3. Interview & Validation** | The assigned interviewer, `rfa.manage` | `date_interview`, `date_validated`, updates to case details | `rfas.workflow` / `rfas.details` (`rfa.manage`) |
| **4. Turnover to Labor Relations** | Interviewer/supervisor | `date_turned_over_lr` | `rfas.workflow` (`rfa.manage`) |
| **5. SEADO Assignment** | Supervisor with `rfa.assign` | Links `seado_id`/`seado_name`, `date_assigned_seado` | `rfas.assignment` (`rfa.assign`) — **triggers a notification** to the newly assigned SEADO |
| **6. Conference(s)** | The assigned SEADO, `rfa.manage` | `date_initial_conference`, `date_second_conference` (blocked unless a first-conference date already exists), `date_both_parties_appeared` | `rfas.conference` (`rfa.manage`) |
| **7. Disposition** | SEADO / authorized officer with `rfa.dispose` | `disposition_status` + `date_disposed` (the two are enforced as a pair — you cannot save one without the other), optional `disposition_mode`, `monetary_benefit`, `workers_benefited`. Saving a valid pair automatically sets `status = disposed` and re-derives the bucket to `disposed` | `rfas.disposition` (`rfa.dispose`) |
| **8. Reopen (exception path)** | `rfa.dispose` holder | Clears `date_disposed`/`disposition_status`, sets status back to `for_disposition`, requires a written reason; the prior disposition values remain visible as history on the timeline, not erased | `rfas.reopen` (`rfa.dispose`) |

Every one of these saves is funneled through a single service,
`RfaWorkflowService::apply()`, so that three things always happen together:
the record is updated, the monitoring bucket is re-derived, and a
timeline entry is written recording exactly which fields changed and their
before/after values.

### 4.3 Chronology guarding

The system maintains an ordered "date chain" (filed → assigned to
interviewer → interview → validated → turned over to LR → assigned to
SEADO → 1st conference → 2nd conference → disposed) and checks every save
against it. **New** problems (e.g., setting an assignment date earlier than
the filing date, or a 2nd conference date with no 1st) are rejected outright
with a validation error naming the conflicting dates. Problems that already
exist in historical/imported data are *not* retroactively blocked — they are
surfaced as visible warnings on the case page instead, so an officer can fix
them deliberately rather than being locked out of unrelated edits. This same
chronology check also flags: a case marked disposed with no `date_disposed`;
a `date_disposed` with no official disposition status; and conference dates
that fall after disposition.

### 4.4 CSV import as an alternate filing path

Instead of typing a case in by hand, records can arrive in bulk via CSV
import (§9) — the importer normalizes headers, maps to all the same fields,
derives a workflow status and monitoring bucket from whatever dates the
source file provides, and either creates a new `Rfa` or updates an existing
one (matched first by a stable content hash `source_row_key`, then by
`reference_no`) so re-importing the same export is idempotent rather than
duplicating cases.

---

## 5. PCT (Prescribed Case Time) Deadline Tracking

PCT is the office's internal service-level clock. All calculation logic
lives in `App\Services\PctService`, evaluated fresh every time a case is
viewed, reported on, or scanned — nothing is pre-computed and stored, so it
is always accurate as of "now" (or an explicit `asOf` date, used for
historical/point-in-time evaluation).

The service tracks **five checkpoints** per case, in workflow order
(`PctService::definitions()`), each with its own limit:

| # | Checkpoint (`key`) | Start → End | Limit |
|---|---|---|---|
| 1 | Date Filed - Interviewer Assignment (`filing_assignment`) | `date_filed` → `date_assigned_interviewer` | On-site: same day (0) · Online: 2 days, from `mode_of_filing` |
| 2 | Interviewer Assignment - Date Interviewed (`assignment_interview`) | `date_assigned_interviewer` → `date_interview` | 3 days |
| 3 | Date Interviewed - SEADO Assignment (`interview_seado`) | `date_interview` → `date_assigned_seado` | 3 days |
| 4 | SEADO Assignment - 1st Conference (`seado_conference`) | `date_assigned_seado` → `date_initial_conference` | 10 days |
| 5 | 1st Conference - Date Disposed (`conference_disposed`) | `date_initial_conference` → `date_disposed` | 30 days |

Elapsed calendar days (start day = day 0) are classified against the
checkpoint's own limit (`PctService::classify()`):

- **Beyond PCT** — past the limit.
- **On PCT** — the deadline day itself; still compliant.
- **Nearing PCT** — the day before the deadline, or the last 3 days before
  it for limits of 10+ days (days 7–9 of 10, 27–29 of 30). The same-day
  rule has no nearing day: day 0 is On PCT, day 1 is Beyond.
- **Within PCT** — anything earlier.

`mode_of_filing` is normalised (`onsite`/`on-site`/`walk-in` → on-site,
`online` → online). A blank or unknown mode makes checkpoint 1
**`missing_mode`**: it is not rated and is listed as a data issue.

Each checkpoint can be in one of several *states*, not just a day count:

- **`active`** — the clock is currently running (start date present, end
  date not yet reached).
- **`completed`** — both start and end date are present; this is a
  historical result used for compliance-rate reporting, not an alarm.
- **`missing_start`** — the checkpoint's start date itself is missing (e.g.
  no `date_interview`), so nothing can be measured.
- **`missing_mode`** — checkpoint 1 only: the mode of filing is unknown.
- **`missing_end`** — the end date is missing, but there's clear evidence the
  case has already moved past this checkpoint (a later workflow date exists,
  the status is past that step, or the case is disposed). This is
  deliberately **not** treated as an ageing active timer — a historical
  import missing one date should not show as perpetually "Beyond PCT"; it is
  flagged as a data-quality gap instead (see §11, Data Governance).
- **`invalid`** — the recorded end date is earlier than the start date.

A sixth, purely informational figure — **Total Processing Duration**
(`date_filed` → `date_disposed`) — is also computed, but is explicitly *not*
assigned a PCT compliance verdict; it is reporting-only.

**Where PCT results surface:**
- The case detail page shows all five checkpoints for that one case.
- **Process Cycle Time** (`/pct-process/cycle-time`, route `pct-cycle-time`,
  `pct.view`; sidebar: PCT Process → Process Cycle Time) explains the
  process visually: one card per checkpoint (where its clock starts and
  stops, its limit, and live stats — running now, running late, average
  days when completed, on-time rate), then a per-case timeline showing the
  start date, days taken/so far and rating of every step. Filters: office
  (same list as PCT Monitoring), open/disposed/all cases, and search.
- **PCT Process Monitoring** (`/pct-process`, `pct.view`) lists every active
  timer system-wide, sorted by urgency (Beyond → On → Nearing → Within),
  plus a historical compliance-rate summary and a dedicated **data issues**
  table for cases whose PCT cannot currently be computed. An **Office**
  dropdown at the top (`?office=`, values from `config/offices.php`, e.g.
  `RO-V-APFO`) limits the whole page — cards, tables and statistics — to one
  office; the search/checkpoint/status filters then apply within it. The
  five summary cards (Active Timers, Within, Nearing, On PCT, Beyond) are
  links: each opens the active-timer table filtered to that group
  (`?pct_status=`, keeping the office, clearing search/checkpoint) and
  scrolls to it; Active Timers shows every group again.
- **Reports** (§8) roll PCT results up into aggregate compliance percentages,
  filterable by SEADO, office, date range, etc.
- CSV report export includes per-record PCT status, days, deadlines,
  remaining/overdue days for every checkpoint.

---

## 6. Notifications

Notifications are **in-app** (`App\Services\NotificationService`,
`App\Models\AppNotification`), with an optional **email channel for PCT
alerts** (`App\Services\PctEmailService`, see below). There is no SMS or
push channel. A notification is a private work item
addressed to exactly one user; nobody can read or mark-read another user's
alerts (enforced by ownership check in `OperationsController`).

Two ways a notification is created:

1. **On-assignment trigger** — the instant a case's `interviewer_id` or
   `seado_id` actually *changes* to a different account (re-saving the same
   assignment does not re-notify), `RfaCaseController::announceAssignment()`
   fires `NotificationService::notifyAssignment()`, creating an `info`
   severity notification for that officer linking straight to the case.
2. **PCT scan** — `NotificationService::scanPct()`, run either on a
   schedule or manually. It walks every non-disposed RFA, evaluates all
   five PCT checkpoints, and raises a notification for each active breach:
   - A checkpoint on its deadline day ("On PCT") → **warning**, "due today."
   - A checkpoint past its deadline ("Beyond PCT") → **critical**,
     "breached," with the overdue day count.
   - Categories are `pct_<checkpoint key>` (e.g. `pct_seado_conference`).
   - Checkpoints 1–3 alert the assigned **interviewer** (falling back to
     the SEADO if no interviewer is linked); checkpoints 4–5 (from SEADO
     assignment on) alert the assigned **SEADO** (falling back to the
     interviewer). If neither exists, the case is added to an **unassigned
     backlog** count instead, and a single aggregated notification ("N
     unassigned cases breaching X") is sent to every "watcher" — every
     active Administrator plus anyone whose role grants `rfa.assign`.
   - The scan is **idempotent**: notifications are keyed by a stable
     dedupe key (`pct:{stage}:{rfa_id}`), so running it twice does not
     duplicate alerts — an existing alert is simply refreshed, and any
     alert whose underlying condition has since cleared is deleted. An
     already-read alert that escalates (warning → critical) is marked
     unread again so it isn't missed.

**PCT email alerts** — when `PCT_EMAIL_ENABLED=true` (off by default;
`config/notifications.php`), the same scan hands its per-case results to
`PctEmailService`, which sends the assigned officer one digest email
(`App\Mail\PctAlertDigest`, template `resources/views/emails/pct-alert-digest.blade.php`)
listing their cases that reached **Nearing PCT**, **Due today** (the
deadline day) or **Beyond PCT** on any of the five checkpoints. Each case is emailed once per
level per officer, recorded in the `pct_email_deliveries` table
(`App\Models\PctEmailDelivery`), so daily scans don't repeat an alert.
Unassigned backlogs are not emailed. Mail is sent synchronously; a failed
send is logged, not recorded, and retried on the next scan. The scan's
result (command output and the on-demand flash message) includes emails
sent and failed.

**When the PCT scan runs**: it is scheduled daily at **07:00**
(`Schedule::command('rfa:scan-pct')->dailyAt('07:00')` in
`routes/console.php`, backed by the `rfa:scan-pct` Artisan command). This
requires either `php artisan schedule:work` to be running continuously, or a
Windows Task Scheduler entry invoking `php artisan schedule:run` every
minute — this is *not* automatic out of the box on a bare deployment, and the
system's own readiness checks (§11) flag "no PCT notifications exist yet" as
a warning if that scheduler is not actually wired up. Any user can also
trigger an on-demand rescan from the Notifications screen (`operations.scan`).

The **Notifications & Audit** screen (`/operations`, gated on
`notifications.view`) shows: unread count, "due today" count, "overdue"
count, and assignment-notification count for the signed-in user; a list of
their notifications (critical sorted first); mark-one-read, mark-all-read,
and dismiss actions; and, only for users who also hold `audit.view`, the
system audit trail with filtering (see §7).

---

## 7. Audit Log (System-Wide Trail)

Distinct from the per-case timeline (§4), the audit trail
(`App\Models\AuditLog`, written via `App\Services\AuditLogger`) is a
system-wide, administrator-facing record of *everything material that
changed*, automatically — nobody has to remember to log anything.

**How it's wired up**: `App\Observers\AuditableObserver` is registered in
`AppServiceProvider::boot()` against three model classes —
`Rfa`, `User`, and `Role` — via Eloquent's `observe()`. Every `created`,
`updating` (captured pre-save, while dirty/original values are still
available), and `deleted` event on any of those three models writes an
`AuditLog` row automatically, with:
- who did it (`user_id`/`user_name`, falling back to "System" for console
  actions),
- what kind of record and a human label (e.g. "RFA RFA-2026-00123", "User
  Jane Doe <jane@example.gov>"),
- a short change summary and a full field-level before/after `changes` JSON,
- the client IP and user agent (or `"console"` for CLI-triggered changes).

Notably **excluded** from this auto-observer (so the trail doesn't just
duplicate history that already exists elsewhere): the audit log itself, the
per-case `rfa_activities` timeline, and `AppNotification` rows.

**Authentication events** are separately wired via Laravel's own `Login`,
`Logout`, and `Failed` events (also in `AppServiceProvider`), producing
`login`, `logout`, and `login_failed` entries.

**Bulk CSV imports** are handled specially: `AuditLogger::pause()` /
`::resume()` suspend the automatic per-record observer during an import (so
a 2,000-row import doesn't write 2,000 near-identical audit rows), and the
import controller writes one summary entry instead (rows processed/created/
updated/duplicates skipped).

**Sensitive data protection**: `password`, `remember_token`, and
`import_payload` are always redacted to `••••••` rather than logged in
plain text, and purely bookkeeping columns (`created_at`, `updated_at`,
`last_login_at`, `last_login_ip`) are excluded from change tracking entirely
so they don't clutter the trail.

**Retention**: `rfa:prune-audit` (an Artisan command, not currently
scheduled by default) deletes entries older than the
`audit_retention_days` system setting (§12); a setting of `0` keeps the
trail forever.

**Viewing it**: the audit section of `/operations`, gated on `audit.view`,
supports filtering by event type, actor, and date range, paginated 25 at a
time.

---

## 8. Reports & Exports

The Reports screen (`/reports`, `reports.view`) and its print/export
siblings are built in `App\Http\Controllers\ReportController` on top of the
same filtered RFA query (search text, date range, office, SEADO, monitoring
bucket, workflow status, source status, conference level, disposition
status/mode). It presents:

- **Overview summary**: total/pending/ongoing/disposed counts, total
  monetary benefit, workers involved/benefited across the filtered set.
- **PCT rollups**: active-timer counts by classification (within/nearing/
  on/beyond) across all five checkpoints, and a historical compliance card
  per checkpoint.
- **SEADO performance panel** — appears when a specific SEADO is selected in
  the filter: their case volume by bucket, disposition rate, workforce/
  monetary totals, and their own PCT compliance breakdown for all five
  checkpoints.
- **Management breakdowns**: cases by office, by source case status, by
  official disposition, by source disposition mode.
- **Conference analytics**: how many cases have had no conference / a first
  conference only / a second conference, how many were disposed at/after
  each conference stage, and a count of conference-related chronology
  issues (2nd without 1st, out-of-order dates, conference after disposal).
- **Paginated record table** with per-row PCT status.

**Exports**:
- **CSV export** (`reports.export`, `reports.export` permission) streams
  every filtered record (not just the current page) with a UTF-8 BOM for
  Excel compatibility, including reference/docket/office, parties, filing
  date, both source and workflow status, assignment info, conference dates,
  disposition status/mode/date, status/days/deadline/overdue days for each
  of the five PCT checkpoints, total processing days, and workforce/monetary
  figures. The on-screen table has one column per checkpoint; the print
  report lists all five in a single compact "PCT (1–5)" column.
- **Print report** (`reports.print`) renders a dedicated print-oriented view
  (an "8×13" landscape-format operational report, per the UAT test plan)
  with the same filters applied, summary totals, disposition PCT summary,
  and conference summary — intended to be printed or saved as PDF directly
  from the browser (no separate PDF library is used).

A separate, related surface is **Data Governance** (§11), which reports on
data *quality* rather than case *progress*, and its own CSV export of
data-quality findings.

---

## 9. Bulk Import (CSV)

`App\Http\Controllers\RfaImportController` (`/imports`, `import.manage`)
lets the System Administrator upload a `.csv`/`.txt` file (max 20 MB) exported from
an upstream case-tracking spreadsheet. Processing, per row:

1. Header row is cleaned (BOM stripped) and normalized into snake_case
   tokens; duplicate normalized headers reject the whole file up front.
2. Each data row is read into two parallel structures: the **original**
   values (kept verbatim as `import_payload` JSON, for full traceability)
   and a **normalized** version used for mapping.
3. A wide set of column-name aliases is checked per field (e.g.
   `date_interview` / `date_of_interview` / `interview_date` all map to the
   same column), so the importer tolerates real-world variation in the
   source export's headers.
4. Dates are parsed against several common formats (`Y-m-d`, `m/d/Y`,
   `M j, Y`, etc.); legacy `0000-00-00` sentinels are treated as null.
5. Workflow status and monitoring bucket are derived from whichever dates
   and source-status text are present, using the same forward-progression
   logic described in §4, unless the CSV supplies an explicit status column.
6. A **stable identity key** (`source_row_key`, a SHA-256 hash of
   office+docket+date filed+both parties) is computed per row — because the
   real source data contains duplicate docket numbers across different
   cases, docket number alone cannot be used as identity. Rows matching an
   existing `source_row_key` (or, for compatibility with earlier imports, an
   existing `reference_no`) **update** that record rather than creating a
   duplicate; rows repeated *within the same file* are skipped and counted
   separately.
7. The whole file runs inside one database transaction — if anything throws
   partway through, nothing is saved.
8. Per-record audit logging is paused for the duration of the import
   (§7) and replaced with a single audit summary entry for the whole batch.
9. An `ImportBatch` row records the file name, uploader, and
   processed/imported/created/updated/duplicate counts; every imported `Rfa`
   carries that batch's UUID in `import_batch_uuid`, so a batch's records
   can always be reviewed together later (the Imports screen lets you browse
   a specific batch's rows, and Data Governance lists recent batches).

---

## 10. Dashboard

The Dashboard (`/dashboard`, `dashboard.view`, `App\Http\Controllers\
DashboardController`) is intentionally lightweight at the controller level:
it counts RFAs by `monitoring_bucket` (pending / ongoing / disposed) and
their percentage share of the total, then hands that to the view, which
renders it (with Chart.js) alongside quick-action links such as **Import
CSV**. It is the at-a-glance landing page every authenticated user sees
first (`Route::redirect('/', '/dashboard')`), while the heavier, filterable
breakdowns (PCT compliance, office/SEADO breakdowns, conference analytics)
live on the dedicated Reports and PCT Process Monitoring screens instead.

---

## 11. Release Readiness / Governance / UAT

This is an internal **QA and deployment-readiness tracking feature** — it is
about the health of the *software system itself and its data*, not about the
progress of any individual RFA case. It has two halves:

### 11.1 Data Governance (`/admin/governance`, `governance.view`)

`App\Services\DataQualityService` runs a fixed catalogue of standing checks
directly against the live `rfas` table every time the page loads (nothing is
pre-computed or stored — a finding disappears the instant the underlying
record is corrected). Checks include: missing Date Filed (critical — no PCT
stage can be measured at all); disposed without a Date Disposed (critical);
disposed before filed (critical); interview before assignment (warning);
2nd conference without a 1st (warning); conference after disposal (warning);
disposal without an official disposition status (warning); missing docket
number / missing office / missing party info (info/warning); active cases
with no assigned account (info — these fall back to the aggregate
unassigned-backlog PCT alert, §6); repeated docket numbers (info only,
since docket numbers are not guaranteed unique — `reference_no` remains the
true identifier). The page also lists recent **import batch** history
(joining `import_batches` metadata with the batch UUIDs actually present on
`rfas` records, so batches from before the table existed still show up), and
findings can be exported to CSV.

### 11.2 Release Readiness / UAT (`/admin/readiness`)

Two independent things live here:

- **Live system readiness checks** (`App\Services\ReadinessService`,
  `readiness.view`) — real-time checks against the *running* system, grouped
  into Environment (APP_ENV, APP_KEY set, PHP ≥ 8.3, storage/bootstrap-cache
  writable), Security (debug mode off, HTTPS, administrator account count —
  warns if only one exists, accounts without a role, outstanding temporary
  passwords, never-signed-in accounts), Data (DB connectivity, pending
  migrations, recency of the last backup — fails if none ever taken, warns
  if older than 7 days — and whether the audit trail is actually recording
  anything), and Deployment (built frontend assets present, `.env` not
  tracked by git, queue driver, whether the scheduled PCT scan has actually
  produced any notifications yet — a proxy for "is the scheduler running").
  Each check reports pass/warn/fail plus a plain-language recommendation.

- **UAT test plan & results** (`App\Support\UatPlan`, `readiness.manage` to
  record results) — a fixed, code-defined test plan (not stored in the
  database, so editing test case wording never orphans a result) covering
  Authentication & Access, Case Management, PCT & Reporting, Notifications &
  Audit, Administration & Data, and Security & Deployment. Only the outcome
  of each named case (`pending`/`passed`/`failed`/`blocked`/`not_applicable`,
  who tested it, when, and notes) is persisted, in `uat_results`.

- **Release sign-off** (`release_signoffs`) — a dated, immutable record that
  someone formally accepted a specific version for a specific environment;
  it freezes the readiness picture at that moment (cases passed/total,
  checks failing) into the record itself, so a later sign-off can never be
  misread as having covered a state it didn't actually see.

In short: this section exists to answer "is this deployment/release safe and
tested?", separate entirely from "how are our RFA cases progressing?".

---

## 12. System Settings

`/admin/settings` (`settings.manage`), backed by `App\Support\SystemSettings`
and `App\Services\SettingsService`, exposes a deliberately **closed** list of
configurable values — only keys defined in `SystemSettings::definitions()`
can ever be stored, each with its own validation rule, so the settings table
can never become an arbitrary free-form key/value store:

| Setting | Purpose | Default |
|---|---|---|
| Organization Name | Printed as the report heading | "DOLE 5 RFA MONITORING" |
| Unit / Sub-heading | Printed beneath the report heading | "Request for Assistance Monitoring Report" |
| Report Footer Note | Optional confidentiality/footer line on printed reports | (blank) |
| Default Listing Page Size | Rows per page on the RFA master listing | 10 (choice of 10/20/50) |
| Audit Retention (days) | Entries older than this are removed by `rfa:prune-audit`; 0 = keep forever | 0 |
| Backups Kept | Oldest backup files beyond this count are auto-deleted after a new backup completes; 0 = keep all | 10 |

Notably, the PCT limits themselves (3 days / 30 days) are explicitly **not**
configurable here — the code comments state this is deliberate: those are
policy, not runtime configuration.

A related, closely-tied admin feature is **Database Backups**
(`/admin/backups`, `backup.view` / `backup.manage`,
`App\Services\BackupService`): a pure-PHP MySQL dump (no shelling out to
`mysqldump`, so it works even without MySQL client tools on PATH) written to
`storage/app/backups`, registered in `database_backups` with size/table/row
counts. Restoring is intentionally **not** automated from the UI — the
screen documents the `mysql ... < file.sql` command instead, on the
reasoning that overwriting a live database should be a deliberate,
console-level decision by a person, not a button click.

---

## 13. From Filing to Closure — An End-to-End Walkthrough

To tie all of the above together, here is one case moving through the whole
system.

1. **Filing.** A batch of new cases arrives as a CSV export from another
   office system. The System Administrator uploads it under **CSV
   Import**; the new records immediately appear for every user who can
   view RFAs. The importer normalizes headers, parses dates, computes a
   stable identity hash per row, and creates a new `Rfa` record for each
   genuinely new case — including ours, `RFA-2026-00456` — with
   `status = for_interviewer_assignment`, `monitoring_bucket = pending`, and
   the full original row preserved as `import_payload`. One `ImportBatch`
   row and one audit-log summary entry are written for the whole batch.
   From this moment, **PCT 1 (Date Filed - Interviewer Assignment) is
   running** (`date_filed` is set, `date_assigned_interviewer` is not) — the
   same day for an on-site filing, 2 days for an online one.

2. **Interviewer assignment.** A supervisor opens the case (`rfa.view`) and,
   under **Assignment** (`rfa.assign`), links it to an interviewer's system
   account and records `date_assigned_interviewer`. This save (i) updates
   the record, (ii) re-derives `monitoring_bucket` to `ongoing` (a workflow
   date now exists beyond filing), (iii) writes a timeline entry noting the
   assignment change, and (iv) — because the assigned account actually
   changed — fires an **in-app notification** to that interviewer. PCT 1 is
   now `completed`; PCT 2 (Interviewer Assignment - Date Interviewed, 3 days)
   starts running.

3. **Interview & validation.** The interviewer processes the case and, via
   **Workflow Progress** (`rfa.manage`), records `date_interview` and later
   `date_validated`, advancing `status` through `for_validation` →
   `validated`. PCT 2 completes and PCT 3 (Date Interviewed - SEADO
   Assignment, 3 days) starts. If a checkpoint had instead passed its
   deadline before being closed out, the next 07:00 scheduled scan (or a manual
   rescan) would have already raised a warning/critical notification to this
   interviewer.

4. **Turnover & SEADO assignment.** The case is turned over to Labor
   Relations (`date_turned_over_lr`), then assigned to a SEADO the same way
   interviewer assignment worked — another `rfa.assign` save, another
   targeted notification, another audit-observed change, `status` advancing
   to `assigned_to_seado`. PCT 3 completes; PCT 4 (SEADO Assignment - 1st
   Conference, 10 days) starts.

5. **Conference.** The SEADO records `date_initial_conference` and, if
   needed, `date_second_conference` (rejected if attempted without a first
   conference date already on file). `status` moves to `for_conference` /
   `ongoing` as appropriate. The 1st conference completes PCT 4 and starts
   **PCT 5 (1st Conference - Date Disposed, 30 days)**; if the case reaches
   or passes day 30 after the 1st conference without a `date_disposed`, the SEADO
   (or, if unassigned, every Administrator/`rfa.assign` holder) is alerted.

6. **Disposition.** The SEADO records the official `disposition_status`
   together with `date_disposed` (enforced as an all-or-nothing pair),
   plus `monetary_benefit` and `workers_benefited` if applicable. This
   automatically sets `status = disposed` and `monitoring_bucket =
   disposed`. PCT 5 is now a closed, historical result —
   "Disposed Within PCT" or "Disposed Beyond PCT" — feeding directly into
   compliance-rate statistics on the Reports and PCT Process Monitoring
   screens. If a mistake is found later, `rfa.dispose` holders can **reopen**
   the case with a written reason; the prior disposition stays visible on
   the timeline rather than being erased.

7. **Reporting & oversight (ongoing, throughout).** At any point,
   `reports.view`/`export` users can filter and export this case (and every
   other) by office, SEADO, status, date range, or disposition, as CSV or
   the landscape print report. `audit.view` users can trace every
   field-level change ever made to it, by whom, from where. `governance.view`
   users see it flagged automatically if any of its dates are inconsistent.
   And throughout, none of this depends on a queue worker or a JSON API —
   every screen is a plain server-rendered Blade page protected by a session
   cookie and a permission gate.

---

## 14. Known Limitations / Not Yet Implemented

- **Email covers PCT alerts only, and is off by default.** PCT email
  alerts need a real mail server configured (`MAIL_MAILER` is `log` out of
  the box) and `PCT_EMAIL_ENABLED=true`. Assignment notifications and
  unassigned-backlog alerts are in-app only. There is no SMS or push
  channel.
- **The 07:00 PCT scan requires an external scheduler to actually be
  running** (`schedule:work` or a Task Scheduler entry calling
  `schedule:run` every minute) — it is not self-triggering out of the box,
  and the system's own Release Readiness checks will flag this if no PCT
  notifications exist yet.
- **Backup restore is manual by design**, not a button in the UI — the
  system documents the `mysql ... < backup.sql` command rather than
  performing the restore itself.
- **User account offices and RFA offices use different values.** A user's
  office is picked from a fixed list — the Regional Office and the six
  provincial field offices (`config/offices.php`, e.g. "Albay PFO") — while
  imported RFAs carry office codes (e.g. `RO-V`, `RO-V-APFO`). The config
  maps each office to its code, but nothing matches users to cases by
  office yet.
