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
| G | Clickable Pending / Ongoing / Disposed cards on the RFA listing | **Remaining** |
| H | Dashboard line chart: RFAs filed per day, On-site vs Online | **Remaining** |
| I | PCT rules replaced by the five named checkpoints | Complete |

### Remaining phases

**G — Clickable listing cards.** On the RFA listing, clicking Pending, Ongoing
or Disposed shows every RFA with that status. The listing already has a status
filter with the same values, so each card links to it (keep other filters),
built the same way as the PCT cards in Phase E. Open question: should the
dashboard's cards be clickable too?

**H — Dashboard filing chart.** A smooth line chart on the Dashboard showing
how many RFAs were filed each day (`date_filed`), one line for On-site and one
for Online (`mode_of_filing`), zero-filled for days with no filings, using the
Chart.js already on the dashboard. Style: white card, small uppercase label
over a bold title, curved lines with hollow point markers, light fill under
one series, legend centred below. Range: 5 days of the current month. Open
question: near the start of a month (e.g. the 2nd), show only the days so far
or reach back into the previous month?

Phases 7–11 are implemented, tested and verified against the live database.
Phase 11 provides the UAT plan and sign-off register; **user acceptance testing
itself is still to be performed** by the organisation — the system records it
rather than replacing it.

## Verification at the time of writing

| Item | Result |
| --- | --- |
| Automated tests | 187 passing (2026-09-24) |
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
9. **No case has a Date of Interview** — the CSV has no interview date, so
   "Date Interviewed - SEADO Assignment" cannot be measured and
   "Interviewer Assignment - Date Interviewed" mostly reads Completion Date
   Missing. 47 cases also have no mode of filing.

## Business rules that must not be changed casually

- Five checkpoints (`PctService::definitions()`):
  1. Date Filed - Interviewer Assignment — on-site: same day, online: 2 days
  2. Interviewer Assignment - Date Interviewed — 3 days
  3. Date Interviewed - SEADO Assignment — 3 days
  4. SEADO Assignment - 1st Conference — 10 days
  5. 1st Conference - Date Disposed — 30 days
- Calendar days, start day = day 0. The deadline day is **On PCT** and
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
from System Settings.

## Documentation

| File | Audience |
| --- | --- |
| `docs/OPERATIONS_MANUAL.md` | Whoever runs the system day to day |
| `docs/DEPLOYMENT.md` | Whoever deploys and rolls back |
| `docs/CODE_WALKTHROUGH.md` | Whoever maintains the code |
| `docs/PROJECT_STATUS.md` | This file |
