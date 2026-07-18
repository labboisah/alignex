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
use App\Models\StudentGroup;
use App\Models\Subject;
use App\Models\User;
use App\Support\AccessControl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DashboardSummaryService
{
    public function summary(User $user, ?array $context): array
    {
        $context ??= app(CurrentContextService::class)->current($user);
        $examScope = $this->examScope($context);

        if ($user->isTeacher()) {
            $examScope = $this->teacherExamScope($user, $examScope);
        } elseif ($user->isFacilitator()) {
            $examScope = $this->facilitatorExamScope($user, $examScope);
        }

        $attemptScope = CandidateExamAttempt::query()->whereIn('exam_id', (clone $examScope)->select('id'));
        $submittedScope = (clone $attemptScope)->whereIn('status', [CandidateExamAttempt::STATUS_SUBMITTED, CandidateExamAttempt::STATUS_AUTO_SUBMITTED]);
        $result = $this->resultSummary($submittedScope);
        $teacherPanel = $user->isTeacher()
            ? $this->teacherPanel($user, $examScope, $submittedScope)
            : ($user->isFacilitator() ? $this->facilitatorPanel($user, $examScope, $submittedScope) : null);

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
            'metrics' => $teacherPanel['metrics'] ?? $this->metrics($context, $examScope, $submittedScope, $result),
            'exam_status' => $this->examStatus($examScope),
            'result_summary' => $result,
            'charts' => $this->charts($context, $examScope, $submittedScope, $result),
            'organization_charts' => $this->charts($context, $examScope, $submittedScope, $result),
            'recent_candidates' => $this->recentCandidates($context),
            'recent_results' => $this->recentResults($submittedScope),
            'recent_exams' => $this->recentExams($examScope),
            'work_queue' => $this->workQueue($examScope, $attemptScope),
            'quick_actions' => $this->quickActions($user, $context),
            'teacher_panel' => $teacherPanel,
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

    private function teacherExamScope(User $user, Builder $examScope): Builder
    {
        return $examScope
            ->where('exam_category', Exam::CATEGORY_ASSESSMENT)
            ->whereHas('examSubjects', fn (Builder $query) => $query->whereIn('subject_id', $user->assignedSubjects()->select('subjects.id')))
            ->when($user->secondary_school_id, fn (Builder $query) => $query->where('secondary_school_id', $user->secondary_school_id))
            ->when($user->school_id, fn (Builder $query) => $query->where('school_id', $user->school_id));
    }

    private function facilitatorExamScope(User $user, Builder $examScope): Builder
    {
        return $examScope
            ->where('exam_category', Exam::CATEGORY_ASSESSMENT)
            ->where('professional_school_id', $user->professional_school_id)
            ->where(function (Builder $query) use ($user): void {
                $query
                    ->whereIn('course_id', $user->assignedCourses()->select('courses.id'))
                    ->orWhereIn('module_id', $user->assignedModules()->select('modules.id'))
                    ->orWhereHas('examSubjects.questionBank', fn (Builder $bankQuery) => $bankQuery
                        ->whereIn('course_id', $user->assignedCourses()->select('courses.id'))
                        ->orWhereIn('module_id', $user->assignedModules()->select('modules.id')));
            });
    }

    private function teacherPanel(User $user, Builder $examScope, Builder $submittedScope): array
    {
        $assignments = $user->assignedSubjects()
            ->with('schoolClass:id,name,level')
            ->orderBy('name')
            ->get();
        $subjectIds = $assignments->pluck('id')->unique()->values();
        $classIds = $assignments->pluck('pivot.school_class_id')->filter()->unique()->values();
        $students = Student::query()
            ->whereIn('school_class_id', $classIds)
            ->when($user->secondary_school_id, fn (Builder $query) => $query->where('secondary_school_id', $user->secondary_school_id))
            ->latest()
            ->limit(8)
            ->get();

        return [
            'metrics' => [
                $this->metric('Assigned Subjects', $subjectIds->count(), 'Subjects this teacher can manage.', 'BookOpen'),
                $this->metric('Assigned Classes', $classIds->count(), 'Classes linked to assigned subjects.', 'GraduationCap'),
                $this->metric('Students', $this->teacherStudentCount($user, $classIds), 'Students in assigned classes.', 'Users'),
                $this->metric('Student Groups', $this->teacherGroupCount($user, $classIds), 'Groups in assigned classes.', 'Users'),
                $this->metric('Assessments', (clone $examScope)->where('exam_category', Exam::CATEGORY_ASSESSMENT)->count(), 'Assessments using assigned subjects.', 'ClipboardList'),
                $this->metric('Submitted Results', (clone $submittedScope)->count(), 'Submitted attempts for assigned-subject exams.', 'CheckCircle2'),
            ],
            'subjects' => $assignments->map(fn (Subject $subject) => [
                'id' => $subject->id,
                'name' => $subject->name,
                'code' => $subject->code,
                'class_name' => $subject->schoolClass?->name,
            ])->values()->all(),
            'classes' => $this->teacherClasses($classIds),
            'student_groups' => $this->teacherGroups($classIds),
            'students' => $students->map(fn (Student $student) => [
                'id' => (string) $student->id,
                'name' => trim($student->first_name.' '.$student->last_name),
                'registration_number' => $student->admission_number,
                'status' => $student->status,
            ])->all(),
        ];
    }

    private function facilitatorPanel(User $user, Builder $examScope, Builder $submittedScope): array
    {
        $courses = $user->assignedCourses()
            ->with('programme:id,name')
            ->orderBy('name')
            ->get()
            ->unique('id')
            ->values();
        $courseIds = $courses->pluck('id')->unique()->values();
        $modules = ProfessionalModule::query()
            ->whereIn('course_id', $courseIds)
            ->with('course:id,name')
            ->orderBy('name')
            ->get()
            ->unique('id')
            ->values();
        $moduleIds = $modules->pluck('id')->unique()->values();

        $candidates = Candidate::query()
            ->where('professional_school_id', $user->professional_school_id)
            ->where(function (Builder $query) use ($courseIds): void {
                $query
                    ->whereIn('course_id', $courseIds)
                    ->orWhereHas('trainingBatch.programme.courses', fn (Builder $courseQuery) => $courseQuery->whereIn('courses.id', $courseIds));
            })
            ->latest()
            ->limit(8)
            ->get();

        return [
            'kind' => 'facilitator',
            'metrics' => [
                $this->metric('Assigned Courses', $courseIds->count(), 'Courses this facilitator can manage.', 'BookOpen'),
                $this->metric('Assigned Modules', $moduleIds->count(), 'Modules linked to assigned courses.', 'Library'),
                $this->metric('Candidates / Trainees', $candidates->count(), 'Recent trainees linked to assigned courses.', 'Users'),
                $this->metric('Question Banks', QuestionBank::query()->where('professional_school_id', $user->professional_school_id)->whereIn('course_id', $courseIds)->count(), 'Question banks in assigned courses.', 'FileQuestion'),
                $this->metric('Assessments', (clone $examScope)->count(), 'Assessments using assigned courses or modules.', 'ClipboardList'),
                $this->metric('Submitted Results', (clone $submittedScope)->count(), 'Submitted attempts for assigned-course assessments.', 'CheckCircle2'),
            ],
            'subjects' => [],
            'classes' => [],
            'student_groups' => [],
            'students' => [],
            'courses' => $courses->map(fn (Course $course) => [
                'id' => $course->id,
                'name' => $course->name,
                'code' => $course->code,
                'programme_name' => $course->programme?->name,
            ])->all(),
            'modules' => $modules->map(fn (ProfessionalModule $module) => [
                'id' => $module->id,
                'name' => $module->name,
                'code' => $module->code,
                'course_name' => $module->course?->name,
            ])->all(),
            'candidates' => $candidates->map(fn (Candidate $candidate) => [
                'id' => (string) $candidate->id,
                'name' => trim($candidate->first_name.' '.$candidate->last_name),
                'registration_number' => $candidate->candidate_number,
                'status' => $candidate->status,
            ])->all(),
        ];
    }

    private function teacherStudentCount(User $user, Collection $classIds): int
    {
        return Student::query()
            ->whereIn('school_class_id', $classIds)
            ->when($user->secondary_school_id, fn (Builder $query) => $query->where('secondary_school_id', $user->secondary_school_id))
            ->count();
    }

    private function teacherGroupCount(User $user, Collection $classIds): int
    {
        return StudentGroup::query()
            ->whereIn('school_class_id', $classIds)
            ->whereHas('schoolClass', fn (Builder $query) => $query
                ->when($user->secondary_school_id, fn (Builder $scope) => $scope->where('secondary_school_id', $user->secondary_school_id))
                ->when($user->school_id, fn (Builder $scope) => $scope->where('school_id', $user->school_id)))
            ->count();
    }

    private function teacherClasses(Collection $classIds): array
    {
        return SchoolClass::query()
            ->whereIn('id', $classIds)
            ->withCount(['students', 'groups'])
            ->orderBy('level_order')
            ->orderBy('name')
            ->get()
            ->map(fn (SchoolClass $class) => [
                'id' => $class->id,
                'name' => $class->name,
                'level' => $class->level,
                'students_count' => $class->students_count,
                'groups_count' => $class->groups_count,
            ])
            ->all();
    }

    private function teacherGroups(Collection $classIds): array
    {
        return StudentGroup::query()
            ->whereIn('school_class_id', $classIds)
            ->with('schoolClass:id,name')
            ->withCount('students')
            ->orderBy('name')
            ->get()
            ->map(fn (StudentGroup $group) => [
                'id' => $group->id,
                'name' => $group->name,
                'code' => $group->code,
                'class_name' => $group->schoolClass?->name,
                'students_count' => $group->students_count,
                'status' => $group->status,
            ])
            ->all();
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
        if ($user->isTeacher()) {
            return [
                ['label' => 'Create Assessment', 'href' => '/exams/create?category=assessment'],
                ['label' => 'Manage Questions', 'href' => '/questions'],
                ['label' => 'Question Bank', 'href' => '/question-bank'],
                ['label' => 'View Results', 'href' => '/results'],
            ];
        }

        if ($user->isFacilitator()) {
            return [
                ['label' => 'Create Assessment', 'href' => '/exams/create?category=assessment'],
                ['label' => 'Manage Questions', 'href' => '/questions'],
                ['label' => 'Question Bank', 'href' => '/question-bank'],
                ['label' => 'View Results', 'href' => '/results'],
            ];
        }

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
                ['label' => 'Create Assessment', 'href' => '/exams/create?category=assessment', 'permission' => 'manageExams'],
                ['label' => 'Monitor Assessments', 'href' => '/exams?category=assessment', 'permission' => 'manageExams'],
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
