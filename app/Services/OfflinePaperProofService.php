<?php

namespace App\Services;

use App\Models\CandidateExamAttempt;
use Illuminate\Support\Facades\Crypt;

class OfflinePaperProofService
{
    public function paper(CandidateExamAttempt $attempt): array
    {
        $attempt->loadMissing('papers.question.options');

        return $attempt->papers->sortBy('question_id')->map(fn ($paper) => [
            'question_id' => (string) $paper->question_id,
            'marks' => number_format($paper->scoringMarks(), 2, '.', ''),
            'option_ids' => $paper->question->options->pluck('id')->sort()->values()->all(),
            'correct_option_ids' => $paper->question->options->where('is_correct', true)->pluck('id')->sort()->values()->all(),
        ])->values()->all();
    }

    public function fingerprint(CandidateExamAttempt $attempt): string
    {
        $attempt->loadMissing('exam');

        return hash('sha256', json_encode([
            $this->paper($attempt),
            $attempt->papers->sortBy('question_id')->map(fn ($p) => [
                $p->question->question_type, (float) $p->question->negative_marks,
            ])->values()->all(),
            data_get($attempt->exam->settings, 'negative_marking', false),
            data_get($attempt->exam->settings, 'negative_mark_value'),
            (float) $attempt->exam->pass_mark,
        ], JSON_THROW_ON_ERROR));
    }

    public function issue(CandidateExamAttempt $attempt, string $activationId, string $packageId): string
    {
        return Crypt::encryptString(json_encode([
            'attempt_id' => $attempt->id,
            'activation_id' => $activationId,
            'package_id' => $packageId,
            'fingerprint' => $this->fingerprint($attempt),
        ], JSON_THROW_ON_ERROR));
    }
}
