<?php

use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$backupDirectory = $argv[1] ?? null;
$expectedDatabase = 'mtcgs-ems';
$expectedPendingMigration = '2026_10_20_000001_link_active_cash_charge_staff_to_employee_profiles';
$dumpBinary = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';
$mysqlBinary = 'C:\\xampp\\mysql\\bin\\mysql.exe';
$runDirectory = $backupDirectory ? realpath($backupDirectory) : false;
$configFile = null;
$manifestPath = null;

set_exception_handler(static function (Throwable $exception) use (&$configFile, &$manifestPath): void {
    if ($configFile && is_file($configFile)) {
        unlink($configFile);
    }

    if ($manifestPath && is_file($manifestPath)) {
        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        if (is_array($manifest)) {
            $manifest['migration_rehearsal'] = 'failed';
            $manifest['failure'] = $exception->getMessage();
            file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL, LOCK_EX);
        }
    }

    fwrite(STDERR, "FAILED: {$exception->getMessage()}\n");
    exit(1);
});

function fail(string $message): never
{
    throw new RuntimeException($message);
}

function runProcess(array $command, array $descriptors, array &$pipes): int
{
    $process = proc_open($command, $descriptors, $pipes, dirname(__DIR__), null, ['bypass_shell' => true]);

    if (! is_resource($process)) {
        fail('Could not start a required database utility.');
    }

    $stdout = isset($pipes[1]) ? stream_get_contents($pipes[1]) : '';
    if (isset($pipes[1])) {
        fclose($pipes[1]);
    }
    $stderr = isset($pipes[2]) ? stream_get_contents($pipes[2]) : '';
    if (isset($pipes[2])) {
        fclose($pipes[2]);
    }

    $exitCode = proc_close($process);

    if ($exitCode !== 0) {
        fail('A database utility failed with exit code '.$exitCode.': '.trim((string) $stderr.' '.(string) $stdout));
    }

    if (trim((string) $stderr) !== '') {
        fwrite(STDERR, trim((string) $stderr).PHP_EOL);
    }

    return $exitCode;
}

function quoteIdentifier(string $identifier): string
{
    return '`'.str_replace('`', '``', $identifier).'`';
}

function tableRowCounts(PDO $pdo, string $database): array
{
    $statement = $pdo->prepare(
        'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = ? ORDER BY TABLE_NAME'
    );
    $statement->execute([$database, 'BASE TABLE']);
    $counts = [];

    foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $table) {
        $counts[$table] = (int) $pdo->query(
            'SELECT COUNT(*) FROM '.quoteIdentifier($database).'.'.quoteIdentifier($table)
        )->fetchColumn();
    }

    return $counts;
}

function migrationFiles(): array
{
    return array_map(
        static fn (string $path): string => pathinfo($path, PATHINFO_FILENAME),
        glob(dirname(__DIR__).'/database/migrations/*.php') ?: []
    );
}

function pendingMigrations(PDO $pdo, string $database): array
{
    $migrationTableExists = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?'
    );
    $migrationTableExists->execute([$database, 'migrations']);

    if ((int) $migrationTableExists->fetchColumn() === 0) {
        fail("The restored database {$database} is missing Laravel's migrations table.");
    }

    $statement = $pdo->query('SELECT migration FROM '.quoteIdentifier($database).'.`migrations`');
    $ran = array_flip($statement->fetchAll(PDO::FETCH_COLUMN));

    return array_values(array_filter(
        migrationFiles(),
        static fn (string $migration): bool => ! isset($ran[$migration])
    ));
}

if (! $runDirectory || ! is_dir($runDirectory)) {
    fail('Pass an existing, newly-created backup run directory.');
}

$appRoot = realpath(dirname(__DIR__));
if ($appRoot && str_starts_with(strtolower($runDirectory), strtolower($appRoot.DIRECTORY_SEPARATOR))) {
    fail('Database backups must be stored outside the Git repository.');
}

$config = $app['config'];
$mysql = $config->get('database.connections.mysql', []);
$database = (string) ($mysql['database'] ?? '');

