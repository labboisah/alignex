<?php

namespace App\Http\Controllers;

use App\Http\Resources\ExamResource;
use App\Models\Exam;
use App\Services\ExamPaperGeneratorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ExamPaperController extends Controller
{
    public function __construct(private readonly ExamPaperGeneratorService $generator)
    {
    }

    public function show(Request $request, Exam $exam): Response
    {
        Gate::authorize('update', $exam);

        return Inertia::render('ExamPapers/Show', [
            'exam' => ExamResource::make($exam->load(['examType', 'examSubjects.subject'])),
            'preview' => $this->generator->preview($exam),
            'generatedPapers' => $this->generator->generatedSummary($exam),
        ]);
    }

    public function generate(Exam $exam): RedirectResponse
    {
        Gate::authorize('update', $exam);

        $result = $this->generator->generate($exam);

        return back()->with('success', "{$result['created']} papers generated. {$result['skipped']} already existed.");
    }
}
