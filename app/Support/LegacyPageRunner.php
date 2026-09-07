<?php

namespace App\Support;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Compatibility service used during the Laravel migration.
 *
 * Complex TrainHub V4 SQL/business rules are executed from *.logic.php files,
 * while the HTML is rendered through Laravel Blade views. This preserves the
 * tested database behaviour while allowing routes, controllers, models,
 * configuration and presentation to live inside Laravel immediately.
 */
class LegacyPageRunner
{
    private const ALLOWED = [
        'dashboard', 'teacher', 'course',
        'trainer', 'feedback', 'certificate',
    ];

    public function prepare(string $page, Request $request): array
    {
        if (!in_array($page, self::ALLOWED, true)) {
            abort(404);
        }

        $file = app_path('Legacy/'.$page.'.logic.php');
        if (!is_file($file)) {
            abort(500, "Laravel migration logic file is missing for {$page}.");
        }

        $this->syncRequestGlobals($request, $page);

        // Execute from the legacy module directory so relative upload paths
        // (uploads/..., generated/...) keep the exact V4 behaviour.
        $previousCwd = getcwd();
        chdir(app_path('Legacy'));
        try {
            return (static function (string $__logicFile): array {
                include $__logicFile;
                $vars = get_defined_vars();
                unset($vars['__logicFile']);
                return $vars;
            })($file);
        } finally {
            if ($previousCwd !== false) {
                chdir($previousCwd);
            }
        }
    }

    public function renderBlade(string $page, Request $request, ?string $view = null): Response
    {
        $data = $this->prepare($page, $request);
        return response()->view($view ?? 'legacy.'.$page, $data);
    }


    private function syncRequestGlobals(Request $request, string $page): void
    {
        $_SERVER['PHP_SELF'] = '/'.$page.'.php';
        $_GET = $request->query->all();
        $_POST = $request->isMethod('post') ? $request->request->all() : [];
    }

}
