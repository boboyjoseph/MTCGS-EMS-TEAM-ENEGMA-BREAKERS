# MTCGS EMS Runtime and Deployment Verification

**Last updated:** 2026-10-14
**Deployment status:** **NOT CLEARED FOR PUBLIC PRODUCTION**

This report replaces the previous report that stated “PRODUCTION READY.” The previous claims were based on older verification and must not be used as current deployment evidence.

## Verified in this workspace

- The biometric API security changes require a registered device signature, correct branch assignment, and a matching stored fingerprint before the legacy attendance clock records attendance.
- Temporary fingerprint reads require an authenticated administrator and enforce branch scope for branch administrators.
- Full local Laravel suite passed: **159 tests, 771 assertions**.
- Production Vite asset build passed with `npm run build`.
- `composer validate --no-check-publish` passed.
- `composer check-platform-reqs --no-dev` passed on local PHP 8.2.12.
- `php artisan about --only=environment` confirmed the checked-in working copy is running in `local` mode with debug enabled; no production values were inferred from it.
- The committed `.env.example` uses `LOG_LEVEL=warning` and placeholders for deployment-specific host and database settings.
- `.env` is local-only, is not tracked by git, and currently specifies `APP_ENV=local`, `APP_DEBUG=true`, and a database at `127.0.0.1:3306`. Its credentials and keys were not displayed or copied.
- Read-only `php artisan migrate:status` against the local MySQL database reported **127 migrations Ran, 0 Pending**. No migrations were applied.
- `git diff --check` passed.

The local test/build results do not constitute a production database or live-hosting verification.

## Remaining deployment gates

| Check | Current status | Required before public deployment |
|---|---|---|
| Production URL and database credentials | Not configured in the committed example; values are intentionally placeholders | Set `APP_URL`, `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` in the hosting environment. Do not commit real credentials. |
| Production application key | Blank in the example, as expected for a template | Provision `APP_KEY` securely for the target environment; do not reuse a public or development key. |
| Production logging | Example now uses `LOG_LEVEL=warning` | Confirm the hosting environment does not override it to `debug`. |
| Local MySQL migration status | Verified against the local development database: **127 migrations Ran, 0 Pending** | This is not evidence about staging or production. Run `php artisan migrate:status` against the actual staging database as well. |
| MySQL migration execution | Not verified on staging/production | Apply pending migrations in staging first, then validate schema and application behavior before production. Do not run migrations against production without an approved backup and deployment plan. |
| Hosting end-to-end behavior | Not verified | Validate login, enrollment polling, fingerprint registration, device-signed attendance, and attendance/DTR results on staging with a registered device. |
| Production environment | Not configured in this workspace; the supplied `.env` is local development configuration | Set the actual production `APP_URL`, database connection, `APP_KEY`, and biometric device secret securely in the hosting environment. Do not use the local `.env` or commit secrets. |

## Migration data-loss review

The migration set includes migrations that drop existing tables:

- `2026_08_20_000010_drop_overtime_requests_table.php` drops `overtime_requests`.
- `2026_09_05_000002_drop_documents_and_resignation_tables.php` drops `clearance_items`, `resignation_requests`, and `employee_documents`; its `down()` method does not restore the tables or their data.

These migrations are already marked as run in the local database, but their status on staging/production is unknown. Before applying any pending migration there, inspect the actual pending list, confirm whether these tables contain data that must be retained, and take a verified backup. A migration rollback alone cannot recover data from these dropped tables.

## Safe pre-deployment checks

Composer validation/platform checks, the full test suite, and asset build have passed locally. Run database and hosting checks only after the staging environment is configured with its own database and secrets:

```bash
php artisan config:clear
php artisan migrate:status
php artisan test
npm run build
```

Review the migration plan and take a database backup before running `php artisan migrate`. Do not copy development credentials, device secrets, or local `.env` values into source control.

## Conclusion

The two reported HIGH API access-control findings have been addressed. Composer checks, local migration status (127 Ran, 0 Pending), the full local test suite, and production asset build have passed. Deployment remains **blocked pending review of the staging migration list (especially destructive drops), production-specific secrets/configuration, and end-to-end validation on the actual hosting environment**.
