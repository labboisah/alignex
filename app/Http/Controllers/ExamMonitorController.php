<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\CandidateExamAttempt;
use App\Models\ExamSupervisor;
use App\Models\User;
use App\Services\ExamMonitorService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ExamMonitorController extends Controller
{
    public function __construct(private readonly ExamMonitorService $monitor)
    {
    }

    public function show(Request $request, Exam $exam): Response
    {
        $this->authorizeExam($request->user(), $exam);

        return Inertia::render('ExamMonitor/Show', [
            'exam' => [
                'id' => $exam->id,
                'title' => $exam->title,
                'exam_code' => $exam->code,
                'status' => $exam->status,
                'starts_at' => $exam->starts_at?->toISOString(),
                'ends_at' => $exam->ends_at?->toISOString(),
                'timezone' => $exam->timezone ?: config('app.timezone'),
                'duration_minutes' => $exam->duration_minutes,
                'server_time' => now()->toISOString(),
            ],
            'summary' => $this->monitor->summary($exam),
            'rows' => $this->monitor->rows($exam),
            'feed' => $this->monitor->feed($exam),
            'events' => $this->monitor->events($exam),
            'broadcast' => [
                'channel' => "exam-monitor.{$exam->id}",
                'event' => '.exam.monitor',
                'reverb_key' => config('broadcasting.connections.reverb.key'),
                'reverb_host' => config('broadcasting.connections.reverb.options.host'),
                'reverb_port' => config('broadcasting.connections.reverb.options.port'),
                'reverb_scheme' => config('broadcasting.connections.reverb.options.scheme'),
            ],
        ]);
    }

    public function assignedExams(Request $request): Response
    {
        $exams = $request->user()->supervisedExams()
            ->with('course:id,name,code')
            ->whereNull('exam_supervisors.revoked_at')
            ->whereIn('exams.status', [Exam::STATUS_ACTIVE, Exam::STATUS_SCHEDULED])
            ->orderByRaw('CASE WHEN exams.status = ? THEN 0 ELSE 1 END', [Exam::STATUS_ACTIVE])
            ->orderBy('exams.starts_at')
            ->get(['exams.id', 'exams.title', 'exams.code', 'exams.course_id', 'exams.exam_category', 'exams.delivery_mode', 'exams.duration_minutes', 'exams.starts_at', 'exams.ends_at', 'exams.status'])
            ->map(fn (Exam $exam) => [
                'id' => $exam->id,
                'title' => $exam->title,
                'exam_code' => $exam->code,
                'course_label' => $this->courseLabel($exam),
                'exam_category' => $exam->exam_category,
                'delivery_mode' => $exam->delivery_mode,
                'duration_minutes' => $exam->duration_minutes,
                'starts_at' => $exam->starts_at?->toISOString(),
                'ends_at' => $exam->ends_at?->toISOString(),
                'status' => $exam->status,
                'status_label' => str($exam->status)->replace('_', ' ')->title()->toString(),
                'supervision_role' => $exam->pivot?->role ?? 'supervisor',
                'can_manage' => ($exam->pivot?->role ?? null) === 'co_manager' && $request->user()->hasPermission('manageExams'),
            ])
            ->values()
            ->all();

        return Inertia::render('ExamMonitor/AssignedExams', [
            'exams' => $exams,
        ]);
    }

    private function courseLabel(Exam $exam): ?string
    {
        if (! $exam->course) {
            return null;
        }

        $code = str((string) $exam->course->code)->squish()->upper()->toString();

        return $code !== ''
            ? $code
            : (str($exam->course->name)->squish()->toString() ?: null);
    }


    public function summary(Request $request, Exam $exam): JsonResponse
    {
        $this->authorizeExam($request->user(), $exam);

        return response()->json($this->monitor->summary($exam));
    }

    public function rows(Request $request, Exam $exam): JsonResponse
    {
        $this->authorizeExam($request->user(), $exam);

        return response()->json(['rows' => $this->monitor->rows($exam)]);
    }

    public function feed(Request $request, Exam $exam): JsonResponse
    {
        $this->authorizeExam($request->user(), $exam);

        return response()->json(['feed' => $this->monitor->feed($exam)]);
    }

    public function events(Request $request, Exam $exam): JsonResponse
    {
        $this->authorizeExam($request->user(), $exam);

        return response()->json(['events' => $this->monitor->events($exam)]);
    }

    public function incidentReport(Request $request, Exam $exam): Response
    {
        $this->authorizeExam($request->user(), $exam);

        return Inertia::render('ExamMonitor/IncidentReport', [
            'exam' => [
                'id' => $exam->id,
                'title' => $exam->title,
                'exam_code' => $exam->code,
                'status' => $exam->status,
                'starts_at' => $exam->starts_at?->toISOString(),
                'ends_at' => $exam->ends_at?->toISOString(),
                'duration_minutes' => $exam->duration_minutes,
            ],
            'supervisor' => [
                'name' => $request->user()->name,
                'email' => $request->user()->email,
            ],
            'summary' => $this->monitor->summary($exam),
            'rows' => $this->monitor->rows($exam),
            'events' => $this->monitor->events($exam, 500),
            'feed' => $this->monitor->feed($exam, 500),
            'generatedAt' => now()->toISOString(),
        ]);
    }

    public function reset(Request $request, Exam $exam, CandidateExamAttempt $attempt): JsonResponse
    {
        $this->authorizeExam($request->user(), $exam);
        abort_unless($attempt->exam_id === $exam->id, 404);

        if ($exam->ends_at && $exam->ends_at->isPast()) {
            return response()->json([
                'message' => 'This exam has ended. Candidate attempts can no longer be reset.',
                'errors' => ['exam' => ['This exam has ended. Candidate attempts can no longer be reset.']],
            ], 422);
        }

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $attempt = $this->monitor->resetAttempt(
            $exam,
            $attempt,
            $request->user(),
            $data['reason'] ?? 'Candidate device issue during exam.'
        );

        return response()->json([
            'reset' => true,
            'row' => $this->monitor->row($attempt),
            'summary' => $this->monitor->summary($exam),
            'feed' => $this->monitor->feed($exam),
        ]);
    }

    public function end(Request $request, Exam $exam): JsonResponse
    {
        $this->authorizeExam($request->user(), $exam);
        abort_unless($request->user()->hasPermission('manageExams'), 403);

        DB::transaction(function () use ($exam): void {
            $exam->forceFill([
                'status' => Exam::STATUS_COMPLETED,
                'ends_at' => now(),
            ])->save();

            $exam->attempts()
                ->whereIn('status', [
                    CandidateExamAttempt::STATUS_NOT_STARTED,
                    CandidateExamAttempt::STATUS_IN_PROGRESS,
                ])
                ->update([
                    'status' => CandidateExamAttempt::STATUS_AUTO_SUBMITTED,
                    'auto_submitted_at' => now(),
                    'submitted_at' => now(),
                    'updated_at' => now(),
                ]);
        });

        return response()->json([
            'ended' => true,
            'summary' => $this->monitor->summary($exam->fresh()),
            'rows' => $this->monitor->rows($exam->fresh()),
            'feed' => $this->monitor->feed($exam->fresh()),
        ]);
    }

    private function authorizeExam(User $user, Exam $exam): void
    {
        $hasMonitorPermission = $user->hasPermission('viewSupervisorMonitor') || $user->hasPermission('manageExams');
        $isSharedSupervisor = $this->isSharedSupervisor($user, $exam);

        abort_unless($hasMonitorPermission || $isSharedSupervisor, 403);
        abort_unless($isSharedSupervisor || $this->examScope($user)->whereKey($exam->id)->exists(), 403);
    }

    private function isSharedSupervisor(User $user, Exam $exam): bool
    {
        return ExamSupervisor::query()
            ->where('exam_id', $exam->id)
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->exists();
    }

    private function examScope(User $user): Builder
    {
        return Exam::query()
            ->when(! $user->isSuperAdmin(), function (Builder $query) use ($user): void {
                $query->where(function (Builder $inner) use ($user): void {
                    $inner->whereRaw('1 = 0')
                        ->when($user->organization_id, fn ($scope) => $scope->orWhere('organization_id', $user->organization_id))
                        ->when($user->institution_id, fn ($scope) => $scope->orWhere('institution_id', $user->institution_id))
                        ->when($user->school_id, fn ($scope) => $scope->orWhere('school_id', $user->school_id))
                        ->when($user->center_id, fn ($scope) => $scope->orWhere('center_id', $user->center_id))
                        ->when($user->secondary_school_id, fn ($scope) => $scope->orWhere('secondary_school_id', $user->secondary_school_id))
                        ->when($user->professional_school_id, fn ($scope) => $scope->orWhere('professional_school_id', $user->professional_school_id))
                        ->when($user->cbt_center_id, fn ($scope) => $scope->orWhere('cbt_center_id', $user->cbt_center_id));
                });
            })
            ->when($user->isTeacher(), fn (Builder $query) => $query->whereHas('examSubjects', fn (Builder $subjectQuery) => $subjectQuery->whereIn('subject_id', $user->assignedSubjects()->select('subjects.id'))))
            ->when($user->isFacilitator(), fn (Builder $query) => $query
                ->where('exam_category', Exam::CATEGORY_ASSESSMENT)
                ->whereHas('examSubjects.questionBank', fn (Builder $bankQuery) => $bankQuery
                    ->whereIn('course_id', $user->assignedCourses()->select('courses.id'))
                    ->orWhereIn('module_id', $user->assignedModules()->select('modules.id'))));
    }
}
