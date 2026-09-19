# RFA Monitoring System — Deployment and Rollback

Written for: the person deploying the application, who has console access to
the server and the database.

---

## Requirements

| Component | Version |
| --- | --- |
| PHP | 8.3 or newer (8.4 in development) |
| PHP extensions | `pdo_mysql`, plus `pdo_sqlite` if you run the test suite |
| MySQL | 8.0 or compatible |
| Node.js | 20 or newer, for building assets |
| Composer | 2.x |

---

## First installation

```bash
git clone https://github.com/ABuenaflor/RFA-Monitoring.git
cd RFA-Monitoring

composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

Edit `.env`:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-host

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=rfa_monitoring
DB_USERNAME=<user>
DB_PASSWORD=<password>
```

Then:

```bash
php artisan migrate --force
php artisan db:seed --class=RolePermissionSeeder
php artisan rfa:create-admin --name="Administrator" --email="admin@example.gov.ph"

npm ci
npm run build

php artisan optimize:clear
```

Point the web server's document root at **`public/`**, never at the project
root. Serving the project root exposes `.env`.

Open `Release Readiness` and work through anything not passing.

---

## Deploying a new version

Every deployment starts with a backup. It is the only thing that makes the
rollback below possible.

```bash
# 1. Back up — from the app (Database Backups → Create Backup) or:
php artisan tinker --execute="app(\App\Services\BackupService::class)->create();"

# 2. Note the commit you are on, so you know what to return to
git rev-parse --short HEAD

# 3. Stop traffic
php artisan down

# 4. Take the new code
git fetch origin
git switch alex_branch      # or the release branch
git pull

# 5. Dependencies
composer install --no-dev --optimize-autoloader
npm ci

# 6. Database
php artisan migrate --force

# 7. Assets
npm run build

# 8. Caches
php artisan optimize:clear

# 9. Resume
php artisan up
```

### Verify before telling anyone it is done

- Sign in.
- Dashboard record counts match what they were before.
- Open one case; the timeline is intact.
- `Release Readiness` shows no new failing checks.
- Open the print report and confirm it still paginates at 13in × 8in.

---

## Rolling back

Rollback is two independent moves: the **code** and the **data**. Do only what
is needed.

### Code only — no migration ran

```bash
php artisan down
git switch --detach <previous-commit>
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan optimize:clear
php artisan up
```

### Code and data — a migration ran

The safe path is a restore, not `migrate:rollback`. A rollback re-runs `down()`
methods that can drop columns and lose whatever was written since.

```bash
php artisan down

# Restore the pre-deployment dump
mysql -u <user> -p rfa_monitoring < storage/app/private/backups/rfa_backup_YYYY-MM-DD_HHMMSS.sql

# Return the code to the matching commit
git switch --detach <previous-commit>
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan optimize:clear

php artisan up
```

Anything entered between the backup and the rollback is lost. That window is
why the backup is taken immediately before the deployment, not that morning.

---

## Scheduler

The PCT scan only runs if something drives the scheduler.

**Linux (cron):**

```cron
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

**Windows (Task Scheduler):** a task running every minute:

```
Program:   php
Arguments: artisan schedule:run
Start in:  C:\path\to\RFA_Monitoring
```

**Development:** `php artisan schedule:work` in a terminal.

Confirm it is working: `Release Readiness` → `deploy.scheduler`.

---

## Security checklist before go-live

| Item | Why |
| --- | --- |
| `APP_DEBUG=false` | A debug error page exposes paths, config and query contents |
| `APP_ENV=production` | Framework behaviour matches the deployed environment |
| HTTPS on `APP_URL` | Credentials and session cookies are otherwise sent in the clear |
| Document root is `public/` | Anything else exposes `.env` |
| `.env` not in version control | It holds the database password and `APP_KEY` |
| At least two administrators | One lost password cannot lock out access control |
| No outstanding temporary passwords | An unused issued password is a standing risk |
| A backup exists and restoring it has been rehearsed | An untested backup is not a backup |

`Release Readiness` checks every one of these against the running system.

---

## Environment notes

- The `.env` file is **never** committed. `.gitignore` excludes it, and the
  readiness check `deploy.env_untracked` verifies this.
- Backups live in `storage/app/private/backups` and are excluded from version
  control by `storage/app/.gitignore`. Copy them off the machine — a backup on
  the same disk as the database does not survive that disk failing.
- The database name may differ per machine. Confirm with
  `php artisan tinker --execute="echo DB::connection()->getDatabaseName();"`
  before assuming.
