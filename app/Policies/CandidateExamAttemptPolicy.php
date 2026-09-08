<?php

namespace App\Policies;

use App\Models\Candidate;
use App\Models\CandidateExamAttempt;

class CandidateExamAttemptPolicy
{
    // Candidate identity comes from the server-decrypted exam token, never a request candidate_id.
    public function participate(Candidate $candidate, CandidateExamAttempt $attempt): bool
    {
        return $candidate->id === $attempt->candidate_id
            && $attempt->exam->candidates()->where('candidates.id', $candidate->id)->exists();
    }
}
