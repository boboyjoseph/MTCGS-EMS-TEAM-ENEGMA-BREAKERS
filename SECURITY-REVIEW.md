# Security Review: Biometric API Endpoints

**Review date:** 2026-10-14
**Scope:** Attendance clocking and temporary fingerprint enrollment reads
**Status:** Both HIGH findings below have been mitigated in this change.

## Findings

| # | Severity | Finding | Status |
|---|----------|---------|--------|
| 1 | HIGH | `POST /api/attendance/clock` accepted caller-selected employee, branch, and attendance type without authenticating the scanner or matching the submitted fingerprint to the employee's saved template. The legacy `POST /attendance/clock` URL called the same controller. | Fixed |
| 2 | HIGH | `GET /api/fingerprint-temp` returned cached enrollment data, including fingerprint templates and enrollment metadata, without login or branch authorization. | Fixed |

### 1. Attendance clock forgery

The attendance controller now requires a registered device's signed request credentials, checks that the device is assigned to the requested branch, and matches the scanned fingerprint against the selected employee's saved template before recording attendance. The same checks apply to both attendance-clock URLs because they share the controller.

The attendance desktop client uses `/api/biometric/time-clock`, which already performs registered-device authentication and fingerprint matching; that client flow was not changed.

### 2. Temporary fingerprint disclosure

The temporary fingerprint read route now runs behind web-session authentication. The controller allows only super admins and branch admins (including branch heads); branch admins are restricted to their branch. New enrollment cache entries include the device's branch ID so reads can be scoped even before a new employee profile exists. The existing enrollment polling response remains available to authorized administrators.

## Verification

- `php artisan test tests/Feature/ApiBiometricEndpointSecurityTest.php tests/Feature/BiometricDeviceMetadataTest.php tests/Feature/EmployeeFingerprintRegistrationOnEditTest.php`
- Result: **28 tests passed, 187 assertions**
- `git diff --check` passed.
- Route listing confirmed both attendance-clock URLs use the hardened controller and the temporary fingerprint read route remains registered.

## Deployment caveat

These application-level tests use the test database and do not verify production MySQL migrations or end-to-end behavior on staging/hosting. Run migrations and verify scanner/enrollment workflows against the intended deployment environment before treating the application as production-ready.
