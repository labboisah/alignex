<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\AcademicTerm;
use App\Models\AdminRegistrationRequest;
use App\Models\Candidate;
use App\Models\CandidateExamAttempt;
use App\Models\Certificate;
use App\Models\CbtCenter;
use App\Models\Course;
use App\Models\Exam;
use App\Models\ProfessionalModule;
use App\Models\ProfessionalSchool;
use App\Models\Programme;
use App\Models\QuestionBank;
use App\Models\Organization;
use App\Models\SecondarySchool;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Support\AccessControl;
use Illuminate\Database\Eloquent\Builder;

class DashboardSummaryService
{
    public function summary(User $user, ?array $context): array
    {
        $context ??= app(CurrentContextService::class)->current($user);
        $examScope = $this->examScope($context);
        $attemptScope = CandidateExamAttempt::query()->whereIn('exam_id', (clone $examScope)->select('id'));
        $submittedScope = (clone $attemptScope)->whereIn('status', [CandidateExamAttempt::STATUS_SUBMITTED, CandidateExamAttempt::STATUS_AUTO_SUBMITTED]);
        $result = $this->resultSummary($submittedScope);

        return [
            'role' => [
                'name' => $user->role,
                'label' => AccessControl::roleLabel($user->role),
                'scope' => match ($context['type'] ?? null) {
                    'organization' => 'Organization scope',
                    'secondary_school' => 'Secondary school scope',
                    'professional_school' => 'Professional school scope',
                    'cbt_center' => 'CBT center scope',
                    default => 'Platform-wide',
                },
            ],
            'current_context' => $context,
            'metrics' => $this->metrics($context, $examScope, $submittedScope, $result),
            'exam_status' => $this->examStatus($examScope),
            'result_summary' => $result,
            'charts' => $this->charts($context, $examScope, $submittedScope, $result),
            'organization_charts' => $this->charts($context, $examScope, $submittedScope, $result),
            'recent_candidates' => $this->recentCandidates($context),
            'recent_results' => $this->recentResults($submittedScope),
            'recent_exams' => $this->recentExams($examScope),
            'work_queue' => $this->workQueue($examScope, $attemptScope),
            'quick_actions' => $this->quickActions($user, $context),
        ];
    }

