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
                    'eyebrow' => 'Trusted CBT operations',
                    'title' => 'Examination delivery built for schools, centers, and professional bodies',
                    'description' => 'AlignEx helps teams prepare question banks, assign candidates, deliver secure exams, monitor live sessions, manage offline centers, and release results with confidence.',
                    'badges' => ['Online and offline delivery', 'Live supervision', 'Controlled result release'],
                ],
                'solutions' => [
                    ['title' => 'Secondary Schools', 'body' => 'Plan terminal exams, mock tests, entrance assessments, and subject-based CBT sessions from one organized workspace.', 'icon' => 'GraduationCap'],
                    ['title' => 'Professional Exams', 'body' => 'Run certification exams with candidate assignment, timed delivery, supervisor review, and controlled result release.', 'icon' => 'FileCheck2'],
                    ['title' => 'Recruitment Exams', 'body' => 'Screen applicants with secure exams, candidate tracking, anti-cheating records, and export-ready reports.', 'icon' => 'Users'],
                ],
                'features' => [
                    ['title' => 'Flexible exam delivery', 'body' => 'Deliver exams online or through offline center servers where internet access is limited or controlled.', 'icon' => 'TabletSmartphone'],
                    ['title' => 'Live candidate monitoring', 'body' => 'Supervisors can track logins, progress, answer saving, submissions, and exam events in real time.', 'icon' => 'MonitorDot'],
                    ['title' => 'Question paper control', 'body' => 'Generate candidate papers before delivery and verify imported papers before offline exams begin.', 'icon' => 'Activity'],
                    ['title' => 'Results and reports', 'body' => 'Manage scoring, moderation, release decisions, exports, and operational reports from the portal.', 'icon' => 'BarChart3'],
                ],
                'workflow' => [
                    ['title' => 'Prepare', 'body' => 'Create exam structures, question banks, candidates, schedules, and delivery rules.'],
                    ['title' => 'Generate', 'body' => 'Generate candidate papers and confirm each assigned candidate has a complete paper.'],
                    ['title' => 'Deliver', 'body' => 'Run the exam online or import it into an offline center server for local delivery.'],
                    ['title' => 'Release', 'body' => 'Review submissions, finalize results, publish outcomes, and export reports.'],
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
