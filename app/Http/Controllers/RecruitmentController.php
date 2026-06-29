<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\User;
use App\Services\RecruitmentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class RecruitmentController extends Controller
{
    public function __construct(private readonly RecruitmentService $recruitment)
    {
    }

    public function show(Request $request, Exam $exam): InertiaResponse
    {
        $this->authorizeExam($request->user(), $exam);

        return Inertia::render('Recruitment/Show', [
            'exam' => [
                'id' => $exam->id,
                'title' => $exam->title,
                'exam_code' => $exam->code,
                'total_marks' => $exam->total_marks,
                'pass_mark' => $exam->pass_mark,
            ],
            'settings' => $this->recruitment->settings($exam),
            'ranking' => $this->recruitment->ranking($exam),
            'shortlisted' => $this->recruitment->shortlisted($exam),
            'anomalies' => $this->recruitment->anomalies($exam),
        ]);
    }

    public function updateSettings(Request $request, Exam $exam): RedirectResponse
    {
        $this->authorizeExam($request->user(), $exam);
        $data = $request->validate([
            'cutoff_score' => ['required', 'numeric', 'min:0'],
            'auto_shortlist' => ['boolean'],
            'shortlist_limit' => ['nullable', 'integer', 'min:1'],
            'negative_marking' => ['boolean'],
            'negative_mark_value' => ['nullable', 'numeric', 'min:0'],
        ]);

        $this->recruitment->updateSettings($exam, $data);

        return back()->with('success', 'Recruitment settings updated.');
    }

    public function generateAccessCodes(Request $request, Exam $exam): RedirectResponse
    {
        $this->authorizeExam($request->user(), $exam);
        $count = $this->recruitment->generateAccessCodes($exam);

        return back()->with('success', "{$count} access codes generated.");
    }

    public function applyShortlist(Request $request, Exam $exam): RedirectResponse
    {
        $this->authorizeExam($request->user(), $exam);
        $count = $this->recruitment->applyShortlist($exam);

        return back()->with('success', "{$count} candidates shortlisted.");
    }

    public function exportShortlist(Request $request, Exam $exam)
    {
        $this->authorizeExam($request->user(), $exam);

        return $this->csv("{$exam->code}-shortlist.csv", $this->recruitment->shortlisted($exam), [
            'Rank',
            'Candidate Name',
            'Registration Number',
            'Email',
            'Phone',
            'Score',
            'Percentage',
            'Status',
        ], fn (array $row) => [
            $row['rank'],
            $row['candidate_name'],
            $row['registration_number'],
            $row['email'],
            $row['phone'],
            $row['score'],
            $row['percentage'],
            $row['status'],
        ]);
    }

    public function exportAccessCodes(Request $request, Exam $exam)
    {
        $this->authorizeExam($request->user(), $exam);

        return $this->csv("{$exam->code}-access-codes.csv", $this->recruitment->accessCodeRows($exam), [
            'Candidate Name',
            'Registration Number',
            'Access Code',
        ], fn (array $row) => [
            $row['candidate_name'],
            $row['registration_number'],
            $row['access_code'],
        ]);
    }

    private function csv(string $filename, $rows, array $headers, callable $mapper)
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $headers);

        foreach ($rows as $row) {
            fputcsv($handle, $mapper($row));
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return Response::make($content ?: '', 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function authorizeExam(User $user, Exam $exam): void
    {
        abort_unless($user->hasPermission('manageExams') || $user->hasPermission('viewReports'), 403);
        abort_unless($this->examScope($user)->whereKey($exam->id)->exists(), 403);
        abort_unless(($exam->examType?->code ?? null) === 'recruitment', 404);
    }

    private function examScope(User $user): Builder
    {
        return Exam::query()
            ->with('examType')
            ->when(! $user->isSuperAdmin(), function (Builder $query) use ($user): void {
                $query->where(function (Builder $inner) use ($user): void {
                    $inner->whereRaw('1 = 0')
                        ->when($user->organization_id, fn ($scope) => $scope->orWhere('organization_id', $user->organization_id))
                        ->when($user->school_id, fn ($scope) => $scope->orWhere('school_id', $user->school_id))
                        ->when($user->center_id, fn ($scope) => $scope->orWhere('center_id', $user->center_id));
                });
            });
    }
}
