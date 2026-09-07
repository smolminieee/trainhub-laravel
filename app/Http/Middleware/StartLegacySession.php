<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StartLegacySession
{
    public function handle(Request $request, Closure $next): Response
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_name('TRAINHUBSESSID');
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'domain' => '',
                'secure' => $request->isSecure(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }

        try {
            return $next($request);
        } finally {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
        }
    }
}
