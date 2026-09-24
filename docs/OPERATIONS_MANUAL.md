# RFA Monitoring System — Operations Manual

Written for: whoever runs this system day to day — an administrator or records
officer, not necessarily a developer.

---

## 1. Getting in

The system is closed. Every page requires a signed-in, active account.

| Item | Value |
| --- | --- |
| Sign-in page | `/login` |
| Local address | `http://127.0.0.1:8000` when started with `php artisan serve` |

### The first administrator

If no account exists yet:

```bash
php artisan db:seed --class=RolePermissionSeeder
php artisan rfa:create-admin --name="Full Name" --email="you@example.gov.ph"
```

The command prints a temporary password once. The account must change it at
first sign-in.

### If everyone is locked out

Create another administrator with the same command. It works from the console
without a session, which is deliberate — it is the recovery path.

---

## 2. Roles and permissions

A **role** is a named bundle of permissions. A **user** holds exactly one role.

Four roles are created by the seeder:

| Role | Intended for | Can do |
| --- | --- | --- |
| Administrator | System owner | Everything, always |
| SEADO | Conference and disposition work | View, manage, assign, dispose, export reports |
| Interviewer | Interview-stage processing | View, manage case records, view reports |
| Viewer | Read-only oversight | Dashboard, listing, PCT, reports |

**Administrator is special.** It holds every permission implicitly and cannot be
narrowed, so the system can never be left with nobody able to reach access
control. For a narrower administrative profile, create a custom role instead.

**CSV import is Administrator only.** It appears locked ("Administrator only")
in the role editor and cannot be given to any other role, including custom
ones. Imported records are not private to the administrator: every user who
can view RFAs sees them in the listing, dashboard, PCT Process and reports.

### Things the system refuses to let you do

- Demote, deactivate, or delete the **last active administrator**
- Deactivate or delete **your own** account
- Delete a **system role**, or a role that still has users on it

These are not warnings. The save is refused.

### Managing accounts

`Users & Access` → Add User, or Edit on an existing row.

- **Status: Inactive** blocks sign-in *and* signs out any session that account
  already has, on its next request.
- **Require a password change at next sign-in** — leave this on when issuing a
  password. The account cannot use any other page until it sets a new one.
- Setting a password from the Edit screen always forces a change at next
  sign-in.

---

## 3. Importing CSV data

`CSV Import` → choose file → upload. Only the Administrator sees this menu item
and the Import CSV buttons.

What happens:

- Rows are matched on `source_row_key`, falling back to `reference_no`. A row
  already present is **updated**, not duplicated.
- Exact duplicate rows inside one file are skipped and counted.
- The whole import runs in a transaction. If anything fails, **nothing** is
  saved.
- One audit entry is written for the batch, not one per record.

After importing, check `Data Governance` → Import Batch History.

### What the importer will not guess

- `Mode` from the CSV is stored as the **raw source disposition mode**. It is
  never treated as the official disposition, and no meaning is inferred from
  values like SC, RCA, SWBF, LOI, NSWBF, DP, ROGO, RVA.
- `Initial Conference` is **not** a Date of Interview.
- A missing historical interview date does **not** become a Beyond PCT result.
- Zero dates normalise to empty.

---

## 4. Working a case

Open any row from the listing with **Open**.

The case screen saves in five independent sections. Each has its own
permission, so an officer can be allowed to assign work without being allowed
to close cases.

| Section | Permission | What it holds |
| --- | --- | --- |
| Assignments | `rfa.assign` | Interviewer and SEADO, and their assignment dates |
| Workflow Progress | `rfa.manage` | Workflow status, filing, interview, validation, turnover dates |
| Conference Progression | `rfa.manage` | 1st and 2nd conference, both-parties-appeared |
| Disposition Capture | `rfa.dispose` | Official disposition, source mode, date, benefits |
| Case Information | `rfa.manage` | Parties, establishment, industry, workers, issues |

### Date rules the system enforces

- A save that would create a **new** date conflict is refused, and the message
  names both dates.