    private function metrics(?array $context, Builder $examScope, Builder $submittedScope, array $result): array
    {
        $type = $context['type'] ?? 'platform';
        $id = $context['id'] ?? null;

        return match ($type) {
            'platform' => [
                $this->metric('Total Organizations', Organization::query()->count(), 'Registered organizations.', 'Building2'),
                $this->metric('Total Secondary Schools', SecondarySchool::query()->count(), 'Registered secondary schools.', 'GraduationCap'),
                $this->metric('Total Professional Schools', ProfessionalSchool::query()->count(), 'Registered professional schools.', 'GraduationCap'),
                $this->metric('Total CBT Centers', CbtCenter::query()->count(), 'Registered CBT centers.', 'MapPin'),
                $this->metric('Total Exams', (clone $examScope)->count(), 'All platform exams.', 'ShieldCheck'),
                $this->metric('Active Exams', (clone $examScope)->where('status', Exam::STATUS_ACTIVE)->count(), 'Currently active exams.', 'Activity'),
                $this->metric('Total Candidates', Candidate::query()->count(), 'All candidate records.', 'Users'),
                $this->metric('Total Question Banks', QuestionBank::query()->count(), 'All question banks.', 'Library'),
                $this->metric('Pending Applications', AdminRegistrationRequest::query()->where('status', AdminRegistrationRequest::STATUS_PENDING)->count(), 'Admin registrations awaiting review.', 'ClipboardList'),
                $this->metric('Submitted Results', $result['submitted'], 'Submitted attempts.', 'CheckCircle2'),
            ],
            'secondary_school' => [
                $this->metric('Total Students', Student::query()->where('secondary_school_id', $id)->count(), 'Registered students.', 'Users'),
                $this->metric('Total Classes', SchoolClass::query()->where('secondary_school_id', $id)->count(), 'Configured classes.', 'GraduationCap'),
                $this->metric('Total Subjects', Subject::query()->where('secondary_school_id', $id)->count(), 'Subjects available.', 'BookOpen'),
                $this->metric('Active Academic Session', AcademicSession::query()->where('secondary_school_id', $id)->where('is_active', true)->count(), 'Current active session.', 'ClipboardList'),
                $this->metric('Active Term', AcademicTerm::query()->where('secondary_school_id', $id)->where('is_active', true)->count(), 'Current active term.', 'ClipboardList'),
                $this->metric('Terminal Exams', (clone $examScope)->where('exam_category', Exam::CATEGORY_TERMINAL)->count(), 'Terminal exams.', 'ShieldCheck'),
                $this->metric('Completed Exams', (clone $examScope)->where('status', Exam::STATUS_COMPLETED)->count(), 'Completed exams.', 'CheckCircle2'),
                $this->metric('Pending Results', (clone $submittedScope)->whereNull('result_hash')->count(), 'Submitted attempts awaiting result hash.', 'AlertTriangle'),
                $this->metric('Report Cards Generated', 0, 'Report card workflow foundation.', 'FileQuestion'),
                $this->metric('Low Performance Alerts', $result['failed'], 'Failed submitted attempts.', 'AlertTriangle'),
            ],
            'professional_school' => [
                $this->metric('Total Candidates / Trainees', Candidate::query()->where('professional_school_id', $id)->count(), 'Registered candidates and trainees.', 'Users'),
                $this->metric('Total Programmes', Programme::query()->where('professional_school_id', $id)->count(), 'Professional programmes.', 'GraduationCap'),
                $this->metric('Total Courses', Course::query()->where('professional_school_id', $id)->count(), 'Courses.', 'BookOpen'),
                $this->metric('Total Modules', ProfessionalModule::query()->where('professional_school_id', $id)->count(), 'Modules.', 'Library'),
                $this->metric('Training Batches', Candidate::query()->where('professional_school_id', $id)->whereNotNull('training_batch_id')->distinct('training_batch_id')->count('training_batch_id'), 'Training batch usage.', 'ClipboardList'),
                $this->metric('Professional Exams', (clone $examScope)->where('exam_category', Exam::CATEGORY_PROFESSIONAL)->count(), 'Professional exams.', 'ShieldCheck'),
                $this->metric('Adaptive Exams', (clone $examScope)->where(fn ($query) => $query->where('exam_mode', Exam::MODE_ADAPTIVE)->orWhere('mode', Exam::MODE_ADAPTIVE))->count(), 'Adaptive exams.', 'Activity'),
                $this->metric('Certification Exams', (clone $examScope)->where('exam_category', Exam::CATEGORY_CERTIFICATION)->count(), 'Certification exams.', 'ClipboardList'),
                $this->metric('Passed Candidates', $result['passed'], 'Passed submissions.', 'CheckCircle2'),
                $this->metric('Failed Candidates', $result['failed'], 'Failed submissions.', 'AlertTriangle'),
                $this->metric('Certificates Generated', Certificate::query()->where('professional_school_id', $id)->count(), 'Issued certificates.', 'CheckCircle2'),
            ],
            'cbt_center' => [
                $this->metric('Total Candidates', Candidate::query()->where('cbt_center_id', $id)->count(), 'Registered candidates.', 'Users'),
                $this->metric('Total Question Banks', QuestionBank::query()->where('cbt_center_id', $id)->count(), 'Question banks.', 'Library'),
                $this->metric('Total Exams', (clone $examScope)->count(), 'All center exams.', 'ShieldCheck'),
                $this->metric('Traditional Exams', (clone $examScope)->where(fn ($query) => $query->where('exam_mode', Exam::MODE_TRADITIONAL)->orWhere('mode', Exam::MODE_TRADITIONAL))->count(), 'Traditional CBT exams.', 'ClipboardList'),
                $this->metric('Adaptive Exams', (clone $examScope)->where(fn ($query) => $query->where('exam_mode', Exam::MODE_ADAPTIVE)->orWhere('mode', Exam::MODE_ADAPTIVE))->count(), 'Adaptive CBT exams.', 'Activity'),
                $this->metric('Active Exams', (clone $examScope)->where('status', Exam::STATUS_ACTIVE)->count(), 'Active exams.', 'Activity'),
                $this->metric('Completed Exams', (clone $examScope)->where('status', Exam::STATUS_COMPLETED)->count(), 'Completed exams.', 'CheckCircle2'),
                $this->metric('Pending Results', (clone $submittedScope)->whereNull('result_hash')->count(), 'Pending result reviews.', 'AlertTriangle'),
                $this->metric('Available Seats / Capacity', CbtCenter::query()->find($id)?->capacity ?? 0, 'Registered center capacity.', 'MapPin'),
                $this->metric('Recent Exams', (clone $examScope)->where('created_at', '>=', now()->subDays(30))->count(), 'Created in the last 30 days.', 'ClipboardList'),
            ],
            default => [
                $this->metric('Total Exams', (clone $examScope)->count(), 'All organization exams.', 'ShieldCheck'),
                $this->metric('Recruitment Exams', (clone $examScope)->where('exam_category', Exam::CATEGORY_RECRUITMENT)->count(), 'Recruitment exams.', 'ClipboardList'),
                $this->metric('Assessment Exams', (clone $examScope)->where('exam_category', Exam::CATEGORY_ASSESSMENT)->count(), 'Assessment exams.', 'ClipboardList'),
                $this->metric('Certification Exams', (clone $examScope)->where('exam_category', Exam::CATEGORY_CERTIFICATION)->count(), 'Certification exams.', 'ClipboardList'),
                $this->metric('Traditional Exams', (clone $examScope)->where(fn ($query) => $query->where('exam_mode', Exam::MODE_TRADITIONAL)->orWhere('mode', Exam::MODE_TRADITIONAL))->count(), 'Traditional exams.', 'Activity'),
                $this->metric('Adaptive Exams', (clone $examScope)->where(fn ($query) => $query->where('exam_mode', Exam::MODE_ADAPTIVE)->orWhere('mode', Exam::MODE_ADAPTIVE))->count(), 'Adaptive exams.', 'Activity'),
                $this->metric('Total Candidates', Candidate::query()->where('organization_id', $id)->count(), 'Registered candidates.', 'Users'),
                $this->metric('Total Question Banks', QuestionBank::query()->where('organization_id', $id)->count(), 'Question banks.', 'Library'),
                $this->metric('Total Results', $result['submitted'], 'Submitted results.', 'CheckCircle2'),
                $this->metric('Passed Candidates', $result['passed'], 'Passed candidates.', 'CheckCircle2'),
                $this->metric('Failed Candidates', $result['failed'], 'Failed candidates.', 'AlertTriangle'),
                $this->metric('Total Secondary Schools', SecondarySchool::query()->where('organization_id', $id)->count(), 'Secondary schools.', 'GraduationCap'),
                $this->metric('Total Professional Schools', ProfessionalSchool::query()->where('organization_id', $id)->count(), 'Professional schools.', 'GraduationCap'),
                $this->metric('Total CBT Centers', CbtCenter::query()->where('organization_id', $id)->count(), 'CBT centers.', 'MapPin'),
            ],
        };
    }

