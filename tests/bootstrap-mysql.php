<?php

$expectedEnvironment = [
    'APP_ENV' => 'testing',
    'DB_CONNECTION' => 'mysql',
    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => '3306',
    'DB_DATABASE' => 'mtcgs_ems_test',
];

foreach ($expectedEnvironment as $name => $expectedValue) {
    if (getenv($name) !== $expectedValue) {
        fwrite(STDERR, "Refusing to run MySQL tests: {$name} must be {$expectedValue}.\n");
        exit(1);
    }
}

$username = getenv('TEST_DB_USERNAME');

if ($username === false || trim($username) === '') {
    fwrite(STDERR, "Set TEST_DB_USERNAME to a MySQL user restricted to mtcgs_ems_test.\n");
    exit(1);
}

$password = getenv('TEST_DB_PASSWORD');

if ($password === false) {
    $password = '';
}

foreach (['DB_USERNAME' => $username, 'DB_PASSWORD' => $password] as $name => $value) {
    putenv("{$name}={$value}");
    $_ENV[$name] = $value;
    $_SERVER[$name] = $value;
}

require dirname(__DIR__).'/vendor/autoload.php';

$mysqlApp = require dirname(__DIR__).'/bootstrap/app.php';
$mysqlApp->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$mysql = $mysqlApp['config']->get('database.connections.mysql', []);
$connectionIsSafe = $mysqlApp['config']->get('app.env') === 'testing'
    && $mysqlApp['config']->get('database.default') === 'mysql'
    && ($mysql['driver'] ?? null) === 'mysql'
    && ($mysql['url'] ?? '') === ''
    && ($mysql['host'] ?? null) === '127.0.0.1'
    && (string) ($mysql['port'] ?? '') === '3306'
    && ($mysql['database'] ?? null) === 'mtcgs_ems_test'
    && ($mysql['username'] ?? null) === $username
    && ($mysql['password'] ?? null) === $password
    && ($mysql['unix_socket'] ?? '') === '';

if (!$connectionIsSafe) {
    fwrite(STDERR, "Refusing to run MySQL tests: Laravel's effective connection is not the dedicated local test database.\n");
    exit(1);
}
