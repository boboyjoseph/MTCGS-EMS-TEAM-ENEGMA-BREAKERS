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