    private function examScope(?array $context): Builder
    {
        $type = $context['type'] ?? null;
        $id = $context['id'] ?? null;

        return Exam::query()
            ->when($type === 'organization', fn ($query) => $query->where('organization_id', $id))
            ->when($type === 'secondary_school', fn ($query) => $query->where('secondary_school_id', $id))
            ->when($type === 'professional_school', fn ($query) => $query->where('professional_school_id', $id))
            ->when($type === 'cbt_center', fn ($query) => $query->where('cbt_center_id', $id));
    }

    private function charts(?array $context, Builder $examScope, Builder $submittedScope, array $result): array
    {
        return [
            'exams_by_category' => collect(Exam::CATEGORIES)->map(fn ($category) => ['name' => str($category)->headline()->toString(), 'value' => (clone $examScope)->where('exam_category', $category)->count()])->values()->all(),
            'exams_by_mode' => collect([Exam::MODE_TRADITIONAL, Exam::MODE_ADAPTIVE])->map(fn ($mode) => ['name' => str($mode)->headline()->toString(), 'value' => (clone $examScope)->where(fn ($query) => $query->where('exam_mode', $mode)->orWhere('mode', $mode))->count()])->values()->all(),
            'candidate_performance' => [['name' => 'Average Score', 'value' => $result['average_score']], ['name' => 'Submitted', 'value' => $result['submitted']]],
            'pass_fail_summary' => [['name' => 'Passed', 'value' => $result['passed']], ['name' => 'Failed', 'value' => $result['failed']]],
            'recent_exam_activity' => (clone $examScope)->latest()->limit(6)->get()->map(fn (Exam $exam) => ['name' => str($exam->title)->limit(18)->toString(), 'value' => 1])->all(),
            'certification_status' => [['name' => 'Certification Exams', 'value' => (clone $examScope)->where('exam_category', Exam::CATEGORY_CERTIFICATION)->count()], ['name' => 'Certificates', 'value' => Certificate::query()->whereIn('exam_id', (clone $examScope)->select('id'))->count()]],
        ];
    }

