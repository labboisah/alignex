<?php

namespace App\Services;

use App\Models\Exam;

class CandidateResultVisibilityService
{
    public function allows(Exam $exam): bool
    {
        return (bool) data_get($exam->settings ?? [], 'show_result_immediately', false)
            || in_array(data_get($exam->result_release_settings ?? [], 'release_mode'), ['public', 'automatic', 'released'], true);
    }
}
