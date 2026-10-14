<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Testing

The existing `php artisan test` and `npm run test:e2e` workflows remain unchanged.

- `composer format:check` checks PHP formatting without rewriting files.
- `composer analyse` runs Larastan against the application at PHPStan level 3.
- `composer audit` and `npm audit --package-lock-only` check locked dependencies without upgrading them.
- `npm run test:e2e` runs the Playwright browser tests, including WCAG A/AA scans. Start the local app first; Playwright defaults to `http://127.0.0.1:8000`.

### Optional MySQL integration tests

The MySQL suite is separate from the default in-memory SQLite suite. It uses the fixed local database `mtcgs_ems_test`; tests using Laravel's `RefreshDatabase` trait can drop and recreate tables in that database. Never point this suite at a production or normal development database.

Create an empty `mtcgs_ems_test` database in local XAMPP/MariaDB and use a dedicated test account with privileges only on that database. Then, in PowerShell, set `TEST_DB_USERNAME` (and `TEST_DB_PASSWORD` if the account has a password) and run:

```powershell
$env:TEST_DB_USERNAME = 'mtcgs_test_user'
$env:TEST_DB_PASSWORD = 'your-test-only-password'
composer test:mysql
```

The runner refuses to start unless the environment is `testing` and the connection is exactly `127.0.0.1:3306/mtcgs_ems_test`. It does not create the database or read database credentials from `.env`.

### Optional k6 smoke test

Install k6 separately to run this bounded, read-only check against the local app:

```powershell
k6 run tests/performance/smoke.js
```

It checks only `GET /` and `GET /login` with one virtual user for 10 seconds. Do not target production. Set `BASE_URL` only to a local or authorized staging environment.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