    private function resultSummary(Builder $submittedScope): array
    {
        $attempts = (clone $submittedScope)->with('exam')->get();
        $passed = $attempts->filter(fn ($attempt) => ($attempt->result_status === 'passed') || ((float) ($attempt->score ?? 0) >= (float) ($attempt->exam?->pass_mark ?? 0)))->count();

        return [
            'submitted' => $attempts->count(),
            'passed' => $passed,
            'failed' => $attempts->count() - $passed,
            'average_score' => round($attempts->avg(fn ($attempt) => (float) ($attempt->score ?? 0)) ?? 0, 2),
        ];
    }

    private function examStatus(Builder $examScope): array
    {
        return collect([Exam::STATUS_DRAFT, Exam::STATUS_SCHEDULED, Exam::STATUS_ACTIVE, Exam::STATUS_COMPLETED, Exam::STATUS_CANCELLED])
            ->map(fn ($status) => ['name' => str($status)->replace('_', ' ')->headline()->toString(), 'value' => (clone $examScope)->where('status', $status)->count()])
            ->values()
            ->all();
    }

    private function recentExams(Builder $examScope): array
    {
        return (clone $examScope)->withCount(['candidates', 'attempts'])->latest()->limit(6)->get()->map(fn (Exam $exam) => [
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

    private function recentResults(Builder $submittedScope): array
    {
        return (clone $submittedScope)->with(['candidate', 'exam'])->latest('submitted_at')->limit(5)->get()->map(fn ($attempt) => [
            'id' => $attempt->id,
            'candidate_name' => trim(($attempt->candidate?->first_name ?? '').' '.($attempt->candidate?->last_name ?? '')),
            'exam_title' => $attempt->exam?->title,
            'score' => $attempt->score,
            'submitted_at' => $attempt->submitted_at?->toISOString(),
        ])->all();
    }

    private function recentCandidates(?array $context): array
    {
        $type = $context['type'] ?? null;
        $id = $context['id'] ?? null;

        if ($type === 'secondary_school') {
            return Student::query()->where('secondary_school_id', $id)->latest()->limit(5)->get()->map(fn ($student) => [
                'id' => (string) $student->id,
                'name' => trim($student->first_name.' '.$student->last_name),
                'registration_number' => $student->admission_number,
                'status' => $student->status,
            ])->all();
        }

        return Candidate::query()
            ->when($type === 'organization', fn ($query) => $query->where('organization_id', $id))
            ->when($type === 'professional_school', fn ($query) => $query->where('professional_school_id', $id))
            ->when($type === 'cbt_center', fn ($query) => $query->where('cbt_center_id', $id))
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn ($candidate) => [
                'id' => $candidate->id,
                'name' => trim($candidate->first_name.' '.$candidate->last_name),
                'registration_number' => $candidate->candidate_number,
                'status' => $candidate->status,
            ])->all();
    }

    private function workQueue(Builder $examScope, Builder $attemptScope): array
    {
        return [
            ['label' => 'Scheduled Exams', 'value' => (clone $examScope)->where('status', Exam::STATUS_SCHEDULED)->count(), 'href' => '/exams', 'tone' => 'info'],
            ['label' => 'Live Monitoring', 'value' => (clone $examScope)->where('status', Exam::STATUS_ACTIVE)->count(), 'href' => '/exams', 'tone' => 'success'],
            ['label' => 'Pending Results', 'value' => (clone $attemptScope)->whereIn('status', [CandidateExamAttempt::STATUS_SUBMITTED, CandidateExamAttempt::STATUS_AUTO_SUBMITTED])->whereNull('result_hash')->count(), 'href' => '/results', 'tone' => 'warning'],
        ];
    }

    private function quickActions(User $user, ?array $context): array
    {
        $type = $context['type'] ?? 'platform';
        $id = $context['id'] ?? null;
        $secondaryBase = $type === 'secondary_school' ? '/secondary-schools/'.$id : '/secondary-school';
        $professionalBase = $type === 'professional_school' ? '/professional-schools/'.$id : '/professional-schools';
        $cbtBase = $type === 'cbt_center' ? '/cbt-centers/'.$id : '/cbt-centers';

        $actions = match ($type) {
            'secondary_school' => [
                ['label' => 'Manage Academic Sessions', 'href' => $secondaryBase.'/academic-sessions', 'permission' => 'manageSchools'],
                ['label' => 'Manage Terms', 'href' => $secondaryBase.'/terms', 'permission' => 'manageSchools'],
                ['label' => 'Manage Classes', 'href' => $secondaryBase.'/classes', 'permission' => 'manageSchools'],
                ['label' => 'Upload Students', 'href' => $secondaryBase.'/students', 'permission' => 'manageExams'],
                ['label' => 'Create Terminal Exam', 'href' => '/exams/create', 'permission' => 'manageExams'],
                ['label' => 'View Result Sheet', 'href' => '/results', 'permission' => 'viewReports'],
                ['label' => 'Generate Report Cards', 'href' => '/results', 'permission' => 'viewReports'],
            ],
            'professional_school' => [
                ['label' => 'Manage Programmes', 'href' => $professionalBase.'/programmes', 'permission' => 'manageSchools'],
                ['label' => 'Manage Courses', 'href' => $professionalBase.'/courses', 'permission' => 'manageSchools'],
                ['label' => 'Manage Modules', 'href' => $professionalBase.'/modules', 'permission' => 'manageSchools'],
                ['label' => 'Manage Training Batches', 'href' => $professionalBase.'/training-batches', 'permission' => 'manageSchools'],
                ['label' => 'Register Candidates', 'href' => $professionalBase.'/candidates', 'permission' => 'manageExams'],
                ['label' => 'Create Traditional Exam', 'href' => '/exams/create', 'permission' => 'manageExams'],
                ['label' => 'Create Adaptive Exam', 'href' => '/exams/create', 'permission' => 'manageExams'],
                ['label' => 'Create Certification Exam', 'href' => '/exams/create', 'permission' => 'manageExams'],
                ['label' => 'View Results', 'href' => '/results', 'permission' => 'viewReports'],
                ['label' => 'Generate Certificates', 'href' => '/verify-certificate', 'permission' => 'viewReports'],
            ],
            'cbt_center' => [
                ['label' => 'Register Candidates', 'href' => $cbtBase.'/candidates', 'permission' => 'manageExams'],
                ['label' => 'Import Candidates', 'href' => $cbtBase.'/candidates', 'permission' => 'manageExams'],
                ['label' => 'Manage Question Bank', 'href' => $cbtBase.'/question-banks', 'permission' => 'manageQuestionBank'],
                ['label' => 'Create Traditional CBT Exam', 'href' => '/exams/create', 'permission' => 'manageExams'],
                ['label' => 'Create Adaptive CBT Exam', 'href' => '/exams/create', 'permission' => 'manageExams'],
                ['label' => 'View Results', 'href' => '/results', 'permission' => 'viewReports'],
                ['label' => 'Generate Reports', 'href' => '/reports', 'permission' => 'viewReports'],
                ['label' => 'Center Settings', 'href' => $cbtBase.'/edit', 'permission' => 'manageCenters'],
            ],
            'platform' => [
                ['label' => 'Review Applications', 'href' => '/admin-registrations', 'permission' => 'manageAdminRegistrations'],
                ['label' => 'Manage Organizations', 'href' => '/organizations', 'permission' => 'manageOrganizations'],
                ['label' => 'Manage Secondary Schools', 'href' => '/secondary-schools', 'permission' => 'manageSchools'],
                ['label' => 'Manage Professional Schools', 'href' => '/professional-schools', 'permission' => 'manageSchools'],
                ['label' => 'Manage CBT Centers', 'href' => '/cbt-centers', 'permission' => 'manageCenters'],
                ['label' => 'Manage Users', 'href' => '/users', 'permission' => 'manageUsers'],
                ['label' => 'View Reports', 'href' => '/reports', 'permission' => 'viewReports'],
            ],
            default => [
                ['label' => 'Create Recruitment Exam', 'href' => '/exams/create', 'permission' => 'manageExams'],
                ['label' => 'Create Assessment Exam', 'href' => '/exams/create', 'permission' => 'manageExams'],
                ['label' => 'Create Certification Exam', 'href' => '/exams/create', 'permission' => 'manageExams'],
                ['label' => 'Create Adaptive Exam', 'href' => '/exams/create', 'permission' => 'manageExams'],
                ['label' => 'Register Candidates', 'href' => '/candidates/create', 'permission' => 'manageExams'],
                ['label' => 'Manage Question Bank', 'href' => '/question-bank', 'permission' => 'manageQuestionBank'],
                ['label' => 'View Results', 'href' => '/results', 'permission' => 'viewReports'],
                ['label' => 'Generate Reports', 'href' => '/reports', 'permission' => 'viewReports'],
                ['label' => 'Manage Users', 'href' => '/users', 'permission' => 'manageUsers'],
            ],
        };

        return collect($actions)
            ->filter(fn ($action) => $user->hasPermission($action['permission']))
            ->map(fn ($action) => ['label' => $action['label'], 'href' => $action['href']])
            ->values()
            ->all();
    }

    private function metric(string $label, int|float|string $value, string $description, string $icon): array
    {
        return compact('label', 'value', 'description', 'icon');
    }
}
