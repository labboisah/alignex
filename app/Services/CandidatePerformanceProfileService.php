<?php

namespace App\Services;

use App\Models\CandidateExamAttempt;
use App\Models\CandidatePerformanceProfile;
use Illuminate\Support\Collection;

class CandidatePerformanceProfileService
{
    public function generate(CandidateExamAttempt $attempt): void
    {
        $attempt->loadMissing(['papers.question.subject', 'papers.question.topic', 'answers.question']);
        $answers = $attempt->answers->keyBy('question_id');

        CandidatePerformanceProfile::query()
            ->where('candidate_id', $attempt->candidate_id)
            ->where('exam_id', $attempt->exam_id)
            ->delete();

        $attempt->papers
            ->map(function ($paper) use ($answers): array {
                $question = $paper->question;
                $answer = $answers->get($paper->question_id);
                $marks = max((float) ($question?->marks ?? 0), 0);
                $score = (float) ($answer?->score_awarded ?? 0);

                return [
                    'subject_id' => $question?->subject_id,
                    'topic_id' => $question?->topic_id,
                    'difficulty' => $question?->difficulty ?? 'medium',
                    'questions' => 1,
                    'correct' => $marks > 0 && $score >= $marks ? 1 : 0,
                    'score' => $score,
                    'marks' => $marks,
                ];
            })
            ->groupBy(fn (array $row) => implode('|', [
                $row['subject_id'] ?? 'none',
                $row['topic_id'] ?? 'none',
                $row['difficulty'] ?? 'none',
            ]))
            ->each(function (Collection $rows) use ($attempt): void {
                $first = $rows->first();
                $totalMarks = max((float) $rows->sum('marks'), 0);
                $score = max(0, (float) $rows->sum('score'));
                $percentage = $totalMarks > 0 ? min(100, round(($score / $totalMarks) * 100, 2)) : 0;

                CandidatePerformanceProfile::query()->create([
                    'candidate_id' => $attempt->candidate_id,
                    'exam_id' => $attempt->exam_id,
                    'subject_id' => $first['subject_id'],
                    'topic_id' => $first['topic_id'],
                    'difficulty' => $first['difficulty'],
                    'total_questions' => $rows->sum('questions'),
                    'correct_answers' => $rows->sum('correct'),
                    'score_percentage' => $percentage,
                    'mastery_level' => $this->masteryLevel($percentage),
                ]);
            });
    }

    public function masteryLevel(float $percentage): string
    {
        return match (true) {
            $percentage >= 70 => CandidatePerformanceProfile::MASTERY_STRONG,
            $percentage >= 50 => CandidatePerformanceProfile::MASTERY_AVERAGE,
            default => CandidatePerformanceProfile::MASTERY_WEAK,
        };
    }
}
