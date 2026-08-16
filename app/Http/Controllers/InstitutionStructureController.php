<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Institution;
use App\Models\Programme;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('faculties')->where('institution_id', $institution->id)],
            'dean_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('faculties')->where('institution_id', $institution->id)],
            'phone' => ['nullable', 'string', 'max:50'],
            'status' => ['required', Rule::in([Faculty::STATUS_ACTIVE, Faculty::STATUS_INACTIVE])],
        ]);

        $institution->faculties()->create($data);

        return back()->with('success', 'Faculty created.');
    }

    public function departments(Request $request, Institution $institution): Response
    {
        $this->authorizeInstitution($request->user(), $institution);

        return Inertia::render('Institutions/Departments', [
            'institution' => $institution,
            'faculties' => $institution->faculties()->orderBy('name')->get(['id', 'name', 'code']),
            'departments' => $institution->departments()->with('faculty:id,name')->orderBy('name')->get(),
        ]);
    }

    public function storeDepartment(Request $request, Institution $institution): RedirectResponse
    {
        $this->authorizeInstitution($request->user(), $institution);

        $data = $request->validate([
            'faculty_id' => ['required', 'exists:faculties,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('departments')->where('institution_id', $institution->id)],
            'head_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('departments')->where('institution_id', $institution->id)],
            'phone' => ['nullable', 'string', 'max:50'],
            'status' => ['required', Rule::in([Department::STATUS_ACTIVE, Department::STATUS_INACTIVE])],
        ]);

        abort_unless($institution->faculties()->whereKey($data['faculty_id'])->exists(), 422);

        $institution->departments()->create($data);

        return back()->with('success', 'Department created.');
    }

    public function programmes(Request $request, Institution $institution): Response
    {
        $this->authorizeInstitution($request->user(), $institution);

        return Inertia::render('Institutions/Programmes', [
            'institution' => $institution,
            'faculties' => $institution->faculties()->orderBy('name')->get(['id', 'name', 'code']),
            'departments' => $institution->departments()->with('faculty:id,name')->orderBy('name')->get(['id', 'faculty_id', 'name', 'code']),
            'programmes' => $institution->programmes()->with(['faculty:id,name', 'department:id,name'])->orderBy('name')->get(),
        ]);
    }

    public function storeProgramme(Request $request, Institution $institution): RedirectResponse
    {
        $this->authorizeInstitution($request->user(), $institution);

        $data = $request->validate([
            'faculty_id' => ['nullable', 'exists:faculties,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('programmes')->where('institution_id', $institution->id)],
            'duration' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in([Programme::STATUS_ACTIVE, Programme::STATUS_INACTIVE])],
        ]);

        if (! empty($data['faculty_id'])) {
            abort_unless($institution->faculties()->whereKey($data['faculty_id'])->exists(), 422);
        }
        if (! empty($data['department_id'])) {
            abort_unless($institution->departments()->whereKey($data['department_id'])->exists(), 422);
        }

        $institution->programmes()->create($data);

        return back()->with('success', 'Programme created.');
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

        $data = $request->validate([
            'faculty_id' => ['nullable', 'exists:faculties,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'programme_id' => ['nullable', 'exists:programmes,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('courses')->where('institution_id', $institution->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in([Course::STATUS_ACTIVE, Course::STATUS_INACTIVE])],
        ]);

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

    private function authorizeInstitution($user, Institution $institution): void
    {
        if ($user->isSuperAdmin()) {
            return;
        }

        abort_unless($user->isInstitutionAdmin() && (int) $user->institution_id === (int) $institution->id, 403);
    }
}
