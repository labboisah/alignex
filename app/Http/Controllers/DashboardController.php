<?php

namespace App\Http\Controllers;

use App\Models\AdminRegistrationRequest;
use App\Models\Candidate;
use App\Models\CandidateExamAttempt;
use App\Models\Center;
use App\Models\Exam;
use App\Models\Organization;
use App\Models\ProctoringEvent;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\School;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;
use App\Support\AccessControl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $examScope = $this->examScope($user);
        $attemptScope = CandidateExamAttempt::query()
            ->whereIn('exam_id', (clone $examScope)->select('id'));
        $submittedScope = (clone $attemptScope)
            ->whereIn('status', [CandidateExamAttempt::STATUS_SUBMITTED, CandidateExamAttempt::STATUS_AUTO_SUBMITTED]);

        return Inertia::render('Dashboard', [
            'role' => [
                'name' => $user->role,
                'label' => AccessControl::roleLabel($user->role),
                'scope' => $this->scopeLabel($user),
            ],
            'metrics' => $this->metrics($user, $examScope, $attemptScope, $submittedScope),
            'exam_status' => $this->examStatus($examScope),
            'result_summary' => $this->resultSummary($submittedScope),
            'organization_charts' => $this->organizationCharts($user, $examScope, $submittedScope),
            'recent_candidates' => $this->recentCandidates($user),
            'recent_results' => $this->recentResults($submittedScope),
            'recent_exams' => $this->recentExams($examScope),
            'work_queue' => $this->workQueue($user, $examScope, $attemptScope),
            'quick_actions' => $this->quickActions($user),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function metrics(User $user, Builder $examScope, Builder $attemptScope, Builder $submittedScope): array
    {
        $items = [
            ['label' => 'Exams', 'value' => (clone $examScope)->count(), 'description' => 'All exams in your scope.', 'icon' => 'ShieldCheck'],
            ['label' => 'Active Exams', 'value' => (clone $examScope)->where('status', Exam::STATUS_ACTIVE)->count(), 'description' => 'Currently open for candidates.', 'icon' => 'Activity'],
            ['label' => 'Candidates', 'value' => $this->owned(Candidate::query(), $user)->count(), 'description' => 'Registered candidate records.', 'icon' => 'Users'],
            ['label' => 'Submitted Attempts', 'value' => (clone $submittedScope)->count(), 'description' => 'Completed or auto-submitted papers.', 'icon' => 'CheckCircle2'],
            ['label' => 'Suspicious Events', 'value' => $this->proctoringScope($user, $examScope)->count(), 'description' => 'Warnings and proctoring evidence.', 'icon' => 'AlertTriangle'],
        ];

        if ($user->hasPermission('manageQuestionBank')) {
            $items[] = ['label' => 'Subjects', 'value' => $this->owned(Subject::query(), $user)->count(), 'description' => 'Available subject catalogue.', 'icon' => 'BookOpen'];
            $items[] = ['label' => 'Question Banks', 'value' => $this->owned(QuestionBank::query(), $user)->count(), 'description' => 'Banks ready for exam setup.', 'icon' => 'Library'];
            $items[] = ['label' => 'Questions', 'value' => $this->questionScope($user)->count(), 'description' => 'Questions available to this role.', 'icon' => 'FileQuestion'];
        }

        if ($user->isSuperAdmin()) {
            $items[] = ['label' => 'Organizations', 'value' => Organization::query()->count(), 'description' => 'Registered platform organizations.', 'icon' => 'Building2'];
            $items[] = ['label' => 'Pending Applications', 'value' => AdminRegistrationRequest::query()->where('status', AdminRegistrationRequest::STATUS_PENDING)->count(), 'description' => 'Admin registration requests awaiting review.', 'icon' => 'ClipboardList'];
        }

        if ($user->isOrganizationAdmin() || $user->isSuperAdmin()) {
            $items[] = ['label' => 'Centers', 'value' => $this->owned(Center::query(), $user)->count(), 'description' => 'Centers under administration.', 'icon' => 'MapPin'];
            $items[] = ['label' => 'Schools', 'value' => $this->owned(School::query(), $user)->count(), 'description' => 'Schools under administration.', 'icon' => 'GraduationCap'];
        }

        if (($user->currentContext()['type'] ?? null) === 'organization') {
            foreach ([Exam::CATEGORY_RECRUITMENT, Exam::CATEGORY_ASSESSMENT, Exam::CATEGORY_CERTIFICATION] as $category) {
                $items[] = [
                    'label' => str($category)->headline()->append(' Exams')->toString(),
                    'value' => (clone $examScope)->where('exam_category', $category)->count(),
                    'description' => 'Organization-level '.str($category)->replace('_', ' ')->toString().' exams.',
                    'icon' => 'ClipboardList',
                ];
            }

            foreach ([Exam::MODE_TRADITIONAL, Exam::MODE_ADAPTIVE] as $mode) {
                $items[] = [
                    'label' => str($mode)->headline()->append(' Exams')->toString(),
                    'value' => (clone $examScope)->where(fn (Builder $query) => $query->where('exam_mode', $mode)->orWhere('mode', $mode))->count(),
                    'description' => 'Organization exams using this delivery mode.',
                    'icon' => 'Activity',
                ];
            }

            $items[] = ['label' => 'Passed Candidates', 'value' => $this->resultSummary($submittedScope)['passed'], 'description' => 'Submitted attempts meeting pass mark.', 'icon' => 'CheckCircle2'];
            $items[] = ['label' => 'Failed Candidates', 'value' => $this->resultSummary($submittedScope)['failed'], 'description' => 'Submitted attempts below pass mark.', 'icon' => 'AlertTriangle'];
        }

        return $items;
    }

    /**
     * @return array<int, array{name: string, value: int}>
     */
    private function examStatus(Builder $examScope): array
    {
        return collect([
            Exam::STATUS_DRAFT,
            Exam::STATUS_SCHEDULED,
            Exam::STATUS_ACTIVE,
            Exam::STATUS_COMPLETED,
            Exam::STATUS_CANCELLED,
        ])->map(fn (string $status) => [
            'name' => str($status)->replace('_', ' ')->headline()->toString(),
            'value' => (clone $examScope)->where('status', $status)->count(),
        ])->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function resultSummary(Builder $submittedScope): array
    {
        $attempts = (clone $submittedScope)
            ->with('exam')
            ->get();

        $passed = $attempts->filter(fn (CandidateExamAttempt $attempt) => (float) ($attempt->score ?? 0) >= (float) ($attempt->exam?->pass_mark ?? 0))->count();
        $failed = $attempts->count() - $passed;

        return [
            'submitted' => $attempts->count(),
            'passed' => $passed,
            'failed' => $failed,
            'average_score' => round($attempts->avg(fn (CandidateExamAttempt $attempt) => (float) ($attempt->score ?? 0)) ?? 0, 2),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentExams(Builder $examScope): array
    {
        return (clone $examScope)
            ->withCount(['candidates', 'attempts'])
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn (Exam $exam) => [
                'id' => $exam->id,
                'title' => $exam->title,
                'code' => $exam->code,
                'status' => $exam->status,
                'starts_at' => $exam->starts_at?->toISOString(),
                'ends_at' => $exam->ends_at?->toISOString(),
                'candidates_count' => $exam->candidates_count,
                'attempts_count' => $exam->attempts_count,
            ])->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function workQueue(User $user, Builder $examScope, Builder $attemptScope): array
    {
        $queue = [
            ['label' => 'Scheduled Exams', 'value' => (clone $examScope)->where('status', Exam::STATUS_SCHEDULED)->count(), 'href' => '/exams', 'tone' => 'info'],
            ['label' => 'Live Monitoring', 'value' => (clone $examScope)->where('status', Exam::STATUS_ACTIVE)->count(), 'href' => '/exams', 'tone' => 'success'],
            ['label' => 'Disconnected Attempts', 'value' => (clone $attemptScope)->where('status', CandidateExamAttempt::STATUS_IN_PROGRESS)->where('server_due_at', '<', now())->count(), 'href' => '/exams', 'tone' => 'warning'],
        ];

        if ($user->hasPermission('viewReports')) {
            $queue[] = ['label' => 'Results To Review', 'value' => (clone $attemptScope)->whereIn('status', [CandidateExamAttempt::STATUS_SUBMITTED, CandidateExamAttempt::STATUS_AUTO_SUBMITTED])->whereNull('result_hash')->count(), 'href' => '/results', 'tone' => 'neutral'];
        }

        if ($user->hasPermission('manageQuestionBank')) {
            $queue[] = ['label' => 'Draft Questions', 'value' => $this->questionScope($user)->where('status', Question::STATUS_DRAFT)->count(), 'href' => '/questions', 'tone' => 'warning'];
        }

        if ($user->isSuperAdmin()) {
            $queue[] = ['label' => 'Pending Applications', 'value' => AdminRegistrationRequest::query()->where('status', AdminRegistrationRequest::STATUS_PENDING)->count(), 'href' => '/admin-registrations', 'tone' => 'warning'];
        }

        return $queue;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function quickActions(User $user): array
    {
        if (($user->currentContext()['type'] ?? null) === 'organization') {
            return collect([
                ['label' => 'Create Recruitment Exam', 'href' => '/exams/create', 'permission' => 'manageExams'],
                ['label' => 'Create Assessment Exam', 'href' => '/exams/create', 'permission' => 'manageExams'],
                ['label' => 'Create Certification Exam', 'href' => '/exams/create', 'permission' => 'manageExams'],
                ['label' => 'Create Adaptive Exam', 'href' => '/exams/create', 'permission' => 'manageExams'],
                ['label' => 'Register Candidates', 'href' => '/candidates/create', 'permission' => 'manageExams'],
                ['label' => 'Manage Question Bank', 'href' => '/question-bank', 'permission' => 'manageQuestionBank'],
                ['label' => 'View Results', 'href' => '/results', 'permission' => 'viewReports'],
                ['label' => 'Generate Reports', 'href' => '/reports', 'permission' => 'viewReports'],
                ['label' => 'Manage Users', 'href' => '/users', 'permission' => 'manageUsers'],
            ])
                ->filter(fn (array $action) => $user->hasPermission($action['permission']))
                ->map(fn (array $action) => ['label' => $action['label'], 'href' => $action['href']])
                ->values()
                ->all();
        }

        return collect([
            ['label' => 'Create Exam', 'href' => '/exams/create', 'permission' => 'manageExams'],
            ['label' => 'Add Candidate', 'href' => '/candidates/create', 'permission' => 'manageExams'],
            ['label' => 'Assign Candidates', 'href' => '/candidates/assignments', 'permission' => 'manageExams'],
            ['label' => 'Create Question', 'href' => '/questions/create', 'permission' => 'manageQuestionBank'],
            ['label' => 'Question Banks', 'href' => '/question-bank', 'permission' => 'manageQuestionBank'],
            ['label' => 'Results', 'href' => '/results', 'permission' => 'viewReports'],
            ['label' => 'Access Controls', 'href' => '/access-controls', 'permission' => 'manageAccessControls'],
            ['label' => 'Applications', 'href' => '/admin-registrations', 'permission' => 'manageAdminRegistrations'],
        ])
            ->filter(fn (array $action) => $user->hasPermission($action['permission']))
            ->map(fn (array $action) => ['label' => $action['label'], 'href' => $action['href']])
            ->values()
            ->all();
    }

    private function examScope(User $user): Builder
    {
        return Exam::query()
            ->when(! $user->isSuperAdmin(), function (Builder $query) use ($user): void {
                $query->where(function (Builder $inner) use ($user): void {
                    $inner->whereRaw('1 = 0')
                        ->when($user->organization_id, fn (Builder $scope) => $scope->orWhere('organization_id', $user->organization_id))
                        ->when($user->center_id, fn (Builder $scope) => $scope->orWhere('center_id', $user->center_id))
                        ->when($user->school_id, fn (Builder $scope) => $scope->orWhere('school_id', $user->school_id))
                        ->orWhere('created_by', $user->id);
                });
            });
    }

    private function owned(Builder $query, User $user): Builder
    {
        return $query->when(! $user->isSuperAdmin(), function (Builder $query) use ($user): void {
            $query->where(function (Builder $inner) use ($user): void {
                $inner->whereRaw('1 = 0')
                    ->when($user->organization_id, fn (Builder $scope) => $scope->orWhere('organization_id', $user->organization_id))
                    ->when($user->center_id, fn (Builder $scope) => $scope->orWhere('center_id', $user->center_id))
                    ->when($user->school_id, fn (Builder $scope) => $scope->orWhere('school_id', $user->school_id));
            });
        });
    }

    private function questionScope(User $user): Builder
    {
        return Question::query()
            ->when(! $user->isSuperAdmin(), function (Builder $query) use ($user): void {
                $query->where(function (Builder $inner) use ($user): void {
                    $inner->where('created_by', $user->id)
                        ->orWhereHas('questionBank', function (Builder $bank) use ($user): void {
                            $this->owned($bank, $user);
                        });
                });
            });
    }

    private function proctoringScope(User $user, Builder $examScope): Builder
    {
        return ProctoringEvent::query()
            ->whereIn('exam_id', (clone $examScope)->select('id'))
            ->whereIn('severity', ['warning', 'high', 'critical']);
    }

    private function scopeLabel(User $user): string
    {
        return match (true) {
            $user->isSuperAdmin() => 'Platform-wide',
            $user->organization_id !== null => 'Organization scope',
            $user->center_id !== null => 'Center scope',
            $user->school_id !== null => 'School scope',
            default => 'Personal scope',
        };
    }

    private function organizationCharts(User $user, Builder $examScope, Builder $submittedScope): array
    {
        if (($user->currentContext()['type'] ?? null) !== 'organization') {
            return [];
        }

        $submitted = (clone $submittedScope)->with('exam')->get();
        $passFail = $this->resultSummary($submittedScope);

        return [
            'exams_by_category' => collect([Exam::CATEGORY_RECRUITMENT, Exam::CATEGORY_ASSESSMENT, Exam::CATEGORY_CERTIFICATION, Exam::CATEGORY_PROFESSIONAL, Exam::CATEGORY_PRACTICE, Exam::CATEGORY_GENERAL])
                ->map(fn (string $category) => ['name' => str($category)->headline()->toString(), 'value' => (clone $examScope)->where('exam_category', $category)->count()])
                ->values()
                ->all(),
            'exams_by_mode' => collect([Exam::MODE_TRADITIONAL, Exam::MODE_ADAPTIVE])
                ->map(fn (string $mode) => ['name' => str($mode)->headline()->toString(), 'value' => (clone $examScope)->where(fn (Builder $query) => $query->where('exam_mode', $mode)->orWhere('mode', $mode))->count()])
                ->values()
                ->all(),
            'candidate_performance' => [
                ['name' => 'Average Score', 'value' => $passFail['average_score']],
                ['name' => 'Submitted', 'value' => $submitted->count()],
            ],
            'pass_fail_summary' => [
                ['name' => 'Passed', 'value' => $passFail['passed']],
                ['name' => 'Failed', 'value' => $passFail['failed']],
            ],
            'certification_status' => [
                ['name' => 'Certification Exams', 'value' => (clone $examScope)->where('exam_category', Exam::CATEGORY_CERTIFICATION)->count()],
                ['name' => 'Certification Results', 'value' => $submitted->where('exam.exam_category', Exam::CATEGORY_CERTIFICATION)->count()],
            ],
        ];
    }

    private function recentCandidates(User $user): array
    {
        return $this->owned(Candidate::query(), $user)
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (Candidate $candidate) => [
                'id' => $candidate->id,
                'name' => trim($candidate->first_name.' '.$candidate->last_name),
                'registration_number' => $candidate->candidate_number,
                'status' => $candidate->status,
            ])
            ->all();
    }

    private function recentResults(Builder $submittedScope): array
    {
        return (clone $submittedScope)
            ->with(['candidate', 'exam'])
            ->latest('submitted_at')
            ->limit(5)
            ->get()
            ->map(fn (CandidateExamAttempt $attempt) => [
                'id' => $attempt->id,
                'candidate_name' => trim(($attempt->candidate?->first_name ?? '').' '.($attempt->candidate?->last_name ?? '')),
                'exam_title' => $attempt->exam?->title,
                'score' => $attempt->score,
                'submitted_at' => $attempt->submitted_at?->toISOString(),
            ])
            ->all();
    }
}
