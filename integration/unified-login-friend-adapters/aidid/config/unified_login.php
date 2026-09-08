<?php
return [
    'enabled' => env('UNIFIED_LOGIN_ENABLED', true),
    'login_url' => env('UNIFIED_LOGIN_URL', 'http://127.0.0.1:8000/login.php'),
    'logout_url' => env('UNIFIED_LOGOUT_URL', 'http://127.0.0.1:8000/logout.php'),
];
