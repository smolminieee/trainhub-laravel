<?php

namespace App\Http\Controllers;

use App\Support\LegacyPageRunner;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FeedbackController extends Controller
{
    public function __construct(private readonly LegacyPageRunner $runner) {}

    public function index(Request $request): Response
    {
        $data = $this->runner->prepare('feedback', $request);

        if (!empty($data['isPdfView'])) {
            return response()->view('legacy.feedback_pdf', $data);
        }

        if (!empty($data['isAnswerRequest'])) {
            return response()->view('legacy.feedback_answer', $data);
        }

        return response()->view('legacy.feedback', $data);
    }
}
