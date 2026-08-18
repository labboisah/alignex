<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Institution;
use App\Models\Programme;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class InstitutionStructureController extends Controller
{
    public function faculties(Request $request, Institution $institution): Response
    {
        $this->authorizeInstitution($request->user(), $institution);

        return Inertia::render('Institutions/Faculties', [
            'institution' => $institution,
            'faculties' => $institution->faculties()->withCount(['departments', 'programmes', 'courses'])->orderBy('name')->get(),
        ]);
    }

    public function storeFaculty(Request $request, Institution $institution): RedirectResponse
    {
        $this->authorizeInstitution($request->user(), $institution);

        $data = $request->validate($this->facultyRules($institution));

        $institution->faculties()->create($data);

        return back()->with('success', 'Faculty created.');
    }

    public function updateFaculty(Request $request, Institution $institution, Faculty $faculty): RedirectResponse
    {
        $this->authorizeInstitution($request->user(), $institution);
        $this->authorizeBelongsToInstitution($faculty, $institution);

        $faculty->update($request->validate($this->facultyRules($institution, $faculty)));

        return back()->with('success', 'Faculty updated.');
    }

    public function destroyFaculty(Request $request, Institution $institution, Faculty $faculty): RedirectResponse
    {
        $this->authorizeInstitution($request->user(), $institution);
        $this->authorizeBelongsToInstitution($faculty, $institution);

        $faculty->delete();

        return back()->with('success', 'Faculty deleted.');
    }

    public function departments(Request $request, Institution $institution): Response
    {
        $this->authorizeInstitution($request->user(), $institution);

        return Inertia::render('Institutions/Departments', [
            'institution' => $institution,
            'faculties' => $institution->faculties()->orderBy('name')->get(['id', 'name', 'code']),
            'departments' => $institution->departments()->with('faculty:id,name')->withCount(['programmes', 'courses'])->orderBy('name')->get(),
        ]);
    }

    public function storeDepartment(Request $request, Institution $institution): RedirectResponse
    {
        $this->authorizeInstitution($request->user(), $institution);

        $data = $request->validate($this->departmentRules($institution));

        abort_unless($institution->faculties()->whereKey($data['faculty_id'])->exists(), 422);

        $institution->departments()->create($data);

        return back()->with('success', 'Department created.');
    }

    public function updateDepartment(Request $request, Institution $institution, Department $department): RedirectResponse
    {
        $this->authorizeInstitution($request->user(), $institution);
        $this->authorizeBelongsToInstitution($department, $institution);

        $data = $request->validate($this->departmentRules($institution, $department));
        abort_unless($institution->faculties()->whereKey($data['faculty_id'])->exists(), 422);

        $department->update($data);

        return back()->with('success', 'Department updated.');
    }

    public function destroyDepartment(Request $request, Institution $institution, Department $department): RedirectResponse
    {
        $this->authorizeInstitution($request->user(), $institution);
        $this->authorizeBelongsToInstitution($department, $institution);

        $department->delete();

        return back()->with('success', 'Department deleted.');
    }

    public function programmes(Request $request, Institution $institution): Response
    {
        $this->authorizeInstitution($request->user(), $institution);

        return Inertia::render('Institutions/Programmes', [
            'institution' => $institution,
            'faculties' => $institution->faculties()->orderBy('name')->get(['id', 'name', 'code']),
            'departments' => $institution->departments()->with('faculty:id,name')->orderBy('name')->get(['id', 'faculty_id', 'name', 'code']),
            'programmes' => $institution->programmes()->with(['faculty:id,name', 'department:id,name'])->withCount('courses')->orderBy('name')->get(),
        ]);
    }

    public function storeProgramme(Request $request, Institution $institution): RedirectResponse
    {
        $this->authorizeInstitution($request->user(), $institution);

        $data = $request->validate($this->programmeRules($institution));

        if (! empty($data['faculty_id'])) {
            abort_unless($institution->faculties()->whereKey($data['faculty_id'])->exists(), 422);
        }
        if (! empty($data['department_id'])) {
            abort_unless($institution->departments()->whereKey($data['department_id'])->exists(), 422);
        }

        $institution->programmes()->create($data);

        return back()->with('success', 'Programme created.');
    }

    public function updateProgramme(Request $request, Institution $institution, Programme $programme): RedirectResponse
    {
        $this->authorizeInstitution($request->user(), $institution);
        $this->authorizeBelongsToInstitution($programme, $institution);

        $data = $request->validate($this->programmeRules($institution, $programme));
        if (! empty($data['faculty_id'])) {
            abort_unless($institution->faculties()->whereKey($data['faculty_id'])->exists(), 422);
        }
        if (! empty($data['department_id'])) {
            abort_unless($institution->departments()->whereKey($data['department_id'])->exists(), 422);
        }

        $programme->update($data);

        return back()->with('success', 'Programme updated.');
    }

    public function destroyProgramme(Request $request, Institution $institution, Programme $programme): RedirectResponse
    {
        $this->authorizeInstitution($request->user(), $institution);
        $this->authorizeBelongsToInstitution($programme, $institution);

        $programme->delete();

        return back()->with('success', 'Programme deleted.');
    }

    public function courses(Request $request, Institution $institution): Response
    {
        $this->authorizeInstitution($request->user(), $institution);

        return Inertia::render('Institutions/Courses', [
            'institution' => $institution,
            'faculties' => $institution->faculties()->orderBy('name')->get(['id', 'name', 'code']),
            'departments' => $institution->departments()->with('faculty:id,name')->orderBy('name')->get(['id', 'faculty_id', 'name', 'code']),
            'programmes' => $institution->programmes()->with(['faculty:id,name', 'department:id,name'])->orderBy('name')->get(['id', 'faculty_id', 'department_id', 'name', 'code']),
            'courses' => $institution->courses()->with(['faculty:id,name', 'department:id,name', 'programme:id,name'])->orderBy('name')->get(),
        ]);
    }

    public function storeCourse(Request $request, Institution $institution): RedirectResponse
    {
        $this->authorizeInstitution($request->user(), $institution);

        $data = $request->validate($this->courseRules($institution));

        if (! empty($data['faculty_id'])) {
            abort_unless($institution->faculties()->whereKey($data['faculty_id'])->exists(), 422);
        }
        if (! empty($data['department_id'])) {
            abort_unless($institution->departments()->whereKey($data['department_id'])->exists(), 422);
        }
        if (! empty($data['programme_id'])) {
            abort_unless($institution->programmes()->whereKey($data['programme_id'])->exists(), 422);
        }

        $institution->courses()->create($data);

        return back()->with('success', 'Course created.');
    }

    public function updateCourse(Request $request, Institution $institution, Course $course): RedirectResponse
    {
        $this->authorizeInstitution($request->user(), $institution);
        $this->authorizeBelongsToInstitution($course, $institution);

        $data = $request->validate($this->courseRules($institution, $course));
        if (! empty($data['faculty_id'])) {
            abort_unless($institution->faculties()->whereKey($data['faculty_id'])->exists(), 422);
        }
        if (! empty($data['department_id'])) {
            abort_unless($institution->departments()->whereKey($data['department_id'])->exists(), 422);
        }
        if (! empty($data['programme_id'])) {
            abort_unless($institution->programmes()->whereKey($data['programme_id'])->exists(), 422);
        }

        $course->update($data);

        return back()->with('success', 'Course updated.');
    }

    public function destroyCourse(Request $request, Institution $institution, Course $course): RedirectResponse
    {
        $this->authorizeInstitution($request->user(), $institution);
        $this->authorizeBelongsToInstitution($course, $institution);

        $course->delete();

        return back()->with('success', 'Course deleted.');
    }

    public function lecturers(Request $request, Institution $institution, Department $department): Response
    {
        $this->authorizeInstitution($request->user(), $institution);
        $this->authorizeBelongsToInstitution($department, $institution);

        return Inertia::render('Institutions/Lecturers', [
            'institution' => $institution,
            'department' => $department->load('faculty:id,name,code'),
            'courses' => $department->courses()
                ->where('institution_id', $institution->id)
                ->with('programme:id,name')
                ->orderBy('name')
                ->get(['id', 'programme_id', 'name', 'code']),
            'lecturers' => User::query()
                ->where('role', User::ROLE_FACILITATOR)
                ->where('institution_id', $institution->id)
                ->where('department_id', $department->id)
                ->with(['assignedCourses' => fn ($query) => $query
                    ->where('courses.department_id', $department->id)
                    ->orderBy('name')])
                ->orderBy('name')
                ->get()
                ->map(fn (User $lecturer) => $this->lecturerRow($lecturer)),
        ]);
    }

    public function storeLecturer(Request $request, Institution $institution, Department $department): RedirectResponse
    {
        $this->authorizeInstitution($request->user(), $institution);
        $this->authorizeBelongsToInstitution($department, $institution);

        $data = $request->validate($this->lecturerRules());

        DB::transaction(function () use ($institution, $department, $data): void {
            $lecturer = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => User::ROLE_FACILITATOR,
                'organization_id' => $institution->organization_id,
                'institution_id' => $institution->id,
                'faculty_id' => $department->faculty_id,
                'department_id' => $department->id,
                'active_context_type' => 'institution',
                'active_context_id' => $institution->id,
                'status' => User::STATUS_ACTIVE,
            ]);

            $this->syncLecturerAssignments($lecturer, $institution, $department, $data['course_ids']);
        });

        return back()->with('success', 'Lecturer created.');
    }

    public function updateLecturer(Request $request, Institution $institution, Department $department, User $lecturer): RedirectResponse
    {
        $this->authorizeInstitution($request->user(), $institution);
        $this->authorizeBelongsToInstitution($department, $institution);
        $this->authorizeLecturerRecord($institution, $department, $lecturer);

        $data = $request->validate($this->lecturerRules($lecturer));

        DB::transaction(function () use ($lecturer, $institution, $department, $data): void {
            $payload = [
                'name' => $data['name'],
                'email' => $data['email'],
                'organization_id' => $institution->organization_id,
                'institution_id' => $institution->id,
                'faculty_id' => $department->faculty_id,
                'department_id' => $department->id,
                'active_context_type' => 'institution',
                'active_context_id' => $institution->id,
                'status' => User::STATUS_ACTIVE,
            ];

            if (filled($data['password'] ?? null)) {
                $payload['password'] = Hash::make($data['password']);
            }

            $lecturer->update($payload);
            $this->syncLecturerAssignments($lecturer, $institution, $department, $data['course_ids']);
        });

        return back()->with('success', 'Lecturer updated.');
    }

    public function destroyLecturer(Request $request, Institution $institution, Department $department, User $lecturer): RedirectResponse
    {
        $this->authorizeInstitution($request->user(), $institution);
        $this->authorizeBelongsToInstitution($department, $institution);
        $this->authorizeLecturerRecord($institution, $department, $lecturer);

        $lecturer->delete();

        return back()->with('success', 'Lecturer deleted.');
    }

    private function authorizeInstitution($user, Institution $institution): void
    {
        if ($user->isSuperAdmin()) {
            return;
        }

        abort_unless($user->isInstitutionAdmin() && (int) $user->institution_id === (int) $institution->id, 403);
    }

    private function authorizeBelongsToInstitution($model, Institution $institution): void
    {
        abort_unless((int) $model->institution_id === (int) $institution->id, 404);
    }

    private function facultyRules(Institution $institution, ?Faculty $faculty = null): array
    {
        $codeRule = Rule::unique('faculties')->where('institution_id', $institution->id);
        $nameRule = Rule::unique('faculties')->where('institution_id', $institution->id);

        if ($faculty) {
            $codeRule->ignore($faculty);
            $nameRule->ignore($faculty);
        }

        return [
            'name' => ['required', 'string', 'max:255', $nameRule],
            'code' => ['required', 'string', 'max:50', 'alpha_dash', $codeRule],
            'status' => ['required', Rule::in([Faculty::STATUS_ACTIVE, Faculty::STATUS_INACTIVE])],
        ];
    }

    private function departmentRules(Institution $institution, ?Department $department = null): array
    {
        $facultyId = request()->input('faculty_id');
        $codeRule = Rule::unique('departments')->where('faculty_id', $facultyId);
        $nameRule = Rule::unique('departments')->where('institution_id', $institution->id);

        if ($department) {
            $codeRule->ignore($department);
            $nameRule->ignore($department);
        }

        return [
            'faculty_id' => ['required', 'exists:faculties,id'],
            'name' => ['required', 'string', 'max:255', $nameRule],
            'code' => ['required', 'string', 'max:50', 'alpha_dash', $codeRule],
            'status' => ['required', Rule::in([Department::STATUS_ACTIVE, Department::STATUS_INACTIVE])],
        ];
    }

    private function programmeRules(Institution $institution, ?Programme $programme = null): array
    {
        $departmentId = request()->input('department_id');
        $codeRule = Rule::unique('programmes')->where('department_id', $departmentId);
        $nameRule = Rule::unique('programmes')->where('institution_id', $institution->id);

        if ($programme) {
            $codeRule->ignore($programme);
            $nameRule->ignore($programme);
        }

        return [
            'faculty_id' => ['required', 'exists:faculties,id'],
            'department_id' => ['required', 'exists:departments,id'],
            'name' => ['required', 'string', 'max:255', $nameRule],
            'code' => ['required', 'string', 'max:50', 'alpha_dash', $codeRule],
            'duration' => ['required', 'integer', 'min:1', 'max:600'],
            'status' => ['required', Rule::in([Programme::STATUS_ACTIVE, Programme::STATUS_INACTIVE])],
        ];
    }

    private function courseRules(Institution $institution, ?Course $course = null): array
    {
        $departmentId = request()->input('department_id');
        $codeRule = Rule::unique('courses')->where('department_id', $departmentId);
        $nameRule = Rule::unique('courses')->where('department_id', $departmentId);

        if ($course) {
            $codeRule->ignore($course);
            $nameRule->ignore($course);
        }

        return [
            'faculty_id' => ['required', 'exists:faculties,id'],
            'department_id' => ['required', 'exists:departments,id'],
            'programme_id' => ['required', 'exists:programmes,id'],
            'name' => ['required', 'string', 'max:255', $nameRule],
            'code' => ['required', 'string', 'max:50', 'alpha_dash', $codeRule],
            'status' => ['required', Rule::in([Course::STATUS_ACTIVE, Course::STATUS_INACTIVE])],
        ];
    }

    private function lecturerRules(?User $lecturer = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($lecturer)],
            'password' => [$lecturer ? 'nullable' : 'required', 'string', 'min:8', 'max:255'],
            'course_ids' => ['required', 'array', 'min:1'],
            'course_ids.*' => ['required', 'integer', 'exists:courses,id', 'distinct'],
        ];
    }

    private function authorizeLecturerRecord(Institution $institution, Department $department, User $lecturer): void
    {
        abort_unless(
            $lecturer->role === User::ROLE_FACILITATOR
            && (int) $lecturer->institution_id === (int) $institution->id
            && (int) $lecturer->department_id === (int) $department->id,
            404
        );
    }

    private function syncLecturerAssignments(User $lecturer, Institution $institution, Department $department, array $courseIds): void
    {
        $courses = $department->courses()
            ->where('institution_id', $institution->id)
            ->whereIn('id', $courseIds)
            ->get(['id', 'faculty_id', 'department_id'])
            ->keyBy('id');

        abort_unless($courses->count() === count(array_unique($courseIds)), 422);

        DB::table('course_facilitator')->where('user_id', $lecturer->id)->delete();

        $now = now();
        $rows = $courses->map(fn (Course $course) => [
            'user_id' => $lecturer->id,
            'professional_school_id' => null,
            'institution_id' => $institution->id,
            'faculty_id' => $course->faculty_id,
            'department_id' => $course->department_id,
            'course_id' => $course->id,
            'module_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->values()->all();

        DB::table('course_facilitator')->insert($rows);
    }

    private function lecturerRow(User $lecturer): array
    {
        return [
            'id' => $lecturer->id,
            'name' => $lecturer->name,
            'email' => $lecturer->email,
            'course_ids' => $lecturer->assignedCourses->pluck('id')->unique()->values()->all(),
            'courses' => $lecturer->assignedCourses->unique('id')->map(fn (Course $course) => [
                'id' => $course->id,
                'name' => $course->name,
                'code' => $course->code,
            ])->values()->all(),
        ];
    }
}
