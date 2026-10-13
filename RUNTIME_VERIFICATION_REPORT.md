# MTCGS EMS Runtime and Deployment Verification

**Last updated:** 2026-10-14
**Deployment status:** **NOT CLEARED FOR PUBLIC PRODUCTION**

This report replaces the previous report that stated “PRODUCTION READY.” The previous claims were based on older verification and must not be used as current deployment evidence.

## Verified in this workspace

- The biometric API security changes require a registered device signature, correct branch assignment, and a matching stored fingerprint before the legacy attendance clock records attendance.
- Temporary fingerprint reads require an authenticated administrator and enforce branch scope for branch administrators.
- Targeted Laravel regression suites passed:
  - `tests/Feature/ApiBiometricEndpointSecurityTest.php`
  - `tests/Feature/BiometricDeviceMetadataTest.php`
  - `tests/Feature/EmployeeFingerprintRegistrationOnEditTest.php`
  - Result: 28 tests passed, 187 assertions.
- `git diff --check` passed for the change set.

These results cover the listed tests only. They do not constitute a full-suite result or a live-hosting verification.

## Remaining deployment gates

| Check | Current status | Required before public deployment |
|---|---|---|
| Production URL and database credentials | Not configured in the committed example; values are intentionally placeholders | Set `APP_URL`, `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` in the hosting environment. Do not commit real credentials. |
| Production application key | Blank in the example, as expected for a template | Provision `APP_KEY` securely for the target environment; do not reuse a public or development key. |
| Production logging | Example now uses `LOG_LEVEL=warning` | Confirm the hosting environment does not override it to `debug`. |
| MySQL migration status | Not verified; the previously attempted local MySQL connection to `127.0.0.1:3306` was refused | From the actual staging/production-like environment, check `php artisan migrate:status`; review pending migrations and back up the database before applying them. |
| MySQL migration execution | Not verified | Apply migrations in staging first, then validate schema and application behavior before production. Do not run migrations against production without an approved backup and deployment plan. |
| Hosting end-to-end behavior | Not verified | Validate login, enrollment polling, fingerprint registration, device-signed attendance, and attendance/DTR results on staging with a registered device. |
| Full Laravel suite and production build | Not established by the targeted tests listed above | Run the project's full test suite and production asset build in CI or staging for the release candidate. |

## Safe pre-deployment checks

Run these only after the staging environment is configured with its own database and secrets:

```bash
php artisan config:clear
php artisan migrate:status
php artisan test
npm run build
```

Review the migration plan and take a database backup before running `php artisan migrate`. Do not copy development credentials, device secrets, or local `.env` values into source control.

## Conclusion

The two reported HIGH API access-control findings have been addressed and covered by targeted regression tests. Deployment remains **blocked pending real MySQL migration verification, production environment configuration, and staging end-to-end validation**.