- A conflict **already in the data** from an import does not block unrelated
  edits. It is shown as a warning at the top of the case instead, so it can be
  corrected deliberately.
- A 2nd Conference cannot be recorded without a 1st.
- A disposition needs a status **and** a date together. Neither alone is
  accepted, because a case marked closed with no date breaks the 30-day PCT.

### Reopening

Reopening clears the official disposition and the Date Disposed and returns the
case to For Disposition. A reason is required. The previous values stay visible
on the case timeline.

---

## 5. PCT monitoring and notifications

### The rules (fixed, not configurable)

| # | Checkpoint | From → To | Limit |
| --- | --- | --- | --- |
| 1 | Date Filed - Interviewer Assignment | Date Filed → Date Assigned to Interviewer | On-site: same day · Online: 2 days |
| 2 | Interviewer Assignment - Date Interviewed | Date Assigned to Interviewer → Date of Interview | 3 days |
| 3 | Date Interviewed - SEADO Assignment | Date of Interview → Date Assigned to SEADO | 3 days |
| 4 | SEADO Assignment - 1st Conference | Date Assigned to SEADO → 1st Conference | 10 days |
| 5 | 1st Conference - Date Disposed | 1st Conference → Date Disposed | 30 days |

Calendar days; the start day is day 0. Each checkpoint is rated against its
own limit:

| Rating | 2- and 3-day rules | 10- and 30-day rules | Same-day rule |
| --- | --- | --- | --- |
| Within PCT | before the day before | before the last 3 days | — |
| Nearing PCT | the day before the deadline | the last 3 days before it | — |
| On PCT | the deadline day | the deadline day | day 0 |
| Beyond PCT | past the deadline | past the deadline | day 1 or later |

The deadline day is **compliant**, not late. A case with no mode of filing
cannot be rated on checkpoint 1 and is listed as "Mode of Filing Missing" in
the PCT data issues until someone fills it in.

### Running the scan

`Notifications & Audit` → **Run PCT Scan**, or:

```bash
php artisan rfa:scan-pct
```

The scan is safe to run as often as you like:

- Alerts are keyed to the condition, so re-running never duplicates a queue.
- An alert whose condition has cleared is **removed**.
- An alert that escalates to critical reopens even if it was marked read.

### Who gets alerted

- A case with an **assigned system account** alerts that officer directly.
- A case in breach with **no assigned account** is counted into one aggregate
  alert per supervisor — not one alert per case.

That second rule matters here: imported records carry only a text name, so
until accounts are assigned, supervisors see three summary alerts rather than
several hundred individual ones.

### Email alerts

The same scan can also email the assigned officer at their account email.
It is **off until you turn it on**:

1. Point the mailer at a real mail server in `.env` (`MAIL_MAILER=smtp`,
   `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`,
   `MAIL_FROM_ADDRESS`).
2. Set `PCT_EMAIL_ENABLED=true`.
3. Run `php artisan config:clear`, then run a scan.

What gets emailed:

| Level | When (any of the five checkpoints) |
| --- | --- |
| Nearing PCT | the checkpoint is rated Nearing PCT (see the table above) |
| Due today | the deadline day |
| Beyond PCT | past the deadline |

Checkpoints 1–3 alert the interviewer (falling back to the SEADO);
checkpoints 4–5 alert the SEADO (falling back to the interviewer).

- Each officer gets **one email per scan**, listing every case that newly
  reached a level (most urgent first, capped at `PCT_EMAIL_MAX_ITEMS`, default
  50, with "and N more").
- Each case is emailed **once per level**, so a daily scan does not repeat the
  same alert. A case moving from nearing to breached is emailed again.
- Unassigned cases are not emailed; they stay in the supervisors' in-app
  summary alerts.
- Officers whose account email is not a valid address are skipped.
- If sending fails, the scan still completes, the failure is written to
  `storage/logs`, and the email is retried on the next scan.

Do not enable it while `MAIL_MAILER=log`: alerts would be written to the log
file and recorded as sent, so they would not be emailed later.

### Making the scan automatic

```bash
php artisan schedule:work
```

