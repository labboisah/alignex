<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use App\Models\CandidateExamAttempt;
use App\Models\Center;
use App\Models\Exam;
use App\Models\Organization;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\School;
use App\Models\Subject;
use App\Services\ExamStatusService;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;

class PublicWelcomeController extends Controller
{
    public function __invoke(): Response
    {
        app(ExamStatusService::class)->syncOverdue();

        $activeExams = Exam::query()->where('status', Exam::STATUS_ACTIVE)->count();
        $scheduledExams = Exam::query()->where('status', Exam::STATUS_SCHEDULED)->count();
        $completedExams = Exam::query()->where('status', Exam::STATUS_COMPLETED)->count();
        $candidates = Candidate::query()->count();
        $questionBanks = QuestionBank::query()->count();
        $questions = Question::query()->count();
        $subjects = Subject::query()->count();
        $institutions = Organization::query()->count() + School::query()->count() + Center::query()->count();
        $attempts = CandidateExamAttempt::query()->count();
        $submittedAttempts = CandidateExamAttempt::query()
            ->whereIn('status', [
                CandidateExamAttempt::STATUS_SUBMITTED,
                CandidateExamAttempt::STATUS_AUTO_SUBMITTED,
            ])
            ->count();

        $examForMockup = Exam::query()
            ->withSum('examSubjects as question_total', 'question_count')
            ->whereIn('status', [Exam::STATUS_ACTIVE, Exam::STATUS_SCHEDULED])
            ->orderByRaw("case when status = ? then 0 else 1 end", [Exam::STATUS_ACTIVE])
            ->orderBy('starts_at')
            ->first();

        return Inertia::render('Public/Welcome', [
            'landing' => [
                'hero' => [
                    'eyebrow' => 'Secure CBT operations',
                    'title' => 'Secure Online and Offline CBT Examination Platform',
                    'description' => 'AlignEx helps institutions deliver secondary school exams, professional certification exams, and recruitment exams with support for adaptive assessment, online and future offline delivery, real-time supervisor monitoring, anti-cheating controls, result management, and reports.',
                    'badges' => ['Laravel + Inertia', 'React + TypeScript', 'Reverb-ready'],
                ],
                'solutions' => [
                    ['title' => 'Secondary Schools', 'body' => 'Run term exams, mock tests, entrance assessments, and multi-subject CBT sessions with structured subjects and topics.', 'icon' => 'GraduationCap'],
                    ['title' => 'Professional Exams', 'body' => 'Deliver certification assessments with timed sections, question banks, supervisor monitoring, and controlled result release.', 'icon' => 'FileCheck2'],
                    ['title' => 'Recruitment Exams', 'body' => 'Screen applicants at scale with secure online tests, candidate identity controls, and report-ready analytics.', 'icon' => 'Users'],
                ],
                'features' => [
                    ['title' => 'Hybrid delivery', 'body' => 'Online delivery now, with offline center-based examination planned for controlled venues.', 'icon' => 'TabletSmartphone'],
                    ['title' => 'Real-time monitoring', 'body' => 'Supervisors can track candidate status, incidents, timing, and live exam activity.', 'icon' => 'MonitorDot'],
                    ['title' => 'Adaptive-ready', 'body' => 'Architecture leaves room for future FastAPI-based adaptive question selection.', 'icon' => 'Activity'],
                    ['title' => 'Result management', 'body' => 'Support scoring, moderation, release workflows, reports, and exports.', 'icon' => 'BarChart3'],
                ],
                'workflow' => [
                    ['title' => 'Prepare', 'body' => 'Create subjects, topics, question banks, candidates, and exam settings.'],
                    ['title' => 'Deliver', 'body' => 'Candidates write in a focused exam interface while answers autosave through secure APIs.'],
                    ['title' => 'Monitor', 'body' => 'Supervisors review live sessions, warnings, and anti-cheating events in real time.'],
                    ['title' => 'Release', 'body' => 'Scores are reviewed, approved, released, and exported through controlled result workflows.'],
                ],
                'stats' => [
                    ['value' => $this->formatNumber(Exam::query()->count()), 'label' => 'Exams configured'],
                    ['value' => $this->formatNumber($candidates), 'label' => 'Candidates managed'],
                    ['value' => $this->formatNumber($questionBanks), 'label' => 'Question banks'],
                    ['value' => '100%', 'label' => 'Answer-key isolation'],
                ],
                'metrics' => [
                    ['label' => 'Active Exams', 'value' => $this->formatNumber($activeExams)],
                    ['label' => 'Candidates', 'value' => $this->formatNumber($candidates)],
                    ['label' => 'Question Banks', 'value' => $this->formatNumber($questionBanks)],
                ],
                'activity' => [
                    'label' => 'Submissions in the last 7 days',
                    'bars' => $this->submissionActivity(),
                ],
                'candidateMockup' => [
                    'question_label' => $examForMockup
                        ? 'Question 1 of '.max(1, (int) ($examForMockup->question_total ?? 1))
                        : 'Question 1 of 1',
                    'title' => $examForMockup?->title ?? 'No active exam yet',
                    'timer_label' => $examForMockup?->duration_minutes
                        ? $examForMockup->duration_minutes.' mins'
                        : 'Ready',
                ],
                'operations' => [
                    ['label' => 'Institutions', 'value' => $this->formatNumber($institutions)],
                    ['label' => 'Subjects', 'value' => $this->formatNumber($subjects)],
                    ['label' => 'Questions', 'value' => $this->formatNumber($questions)],
                    ['label' => 'Scheduled Exams', 'value' => $this->formatNumber($scheduledExams)],
                    ['label' => 'Completed Exams', 'value' => $this->formatNumber($completedExams)],
                    ['label' => 'Submitted Attempts', 'value' => $this->formatNumber($submittedAttempts)],
                    ['label' => 'Total Attempts', 'value' => $this->formatNumber($attempts)],
                ],
            ],
        ]);
    }

    /**
     * @return array<int, int>
     */
    private function submissionActivity(): array
    {
        $start = CarbonImmutable::now()->subDays(6)->startOfDay();

        $counts = collect(range(0, 6))->map(function (int $offset) use ($start): int {
            $day = $start->addDays($offset);

            return CandidateExamAttempt::query()
                ->whereNotNull('submitted_at')
                ->whereBetween('submitted_at', [$day->startOfDay(), $day->endOfDay()])
                ->count();
        });

        $max = max(1, $counts->max() ?? 0);

        return $counts
            ->map(fn (int $count): int => max(8, (int) round(($count / $max) * 100)))
            ->values()
            ->all();
    }

    private function formatNumber(int|float $value): string
    {
        return number_format($value);
    }
}
