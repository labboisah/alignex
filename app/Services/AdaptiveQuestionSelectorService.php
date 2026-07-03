<?php

namespace App\Services;

use App\Models\CandidateExamAttempt;
use App\Models\Question;

class AdaptiveQuestionSelectorService
{
    public function startingDifficulty(): string
    {
        return 'medium';
    }

    public function nextDifficulty(?bool $wasCorrect, string $currentDifficulty = 'medium'): string
    {
        if ($wasCorrect === true) {
            return match ($currentDifficulty) {
                'easy' => 'medium',
                'medium' => 'hard',
                default => 'hard',
            };
        }

        if ($wasCorrect === false) {
            return match ($currentDifficulty) {
                'hard' => 'medium',
                'medium' => 'easy',
                default => 'easy',
            };
        }

        return $this->startingDifficulty();
    }

    public function nextQuestion(CandidateExamAttempt $attempt, ?bool $wasCorrect = null): ?Question
    {
        $lastQuestion = $attempt->papers()->with('question')->latest('question_order')->first()?->question;
        $difficulty = $this->nextDifficulty($wasCorrect, $lastQuestion?->difficulty ?? $this->startingDifficulty());
        $usedQuestionIds = $attempt->papers()->pluck('question_id');

        return Question::query()
            ->where('question_bank_id', $attempt->exam?->question_bank_id)
            ->where('difficulty', $difficulty)
            ->whereNotIn('id', $usedQuestionIds)
            ->whereIn('status', [Question::STATUS_DRAFT, Question::STATUS_REVIEW, Question::STATUS_APPROVED])
            ->inRandomOrder()
            ->first();
    }
}