Leave it running. It fires `rfa:scan-pct` daily at 07:00. On a server, add a
Windows Task Scheduler entry calling `php artisan schedule:run` every minute
instead.

---

## 6. Reports

`Reports` carries the filters, summaries and analytics. Two outputs:

- **Export CSV** — the filtered records as a file.
- **Print Report** — a dedicated `/reports/print` page sized to exact
  **13in × 8in landscape** (long bond / folio, *not* US Legal). It contains
  every matching record, not just the current page, with no navigation and
  repeated table headers.

Both require `reports.export`.

The report heading, sub-heading and footer note come from **System Settings**.

---

## 7. Data governance

`Data Governance` runs twelve standing checks over the RFA data.

Each check is a live query, not a stored flag — correct a record and the
finding disappears on the next page load, with nothing to mark resolved.

Findings are grouped by what they break:

- **Critical** — a PCT figure cannot be computed at all
- **Warning** — a figure or report will be wrong or incomplete
- **Informational** — expected in this data (missing dockets on TA/NORES rows,
  repeated docket numbers), listed so the proportion stays visible

**Export Findings** produces a CSV to work through offline.

---

## 8. Backups

`Database Backups` → **Create Backup**.

- Writes a complete SQL dump to `storage/app/private/backups`.
- Written in PHP, so **no `mysqldump` binary is needed**.
- Older files beyond the retention count are removed automatically.
- Only works on a MySQL connection; on any other driver the button is disabled
  rather than producing a file that cannot be restored.

### Restoring

Deliberately **not** automated. The procedure is on the page and in
`docs/DEPLOYMENT.md`. In short: take a fresh backup first, stop the app, then

```bash
mysql -u <user> -p rfa_monitoring < rfa_backup_YYYY-MM-DD_HHMMSS.sql
php artisan migrate
php artisan optimize:clear
```

---

## 9. System settings

`System Settings` holds a closed list of controlled values:

| Setting | Effect |
| --- | --- |
| Organization Name | Heading on the print report |
| Unit / Sub-heading | Sub-heading on the print report |
| Report Footer Note | Optional line at the foot of the report |
| Default Listing Page Size | Rows per page on the listing |
| Audit Retention (days) | Used by `rfa:prune-audit`; 0 keeps everything |
| Backups Kept | Older backups removed after a new one; 0 keeps everything |

PCT limits are **not** here. They are policy, not configuration.

---

## 10. Audit trail

`Notifications & Audit` → Recent System Events (needs `audit.view`).

Records creation, update and deletion of RFAs, users and roles, plus sign-in,
sign-out and failed sign-in attempts. Update entries carry the field-level
before and after values.

- Passwords are stored as `••••••`, never in the clear.
- Routine bookkeeping such as `last_login_at` is not recorded, so the trail
  stays readable.
- The trail is append-only — the application never edits or deletes an entry,
  except through the retention prune.

```bash
php artisan rfa:prune-audit          # uses the configured retention
php artisan rfa:prune-audit --days=365
```

---

## 11. Release readiness

`Release Readiness` runs live checks against the running system — environment,
security, data and deployment — and holds the UAT plan and sign-off register.

The checks read the real system. `APP_DEBUG is true` means debug really is on
right now, not that someone ticked a box.

---

## 12. Command reference

| Command | Purpose |
| --- | --- |
| `php artisan rfa:create-admin` | Create an administrator account |
| `php artisan rfa:scan-pct` | Recalculate PCT notifications and send PCT email alerts |
| `php artisan rfa:prune-audit` | Apply audit retention |
| `php artisan schedule:work` | Run the scheduler in the foreground |
| `php artisan db:seed --class=RolePermissionSeeder` | Create the four system roles |
| `php artisan migrate` | Apply pending database changes |
| `php artisan optimize:clear` | Clear all caches |
| `npm run build` | Rebuild frontend assets |

**Never run** `php artisan migrate:fresh` against the real database. It drops
every table.

`php artisan db:seed` on its own is safe — it only creates roles. The
demonstration data seeder is destructive and must be asked for by name:
`php artisan db:seed --class=RfaSeeder` **deletes every RFA record first**.
