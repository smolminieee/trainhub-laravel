<?php

/* Laravel-backed configuration for the preserved mysqli SQL layer. */
$db = (array) config('database.connections.mysql', []);
$host = (string) ($db['host'] ?? '127.0.0.1');
$port = (int) ($db['port'] ?? 3306);
$user = (string) ($db['username'] ?? 'root');
$password = (string) ($db['password'] ?? '');
$database = (string) ($db['database'] ?? 'fyp2.0');
$socket = (string) ($db['unix_socket'] ?? '');

$conn = new mysqli($host, $user, $password, $database, $port, $socket ?: null);

if ($conn->connect_error) {
    http_response_code(500);
    exit('Database connection failed. Check the Laravel .env database settings.');
}

$conn->set_charset((string) ($db['charset'] ?? 'utf8mb4'));