if ($config->get('app.env') !== 'local'
    || $config->get('database.default') !== 'mysql'
    || ($mysql['driver'] ?? null) !== 'mysql'
    || ($mysql['host'] ?? null) !== '127.0.0.1'
    || (string) ($mysql['port'] ?? '') !== '3306'
    || $database !== $expectedDatabase
    || ($mysql['url'] ?? '') !== ''
    || ($mysql['unix_socket'] ?? '') !== '') {
    fail('Refusing to run: the effective Laravel database is not the expected local XAMPP database.');
}

if (! is_file($dumpBinary) || ! is_file($mysqlBinary)) {
    fail('The XAMPP mysqldump.exe or mysql.exe utility was not found.');
}

$pdo = $app['db']->connection('mysql')->getPdo();
$tableCount = (int) $pdo->query(
    'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = '.$pdo->quote($database).' AND TABLE_TYPE = \'BASE TABLE\''
)->fetchColumn();

if ($tableCount === 0) {
    fail('The source database has no base tables; refusing to create an empty backup.');
}

$nonTransactional = $pdo->prepare(
    'SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = ? AND ENGINE <> ?'
);
$nonTransactional->execute([$database, 'BASE TABLE', 'InnoDB']);
$nonTransactionalTables = $nonTransactional->fetchAll(PDO::FETCH_ASSOC);

if ($nonTransactionalTables !== []) {
    fail('Consistent snapshot refused: source contains non-InnoDB tables.');
}

$sizeStatement = $pdo->prepare(
    'SELECT COALESCE(SUM(DATA_LENGTH + INDEX_LENGTH), 0) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?'
);
$sizeStatement->execute([$database]);
$estimatedBytes = (int) $sizeStatement->fetchColumn();
$availableBytes = disk_free_space($runDirectory);
$dataDirectory = (string) $pdo->query('SELECT @@datadir')->fetchColumn();
$databaseFreeBytes = is_dir($dataDirectory) ? disk_free_space($dataDirectory) : false;
$requiredBytes = max(128 * 1024 * 1024, (int) ceil($estimatedBytes * 1.5));

if ($availableBytes === false || $availableBytes < $requiredBytes
    || $databaseFreeBytes === false || $databaseFreeBytes < $requiredBytes) {
    fail('The backup drive or local MySQL data drive does not have enough free space for a dump and restore-test copy.');
}

$timestamp = gmdate('Ymd_His');
$restoreDatabase = 'mtcgs_ems_restore_'.$timestamp.'_'.bin2hex(random_bytes(2));
$sourceCounts = tableRowCounts($pdo, $database);
$schemas = $pdo->query('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA')->fetchAll(PDO::FETCH_COLUMN);

if (in_array($restoreDatabase, $schemas, true)) {
    fail('The unique restore-test database name already exists; no database was changed.');
}

$configFile = $runDirectory.DIRECTORY_SEPARATOR.'mysql-client.cnf';
$dumpFile = $runDirectory.DIRECTORY_SEPARATOR.$database.'-local-'.$timestamp.'.sql';
$manifestPath = $runDirectory.DIRECTORY_SEPARATOR.'verification-manifest.json';
$clientPassword = (string) ($mysql['password'] ?? '');

if (str_contains($clientPassword, "\n") || str_contains($clientPassword, "\r")) {
    fail('The local MySQL password contains unsupported line breaks.');
}

$escapeOption = static fn (string $value): string => '"'.str_replace(
    ['\\', '"'],
    ['\\\\', '\\"'],
    $value
).'"';

$clientConfig = "[client]\n"
    .'host='.$escapeOption((string) $mysql['host'])."\n"
    .'port='.(int) $mysql['port']."\n"
    .'user='.$escapeOption((string) $mysql['username'])."\n"
    .'password='.$escapeOption($clientPassword)."\n"
    ."protocol=tcp\n";

if (file_put_contents($configFile, $clientConfig, LOCK_EX) === false) {
    fail('Could not create a temporary protected MySQL client configuration.');
}

