<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\AcademicTerm;
use App\Models\Candidate;
use App\Models\Exam;
use App\Models\SchoolClass;
use App\Models\StudentGroup;
use App\Models\User;
use App\Services\SecondarySchoolService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class SecondarySchoolController extends Controller
{
    public function __construct(private readonly SecondarySchoolService $secondary)
    {
    }

    public function index(Request $request): InertiaResponse
    {
        $this->authorizeSecondary($request->user());
        $exams = $this->secondary->secondaryExams($request->user());
        $selectedExam = $request->filled('exam_id') ? $exams->firstWhere('id', $request->string('exam_id')->toString()) : $exams->first();

        return Inertia::render('Secondary/Index', [
            'dashboard' => $this->secondary->teacherDashboard($request->user()),
            'sessions' => $this->secondary->sessions($request->user())->map(fn (AcademicSession $session) => [
                'id' => $session->id,
                'name' => $session->name,
                'code' => $session->code,
                'starts_on' => $session->starts_on?->toDateString(),
                'ends_on' => $session->ends_on?->toDateString(),
                'status' => $session->status,
                'terms_count' => $session->terms_count,
                'terms' => $session->terms()->orderBy('starts_on')->get(['id', 'name', 'code', 'status']),
            ]),
            'classes' => $this->secondary->classes($request->user())->map(fn (SchoolClass $class) => [
                'id' => $class->id,
                'name' => $class->name,
                'code' => $class->code,
                'level_order' => $class->level_order,
                'status' => $class->status,
                'groups_count' => $class->groups_count,
                'groups' => $class->groups()->withCount('candidates')->orderBy('name')->get(['id', 'school_class_id', 'name', 'code', 'status']),
            ]),
            'exams' => $exams->map(fn (Exam $exam) => ['id' => $exam->id, 'title' => $exam->title, 'exam_code' => $exam->code, 'settings' => $exam->settings]),
            'candidates' => $this->secondary->candidates($request->user())->map(fn (Candidate $candidate) => [
                'id' => $candidate->id,
                'name' => trim($candidate->first_name.' '.$candidate->last_name),
                'registration_number' => $candidate->candidate_number,
            ]),
            'subjects' => $this->secondary->subjects($request->user())->map(fn ($subject) => ['id' => $subject->id, 'name' => $subject->name, 'code' => $subject->code]),
            'selected_exam_id' => $selectedExam?->id,
            'result_sheet' => $selectedExam ? $this->secondary->resultSheet($selectedExam) : [],
            'weaknesses' => $this->secondary->weaknessReport($request->user(), $selectedExam),
        ]);
    }

    public function storeSession(Request $request): RedirectResponse
    {
        $this->authorizeSecondary($request->user());
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        AcademicSession::query()->create([...$this->secondary->scope($request->user()), ...$data, 'code' => strtoupper($data['code'])]);

        return back()->with('success', 'Academic session created.');
    }

    public function storeTerm(Request $request): RedirectResponse
    {
        $this->authorizeSecondary($request->user());
        $data = $request->validate([
            'academic_session_id' => ['required', 'exists:academic_sessions,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        AcademicTerm::query()->create([...$data, 'code' => strtoupper($data['code'])]);

        return back()->with('success', 'Term created.');
    }

    public function storeClass(Request $request): RedirectResponse
    {
        $this->authorizeSecondary($request->user());
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50'],
            'level_order' => ['required', 'integer', 'min:1', 'max:99'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        SchoolClass::query()->create([...$this->secondary->scope($request->user()), ...$data, 'code' => strtoupper($data['code'])]);

        return back()->with('success', 'Class/level created.');
    }

    public function storeGroup(Request $request): RedirectResponse
    {
        $this->authorizeSecondary($request->user());
        $data = $request->validate([
            'school_class_id' => ['required', 'exists:school_classes,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'candidate_ids' => ['array'],
            'candidate_ids.*' => ['string', 'exists:candidates,id'],
        ]);

        $group = StudentGroup::query()->create([...collect($data)->except('candidate_ids')->all(), 'code' => strtoupper($data['code'])]);
        $group->candidates()->sync($data['candidate_ids'] ?? []);

        return back()->with('success', 'Student group created.');
    }

    public function setupAssessment(Request $request, Exam $exam): RedirectResponse
    {
        $this->authorizeExam($request->user(), $exam);
        $data = $request->validate([
            'academic_session_id' => ['nullable', 'exists:academic_sessions,id'],
            'academic_term_id' => ['nullable', 'exists:academic_terms,id'],
            'school_class_id' => ['nullable', 'exists:school_classes,id'],
            'student_group_id' => ['nullable', 'exists:student_groups,id'],
            'ca_max_score' => ['required', 'numeric', 'min:0', 'max:100'],
            'exam_max_score' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $this->secondary->saveCaSetup($exam, $data);

        return back()->with('success', 'Continuous assessment setup saved.');
    }

    public function storeAssessment(Request $request, Exam $exam): RedirectResponse
    {
        $this->authorizeExam($request->user(), $exam);
        $data = $request->validate([
            'candidate_id' => ['required', 'exists:candidates,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'ca_score' => ['required', 'numeric', 'min:0', 'max:100'],
            'teacher_comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->secondary->saveAssessment($exam, $data);

        return back()->with('success', 'Continuous assessment recorded.');
    }

    public function reportCard(Request $request, Exam $exam, Candidate $candidate)
    {
        $this->authorizeExam($request->user(), $exam);

        return Response::make($this->secondary->reportCardPdf($exam, $candidate, $request->user()), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$candidate->candidate_number.'-'.$exam->code.'-report-card.pdf"',
        ]);
    }

    private function authorizeSecondary(User $user): void
    {
        abort_unless($user->hasPermission('manageSchools') || $user->hasPermission('manageExams') || $user->hasPermission('viewReports'), 403);
        abort_unless($user->school_id !== null, 403);
    }

    private function authorizeExam(User $user, Exam $exam): void
    {
        $this->authorizeSecondary($user);
        abort_unless($this->secondary->owned(Exam::query(), $user)->whereKey($exam->id)->exists(), 403);
        abort_unless(($exam->examType?->code ?? null) === 'secondary', 404);
    }
}
