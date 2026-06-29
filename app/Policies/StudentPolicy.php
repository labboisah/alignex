<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;

class StudentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manageExams') || $user->hasPermission('viewReports');
    }

    public function view(User $user, Student $student): bool
    {
        return $this->viewAny($user) && $user->canAccessSecondarySchool($student->secondary_school_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('manageExams') && ($user->isSuperAdmin() || $user->secondary_school_id || $user->school_id);
    }

    public function update(User $user, Student $student): bool
    {
        return $this->create($user) && $user->canAccessSecondarySchool($student->secondary_school_id);
    }

    public function delete(User $user, Student $student): bool
    {
        return $this->update($user, $student);
    }
}
