<?php

$username = getenv('TEST_DB_USERNAME');

if ($username === false || trim($username) === '') {
    fwrite(STDERR, "Set TEST_DB_USERNAME to a MySQL user restricted to mtcgs_ems_test.\n");
    exit(1);
}

$password = getenv('TEST_DB_PASSWORD');

if ($password === false) {
    $password = '';
}

$environment = [
    'APP_ENV' => 'testing',
    'DB_CONNECTION' => 'mysql',
    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => '3306',
    'DB_DATABASE' => 'mtcgs_ems_test',
    'DB_URL' => '',
    'DB_USERNAME' => $username,
    'DB_PASSWORD' => $password,
];

foreach ($environment as $name => $value) {
    putenv("{$name}={$value}");
    $_ENV[$name] = $value;
    $_SERVER[$name] = $value;
}

require __DIR__.'/bootstrap-mysql.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$mysql = $app['config']->get('database.connections.mysql', []);
$connectionIsSafe = $app['config']->get('app.env') === 'testing'
    && $app['config']->get('database.default') === 'mysql'
    && ($mysql['driver'] ?? null) === 'mysql'
    && ($mysql['url'] ?? '') === ''
    && ($mysql['host'] ?? null) === '127.0.0.1'
    && (string) ($mysql['port'] ?? '') === '3306'
    && ($mysql['database'] ?? null) === 'mtcgs_ems_test'
    && ($mysql['username'] ?? null) === $username
    && ($mysql['password'] ?? null) === $password
    && ($mysql['unix_socket'] ?? '') === '';

if (!$connectionIsSafe) {
    fwrite(STDERR, "Refusing to refresh: Laravel's effective MySQL connection is not the dedicated local test database.\n");
    exit(1);
}

$exitCode = Illuminate\Support\Facades\Artisan::call('migrate:fresh', [
    '--database' => 'mysql',
    '--force' => true,
    '--no-interaction' => true,
]);

fwrite(STDOUT, Illuminate\Support\Facades\Artisan::output());

if ($exitCode !== 0) {
    fwrite(STDERR, "The MySQL test database migration failed; tests were not run.\n");
    exit($exitCode);
}
