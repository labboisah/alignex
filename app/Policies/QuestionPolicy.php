<?php

namespace App\Policies;

use App\Models\Question;
use App\Models\User;
use App\Policies\Concerns\AuthorizesOrganizationAccess;

class QuestionPolicy
{
    use AuthorizesOrganizationAccess;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manageQuestionBank');
    }

    public function view(User $user, Question $question): bool
    {
        if ($user->isTeacher()) {
            return $question->subject_id !== null
                && $user->assignedSubjects()->whereKey($question->subject_id)->exists();
        }

        return $this->viewAny($user) && $this->canAccessTenant($user, $question->loadMissing('questionBank'));
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Question $question): bool
    {
        return $this->view($user, $question);
    }

    public function delete(User $user, Question $question): bool
    {
        return $this->view($user, $question);
    }
}
