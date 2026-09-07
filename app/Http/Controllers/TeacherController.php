<?php

namespace App\Http\Controllers;

use App\Support\LegacyPageRunner;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TeacherController extends Controller
{
    public function __construct(private readonly LegacyPageRunner $runner) {}

    public function index(Request $request): Response
    {
        return $this->runner->renderBlade('teacher', $request, 'legacy.teacher');
    }
}
