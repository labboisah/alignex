<?php

namespace App\Policies;

use App\Models\AdaptiveProgression;
use App\Models\Exam;
use App\Models\User;
use App\Services\AdaptiveRolloutService;

class AdaptiveProgressionPolicy
{
    public function view(User $user, AdaptiveProgression $progression): bool
    {
        $exam = Exam::find($progression->exam_id);

        return $exam && ($user->isSuperAdmin() || $progression->owner_key === app(AdaptiveRolloutService::class)->ownerKey($exam))
            && app(ExamPolicy::class)->viewAdaptiveReport($user, $exam);
    }
}
