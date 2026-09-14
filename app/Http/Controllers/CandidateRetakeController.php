<?php

namespace App\Http\Controllers;

use App\Http\Requests\ScheduleCandidateRetakeRequest;
use App\Models\CandidateExamAttempt;
use App\Services\CandidateRetakeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CandidateRetakeController extends Controller
{
    public function store(ScheduleCandidateRetakeRequest $request, CandidateExamAttempt $attempt, CandidateRetakeService $service): RedirectResponse
    {
        $service->schedule($attempt, $request->validated(), $request->user());

        return back()->with('success', 'Retake scheduled. The existing result remains current until the retake is completed.');
    }

    public function cancel(Request $request, CandidateExamAttempt $attempt, CandidateRetakeService $service): RedirectResponse
    {
        $service->cancel($attempt, $request->user());

        return back()->with('success', 'Retake cancelled. The existing result is unchanged.');
    }
}
