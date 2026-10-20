# RFA Monitoring System — Project Status

**Updated:** 2026-09-24

## Phase status

| Phase | Scope | Status |
| --- | --- | --- |
| 1 | Dashboard / Foundation | Complete |
| 2 | Layout / Sidebar | Complete |
| 3 | CSV Import | Complete |
| 4 | RFA Master Listing | Complete |
| 4.5 | Actual CSV Compatibility | Complete |
| 5 | PCT Process Engine & Monitoring | Complete |
| 6A | SEADO Filter & Workload Summary | Complete |
| 6B | Official Disposition Breakdown | Complete |
| 6C | First / Second Conference Support | Complete |
| 6D | 30-Day Overall Disposition PCT | Complete |
| 6E | SEADO-Specific PCT & Performance | Complete |
| 6F | CSV Export & Detailed Report | Complete |
| 6G | Dedicated 8×13 Print Report | Complete — built and verified |
| 7 | Authentication / Users / RBAC | Complete |
| 8 | Full Workflow / Case Management | Complete |
| 9 | Notifications / Audit | Complete |
| 10 | Administration / Governance / Backup | Complete |
| 11 | QA / UAT / Security / Deployment / Handover | Complete |

## Enhancement phases (requested 2026-09-21 / 2026-09-24)

| Phase | Scope | Status |
| --- | --- | --- |
| A | Email PCT alerts (digest to the assigned officer, once per level) | Complete — **off until SMTP is configured** (`PCT_EMAIL_ENABLED`) |
| B | Office dropdown on Add/Edit User (Regional Office + 6 PFOs, `config/offices.php`) | Complete |
| C | CSV import restricted to the Administrator; imported records visible to all | Complete |
| D | Office filter on PCT Process (whole page) | Complete |
| E | Clickable Within / Nearing / On / Beyond cards on PCT Process | Complete |
| F | Process Cycle Time page (PCT Process → Process Cycle Time) | Complete |
| G | Clickable Pending / Ongoing / Disposed cards on the RFA listing and the Dashboard | Complete |
| H | Dashboard line chart: RFAs filed per day, On-site vs Online, last 5 days | Complete |
| I | PCT rules replaced by the five named checkpoints | Complete |

All enhancement phases (A–I) are complete.

## Enhancements requested 2026-10-06

| Scope | Status |
| --- | --- |
| PCT counts **working days** (Mon–Fri) and skips admin-entered holidays/events (`PctCalendar`, `holidays` table, setting *PCT Day Counting*) | Complete — **run `php artisan migrate`** |
| Schedule → **Holidays & Events** page (view: `pct.view`; add/edit/delete: new `holidays.manage`, Administrator) | Complete |
| Schedule → **My Conference Schedule**: weekly calendar of the signed-in user's own 1st/2nd conferences | Complete |
| **SEADO Assignment** page: workload per SEADO by province, queue of open RFAs with no SEADO, “Assign to a SEADO” offers the 5 least-handled SEADOs in the case's office | Complete |
| Users & Access: one-click **Disable / Enable** per account | Complete |
| **Notification bell** in the header (nearing / due today / beyond PCT) with desktop notifications; in-app *nearing* alerts added to the PCT scan | Complete |
| Reports: **SEADO Settlement Rate** card split by conference phase (settled codes = setting *Settled Disposition Codes*, default `SC`) | Complete |
| Reports: “Official Disposition Missing” card removed; “Indeterminate” defined on SEADO PCT Performance | Complete |
| Process Cycle Time: PCT 5 with no 1st Conference reads “No Date Input” | Complete |

Notes:
- Imported cases carry only officer **names**. The conference schedule, the
  bell and PCT alerts match a case to a user account whose name is exactly the
  same (case and extra spaces ignored), as well as by assigned account.
- Desktop notifications use the browser Notification API: they appear while
  the system is open in a tab, and only on HTTPS or `localhost`.
- The bell refreshes every PCT alert at most once an hour (in-app only); the
  daily 07:00 scan still sends the emails.

