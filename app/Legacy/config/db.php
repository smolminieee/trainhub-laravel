<?php

/*
 * Laravel-backed configuration for the preserved mysqli SQL layer.
 *
 * TrainHub now uses the same database configured in Laravel's .env.  Do not
 * hard-code a schema name in individual legacy pages: the canonical combined
 * database is alamin_main_database, but this file remains environment-aware so
 * the project can also be deployed with a different DB_DATABASE value.
 */
$db = (array) config('database.connections.mysql', []);
$host = (string) ($db['host'] ?? '127.0.0.1');
$port = (int) ($db['port'] ?? 3306);
$user = (string) ($db['username'] ?? 'root');
$password = (string) ($db['password'] ?? '');
$database = (string) ($db['database'] ?? 'alamin_main_database');
$socket = (string) ($db['unix_socket'] ?? '');
$charset = (string) ($db['charset'] ?? 'utf8mb4');
$collation = (string) ($db['collation'] ?? 'utf8mb4_unicode_ci');

if (!defined('TRAINHUB_DATABASE_NAME')) {
    define('TRAINHUB_DATABASE_NAME', $database);
}

$conn = mysqli_init();
if (!$conn) {
    http_response_code(500);
    exit('Database connection could not be initialized.');
}

/* Fail quickly when MySQL/XAMPP is unavailable instead of leaving the page hanging. */
mysqli_options($conn, MYSQLI_OPT_CONNECT_TIMEOUT, 5);

try {
    $connected = mysqli_real_connect(
        $conn,
        $host,
        $user,
        $password,
        $database,
        $port,
        $socket !== '' ? $socket : null
    );
} catch (Throwable $e) {
    error_log('TrainHub database connection error: ' . $e->getMessage());
    $connected = false;
}

if (!$connected) {
    http_response_code(500);
    exit('Database connection failed. Check DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME and DB_PASSWORD in .env.');
}

if (!mysqli_set_charset($conn, $charset)) {
    error_log('Unable to set database charset ' . $charset . ': ' . mysqli_error($conn));
}

/* The canonical schema standardizes text data on utf8mb4_unicode_ci. */
$safeCollation = preg_replace('/[^A-Za-z0-9_]/', '', $collation) ?: 'utf8mb4_unicode_ci';
@mysqli_query($conn, "SET collation_connection = '{$safeCollation}'");
