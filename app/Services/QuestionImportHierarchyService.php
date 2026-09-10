<?php

namespace App\Services;

use App\Models\Course;
use App\Models\ProfessionalModule;
use App\Models\User;

class QuestionImportHierarchyService
{
    public function options(User $user, ?int $professionalSchoolId = null): array
    {
        $context = $professionalSchoolId
            ? ['type' => 'professional_school', 'id' => $professionalSchoolId]
            : app(CurrentContextService::class)->current($user);
        $courses = Course::query()->where('status', Course::STATUS_ACTIVE);
        if (in_array($context['type'] ?? null, ['professional_school', 'institution'], true)) {
            $courses->where($context['type'].'_id', $context['id']);
        } elseif (! $user->isSuperAdmin() || $context !== null) {
            $courses->whereRaw('1 = 0');
        }
        if ($user->isFacilitator()) {
            $courses->where(fn ($query) => $query
                ->whereIn('id', $user->assignedCourses()->select('courses.id'))
                ->orWhereIn('id', $user->assignedModules()->select('modules.course_id')));
        }
        $courses = $courses->orderBy('name')->get(['id', 'name']);
        $modules = ProfessionalModule::query()->where('status', 'active')->whereIn('course_id', $courses->pluck('id'));
        if ($user->isFacilitator()) {
            $modules->where(fn ($query) => $query
                ->whereIn('course_id', $user->assignedCourses()->select('courses.id'))
                ->orWhereIn('id', $user->assignedModules()->select('modules.id')));
        }

        return ['importCourses' => $courses, 'importModules' => $modules->orderBy('name')->get(['id', 'course_id', 'name'])];
    }
}
