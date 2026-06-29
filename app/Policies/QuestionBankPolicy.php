<?php

namespace App\Policies;

use App\Models\QuestionBank;
use App\Models\User;
use App\Policies\Concerns\AuthorizesOrganizationAccess;

class QuestionBankPolicy
{
    use AuthorizesOrganizationAccess;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manageQuestionBank');
    }

    public function view(User $user, QuestionBank $questionBank): bool
    {
        return $this->viewAny($user) && $this->canAccessTenant($user, $questionBank);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, QuestionBank $questionBank): bool
    {
        return $this->view($user, $questionBank);
    }

    public function delete(User $user, QuestionBank $questionBank): bool
    {
        return $user->hasPermission('manageQuestionBank')
            && $this->canAccessTenant($user, $questionBank);
    }
}