try {
    $pipes = [];
    runProcess([
        $dumpBinary,
        '--defaults-extra-file='.$configFile,
        '--single-transaction',
        '--quick',
        '--routines',
        '--events',
        '--triggers',
        '--hex-blob',
        '--default-character-set=utf8mb4',
        '--result-file='.$dumpFile,
        $database,
    ], [
        0 => ['file', 'NUL', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes);

    clearstatcache(true, $dumpFile);
    if (! is_file($dumpFile) || filesize($dumpFile) < 1024) {
        fail('The SQL backup is missing or unexpectedly small.');
    }

    $backupHash = hash_file('sha256', $dumpFile);
    if ($backupHash === false) {
        fail('Could not calculate the backup checksum.');
    }

    $pdo->exec(
        'CREATE DATABASE '.quoteIdentifier($restoreDatabase).' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
    );

    $pipes = [];
    runProcess([
        $mysqlBinary,
        '--defaults-extra-file='.$configFile,
        '--default-character-set=utf8mb4',
        $restoreDatabase,
    ], [
        0 => ['file', $dumpFile, 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes);

    $restoredCounts = tableRowCounts($pdo, $restoreDatabase);
    if ($sourceCounts !== $restoredCounts) {
        fail('Restored table row counts differ from the source database.');
    }

    $pending = pendingMigrations($pdo, $restoreDatabase);
    if ($pending !== [] && $pending !== [$expectedPendingMigration]) {
        fail('Unexpected migration plan on the restored clone: '.implode(', ', $pending));
    }

    $manifest = [
        'created_at_utc' => gmdate(DATE_ATOM),
        'source_database' => $database,
        'source_host' => $mysql['host'],
        'backup_file' => basename($dumpFile),
        'backup_bytes' => filesize($dumpFile),
        'backup_sha256' => $backupHash,
        'source_base_tables' => count($sourceCounts),
        'restored_database' => $restoreDatabase,
        'restore_table_counts_match' => true,
        'migration_rehearsal' => $pending === []
            ? 'no pending migrations on isolated restore clone'
            : 'in progress on isolated restore clone only',
        'pending_migrations_before_rehearsal' => $pending,
    ];
    file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL, LOCK_EX);

    echo "Backup and restore verified: {$dumpFile}\n";
    echo "Restore-test clone: {$restoreDatabase}\n";

    if ($pending !== []) {
        $originalAppEnv = $config->get('app.env');
        $originalAppDebug = $config->get('app.debug');
        $originalDatabase = $mysql['database'];

        try {
            $config->set('app.env', 'staging');
            $config->set('app.debug', false);
            $config->set('database.connections.mysql.database', $restoreDatabase);
            $app['db']->purge('mysql');

            $activeDatabase = $app['db']->connection('mysql')->getDatabaseName();
            if ($activeDatabase !== $restoreDatabase) {
                fail('Refusing to rehearse migrations: Laravel did not connect to the isolated restore clone.');
            }

            $artisanKernel = $app->make(Kernel::class);
            $exitCode = $artisanKernel->call('migrate', [
                '--force' => true,
                '--no-interaction' => true,
                '--database' => 'mysql',
            ]);
            if ($exitCode !== 0) {
                fail('Migration rehearsal failed on the isolated restore clone: '.trim($artisanKernel->output()));
            }

            fwrite(STDOUT, $artisanKernel->output());
        } finally {
            $app['db']->purge('mysql');
            $config->set('database.connections.mysql.database', $originalDatabase);
            $config->set('app.env', $originalAppEnv);
            $config->set('app.debug', $originalAppDebug);
        }
    }

    $pendingAfterMigration = pendingMigrations($pdo, $restoreDatabase);
    if ($pendingAfterMigration !== []) {
        fail('The restore-test clone still has pending migrations after the rehearsal.');
    }

    $countsAfterMigration = tableRowCounts($pdo, $restoreDatabase);
    foreach ($restoredCounts as $table => $count) {
        if (($countsAfterMigration[$table] ?? -1) < $count) {
            fail("The staging migration rehearsal reduced row count in table {$table}.");
        }
    }

    $manifest['migration_rehearsal'] = $pending === []
        ? 'no pending migrations on isolated restore clone'
        : 'passed on isolated restore clone only';
    $manifest['pending_migrations_after_rehearsal'] = [];
    file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL, LOCK_EX);

    echo "Backup created: {$dumpFile}\n";
    echo "SHA-256: {$backupHash}\n";
    echo "Restore verification: passed ({$tableCount} base tables; exact row counts matched)\n";
    echo "Migration rehearsal: {$manifest['migration_rehearsal']} ({$restoreDatabase})\n";
    echo "The source database was not modified. The restore-test clone was retained.\n";
} finally {
    if (is_file($configFile)) {
        unlink($configFile);
    }
}
