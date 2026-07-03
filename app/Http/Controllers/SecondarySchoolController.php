<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\AcademicTerm;
use App\Models\Candidate;
use App\Models\ClassArm;
use App\Models\Exam;
use App\Models\Organization;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\SecondarySchool;
use App\Models\Student;
use App\Models\StudentGroup;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;
use App\Services\SecondarySchoolService;
use App\Support\ReferenceCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class SecondarySchoolController extends Controller
{
    public function __construct(private readonly SecondarySchoolService $secondary)
    {
    }

    public function list(Request $request): InertiaResponse
    {
        abort_unless($this->canListSecondarySchools($request->user()), 403);

        return Inertia::render('SecondarySchools/Index', [
            'secondarySchools' => $this->secondarySchoolScope($request->user())
                ->with('organization:id,name')
                ->withCount(['students', 'schoolClasses', 'exams'])
                ->latest()
                ->get()
                ->map(fn (SecondarySchool $school) => $this->secondarySchoolRow($school)),
            'can' => ['create' => $request->user()->isSuperAdmin()],
        ]);
    }

    public function create(Request $request): InertiaResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        return Inertia::render('SecondarySchools/Create', $this->secondarySchoolFormOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        $data = $request->validate($this->secondarySchoolRules());
        $data['code'] = filled($data['code'] ?? null)
            ? strtoupper($data['code'])
            : ReferenceCode::unique($data['name'], SecondarySchool::query());
        $school = SecondarySchool::query()->create($data);

        return redirect()->route('secondary-schools.show', $school)->with('success', 'Secondary school created.');
    }

    public function show(Request $request, SecondarySchool $secondarySchool): InertiaResponse
    {
        $this->authorizeSecondarySchoolRecord($request->user(), $secondarySchool);

        $secondarySchool->load([
            'organization:id,name',
            'academicSessions' => fn ($query) => $query->withCount('terms')->latest(),
            'schoolClasses' => fn ($query) => $query->orderBy('level_order'),
            'students' => fn ($query) => $query->with('schoolClass')->latest()->limit(8),
            'subjects' => fn ($query) => $query->orderBy('name'),
            'exams' => fn ($query) => $query->latest()->limit(8),
        ])->loadCount(['students', 'schoolClasses', 'subjects', 'questionBanks', 'exams']);

        return Inertia::render('SecondarySchools/Show', [
            'secondarySchool' => $this->secondarySchoolDetail($secondarySchool),
            'dashboard' => $this->secondaryDashboard($secondarySchool),
        ]);
    }

    public function edit(Request $request, SecondarySchool $secondarySchool): InertiaResponse
    {
        $this->authorizeSecondarySchoolRecord($request->user(), $secondarySchool, update: true);

        return Inertia::render('SecondarySchools/Edit', [
            'secondarySchool' => $this->secondarySchoolRow($secondarySchool->load('organization:id,name')),
            ...$this->secondarySchoolFormOptions(),
        ]);
    }

    public function update(Request $request, SecondarySchool $secondarySchool): RedirectResponse
    {
        $this->authorizeSecondarySchoolRecord($request->user(), $secondarySchool, update: true);

        $data = $request->validate($this->secondarySchoolRules($secondarySchool));
        $data['code'] = filled($data['code'] ?? null)
            ? strtoupper($data['code'])
            : ReferenceCode::unique($data['name'], SecondarySchool::query(), $secondarySchool);
        $secondarySchool->update($data);

        return redirect()->route('secondary-schools.show', $secondarySchool)->with('success', 'Secondary school updated.');
    }

    public function academicSessions(Request $request, SecondarySchool $secondarySchool): InertiaResponse
    {
        $this->authorizeSecondarySchoolRecord($request->user(), $secondarySchool);

        return Inertia::render('SecondarySchools/AcademicSessions', [
            'secondarySchool' => $this->secondarySchoolRow($secondarySchool),
            'sessions' => $secondarySchool->academicSessions()->withCount('terms')->latest()->get(),
        ]);
    }

    public function storeAcademicSession(Request $request, SecondarySchool $secondarySchool): RedirectResponse
    {
        $this->authorizeSecondarySchoolRecord($request->user(), $secondarySchool, update: true);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'is_active' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($secondarySchool, $data): void {
            if ($data['is_active']) {
                $secondarySchool->academicSessions()->update(['is_active' => false]);
            }

            $secondarySchool->academicSessions()->create([
                'name' => $data['name'],
                'code' => strtoupper($data['code'] ?: str($data['name'])->slug('-')->toString()),
                'starts_on' => $data['start_date'] ?? null,
                'ends_on' => $data['end_date'] ?? null,
                'status' => $data['status'],
                'is_active' => $data['is_active'],
            ]);
        });

        return back()->with('success', 'Academic session created.');
    }

    public function setActiveAcademicSession(Request $request, SecondarySchool $secondarySchool, AcademicSession $academicSession): RedirectResponse
    {
        $this->authorizeSecondarySchoolRecord($request->user(), $secondarySchool, update: true);
        abort_unless((string) $academicSession->secondary_school_id === (string) $secondarySchool->id, 404);

        DB::transaction(function () use ($secondarySchool, $academicSession): void {
            $secondarySchool->academicSessions()->update(['is_active' => false]);
            $academicSession->update(['is_active' => true, 'status' => 'active']);
        });

        return back()->with('success', 'Active academic session updated.');
    }

    public function updateAcademicSession(Request $request, SecondarySchool $secondarySchool, AcademicSession $academicSession): RedirectResponse
    {
        $this->authorizeSecondarySchoolRecord($request->user(), $secondarySchool, update: true);
        abort_unless((string) $academicSession->secondary_school_id === (string) $secondarySchool->id, 404);

        $data = $request->validate($this->academicSessionRules());

        DB::transaction(function () use ($secondarySchool, $academicSession, $data): void {
            if ($data['is_active']) {
                $secondarySchool->academicSessions()->whereKeyNot($academicSession->id)->update(['is_active' => false]);
            }

            $academicSession->update([
                'name' => $data['name'],
                'code' => strtoupper($data['code'] ?: str($data['name'])->slug('-')->toString()),
                'starts_on' => $data['start_date'] ?? null,
                'ends_on' => $data['end_date'] ?? null,
                'status' => $data['status'],
                'is_active' => $data['is_active'],
            ]);
        });

        return back()->with('success', 'Academic session updated.');
    }

    public function destroyAcademicSession(Request $request, SecondarySchool $secondarySchool, AcademicSession $academicSession): RedirectResponse
    {
        $this->authorizeSecondarySchoolRecord($request->user(), $secondarySchool, update: true);
        abort_unless((string) $academicSession->secondary_school_id === (string) $secondarySchool->id, 404);
        abort_if($academicSession->terms()->exists(), 422, 'Remove the terms under this session first.');

        $academicSession->delete();

        return back()->with('success', 'Academic session deleted.');
    }

    public function terms(Request $request, SecondarySchool $secondarySchool): InertiaResponse
    {
        $this->authorizeSecondarySchoolRecord($request->user(), $secondarySchool);

        return Inertia::render('SecondarySchools/Terms', [
            'secondarySchool' => $this->secondarySchoolRow($secondarySchool),
            'sessions' => $secondarySchool->academicSessions()->orderByDesc('is_active')->latest()->get(['id', 'name', 'code', 'is_active']),
            'terms' => $secondarySchool->terms()->with('session:id,name')->latest()->get(),
        ]);
    }

    public function storeTermForSchool(Request $request, SecondarySchool $secondarySchool): RedirectResponse
    {
        $this->authorizeSecondarySchoolRecord($request->user(), $secondarySchool, update: true);

        $data = $request->validate([
            'academic_session_id' => ['required', 'exists:academic_sessions,id'],
            'name' => ['required', Rule::in(['First Term', 'Second Term', 'Third Term'])],
            'code' => ['nullable', 'string', 'max:50'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'is_active' => ['required', 'boolean'],
        ]);
        abort_unless($secondarySchool->academicSessions()->whereKey($data['academic_session_id'])->exists(), 422);

        DB::transaction(function () use ($secondarySchool, $data): void {
            if ($data['is_active']) {
                $secondarySchool->terms()->update(['is_active' => false]);
            }

            $secondarySchool->terms()->create([
                'academic_session_id' => $data['academic_session_id'],
                'name' => $data['name'],
                'code' => strtoupper(($data['code'] ?? null) ?: str($data['name'])->before(' ')->toString()),
                'starts_on' => $data['start_date'] ?? null,
                'ends_on' => $data['end_date'] ?? null,
                'status' => $data['status'],
                'is_active' => $data['is_active'],
            ]);
        });

        return back()->with('success', 'Term created.');
    }

    public function updateTermForSchool(Request $request, SecondarySchool $secondarySchool, AcademicTerm $academicTerm): RedirectResponse
    {
        $this->authorizeSecondarySchoolRecord($request->user(), $secondarySchool, update: true);
        abort_unless((string) $academicTerm->secondary_school_id === (string) $secondarySchool->id, 404);

        $data = $request->validate($this->termRules());
        abort_unless($secondarySchool->academicSessions()->whereKey($data['academic_session_id'])->exists(), 422);

        DB::transaction(function () use ($secondarySchool, $academicTerm, $data): void {
            if ($data['is_active']) {
                $secondarySchool->terms()->whereKeyNot($academicTerm->id)->update(['is_active' => false]);
            }

            $academicTerm->update([
                'academic_session_id' => $data['academic_session_id'],
                'name' => $data['name'],
                'code' => strtoupper(($data['code'] ?? null) ?: str($data['name'])->before(' ')->toString()),
                'starts_on' => $data['start_date'] ?? null,
                'ends_on' => $data['end_date'] ?? null,
                'status' => $data['status'],
                'is_active' => $data['is_active'],
            ]);
        });

        return back()->with('success', 'Term updated.');
    }

    public function destroyTermForSchool(Request $request, SecondarySchool $secondarySchool, AcademicTerm $academicTerm): RedirectResponse
    {
        $this->authorizeSecondarySchoolRecord($request->user(), $secondarySchool, update: true);
        abort_unless((string) $academicTerm->secondary_school_id === (string) $secondarySchool->id, 404);

        $academicTerm->delete();

        return back()->with('success', 'Term deleted.');
    }

    public function classes(Request $request, SecondarySchool $secondarySchool): InertiaResponse
    {
        $this->authorizeSecondarySchoolRecord($request->user(), $secondarySchool);

        return Inertia::render('SecondarySchools/Classes', [
            'secondarySchool' => $this->secondarySchoolRow($secondarySchool),
            'classes' => $secondarySchool->schoolClasses()->withCount('students')->orderBy('level_order')->get(),
        ]);
    }

    public function arms(Request $request, SecondarySchool $secondarySchool): InertiaResponse
    {
        $this->authorizeSecondarySchoolRecord($request->user(), $secondarySchool);

        return Inertia::render('SecondarySchools/Arms', [
            'secondarySchool' => $this->secondarySchoolRow($secondarySchool),
            'classes' => $secondarySchool->schoolClasses()->orderBy('level_order')->get(['id', 'name', 'level']),
            'arms' => $secondarySchool->classArms()->with('schoolClass:id,name')->withCount('students')->orderBy('name')->get(),
        ]);
    }

    public function storeClassForSchool(Request $request, SecondarySchool $secondarySchool): RedirectResponse
    {
        $this->authorizeSecondarySchoolRecord($request->user(), $secondarySchool, update: true);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'level' => ['required', Rule::in(['JSS 1', 'JSS 2', 'JSS 3', 'SS 1', 'SS 2', 'SS 3'])],
            'level_order' => ['nullable', 'integer', 'min:1', 'max:99'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $secondarySchool->schoolClasses()->create([
            'name' => $data['name'],
            'code' => strtoupper(str_replace(' ', '', $data['level'])),
            'level' => $data['level'],
            'level_order' => $data['level_order'] ?? $this->levelOrder($data['level']),
            'status' => $data['status'],
        ]);

        return back()->with('success', 'Class created.');
    }

    public function updateClassForSchool(Request $request, SecondarySchool $secondarySchool, SchoolClass $schoolClass): RedirectResponse
    {
        $this->authorizeSecondarySchoolRecord($request->user(), $secondarySchool, update: true);
        abort_unless((string) $schoolClass->secondary_school_id === (string) $secondarySchool->id, 404);

        $data = $request->validate($this->classRules());
        $schoolClass->update([
            'name' => $data['name'],
            'code' => strtoupper(str_replace(' ', '', $data['level'])),
            'level' => $data['level'],
            'level_order' => $data['level_order'] ?? $this->levelOrder($data['level']),
            'status' => $data['status'],
        ]);

        return back()->with('success', 'Class updated.');
    }

    public function destroyClassForSchool(Request $request, SecondarySchool $secondarySchool, SchoolClass $schoolClass): RedirectResponse
    {
        $this->authorizeSecondarySchoolRecord($request->user(), $secondarySchool, update: true);
        abort_unless((string) $schoolClass->secondary_school_id === (string) $secondarySchool->id, 404);
        abort_if($schoolClass->students()->exists(), 422, 'Remove students under this class first.');

        $schoolClass->delete();

        return back()->with('success', 'Class deleted.');
    }

    public function storeArmForSchool(Request $request, SecondarySchool $secondarySchool): RedirectResponse
    {
        $this->authorizeSecondarySchoolRecord($request->user(), $secondarySchool, update: true);

        $data = $request->validate([
            'school_class_id' => ['required', 'exists:school_classes,id'],
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);
        abort_unless($secondarySchool->schoolClasses()->whereKey($data['school_class_id'])->exists(), 422);

        $secondarySchool->classArms()->create([
            'school_class_id' => $data['school_class_id'],
            'name' => $data['name'],
            'code' => strtoupper(str($data['name'])->slug('-')->toString()),
            'status' => $data['status'],
        ]);

        return back()->with('success', 'Class arm created.');
    }

    public function updateArmForSchool(Request $request, SecondarySchool $secondarySchool, ClassArm $classArm): RedirectResponse
    {
        $this->authorizeSecondarySchoolRecord($request->user(), $secondarySchool, update: true);
        abort_unless((string) $classArm->secondary_school_id === (string) $secondarySchool->id, 404);

        $data = $request->validate($this->armRules());
        abort_unless($secondarySchool->schoolClasses()->whereKey($data['school_class_id'])->exists(), 422);

        $classArm->update([
            'school_class_id' => $data['school_class_id'],
            'name' => $data['name'],
            'code' => strtoupper(str($data['name'])->slug('-')->toString()),
            'status' => $data['status'],
        ]);

        return back()->with('success', 'Class arm updated.');
    }

    public function destroyArmForSchool(Request $request, SecondarySchool $secondarySchool, ClassArm $classArm): RedirectResponse
    {
        $this->authorizeSecondarySchoolRecord($request->user(), $secondarySchool, update: true);
        abort_unless((string) $classArm->secondary_school_id === (string) $secondarySchool->id, 404);
        abort_if($classArm->students()->exists(), 422, 'Move or remove students in this arm first.');

        $classArm->delete();

        return back()->with('success', 'Class arm deleted.');
    }

    public function studentGroups(Request $request, SecondarySchool $secondarySchool): InertiaResponse
    {
        $this->authorizeSecondarySchoolRecord($request->user(), $secondarySchool);

        return Inertia::render('SecondarySchools/StudentGroups', [
            'secondarySchool' => $this->secondarySchoolRow($secondarySchool),
            'classes' => $secondarySchool->schoolClasses()->orderBy('level_order')->get(['id', 'name', 'level']),
            'students' => $secondarySchool->students()->with('schoolClass:id,name')->orderBy('admission_number')->get()->map(fn (Student $student) => [
                'id' => $student->id,
                'school_class_id' => $student->school_class_id,
                'name' => trim($student->first_name.' '.$student->last_name),
                'code' => $student->admission_number,
                'class_name' => $student->schoolClass?->name,
            ]),
            'groups' => StudentGroup::query()
                ->whereHas('schoolClass', fn (Builder $query) => $query->where('secondary_school_id', $secondarySchool->id))
                ->with('schoolClass:id,name')
                ->with('students:id')
                ->withCount('students')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function storeStudentGroupForSchool(Request $request, SecondarySchool $secondarySchool): RedirectResponse
    {
        $this->authorizeSecondarySchoolRecord($request->user(), $secondarySchool, update: true);

        $data = $request->validate($this->correctedStudentGroupRules());
        abort_unless($secondarySchool->schoolClasses()->whereKey($data['school_class_id'])->exists(), 422);
        $this->authorizeStudentIds($secondarySchool, $data['student_ids'] ?? []);

        $group = StudentGroup::query()->create([
            ...collect($data)->except('student_ids')->all(),
            'code' => strtoupper(($data['code'] ?? null) ?: str($data['name'])->slug('-')->toString()),
        ]);
        $group->students()->sync($data['student_ids'] ?? []);

        return back()->with('success', 'Student group created.');
    }

    public function updateStudentGroupForSchool(Request $request, SecondarySchool $secondarySchool, StudentGroup $studentGroup): RedirectResponse
    {
        $this->authorizeSecondarySchoolRecord($request->user(), $secondarySchool, update: true);
        $this->authorizeStudentGroupRecord($secondarySchool, $studentGroup);

        $data = $request->validate($this->correctedStudentGroupRules());
        abort_unless($secondarySchool->schoolClasses()->whereKey($data['school_class_id'])->exists(), 422);
        $this->authorizeStudentIds($secondarySchool, $data['student_ids'] ?? []);

        $studentGroup->update([
            ...collect($data)->except('student_ids')->all(),
            'code' => strtoupper(($data['code'] ?? null) ?: str($data['name'])->slug('-')->toString()),
        ]);
        $studentGroup->students()->sync($data['student_ids'] ?? []);

        return back()->with('success', 'Student group updated.');
    }

    public function destroyStudentGroupForSchool(Request $request, SecondarySchool $secondarySchool, StudentGroup $studentGroup): RedirectResponse
    {
        $this->authorizeSecondarySchoolRecord($request->user(), $secondarySchool, update: true);
        $this->authorizeStudentGroupRecord($secondarySchool, $studentGroup);

        $studentGroup->delete();

        return back()->with('success', 'Student group deleted.');
    }

    public function students(Request $request, SecondarySchool $secondarySchool): InertiaResponse
    {
        $this->authorizeSecondarySchoolRecord($request->user(), $secondarySchool);

        return Inertia::render('SecondarySchools/Students', [
            'secondarySchool' => $this->secondarySchoolRow($secondarySchool),
            'classes' => $secondarySchool->schoolClasses()->orderBy('level_order')->get(),
            'students' => $secondarySchool->students()->with('schoolClass')->latest()->get()->map(fn (Student $student) => $this->studentRow($student)),
        ]);
    }

    public function storeStudent(Request $request, SecondarySchool $secondarySchool): RedirectResponse
    {
        $this->authorizeSecondarySchoolRecord($request->user(), $secondarySchool, update: true);

        $data = $request->validate([
            'school_class_id' => ['required', 'exists:school_classes,id'],
            'admission_number' => ['required', 'string', 'max:100', Rule::unique('students')->where('secondary_school_id', $secondarySchool->id)],
            'full_name' => ['required', 'string', 'max:255'],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'guardian_name' => ['nullable', 'string', 'max:255'],
            'guardian_phone' => ['nullable', 'string', 'max:50'],
            'status' => ['required', Rule::in(['active', 'inactive', 'suspended'])],
        ]);
        abort_unless($secondarySchool->schoolClasses()->whereKey($data['school_class_id'])->exists(), 422);

        [$firstName, $lastName] = $this->splitFullName($data['full_name']);
        $secondarySchool->students()->create([
            ...collect($data)->except('full_name')->all(),
            'first_name' => $firstName,
            'last_name' => $lastName,
        ]);

        return back()->with('success', 'Student created.');
    }

    public function updateStudent(Request $request, SecondarySchool $secondarySchool, Student $student): RedirectResponse
    {
        $this->authorizeSecondarySchoolRecord($request->user(), $secondarySchool, update: true);
        abort_unless((string) $student->secondary_school_id === (string) $secondarySchool->id, 404);

        $data = $request->validate($this->studentRules($secondarySchool, $student));
        abort_unless($secondarySchool->schoolClasses()->whereKey($data['school_class_id'])->exists(), 422);

        [$firstName, $lastName] = $this->splitFullName($data['full_name']);
        $student->update([
            ...collect($data)->except('full_name')->all(),
            'first_name' => $firstName,
            'last_name' => $lastName,
        ]);

        return back()->with('success', 'Student updated.');
    }

    public function destroyStudent(Request $request, SecondarySchool $secondarySchool, Student $student): RedirectResponse
    {
        $this->authorizeSecondarySchoolRecord($request->user(), $secondarySchool, update: true);
        abort_unless((string) $student->secondary_school_id === (string) $secondarySchool->id, 404);

        $student->delete();

        return back()->with('success', 'Student deleted.');
    }

    public function storeSubjectForSchool(Request $request, SecondarySchool $secondarySchool): RedirectResponse
    {
        $this->authorizeSecondarySchoolRecord($request->user(), $secondarySchool, update: true);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', 'alpha_dash'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in([Subject::STATUS_ACTIVE, Subject::STATUS_INACTIVE])],
        ]);
        $data['code'] = filled($data['code'] ?? null)
            ? strtoupper($data['code'])
            : ReferenceCode::unique($data['name'], Subject::query()->where('secondary_school_id', $secondarySchool->id));

        $secondarySchool->subjects()->create([
            ...$data,
            'owner_type' => Exam::OWNER_SECONDARY_SCHOOL,
            'owner_id' => $secondarySchool->id,
        ]);

        return back()->with('success', 'Subject created.');
    }

    public function storeTopicForSchool(Request $request, SecondarySchool $secondarySchool): RedirectResponse
    {
        $this->authorizeSecondarySchoolRecord($request->user(), $secondarySchool, update: true);

        $data = $request->validate([
            'subject_id' => ['required', 'exists:subjects,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', 'alpha_dash'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in([Topic::STATUS_ACTIVE, Topic::STATUS_INACTIVE])],
        ]);
        abort_unless($secondarySchool->subjects()->whereKey($data['subject_id'])->exists(), 422);
        $data['code'] = filled($data['code'] ?? null)
            ? strtoupper($data['code'])
            : ReferenceCode::unique($data['name'], Topic::query()->where('subject_id', $data['subject_id']));

        Topic::query()->create($data);

        return back()->with('success', 'Topic created.');
    }

    public function index(Request $request): InertiaResponse
    {
        $this->authorizeSecondary($request->user());
        $exams = $this->secondary->secondaryExams($request->user());

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
            'weaknesses' => $this->secondary->weaknessReport($request->user()),
        ]);
    }

    public function legacyAcademicSessions(Request $request): InertiaResponse
    {
        $school = $this->legacySchool($request->user());

        return Inertia::render('SecondarySchools/AcademicSessions', [
            'secondarySchool' => $this->legacySchoolRow($school),
            'basePath' => '/secondary-school/academic-sessions',
            'sessions' => AcademicSession::query()
                ->where('school_id', $school->id)
                ->withCount('terms')
                ->latest()
                ->get(),
        ]);
    }

    public function storeLegacyAcademicSession(Request $request): RedirectResponse
    {
        $school = $this->legacySchool($request->user(), update: true);
        $data = $request->validate($this->academicSessionRules());

        AcademicSession::query()->create([
            'school_id' => $school->id,
            'name' => $data['name'],
            'code' => strtoupper(($data['code'] ?? null) ?: str($data['name'])->slug('-')->toString()),
            'starts_on' => $data['start_date'] ?? null,
            'ends_on' => $data['end_date'] ?? null,
            'status' => $data['status'],
            'is_active' => $data['is_active'],
        ]);

        return back()->with('success', 'Academic session created.');
    }

    public function updateLegacyAcademicSession(Request $request, AcademicSession $academicSession): RedirectResponse
    {
        $school = $this->legacySchool($request->user(), update: true);
        abort_unless((string) $academicSession->school_id === (string) $school->id, 404);

        $data = $request->validate($this->academicSessionRules());
        $academicSession->update([
            'name' => $data['name'],
            'code' => strtoupper(($data['code'] ?? null) ?: str($data['name'])->slug('-')->toString()),
            'starts_on' => $data['start_date'] ?? null,
            'ends_on' => $data['end_date'] ?? null,
            'status' => $data['status'],
            'is_active' => $data['is_active'],
        ]);

        return back()->with('success', 'Academic session updated.');
    }

    public function setActiveLegacyAcademicSession(Request $request, AcademicSession $academicSession): RedirectResponse
    {
        $school = $this->legacySchool($request->user(), update: true);
        abort_unless((string) $academicSession->school_id === (string) $school->id, 404);

        AcademicSession::query()->where('school_id', $school->id)->update(['is_active' => false]);
        $academicSession->update(['is_active' => true, 'status' => 'active']);

        return back()->with('success', 'Active academic session updated.');
    }

    public function destroyLegacyAcademicSession(Request $request, AcademicSession $academicSession): RedirectResponse
    {
        $school = $this->legacySchool($request->user(), update: true);
        abort_unless((string) $academicSession->school_id === (string) $school->id, 404);
        abort_if($academicSession->terms()->exists(), 422, 'Remove terms under this session first.');

        $academicSession->delete();

        return back()->with('success', 'Academic session deleted.');
    }

    public function legacyTerms(Request $request): InertiaResponse
    {
        $school = $this->legacySchool($request->user());
        $sessionIds = AcademicSession::query()->where('school_id', $school->id)->pluck('id');

        return Inertia::render('SecondarySchools/Terms', [
            'secondarySchool' => $this->legacySchoolRow($school),
            'basePath' => '/secondary-school/terms',
            'sessions' => AcademicSession::query()->where('school_id', $school->id)->latest()->get(['id', 'name', 'code']),
            'terms' => AcademicTerm::query()
                ->whereIn('academic_session_id', $sessionIds)
                ->with('session:id,name')
                ->latest()
                ->get(),
        ]);
    }

    public function storeLegacyTerm(Request $request): RedirectResponse
    {
        $school = $this->legacySchool($request->user(), update: true);
        $data = $request->validate($this->termRules());
        $this->authorizeLegacySession($school, $data['academic_session_id']);

        AcademicTerm::query()->create([
            'academic_session_id' => $data['academic_session_id'],
            'name' => $data['name'],
            'code' => strtoupper(($data['code'] ?? null) ?: str($data['name'])->before(' ')->toString()),
            'starts_on' => $data['start_date'] ?? null,
            'ends_on' => $data['end_date'] ?? null,
            'status' => $data['status'],
            'is_active' => $data['is_active'],
        ]);

        return back()->with('success', 'Term created.');
    }

    public function updateLegacyTerm(Request $request, AcademicTerm $academicTerm): RedirectResponse
    {
        $school = $this->legacySchool($request->user(), update: true);
        $this->authorizeLegacyTerm($school, $academicTerm);

        $data = $request->validate($this->termRules());
        $this->authorizeLegacySession($school, $data['academic_session_id']);
        $academicTerm->update([
            'academic_session_id' => $data['academic_session_id'],
            'name' => $data['name'],
            'code' => strtoupper(($data['code'] ?? null) ?: str($data['name'])->before(' ')->toString()),
            'starts_on' => $data['start_date'] ?? null,
            'ends_on' => $data['end_date'] ?? null,
            'status' => $data['status'],
            'is_active' => $data['is_active'],
        ]);

        return back()->with('success', 'Term updated.');
    }

    public function destroyLegacyTerm(Request $request, AcademicTerm $academicTerm): RedirectResponse
    {
        $school = $this->legacySchool($request->user(), update: true);
        $this->authorizeLegacyTerm($school, $academicTerm);

        $academicTerm->delete();

        return back()->with('success', 'Term deleted.');
    }

    public function legacyClasses(Request $request): InertiaResponse
    {
        $school = $this->legacySchool($request->user());

        return Inertia::render('SecondarySchools/Classes', [
            'secondarySchool' => $this->legacySchoolRow($school),
            'basePath' => '/secondary-school/classes',
            'classes' => SchoolClass::query()
                ->where('school_id', $school->id)
                ->with('classArms')
                ->withCount('students')
                ->orderBy('level_order')
                ->get(),
        ]);
    }

    public function storeLegacyClass(Request $request): RedirectResponse
    {
        $school = $this->legacySchool($request->user(), update: true);
        $data = $request->validate($this->classRules());

        SchoolClass::query()->create([
            'school_id' => $school->id,
            'name' => $data['name'],
            'code' => strtoupper(str_replace(' ', '', $data['level'])),
            'level' => $data['level'],
            'level_order' => $data['level_order'] ?? $this->levelOrder($data['level']),
            'status' => $data['status'],
        ]);

        return back()->with('success', 'Class created.');
    }

    public function updateLegacyClass(Request $request, SchoolClass $schoolClass): RedirectResponse
    {
        $school = $this->legacySchool($request->user(), update: true);
        abort_unless((string) $schoolClass->school_id === (string) $school->id, 404);

        $data = $request->validate($this->classRules());
        $schoolClass->update([
            'name' => $data['name'],
            'code' => strtoupper(str_replace(' ', '', $data['level'])),
            'level' => $data['level'],
            'level_order' => $data['level_order'] ?? $this->levelOrder($data['level']),
            'status' => $data['status'],
        ]);

        return back()->with('success', 'Class updated.');
    }

    public function destroyLegacyClass(Request $request, SchoolClass $schoolClass): RedirectResponse
    {
        $school = $this->legacySchool($request->user(), update: true);
        abort_unless((string) $schoolClass->school_id === (string) $school->id, 404);
        abort_if($schoolClass->classArms()->exists() || $schoolClass->groups()->exists(), 422, 'Remove arms and student groups under this class first.');

        $schoolClass->delete();

        return back()->with('success', 'Class deleted.');
    }

    public function legacyArms(Request $request): InertiaResponse
    {
        $school = $this->legacySchool($request->user());

        return Inertia::render('SecondarySchools/Arms', [
            'secondarySchool' => $this->legacySchoolRow($school),
            'basePath' => '/secondary-school/arms',
            'classes' => SchoolClass::query()->where('school_id', $school->id)->orderBy('level_order')->get(['id', 'name', 'level']),
            'arms' => ClassArm::query()
                ->whereHas('schoolClass', fn (Builder $query) => $query->where('school_id', $school->id))
                ->with('schoolClass:id,name')
                ->withCount('students')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function storeLegacyArm(Request $request): RedirectResponse
    {
        $school = $this->legacySchool($request->user(), update: true);
        $data = $request->validate($this->armRules());
        $this->authorizeLegacyClass($school, $data['school_class_id']);

        ClassArm::query()->create([
            'school_id' => $school->id,
            'school_class_id' => $data['school_class_id'],
            'name' => $data['name'],
            'code' => strtoupper(str($data['name'])->slug('-')->toString()),
            'status' => $data['status'],
        ]);

        return back()->with('success', 'Class arm created.');
    }

    public function updateLegacyArm(Request $request, ClassArm $classArm): RedirectResponse
    {
        $school = $this->legacySchool($request->user(), update: true);
        $this->authorizeLegacyArm($school, $classArm);

        $data = $request->validate($this->armRules());
        $this->authorizeLegacyClass($school, $data['school_class_id']);
        $classArm->update([
            'school_class_id' => $data['school_class_id'],
            'name' => $data['name'],
            'code' => strtoupper(str($data['name'])->slug('-')->toString()),
            'status' => $data['status'],
        ]);

        return back()->with('success', 'Class arm updated.');
    }

    public function destroyLegacyArm(Request $request, ClassArm $classArm): RedirectResponse
    {
        $school = $this->legacySchool($request->user(), update: true);
        $this->authorizeLegacyArm($school, $classArm);
        abort_if($classArm->students()->exists(), 422, 'Move or remove students in this arm first.');

        $classArm->delete();

        return back()->with('success', 'Class arm deleted.');
    }

    public function legacyStudents(Request $request): InertiaResponse
    {
        $school = $this->legacySchool($request->user());

        return Inertia::render('SecondarySchools/Students', [
            'secondarySchool' => $this->legacySchoolRow($school),
            'basePath' => '/secondary-school/students',
            'classes' => SchoolClass::query()->where('school_id', $school->id)->with('classArms')->orderBy('level_order')->get(),
            'students' => Candidate::query()
                ->where('school_id', $school->id)
                ->latest()
                ->get()
                ->map(fn (Candidate $candidate) => $this->legacyStudentRow($candidate)),
        ]);
    }

    public function storeLegacyStudent(Request $request): RedirectResponse
    {
        $school = $this->legacySchool($request->user(), update: true);
        $data = $request->validate($this->legacyStudentRules($school));
        $this->authorizeLegacyClass($school, $data['school_class_id']);
        if ($data['class_arm_id'] ?? null) {
            $this->authorizeLegacyArm($school, ClassArm::query()->findOrFail($data['class_arm_id']));
        }

        [$firstName, $lastName] = $this->splitFullName($data['full_name']);
        Candidate::query()->create([
            'school_id' => $school->id,
            'candidate_number' => $data['admission_number'],
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? $data['guardian_phone'] ?? null,
            'status' => $data['status'],
        ]);

        return back()->with('success', 'Student created.');
    }

    public function updateLegacyStudent(Request $request, Candidate $student): RedirectResponse
    {
        $school = $this->legacySchool($request->user(), update: true);
        abort_unless((string) $student->school_id === (string) $school->id, 404);

        $data = $request->validate($this->legacyStudentRules($school, $student));
        $this->authorizeLegacyClass($school, $data['school_class_id']);
        if ($data['class_arm_id'] ?? null) {
            $this->authorizeLegacyArm($school, ClassArm::query()->findOrFail($data['class_arm_id']));
        }

        [$firstName, $lastName] = $this->splitFullName($data['full_name']);
        $student->update([
            'candidate_number' => $data['admission_number'],
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? $data['guardian_phone'] ?? null,
            'status' => $data['status'],
        ]);

        return back()->with('success', 'Student updated.');
    }

    public function destroyLegacyStudent(Request $request, Candidate $student): RedirectResponse
    {
        $school = $this->legacySchool($request->user(), update: true);
        abort_unless((string) $student->school_id === (string) $school->id, 404);

        $student->delete();

        return back()->with('success', 'Student deleted.');
    }

    public function legacyStudentGroups(Request $request): InertiaResponse
    {
        $school = $this->legacySchool($request->user());

        return Inertia::render('SecondarySchools/StudentGroups', [
            'secondarySchool' => $this->legacySchoolRow($school),
            'basePath' => '/secondary-school/student-groups',
            'classes' => SchoolClass::query()->where('school_id', $school->id)->orderBy('level_order')->get(['id', 'name', 'level']),
            'students' => Candidate::query()
                ->where('school_id', $school->id)
                ->orderBy('candidate_number')
                ->get()
                ->map(fn (Candidate $candidate) => [
                    'id' => $candidate->id,
                    'school_class_id' => null,
                    'name' => trim($candidate->first_name.' '.$candidate->last_name),
                    'code' => $candidate->candidate_number,
                    'class_name' => 'N/A',
                    'arm_name' => 'N/A',
                ]),
            'groups' => StudentGroup::query()
                ->whereHas('schoolClass', fn (Builder $query) => $query->where('school_id', $school->id))
                ->with('schoolClass:id,name')
                ->with('candidates:id')
                ->withCount('candidates')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function storeLegacyStudentGroup(Request $request): RedirectResponse
    {
        $school = $this->legacySchool($request->user(), update: true);
        $data = $request->validate($this->legacyStudentGroupRules());
        $this->authorizeLegacyClass($school, $data['school_class_id']);
        $this->authorizeLegacyStudentIds($school, $data['student_ids'] ?? []);

        $group = StudentGroup::query()->create([
            ...collect($data)->except('student_ids')->all(),
            'code' => strtoupper(($data['code'] ?? null) ?: str($data['name'])->slug('-')->toString()),
        ]);
        $group->candidates()->sync($data['student_ids'] ?? []);

        return back()->with('success', 'Student group created.');
    }

    public function updateLegacyStudentGroup(Request $request, StudentGroup $studentGroup): RedirectResponse
    {
        $school = $this->legacySchool($request->user(), update: true);
        $this->authorizeLegacyStudentGroup($school, $studentGroup);

        $data = $request->validate($this->legacyStudentGroupRules());
        $this->authorizeLegacyClass($school, $data['school_class_id']);
        $this->authorizeLegacyStudentIds($school, $data['student_ids'] ?? []);
        $studentGroup->update([
            ...collect($data)->except('student_ids')->all(),
            'code' => strtoupper(($data['code'] ?? null) ?: str($data['name'])->slug('-')->toString()),
        ]);
        $studentGroup->candidates()->sync($data['student_ids'] ?? []);

        return back()->with('success', 'Student group updated.');
    }

    public function destroyLegacyStudentGroup(Request $request, StudentGroup $studentGroup): RedirectResponse
    {
        $school = $this->legacySchool($request->user(), update: true);
        $this->authorizeLegacyStudentGroup($school, $studentGroup);

        $studentGroup->delete();

        return back()->with('success', 'Student group deleted.');
    }

    public function legacyStructureTemplate(Request $request, string $section)
    {
        $this->legacySchool($request->user());

        return response()->streamDownload(function () use ($section): void {
            echo $this->templateForSection($section);
        }, "{$section}-template.csv", ['Content-Type' => 'text/csv']);
    }

    public function importLegacyStructure(Request $request, string $section): RedirectResponse
    {
        $school = $this->legacySchool($request->user(), update: true);
        $rules = ['file' => ['required', 'file', 'mimes:csv,txt', 'max:2048']];

        if ($section === 'students') {
            $rules['school_class_id'] = ['required', 'exists:school_classes,id'];
            $rules['class_arm_id'] = ['nullable', 'exists:class_arms,id'];
        }

        $data = $request->validate($rules);
        $context = [];

        if ($section === 'students') {
            $classId = (string) $data['school_class_id'];
            $armId = filled($data['class_arm_id'] ?? null) ? (string) $data['class_arm_id'] : null;

            abort_unless(SchoolClass::query()->whereKey($classId)->where('school_id', $school->id)->exists(), 422, 'Choose a class in this school.');

            if ($armId) {
                abort_unless(ClassArm::query()->whereKey($armId)->where('school_class_id', $classId)->exists(), 422, 'Choose an arm in this class.');
            }

            $context = ['school_class_id' => $classId, 'class_arm_id' => $armId];
        }

        $created = 0;
        foreach ($this->csvRows($request->file('file')->getRealPath()) as $row) {
            $this->importLegacyStructureRow($school, $section, $row, $context);
            $created++;
        }

        return back()->with('success', "{$created} rows imported.");
    }

    private function legacySchool(User $user, bool $update = false): School
    {
        $this->authorizeSecondary($user);
        abort_if($update && ! ($user->hasPermission('manageSchools') || $user->hasPermission('manageExams')), 403);
        abort_unless($user->school_id, 403);

        return School::query()->findOrFail($user->school_id);
    }

    private function legacySchoolRow(School $school): array
    {
        return [
            'id' => $school->id,
            'name' => $school->name,
            'code' => $school->code,
            'contact_person' => $school->contact_person,
            'email' => $school->email,
            'phone' => $school->phone,
            'address' => $school->location,
            'status' => $school->status,
            'status_label' => str($school->status)->replace('_', ' ')->title()->toString(),
        ];
    }

    private function authorizeLegacySession(School $school, string $sessionId): void
    {
        abort_unless(AcademicSession::query()->whereKey($sessionId)->where('school_id', $school->id)->exists(), 422);
    }

    private function authorizeLegacyTerm(School $school, AcademicTerm $term): void
    {
        $term->loadMissing('session');
        abort_unless((string) $term->session?->school_id === (string) $school->id, 404);
    }

    private function authorizeLegacyClass(School $school, string $classId): void
    {
        abort_unless(SchoolClass::query()->whereKey($classId)->where('school_id', $school->id)->exists(), 422);
    }

    private function authorizeLegacyArm(School $school, ClassArm $arm): void
    {
        $arm->loadMissing('schoolClass');
        abort_unless((string) $arm->schoolClass?->school_id === (string) $school->id, 404);
    }

    private function authorizeLegacyStudentGroup(School $school, StudentGroup $studentGroup): void
    {
        $studentGroup->loadMissing('schoolClass');
        abort_unless((string) $studentGroup->schoolClass?->school_id === (string) $school->id, 404);
    }

    private function authorizeLegacyStudentIds(School $school, array $studentIds): void
    {
        $studentIds = collect($studentIds)->filter()->unique()->values();
        if ($studentIds->isEmpty()) {
            return;
        }

        $count = Candidate::query()->where('school_id', $school->id)->whereIn('id', $studentIds)->count();
        abort_unless($count === $studentIds->count(), 422, 'Choose students within this school.');
    }

    private function legacyStudentRules(School $school, ?Candidate $candidate = null): array
    {
        return [
            'school_class_id' => ['required', 'exists:school_classes,id'],
            'class_arm_id' => ['nullable', 'exists:class_arms,id'],
            'admission_number' => ['required', 'string', 'max:100', Rule::unique('candidates', 'candidate_number')->where('school_id', $school->id)->ignore($candidate)],
            'full_name' => ['required', 'string', 'max:255'],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'guardian_name' => ['nullable', 'string', 'max:255'],
            'guardian_phone' => ['nullable', 'string', 'max:50'],
            'status' => ['required', Rule::in([Candidate::STATUS_ACTIVE, Candidate::STATUS_INACTIVE, Candidate::STATUS_SUSPENDED])],
        ];
    }

    private function legacyStudentRow(Candidate $candidate): array
    {
        return [
            'id' => $candidate->id,
            'admission_number' => $candidate->candidate_number,
            'school_class_id' => null,
            'class_arm_id' => null,
            'full_name' => trim($candidate->first_name.' '.$candidate->last_name),
            'gender' => null,
            'email' => $candidate->email,
            'phone' => $candidate->phone,
            'guardian_name' => null,
            'guardian_phone' => $candidate->phone,
            'photo' => $candidate->photo,
            'class_name' => 'N/A',
            'arm_name' => 'N/A',
            'status' => $candidate->status,
        ];
    }

    private function importLegacyStructureRow(School $school, string $section, array $row, array $context = []): void
    {
        match ($section) {
            'academic-sessions' => AcademicSession::query()->create([
                'school_id' => $school->id,
                'name' => trim($row['name'] ?? ''),
                'code' => strtoupper(trim($row['code'] ?? '') ?: str($row['name'] ?? 'session')->slug('-')->toString()),
                'starts_on' => $row['start_date'] ?? null,
                'ends_on' => $row['end_date'] ?? null,
                'status' => trim($row['status'] ?? 'active') ?: 'active',
                'is_active' => in_array(strtolower(trim($row['is_active'] ?? 'no')), ['yes', 'true', '1'], true),
            ]),
            'terms' => AcademicTerm::query()->create([
                'academic_session_id' => AcademicSession::query()->where('school_id', $school->id)->where('code', strtoupper(trim($row['academic_session_code'] ?? '')))->value('id'),
                'name' => trim($row['name'] ?? ''),
                'code' => strtoupper(trim($row['code'] ?? '') ?: str($row['name'] ?? 'term')->before(' ')->toString()),
                'starts_on' => $row['start_date'] ?? null,
                'ends_on' => $row['end_date'] ?? null,
                'status' => trim($row['status'] ?? 'active') ?: 'active',
                'is_active' => in_array(strtolower(trim($row['is_active'] ?? 'no')), ['yes', 'true', '1'], true),
            ]),
            'classes' => SchoolClass::query()->create([
                'school_id' => $school->id,
                'name' => trim($row['name'] ?? ''),
                'code' => strtoupper(str_replace(' ', '', trim($row['level'] ?? $row['name'] ?? 'CLASS'))),
                'level' => trim($row['level'] ?? ''),
                'level_order' => (int) ($row['level_order'] ?? 1),
                'status' => trim($row['status'] ?? 'active') ?: 'active',
            ]),
            'arms' => ClassArm::query()->create([
                'school_class_id' => SchoolClass::query()->where('school_id', $school->id)->where('code', strtoupper(trim($row['class_code'] ?? '')))->value('id'),
                'name' => trim($row['name'] ?? ''),
                'code' => strtoupper(trim($row['code'] ?? '') ?: str($row['name'] ?? 'arm')->slug('-')->toString()),
                'status' => trim($row['status'] ?? 'active') ?: 'active',
            ]),
            'student-groups' => StudentGroup::query()->create([
                'school_class_id' => SchoolClass::query()->where('school_id', $school->id)->where('code', strtoupper(trim($row['class_code'] ?? '')))->value('id'),
                'name' => trim($row['name'] ?? ''),
                'code' => strtoupper(trim($row['code'] ?? '') ?: str($row['name'] ?? 'group')->slug('-')->toString()),
                'status' => trim($row['status'] ?? 'active') ?: 'active',
            ]),
            'students' => Candidate::query()->create([
                'school_id' => $school->id,
                'candidate_number' => trim($row['admission_number'] ?? ''),
                'first_name' => $this->splitFullName(trim($row['full_name'] ?? ''))[0],
                'last_name' => $this->splitFullName(trim($row['full_name'] ?? ''))[1],
                'email' => trim($row['email'] ?? '') ?: null,
                'phone' => trim($row['phone'] ?? $row['guardian_phone'] ?? '') ?: null,
                'status' => trim($row['status'] ?? Candidate::STATUS_ACTIVE) ?: Candidate::STATUS_ACTIVE,
            ]),
            default => abort(404),
        };
    }

    public function storeSession(Request $request): RedirectResponse
    {
        $this->authorizeSecondary($request->user());
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        AcademicSession::query()->create([...$this->secondary->scope($request->user()), ...$data, 'code' => filled($data['code'] ?? null) ? strtoupper($data['code']) : ReferenceCode::unique($data['name'], AcademicSession::query())]);

        return back()->with('success', 'Academic session created.');
    }

    public function storeTerm(Request $request): RedirectResponse
    {
        $this->authorizeSecondary($request->user());
        $data = $request->validate([
            'academic_session_id' => ['required', 'exists:academic_sessions,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        AcademicTerm::query()->create([...$data, 'code' => filled($data['code'] ?? null) ? strtoupper($data['code']) : ReferenceCode::unique($data['name'], AcademicTerm::query()->where('academic_session_id', $data['academic_session_id']))]);

        return back()->with('success', 'Term created.');
    }

    public function storeClass(Request $request): RedirectResponse
    {
        $this->authorizeSecondary($request->user());
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'level_order' => ['required', 'integer', 'min:1', 'max:99'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        SchoolClass::query()->create([...$this->secondary->scope($request->user()), ...$data, 'code' => filled($data['code'] ?? null) ? strtoupper($data['code']) : ReferenceCode::unique($data['name'], SchoolClass::query())]);

        return back()->with('success', 'Class/level created.');
    }

    public function storeGroup(Request $request): RedirectResponse
    {
        $this->authorizeSecondary($request->user());
        $data = $request->validate([
            'school_class_id' => ['required', 'exists:school_classes,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'candidate_ids' => ['array'],
            'candidate_ids.*' => ['string', 'exists:candidates,id'],
        ]);

        $group = StudentGroup::query()->create([...collect($data)->except('candidate_ids')->all(), 'code' => filled($data['code'] ?? null) ? strtoupper($data['code']) : ReferenceCode::unique($data['name'], StudentGroup::query()->where('school_class_id', $data['school_class_id']))]);
        $group->candidates()->sync($data['candidate_ids'] ?? []);

        return back()->with('success', 'Student group created.');
    }

    public function structureTemplate(Request $request, SecondarySchool $secondarySchool, string $section)
    {
        $this->authorizeSecondarySchoolRecord($request->user(), $secondarySchool);

        return response()->streamDownload(function () use ($section): void {
            echo $this->templateForSection($section);
        }, "{$section}-template.csv", ['Content-Type' => 'text/csv']);
    }

    public function importStructure(Request $request, SecondarySchool $secondarySchool, string $section): RedirectResponse
    {
        $this->authorizeSecondarySchoolRecord($request->user(), $secondarySchool, update: true);
        $rules = ['file' => ['required', 'file', 'mimes:csv,txt', 'max:2048']];

        if ($section === 'students') {
            $rules['school_class_id'] = ['required', 'exists:school_classes,id'];
            $rules['class_arm_id'] = ['nullable', 'exists:class_arms,id'];
        }

        $data = $request->validate($rules);
        $context = [];

        if ($section === 'students') {
            $classId = (string) $data['school_class_id'];
            $armId = filled($data['class_arm_id'] ?? null) ? (string) $data['class_arm_id'] : null;

            abort_unless($secondarySchool->schoolClasses()->whereKey($classId)->exists(), 422, 'Choose a class in this secondary school.');

            if ($armId) {
                abort_unless(ClassArm::query()->whereKey($armId)->where('school_class_id', $classId)->exists(), 422, 'Choose an arm in this class.');
            }

            $context = ['school_class_id' => $classId, 'class_arm_id' => $armId];
        }

        $created = 0;
        foreach ($this->csvRows($request->file('file')->getRealPath()) as $row) {
            $this->importStructureRow($secondarySchool, $section, $row, $context);
            $created++;
        }

        return back()->with('success', "{$created} rows imported.");
    }

    private function canListSecondarySchools(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->isOrganizationAdmin()
            || $user->isSecondarySchoolAdmin()
            || $user->hasPermission('viewReports');
    }

    private function secondarySchoolScope(User $user): Builder
    {
        return SecondarySchool::query()
            ->when(! $user->isSuperAdmin() && $user->organization_id, fn (Builder $query) => $query->where('organization_id', $user->organization_id))
            ->when(! $user->isSuperAdmin() && $user->secondary_school_id, fn (Builder $query) => $query->whereKey($user->secondary_school_id));
    }

    private function authorizeSecondarySchoolRecord(User $user, SecondarySchool $secondarySchool, bool $update = false): void
    {
        $allowed = $user->isSuperAdmin()
            || ($user->organization_id && (string) $user->organization_id === (string) $secondarySchool->organization_id)
            || ($user->secondary_school_id && (string) $user->secondary_school_id === (string) $secondarySchool->id);

        abort_unless($allowed, 403);
        abort_if($update && ! ($user->isSuperAdmin() || $user->hasPermission('manageSchools')), 403);
    }

    private function secondarySchoolRules(?SecondarySchool $secondarySchool = null): array
    {
        return [
            'organization_id' => ['nullable', 'exists:organizations,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', 'alpha_dash', Rule::unique('secondary_schools', 'code')->ignore($secondarySchool)],
            'contact_person' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('secondary_schools', 'email')->ignore($secondarySchool)],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in([SecondarySchool::STATUS_ACTIVE, SecondarySchool::STATUS_INACTIVE])],
        ];
    }

    private function secondarySchoolFormOptions(): array
    {
        return [
            'organizations' => Organization::query()->orderBy('name')->get(['id', 'name', 'code']),
            'statuses' => [
                ['value' => SecondarySchool::STATUS_ACTIVE, 'label' => 'Active'],
                ['value' => SecondarySchool::STATUS_INACTIVE, 'label' => 'Inactive'],
            ],
        ];
    }

    private function academicSessionRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'is_active' => ['required', 'boolean'],
        ];
    }

    private function termRules(): array
    {
        return [
            'academic_session_id' => ['required', 'exists:academic_sessions,id'],
            'name' => ['required', Rule::in(['First Term', 'Second Term', 'Third Term'])],
            'code' => ['nullable', 'string', 'max:50'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'is_active' => ['required', 'boolean'],
        ];
    }

    private function classRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'level' => ['required', Rule::in(['JSS 1', 'JSS 2', 'JSS 3', 'SS 1', 'SS 2', 'SS 3'])],
            'level_order' => ['nullable', 'integer', 'min:1', 'max:99'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }

    private function armRules(): array
    {
        return [
            'school_class_id' => ['required', 'exists:school_classes,id'],
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }

    private function studentGroupRules(): array
    {
        return [
            'school_class_id' => ['required', 'exists:school_classes,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }

    private function correctedStudentGroupRules(): array
    {
        return [
            ...$this->studentGroupRules(),
            'student_ids' => ['array'],
            'student_ids.*' => ['string', 'exists:students,id', 'distinct'],
        ];
    }

    private function legacyStudentGroupRules(): array
    {
        return [
            ...$this->studentGroupRules(),
            'student_ids' => ['array'],
            'student_ids.*' => ['string', 'exists:candidates,id', 'distinct'],
        ];
    }

    private function authorizeStudentIds(SecondarySchool $secondarySchool, array $studentIds): void
    {
        $studentIds = collect($studentIds)->filter()->unique()->values();
        if ($studentIds->isEmpty()) {
            return;
        }

        $count = $secondarySchool->students()->whereIn('id', $studentIds)->count();
        abort_unless($count === $studentIds->count(), 422, 'Choose students within this secondary school.');
    }

    private function studentRules(SecondarySchool $secondarySchool, ?Student $student = null): array
    {
        return [
            'school_class_id' => ['required', 'exists:school_classes,id'],
            'admission_number' => ['required', 'string', 'max:100', Rule::unique('students')->where('secondary_school_id', $secondarySchool->id)->ignore($student)],
            'full_name' => ['required', 'string', 'max:255'],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'guardian_name' => ['nullable', 'string', 'max:255'],
            'guardian_phone' => ['nullable', 'string', 'max:50'],
            'status' => ['required', Rule::in(['active', 'inactive', 'suspended'])],
        ];
    }

    private function authorizeStudentGroupRecord(SecondarySchool $secondarySchool, StudentGroup $studentGroup): void
    {
        $studentGroup->loadMissing('schoolClass');
        abort_unless((string) $studentGroup->schoolClass?->secondary_school_id === (string) $secondarySchool->id, 404);
    }

    private function secondarySchoolRow(SecondarySchool $school): array
    {
        return [
            'id' => $school->id,
            'organization_id' => $school->organization_id,
            'organization_name' => $school->organization?->name,
            'name' => $school->name,
            'code' => $school->code,
            'contact_person' => $school->contact_person,
            'email' => $school->email,
            'phone' => $school->phone,
            'address' => $school->address,
            'status' => $school->status,
            'status_label' => str($school->status)->replace('_', ' ')->title()->toString(),
            'students_count' => $school->students_count ?? 0,
            'classes_count' => $school->school_classes_count ?? 0,
            'exams_count' => $school->exams_count ?? 0,
        ];
    }

    private function secondarySchoolDetail(SecondarySchool $school): array
    {
        return [
            ...$this->secondarySchoolRow($school),
            'subjects_count' => $school->subjects_count ?? 0,
            'question_banks_count' => $school->question_banks_count ?? 0,
            'academic_sessions' => $school->academicSessions->map(fn (AcademicSession $session) => [
                'id' => $session->id,
                'name' => $session->name,
                'code' => $session->code,
                'status' => $session->status,
                'is_active' => (bool) $session->is_active,
                'terms_count' => $session->terms_count ?? 0,
            ]),
            'classes' => $school->schoolClasses->map(fn (SchoolClass $class) => [
                'id' => $class->id,
                'name' => $class->name,
                'code' => $class->code,
                'level' => $class->level,
            ]),
            'students' => $school->students->map(fn (Student $student) => $this->studentRow($student)),
            'subjects' => $school->subjects->map(fn (Subject $subject) => [
                'id' => $subject->id,
                'name' => $subject->name,
                'code' => $subject->code,
            ]),
            'recent_exams' => $school->exams->map(fn (Exam $exam) => [
                'id' => $exam->id,
                'title' => $exam->title,
                'code' => $exam->code,
                'status' => $exam->status,
                'category' => $exam->exam_category,
                'mode' => $exam->exam_mode ?? $exam->mode,
            ]),
        ];
    }

    private function secondaryDashboard(SecondarySchool $school): array
    {
        $activeSession = $school->academicSessions()->where('is_active', true)->first();
        $activeTerm = $school->terms()->where('is_active', true)->first();
        $terminalExams = $school->exams()->where('exam_category', Exam::CATEGORY_TERMINAL);

        return [
            'total_students' => $school->students()->count(),
            'total_classes' => $school->schoolClasses()->count(),
            'total_subjects' => $school->subjects()->count(),
            'active_academic_session' => $activeSession?->name,
            'active_term' => $activeTerm?->name,
            'terminal_exams' => (clone $terminalExams)->count(),
            'completed_exams' => (clone $terminalExams)->where('status', Exam::STATUS_COMPLETED)->count(),
            'pending_results' => (clone $terminalExams)->whereIn('status', [Exam::STATUS_ACTIVE, Exam::STATUS_SCHEDULED])->count(),
        ];
    }

    private function studentRow(Student $student): array
    {
        return [
            'id' => $student->id,
            'admission_number' => $student->admission_number,
            'school_class_id' => $student->school_class_id,
            'full_name' => trim($student->first_name.' '.$student->last_name),
            'gender' => $student->gender,
            'email' => $student->email,
            'phone' => $student->phone,
            'guardian_name' => $student->guardian_name,
            'guardian_phone' => $student->guardian_phone,
            'photo' => $student->photo,
            'class_name' => $student->schoolClass?->name,
            'status' => $student->status,
        ];
    }

    private function splitFullName(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName), 2) ?: [];

        return [$parts[0] ?? '', $parts[1] ?? ''];
    }

    private function levelOrder(string $level): int
    {
        return array_search($level, ['JSS 1', 'JSS 2', 'JSS 3', 'SS 1', 'SS 2', 'SS 3'], true) + 1;
    }

    private function authorizeSecondary(User $user): void
    {
        abort_unless($user->hasPermission('manageSchools') || $user->hasPermission('manageExams') || $user->hasPermission('viewReports'), 403);
        abort_unless($user->school_id !== null || $user->secondary_school_id !== null, 403);
    }

    private function templateForSection(string $section): string
    {
        return match ($section) {
            'academic-sessions' => "name,code,start_date,end_date,status,is_active\n2026/2027,2026,2026-09-01,2027-07-31,active,yes\n",
            'terms' => "academic_session_code,name,code,start_date,end_date,status,is_active\n2026,First Term,T1,2026-09-01,2026-12-10,active,yes\n",
            'classes' => "name,level,level_order,status\nJSS 1,JSS 1,1,active\n",
            'student-groups' => "class_code,name,code,status\nJSS1,Science Group,SCI,active\n",
            'students' => "admission_number,full_name,gender,email,phone,guardian_name,guardian_phone,status\nADM-001,Ada Student,female,ada@example.test,08030000000,Parent Name,08030000001,active\n",
            default => abort(404),
        };
    }

    private function importStructureRow(SecondarySchool $school, string $section, array $row, array $context = []): void
    {
        match ($section) {
            'academic-sessions' => $school->academicSessions()->create([
                'name' => trim($row['name'] ?? ''),
                'code' => strtoupper(trim($row['code'] ?? '') ?: str($row['name'] ?? 'session')->slug('-')->toString()),
                'starts_on' => $row['start_date'] ?? null,
                'ends_on' => $row['end_date'] ?? null,
                'status' => trim($row['status'] ?? 'active') ?: 'active',
                'is_active' => in_array(strtolower(trim($row['is_active'] ?? 'no')), ['yes', 'true', '1'], true),
            ]),
            'terms' => $school->terms()->create([
                'academic_session_id' => $school->academicSessions()->where('code', strtoupper(trim($row['academic_session_code'] ?? '')))->value('id'),
                'name' => trim($row['name'] ?? ''),
                'code' => strtoupper(trim($row['code'] ?? '') ?: str($row['name'] ?? 'term')->before(' ')->toString()),
                'starts_on' => $row['start_date'] ?? null,
                'ends_on' => $row['end_date'] ?? null,
                'status' => trim($row['status'] ?? 'active') ?: 'active',
                'is_active' => in_array(strtolower(trim($row['is_active'] ?? 'no')), ['yes', 'true', '1'], true),
            ]),
            'classes' => $school->schoolClasses()->create([
                'name' => trim($row['name'] ?? ''),
                'code' => strtoupper(str_replace(' ', '', trim($row['level'] ?? $row['name'] ?? 'CLASS'))),
                'level' => trim($row['level'] ?? ''),
                'level_order' => (int) ($row['level_order'] ?? 1),
                'status' => trim($row['status'] ?? 'active') ?: 'active',
            ]),
            'student-groups' => StudentGroup::query()->create([
                'school_class_id' => $school->schoolClasses()->where('code', strtoupper(trim($row['class_code'] ?? '')))->value('id'),
                'name' => trim($row['name'] ?? ''),
                'code' => strtoupper(trim($row['code'] ?? '') ?: str($row['name'] ?? 'group')->slug('-')->toString()),
                'status' => trim($row['status'] ?? 'active') ?: 'active',
            ]),
            'students' => $school->students()->create([
                'school_class_id' => $context['school_class_id'] ?? null,
                'admission_number' => trim($row['admission_number'] ?? ''),
                'first_name' => $this->splitFullName(trim($row['full_name'] ?? ''))[0],
                'last_name' => $this->splitFullName(trim($row['full_name'] ?? ''))[1],
                'gender' => trim($row['gender'] ?? '') ?: null,
                'email' => trim($row['email'] ?? '') ?: null,
                'phone' => trim($row['phone'] ?? '') ?: null,
                'guardian_name' => trim($row['guardian_name'] ?? '') ?: null,
                'guardian_phone' => trim($row['guardian_phone'] ?? '') ?: null,
                'status' => trim($row['status'] ?? 'active') ?: 'active',
            ]),
            default => abort(404),
        };
    }

    private function csvRows(string $path): array
    {
        $handle = fopen($path, 'r');
        $headers = array_map(fn ($header) => strtolower(trim((string) $header)), fgetcsv($handle) ?: []);
        $rows = [];

        while (($row = fgetcsv($handle)) !== false) {
            if (count(array_filter($row, fn ($value) => filled($value))) === 0) {
                continue;
            }

            $rows[] = array_combine($headers, array_pad($row, count($headers), null));
        }

        fclose($handle);

        return $rows;
    }
}
