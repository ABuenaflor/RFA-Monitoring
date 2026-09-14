# RFA Monitoring System — Project Status

**Updated:** 2026-09-14

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

Phases 7–11 are implemented, tested and verified against the live database.
Phase 11 provides the UAT plan and sign-off register; **user acceptance testing
itself is still to be performed** by the organisation — the system records it
rather than replacing it.

## Verification at the time of writing

| Item | Result |
| --- | --- |
| Automated tests | 142 passing, 430 assertions |
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

## Business rules that must not be changed casually

- Stage 1: Date Filed → Date Assigned to Interviewer, maximum 3 calendar days
- Stage 2: Date Assigned to Interviewer → Date of Interview, maximum 3 calendar
  days
- Classification: 0–1 Within, 2 Nearing, 3 On PCT, over 3 Beyond
- Disposition: Date Filed → Date Disposed, maximum 30 calendar days, **day 30
  is compliant**
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
