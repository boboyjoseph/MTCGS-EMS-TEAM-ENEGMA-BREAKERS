# MTCGS EMS Runtime and Deployment Verification

**Last updated:** 2026-10-20
**Deployment status:** **NOT CLEARED FOR PUBLIC PRODUCTION**

This report replaces the previous report that stated “PRODUCTION READY.” The previous claims were based on older verification and must not be used as current deployment evidence.

## Verified in this workspace

- The biometric API security changes require a registered device signature, correct branch assignment, and a matching stored fingerprint before the legacy attendance clock records attendance.
- Temporary fingerprint reads require an authenticated administrator and enforce branch scope for branch administrators.
- The user-management profile view now has a super-admin-only controller action; the dangling user delete and role-change routes without controller handlers were removed.
- Cash Charges employee options are deduplicated within a branch and use the linked active Finance/Admin profile branch for grouping and charge authorization; no existing employee/profile row is changed by displaying the list.
- The full local Laravel suite passed: **187 tests, 1,052 assertions**, including the Buhi Cash Charges profile-backfill regression test.
- The browser accessibility checks reported **5 passed, 1 failed**: the login page has 30 color-contrast findings, including a 4.33:1 contrast ratio where WCAG AA requires 4.5:1. This remains an open UI issue and was not changed as part of the Cash Charges fix.
- The user-management route regression tests passed after that change: **2 tests, 4 assertions**.
- Production Vite asset build passed with `npm run build`.
- `composer validate --no-check-publish` passed.
- `composer check-platform-reqs --no-dev` passed on local PHP 8.2.12.
- `php artisan about --only=environment` confirmed the checked-in working copy is running in `local` mode with debug enabled; no production values were inferred from it.
- The committed `.env.example` uses `LOG_LEVEL=warning` and placeholders for deployment-specific host and database settings.
- `.env` is local-only, is not tracked by git, and currently specifies `APP_ENV=local`, `APP_DEBUG=true`, and a database at `127.0.0.1:3306`. Its credentials and keys were not displayed or copied.
- A local SQL backup was restored into an isolated MySQL clone. Exact row counts matched across **35 base tables** before migration rehearsal.
- The pending Cash Charges migration was applied successfully to the isolated restore-test clone. After a target-isolation defect in the first runner was corrected, the clone was verified to be the active Laravel database before running the migration; the resulting table row counts had no decreases.
- `php artisan migrate:status` now reports no pending migration among current local migration files, including `2026_10_20_000001_link_active_cash_charge_staff_to_employee_profiles.php` as Ran. The migration table has 130 unique historical entries. The configured source is local XAMPP (`127.0.0.1:3306/mtcgs-ems`), not hosting/production.
- **Local-source change disclosure:** an initial migration-rehearsal runner incorrectly used the local `.env` database instead of the restore clone. It applied the additive Cash Charges migration to the local source database, adding two EmployeeProfile rows and linking existing Buhi admin/finance role profiles. No records were deleted; production was not contacted. The code now changes the database connection in-process and verifies the effective database name before any clone migration.
- The backfill is transactional, does not delete records, and leaves malformed/unassigned profiles untouched with a visible warning. It skips a profile if required branch or identity values are missing and aborts on conflicting user/branch identity. Its rollback intentionally does not delete generated profiles because they may later have related records.
- One active finance profile in the local source has neither a profile branch nor a user branch. It is explicitly skipped and remains unlinked; no branch was guessed. The complete Buhi branch-admin and finance profiles were linked by the local backfill.
- The migration was exercised in the isolated SQLite feature test to verify idempotency, preserving incomplete role profiles, Buhi selector grouping, and correct charge branch. MySQL clone verification is separate from production verification.
- Timestamped SQL backups and verification manifests are stored outside Git under `%LOCALAPPDATA%\MTCGS-EMS-Backups`; the folders have an NTFS ACL for the current Windows account. The chosen `D:\` destination is FAT32 and cannot enforce ACLs, so generated plaintext and temporary credential files were removed from it after checksum-verifying a private NTFS copy. The repeatable script refuses plaintext backup output to non-NTFS destinations.
- The retained pre-migration safety snapshot is `MTCGS-EMS-20261020-213424\mtcgs-ems-local-20261020_133426.sql` (**2,816,321 bytes**, SHA-256 `4bb0597078c824d804381bd6618807bb77c63f7b251e52d38f3029b0a77f0a1a`). The latest snapshot is `MTCGS-EMS-20261020-214526\mtcgs-ems-local-20261020_134527.sql` (**2,816,998 bytes**, SHA-256 `c7b7e87f1185065ddef84b69b9ee54e945431c427bc55a77dbc07a313991f2cb`). Both were restored to separate local clones and verified by row counts.
- `git diff --check` passed.

The local test/build results do not constitute a production database or live-hosting verification.

## Remaining deployment gates

| Check | Current status | Required before public deployment |
|---|---|---|
| Production URL and database credentials | Not configured in the committed example; values are intentionally placeholders | Set `APP_URL`, `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` in the hosting environment. Do not commit real credentials. |
| Production application key | Blank in the example, as expected for a template | Provision `APP_KEY` securely for the target environment; do not reuse a public or development key. |
| Production logging | Example now uses `LOG_LEVEL=warning` | Confirm the hosting environment does not override it to `debug`. |
| Local MySQL migration status | All current local migrations report **Ran**; the Cash Charges profile backfill is applied locally | This is not evidence about staging or production. Inspect the pending list against the actual staging database and review every pending migration before applying anything. |
| Local backup and restore test | SQL backup restored to a separate local clone; exact row counts matched across 35 base tables. Migration rehearsal passed on an isolated clone. | Keep a separately encrypted/off-host backup and perform a restore test on the actual staging host. The local clone is not hosting staging. |
| Actual hosting staging | Not available in this workspace | Provision a separate staging host/database; do not treat the local clone as remote staging. |
| MySQL migration execution | Run on the isolated local clone; the additive backfill was also unintentionally applied to the configured local source during the initial runner attempt. No production connection was used. | Test the full pending list on staging, confirm a restorable backup, and obtain approval before production. Do not run migrations against production without an approved backup and deployment plan. |
| Backup destination | Private NTFS copies exist under `%LOCALAPPDATA%\MTCGS-EMS-Backups`; selected `D:\` is FAT32 and the plaintext copy was removed from it. | Provide an NTFS or encrypted off-host destination. Do not format or alter the FAT32 drive as part of this check. |
| Browser accessibility check | **5 passed, 1 failed**; login page contrast findings remain open | Correct the login-page contrast and rerun the browser accessibility suite before treating accessibility checks as passed. |
| Hosting end-to-end behavior | Not verified | Validate login, enrollment polling, fingerprint registration, device-signed attendance, and attendance/DTR results on staging with a registered device. |
| Production environment | Not configured in this workspace; the supplied `.env` is local development configuration | Set the actual production `APP_URL`, database connection, `APP_KEY`, and biometric device secret securely in the hosting environment. Do not use the local `.env` or commit secrets. |

## Migration data-loss review

The migration set previously had destructive migrations; the current worktree makes those migrations no-op:

- `2026_08_20_000010_drop_overtime_requests_table.php` is now a no-op and retains `overtime_requests`.
- `2026_09_05_000002_drop_documents_and_resignation_tables.php` is now a no-op and retains `clearance_items`, `resignation_requests`, and `employee_documents`.
- The admin-profile uniqueness migration now fails without deleting duplicate profiles, and homepage carousel rollback no longer deletes settings.
- `composer setup` no longer runs migrations or rotates `APP_KEY`; generate a key only once for a new installation because changing an existing key invalidates encrypted data and sessions.

The earlier verification reported these migrations as run in the local database; their status on staging/production is unknown. Before applying any pending migration there, inspect the actual pending list, confirm whether these tables contain data that must be retained, and take a verified backup. A migration rollback alone cannot recover data from these dropped tables.

The Cash Charges backfill migration is additive and preserves role-profile rows. It creates EmployeeProfile rows and sets previously-null role-profile links only when required identity and branch data are present. Incomplete profiles are explicitly logged and left unchanged; conflicting identities stop the migration. The migration deliberately has a no-op rollback to avoid deleting profiles that may have acquired attendance or cash-charge records. The local clone rehearsal passed, but the migration remains unverified on hosting.

Other forward migrations can rewrite existing records even though they do not delete rows. In particular, `2026_10_05_000002_update_existing_shift_start_times_to_8am.php` changes stored shift start times and rewrites matching attendance status/late-minute values without a reversible `down()`; `2026_09_05_000001_set_default_shift_start_time_to_7am.php` also rewrites existing shift times; and `2026_09_08_000001_normalize_legacy_dtr_cutoff_periods.php` changes existing DTR periods. Additional migrations backfill leave flags/profile statuses, cash-charge statuses, and device reader IDs. Their pending status and production impact have not been verified. Review each pending data migration against a staging copy and a tested backup before running `composer migrate`. No migration was applied to hosting/production as part of this review.

## Safe pre-deployment checks

Composer validation/platform checks, the full Laravel test suite, and asset build have passed locally. The Laravel tests use SQLite `:memory:`; they do not read or modify MySQL. The PowerShell backup/restore rehearsal is restricted to the configured local XAMPP database and an isolated clone; it is not a production or hosting check:

```powershell
.\scripts\database-safety-check.ps1
```

After a real staging environment is configured with its own database and secrets, perform these checks there:

```bash
php artisan config:clear
php artisan migrate:status
php artisan test
npm run build
```

Review the migration plan and take a database backup before running `php artisan migrate`. Do not copy development credentials, device secrets, or local `.env` values into source control.

## Conclusion

The two reported HIGH API access-control findings have been addressed. Composer checks, the local Laravel suite (187 tests, 1,052 assertions), and production asset build have passed; the browser accessibility check still has a login-page contrast failure. A local backup/restore test and clone-only migration rehearsal passed, and the local source database now has the additive Cash Charges migration applied. The initial runner defect and resulting two-row additive local change are disclosed above. Deployment remains **blocked pending a real staging environment, review of its complete migration list and historical data, a verified encrypted/off-host backup and restore test, production-specific secrets/configuration, resolution of the login contrast issue, and end-to-end validation on hosting**.
