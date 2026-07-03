<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\AcademicTerm;
use App\Models\Candidate;
use App\Models\CandidateGroup;
use App\Models\CandidatePaper;
use App\Models\Course;
use App\Models\Exam;
use App\Models\Organization;
use App\Models\ProfessionalModule;
use App\Models\Programme;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\Result;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentGroup;
use App\Models\Subject;
use App\Models\TrainingBatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class ExamSetupGuideService
{
    /**
     * @param array{type: string, id: int, name: string, source?: string}|null $context
     * @return array<string, mixed>|null
     */
    public function forUser(User $user, ?array $context): ?array
    {
        if (! $context) {
            return $user->isSuperAdmin() ? $this->platformGuide($user) : null;
        }

        $steps = match ($context['type']) {
            'secondary_school' => $this->secondarySchoolSteps($user, $context),
            'professional_school' => $this->professionalSchoolSteps($user, $context),
            'cbt_center' => $this->cbtCenterSteps($user, $context),
            default => $this->organizationSteps($user, $context),
        };

        return $this->summary($context, $steps);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function platformGuide(User $user): array
    {
        return $this->summary(null, [
            $this->step('organizations', 'Organizations', 'Create or approve an organization before configuring exams.', '/organizations', $user->hasPermission('manageOrganizations'), Organization::query()->exists(), Organization::query()->count()),
            $this->step('applications', 'Applications', 'Review pending admin registration requests.', '/admin-registrations', $user->hasPermission('manageAdminRegistrations'), true),
            $this->step('context', 'Context', 'Switch into an organization, school, or center to continue exam setup.', '/dashboard', true, false),
        ]);
    }

    /**
     * @param array{type: string, id: int, name: string, source?: string} $context
     * @return array<int, array<string, mixed>>
     */
    private function organizationSteps(User $user, array $context): array
    {
        return [
            $this->step('subjects', 'Subjects', 'Create the subjects that exams can cover.', '/subjects', $user->hasPermission('manageQuestionBank'), $this->scopedCount(Subject::query(), $context) > 0, $this->scopedCount(Subject::query(), $context)),
            $this->step('banks', 'Banks', 'Create question banks for the subjects.', '/question-bank', $user->hasPermission('manageQuestionBank'), $this->scopedCount(QuestionBank::query(), $context) > 0, $this->scopedCount(QuestionBank::query(), $context)),
            $this->step('questions', 'Questions', 'Add or import approved questions into the banks.', '/questions', $user->hasPermission('manageQuestionBank'), $this->questionCount($context) > 0, $this->questionCount($context)),
            $this->step('candidates', 'Candidates', 'Create candidates and candidate groups for assignment.', '/candidate-groups', $user->hasPermission('manageExams'), $this->scopedCount(CandidateGroup::query(), $context) > 0 || $this->scopedCount(Candidate::query(), $context) > 0, $this->scopedCount(CandidateGroup::query(), $context)),
            $this->step('exams', 'Exams', 'Create the exam, attach subjects, banks, and candidate group.', '/exams/create', $user->hasPermission('manageExams'), $this->scopedCount(Exam::query(), $context) > 0, $this->scopedCount(Exam::query(), $context)),
            $this->step('papers', 'Papers', 'Generate candidate question papers from the selected subject banks.', '/exams', $user->hasPermission('manageExams'), $this->paperCount($context) > 0, $this->paperCount($context)),
            $this->step('results', 'Results', 'Review and release results after candidates submit.', '/results', $user->hasPermission('viewReports'), $this->scopedCount(Result::query(), $context) > 0, $this->scopedCount(Result::query(), $context)),
        ];
    }

    /**
     * @param array{type: string, id: int, name: string, source?: string} $context
     * @return array<int, array<string, mixed>>
     */
    private function secondarySchoolSteps(User $user, array $context): array
    {
        $base = ($context['source'] ?? null) === 'legacy_school' ? '/secondary-school' : '/secondary-schools/'.$context['id'];
        $academicsReady = $this->scopedCount(AcademicSession::query(), $context) > 0
            && $this->scopedCount(AcademicTerm::query(), $context) > 0
            && $this->scopedCount(SchoolClass::query(), $context) > 0;
        $studentsReady = $this->scopedCount(Student::query(), $context) > 0
            && $this->studentGroupCount($context) > 0;

        return [
            $this->step('academics', 'Academics', 'Set up sessions, terms, and classes.', $base.'/academic-sessions', $user->hasPermission('manageSchools'), $academicsReady),
            $this->step('subjects', 'Subjects', 'Create class subjects for terminal exams.', '/subjects', $user->hasPermission('manageQuestionBank'), $this->scopedCount(Subject::query(), $context) > 0, $this->scopedCount(Subject::query(), $context)),
            $this->step('banks', 'Banks', 'Create question banks for each subject.', '/question-bank', $user->hasPermission('manageQuestionBank'), $this->scopedCount(QuestionBank::query(), $context) > 0, $this->scopedCount(QuestionBank::query(), $context)),
            $this->step('questions', 'Questions', 'Add or import questions into the subject banks.', '/questions', $user->hasPermission('manageQuestionBank'), $this->questionCount($context) > 0, $this->questionCount($context)),
            $this->step('students', 'Students', 'Register students and group them for exam assignment.', $base.'/student-groups', $user->hasPermission('manageSchools'), $studentsReady, $this->studentGroupCount($context)),
            $this->step('exams', 'Exams', 'Create the terminal exam and select the student group.', '/exams/create', $user->hasPermission('manageExams'), $this->scopedCount(Exam::query(), $context) > 0, $this->scopedCount(Exam::query(), $context)),
            $this->step('papers', 'Papers', 'Generate student question papers from the selected subject banks.', '/exams', $user->hasPermission('manageExams'), $this->paperCount($context) > 0, $this->paperCount($context)),
            $this->step('results', 'Results', 'Review and publish exam results.', '/results', $user->hasPermission('viewReports'), $this->scopedCount(Result::query(), $context) > 0, $this->scopedCount(Result::query(), $context)),
        ];
    }

    /**
     * @param array{type: string, id: int, name: string, source?: string} $context
     * @return array<int, array<string, mixed>>
     */
    private function professionalSchoolSteps(User $user, array $context): array
    {
        $base = '/professional-schools/'.$context['id'];
        $academicsReady = $this->scopedCount(Programme::query(), $context) > 0
            && $this->scopedCount(Course::query(), $context) > 0
            && $this->scopedCount(ProfessionalModule::query(), $context) > 0;
        $batchReady = $this->scopedCount(TrainingBatch::query(), $context) > 0
            && $this->scopedCount(Candidate::query(), $context) > 0;

        return [
            $this->step('academics', 'Academics', 'Set up programmes, courses, modules, and batches.', $base.'/programmes', $user->hasPermission('manageSchools'), $academicsReady),
            $this->step('subjects', 'Subjects', 'Create course/module subjects used by the banks.', '/subjects', $user->hasPermission('manageQuestionBank'), $this->scopedCount(Subject::query(), $context) > 0, $this->scopedCount(Subject::query(), $context)),
            $this->step('banks', 'Banks', 'Create module question banks.', $base.'/question-banks', $user->hasPermission('manageQuestionBank'), $this->scopedCount(QuestionBank::query(), $context) > 0, $this->scopedCount(QuestionBank::query(), $context)),
            $this->step('questions', 'Questions', 'Add or import questions into module banks.', $base.'/questions', $user->hasPermission('manageQuestionBank'), $this->questionCount($context) > 0, $this->questionCount($context)),
            $this->step('batches', 'Batches', 'Register candidates and assign them to a training batch.', $base.'/training-batches', $user->hasPermission('manageExams'), $batchReady, $this->scopedCount(TrainingBatch::query(), $context)),
            $this->step('exams', 'Exams', 'Create the professional exam and choose the batch.', '/exams/create', $user->hasPermission('manageExams'), $this->scopedCount(Exam::query(), $context) > 0, $this->scopedCount(Exam::query(), $context)),
            $this->step('papers', 'Papers', 'Generate professional question papers from the selected module banks.', '/exams', $user->hasPermission('manageExams'), $this->paperCount($context) > 0, $this->paperCount($context)),
            $this->step('results', 'Results', 'Review results and issue certificates where applicable.', '/results', $user->hasPermission('viewReports'), $this->scopedCount(Result::query(), $context) > 0, $this->scopedCount(Result::query(), $context)),
        ];
    }

    /**
     * @param array{type: string, id: int, name: string, source?: string} $context
     * @return array<int, array<string, mixed>>
     */
    private function cbtCenterSteps(User $user, array $context): array
    {
        $base = '/cbt-centers/'.$context['id'];

        return [
            $this->step('candidates', 'Candidates', 'Register CBT candidates and group them for exam assignment.', $base.'/candidates', $user->hasPermission('manageExams'), $this->scopedCount(Candidate::query(), $context) > 0 && $this->scopedCount(CandidateGroup::query(), $context) > 0, $this->scopedCount(CandidateGroup::query(), $context)),
            $this->step('subjects', 'Subjects', 'Create subjects used by CBT question banks.', '/subjects', $user->hasPermission('manageQuestionBank'), $this->scopedCount(Subject::query(), $context) > 0, $this->scopedCount(Subject::query(), $context)),
            $this->step('banks', 'Banks', 'Create CBT question banks.', $base.'/question-banks', $user->hasPermission('manageQuestionBank'), $this->scopedCount(QuestionBank::query(), $context) > 0, $this->scopedCount(QuestionBank::query(), $context)),
            $this->step('questions', 'Questions', 'Add or import questions for the CBT exam.', '/questions', $user->hasPermission('manageQuestionBank'), $this->questionCount($context) > 0, $this->questionCount($context)),
            $this->step('exams', 'Exams', 'Create the CBT exam and assign candidates.', '/exams/create', $user->hasPermission('manageExams'), $this->scopedCount(Exam::query(), $context) > 0, $this->scopedCount(Exam::query(), $context)),
            $this->step('papers', 'Papers', 'Generate question papers from the selected subject banks.', '/exams', $user->hasPermission('manageExams'), $this->paperCount($context) > 0, $this->paperCount($context)),
            $this->step('monitor', 'Monitor', 'Monitor live exams and webcam-enabled sessions from the exam list.', '/exams', $user->hasPermission('viewSupervisorMonitor'), $this->scopedCount(Exam::query(), $context) > 0),
            $this->step('results', 'Results', 'Review and release CBT results.', '/results', $user->hasPermission('viewReports'), $this->scopedCount(Result::query(), $context) > 0, $this->scopedCount(Result::query(), $context)),
        ];
    }

    private function step(string $id, string $label, string $description, string $href, bool $can, bool $complete, ?int $count = null): array
    {
        return [
            'id' => $id,
            'label' => $label,
            'description' => $description,
            'href' => $can ? $href : null,
            'can' => $can,
            'complete' => $complete,
            'count' => $count,
        ];
    }

    /**
     * @param array{type: string, id: int, name: string, source?: string}|null $context
     * @param array<int, array<string, mixed>> $steps
     * @return array<string, mixed>
     */
    private function summary(?array $context, array $steps): array
    {
        $total = count($steps);
        $completed = collect($steps)->where('complete', true)->count();
        $next = collect($steps)->first(fn (array $step) => ! $step['complete'] && $step['can'])
            ?? collect($steps)->first(fn (array $step) => ! $step['complete'])
            ?? $steps[$total - 1] ?? null;

        return [
            'title' => 'Exam setup',
            'context' => $context,
            'steps' => $steps,
            'completed' => $completed,
            'total' => $total,
            'progress' => $total > 0 ? (int) round(($completed / $total) * 100) : 0,
            'current_step' => $next,
            'next_step' => $next,
            'complete' => $total > 0 && $completed === $total,
        ];
    }

    /**
     * @param Builder<*> $query
     * @param array{type: string, id: int, name: string, source?: string} $context
     */
    private function scopedCount(Builder $query, array $context): int
    {
        if (! Schema::hasTable($query->getModel()->getTable())) {
            return 0;
        }

        return (clone $this->scopeQuery($query, $context))->count();
    }

    /**
     * @param array{type: string, id: int, name: string, source?: string} $context
     */
    private function questionCount(array $context): int
    {
        if (! Schema::hasTable((new Question())->getTable()) || ! Schema::hasTable((new QuestionBank())->getTable())) {
            return 0;
        }

        return Question::query()
            ->whereHas('questionBank', fn (Builder $query) => $this->scopeQuery($query, $context))
            ->count();
    }

    /**
     * @param array{type: string, id: int, name: string, source?: string} $context
     */
    private function studentGroupCount(array $context): int
    {
        if (! Schema::hasTable((new StudentGroup())->getTable()) || ! Schema::hasTable((new SchoolClass())->getTable())) {
            return 0;
        }

        return StudentGroup::query()
            ->whereHas('schoolClass', fn (Builder $query) => $this->scopeQuery($query, $context))
            ->count();
    }

    /**
     * @param array{type: string, id: int, name: string, source?: string} $context
     */
    private function paperCount(array $context): int
    {
        if (! Schema::hasTable((new CandidatePaper())->getTable()) || ! Schema::hasTable((new Exam())->getTable())) {
            return 0;
        }

        return CandidatePaper::query()
            ->whereHas('attempt.exam', fn (Builder $query) => $this->scopeQuery($query, $context))
            ->count();
    }

    /**
     * @param Builder<*> $query
     * @param array{type: string, id: int, name: string, source?: string} $context
     * @return Builder<*>
     */
    private function scopeQuery(Builder $query, array $context): Builder
    {
        if (! Schema::hasTable($query->getModel()->getTable())) {
            return $query->whereRaw('1 = 0');
        }

        $id = $context['id'];
        $has = fn (string $column): bool => Schema::hasColumn($query->getModel()->getTable(), $column);

        return match ($context['type']) {
            'secondary_school' => $this->scopeByAvailableColumns($query, $id, [
                'secondary_school_id',
                'school_id',
            ], $has),
            'professional_school' => $has('professional_school_id') ? $query->where('professional_school_id', $id) : $query->whereRaw('1 = 0'),
            'cbt_center' => $this->scopeByAvailableColumns($query, $id, [
                'cbt_center_id',
                'center_id',
            ], $has),
            default => $has('organization_id') ? $query->where('organization_id', $id) : $query->whereRaw('1 = 0'),
        };
    }

    /**
     * @param Builder<*> $query
     * @param array<int, string> $columns
     * @param callable(string): bool $hasColumn
     * @return Builder<*>
     */
    private function scopeByAvailableColumns(Builder $query, int|string $id, array $columns, callable $hasColumn): Builder
    {
        $available = collect($columns)->filter(fn (string $column): bool => $hasColumn($column))->values();

        if ($available->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $query) use ($available, $id): void {
            foreach ($available as $index => $column) {
                $index === 0
                    ? $query->where($column, $id)
                    : $query->orWhere($column, $id);
            }
        });
    }
}