Phases 7–11 are implemented, tested and verified against the live database.
Phase 11 provides the UAT plan and sign-off register; **user acceptance testing
itself is still to be performed** by the organisation — the system records it
rather than replacing it.

## Verification at the time of writing

| Item | Result |
| --- | --- |
| Automated tests | 198 passing (2026-09-24) |
| Live records | 534 RFAs in `rfa_monitoring` |
| Routes reachable | All 17 authenticated screens return 200 for an administrator |
| Print report | Renders every matching record at exact 13in × 8in |
| Backup | 18 tables, 575 rows, 1.31 MB dump written and downloaded |
| Readiness checks | 15 pass, 4 review, 1 fail (`APP_DEBUG` on, expected locally) |

## Known outstanding work

These are deliberate, not defects:

1. **`APP_DEBUG=true`** — correct for local development, must be false before
   go-live. Flagged by `Release Readiness`.
2. **HTTPS** — `APP_URL` is plain HTTP on the workstation. Required before the
   system is reachable beyond a single machine.
3. **One administrator account** — create a second before go-live.
4. **No system accounts assigned to cases** — every imported record carries a
   text name only, so PCT alerts currently aggregate to supervisors rather than
   reaching individuals. Assigning accounts on the case screen resolves this
   case by case.
5. **Scheduler not yet installed** — `rfa:scan-pct` runs on demand. Install the
   Task Scheduler entry from `docs/DEPLOYMENT.md` for the daily 07:00 scan.
6. **UAT not yet executed** — 38 cases await results in `Release Readiness`.
7. **Run `php artisan migrate`** — Phase A added the `pct_email_deliveries`
   table.
8. **Email alerts need a mail server** — set `MAIL_MAILER=smtp` and its
   settings, then `PCT_EMAIL_ENABLED=true`.
9. **The CSV has no Date Interviewed or Date TA/NORES** — on-site cases always
   take their Date Filed as the Date Assigned to Interviewer, and as the Date
   Interviewed when none is recorded (`rfa:apply-onsite-dates`, run once by
   migration; every save and import applies the same rule). After sign-in,
   each SEADO is reminded of their open cases still missing either date, and
   the case page requires both before they continue; a re-import keeps them.
   47 cases also have no mode of filing.

## Business rules that must not be changed casually

- Five checkpoints (`PctService::definitions()`):
  1. Date Filed - Interviewer Assignment — on-site: 1 day, online: 2 days
  2. Interviewer Assignment - Date Interviewed — 3 days
  3. Date Interviewed - SEADO Assignment — 3 days
  4. SEADO Assignment - 1st Conference — 10 days
  5. 1st Conference - Date Disposed — 30 days
- Working days (Mon–Fri, skipping Holidays & Events) by default; calendar days
  if System Settings → PCT Day Counting says so. Start day = day 0. The
  deadline day is **On PCT** and
  compliant; past it is Beyond. Nearing is the day before the deadline, or the
  last 3 days for the 10- and 30-day rules. On-site day 0 is On PCT.
- A blank mode of filing is **not rated** on checkpoint 1 (Mode of Filing
  Missing)
- A case marked disposed with no Date Disposed is **indeterminate**, never an
  ageing active timer
- Official `disposition_status` and raw source `disposition_mode` are separate
  and never substituted for each other
- `reference_no` is unique; `docket_no` is not
- An Initial Conference date is not a Date of Interview
- A missing historical interview date does not become a Beyond PCT result

These are enforced in `app/Services/PctService.php` and are deliberately absent
from System Settings — except how days are counted (working or calendar), which
is the *PCT Day Counting* setting, and the holidays, which administrators manage.

## Documentation

| File | Audience |
| --- | --- |
| `docs/OPERATIONS_MANUAL.md` | Whoever runs the system day to day |
| `docs/DEPLOYMENT.md` | Whoever deploys and rolls back |
| `docs/CODE_WALKTHROUGH.md` | Whoever maintains the code |
| `docs/PROJECT_STATUS.md` | This file |
