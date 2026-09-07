<?php

use App\Http\Middleware\StartLegacySession;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // TrainHub V4 already performs its own per-form CSRF checks.  The
        // compatibility routes keep those checks while the migration is
        // moved module-by-module into native Laravel Form Requests.
        $middleware->validateCsrfTokens(except: [
            'teacher.php',
            'course.php',
            'trainer.php',
            'feedback.php',
            'certificate.php',
        ]);

        $middleware->web(append: [StartLegacySession::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
