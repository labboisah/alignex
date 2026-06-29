<?php

namespace App\Policies;

use App\Models\Topic;
use App\Models\User;
use App\Policies\Concerns\AuthorizesOrganizationAccess;

class TopicPolicy
{
    use AuthorizesOrganizationAccess;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manageQuestionBank');
    }

    public function view(User $user, Topic $topic): bool
    {
        return $this->viewAny($user) && $this->canAccessTenant($user, $topic->loadMissing('subject'));
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Topic $topic): bool
    {
        return $this->view($user, $topic);
    }

    public function delete(User $user, Topic $topic): bool
    {
        return $this->view($user, $topic);
    }
}
