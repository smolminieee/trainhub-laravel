<?php

/*
 * Compatibility configuration. New certificate sending uses Laravel Mail.
 * SMTP secrets are loaded from Laravel config/.env and never hard-coded.
 */
$smtp = (array) config('mail.mailers.smtp', []);
$from = (array) config('mail.from', []);
$username = (string) ($smtp['username'] ?? '');
$password = (string) ($smtp['password'] ?? '');

return [
    'enabled' => $username !== '' && $password !== '',
    'host' => (string) ($smtp['host'] ?? 'smtp.gmail.com'),
    'port' => (int) ($smtp['port'] ?? 465),
    'encryption' => (string) (($smtp['scheme'] ?? '') === 'smtps' ? 'ssl' : 'tls'),
    'username' => $username,
    'password' => $password,
    'from_email' => (string) ($from['address'] ?? $username),
    'from_name' => (string) ($from['name'] ?? 'TrainHub Al Amin'),
    'timeout' => 20,
    'use_php_mail_fallback' => false,
];
