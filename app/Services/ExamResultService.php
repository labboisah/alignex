<?php

namespace App\Services;

use App\Models\CandidateAnswer;
use App\Models\CandidateExamAttempt;
use App\Models\Certificate;
use App\Models\Exam;
use App\Models\Question;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ExamResultService
{
    public function calculate(CandidateExamAttempt $attempt, bool $force = false): CandidateExamAttempt
    {
        return DB::transaction(function () use ($attempt, $force): CandidateExamAttempt {
            $attempt = CandidateExamAttempt::query()
                ->whereKey($attempt->id)
                ->lockForUpdate()
                ->with(['exam', 'papers.question.options', 'answers.question.options', 'proctoringEvents'])
                ->firstOrFail();

            if (! $force && $attempt->result_hash) {
                return $attempt;
            }

            $submittedAt = $attempt->submitted_at ?? now();
            $score = 0.0;

            foreach ($attempt->answers as $answer) {
                $scoreAwarded = $this->scoreAnswer($attempt, $answer);
                $score += $scoreAwarded;
                $answer->update([
                    'score_awarded' => $scoreAwarded,
                    'scored_at' => $submittedAt,
                    'submitted_at' => $answer->submitted_at ?? $submittedAt,
                ]);
            }

            $totalMarks = (float) ($attempt->total_marks ?: $attempt->papers->sum(fn ($paper) => (float) ($paper->question?->marks ?? 0)));
            $percentage = $totalMarks > 0 ? round(($score / $totalMarks) * 100, 2) : 0;
            $passed = $score >= (float) ($attempt->exam?->pass_mark ?? 0);
            $durationUsed = $attempt->started_at ? $attempt->started_at->diffInSeconds($submittedAt) : null;
            $suspiciousCount = $attempt->proctoringEvents()
                ->whereIn('severity', ['warning', 'high', 'critical'])
                ->count();
            $certificateEligible = $attempt->exam?->exam_category === Exam::CATEGORY_CERTIFICATION && $passed;

            $attempt->update([
                'score' => $score,
                'total_marks' => $totalMarks,
                'percentage' => $percentage,
                'grade' => $this->grade($percentage),
                'result_status' => $passed ? 'passed' : 'failed',
                'duration_used_seconds' => $durationUsed,
                'suspicious_event_count' => $suspiciousCount,
                'certificate_eligible' => $certificateEligible,
                'result_hash' => hash('sha256', $attempt->id.'|'.$score.'|'.$totalMarks.'|'.$submittedAt->timestamp),
            ]);

            if ($certificateEligible && (bool) data_get($attempt->exam?->settings ?? [], 'certificate_auto_generate', false)) {
                $this->ensureCertificate($attempt->refresh());
            }

            return $attempt->refresh();
        });
    }

    public function scoreAnswer(CandidateExamAttempt $attempt, CandidateAnswer $answer): float
    {
        $question = $answer->question;

        if (! $question || ! in_array($question->question_type, [Question::TYPE_SINGLE_CHOICE, Question::TYPE_MULTIPLE_CHOICE, Question::TYPE_TRUE_FALSE], true)) {
            return 0.0;
        }

        $selectedIds = collect($answer->selected_option_ids ?? [])->sort()->values()->all();

        if ($selectedIds === []) {
            return 0.0;
        }

        $correctIds = $question->options
            ->where('is_correct', true)
            ->pluck('id')
            ->sort()
            ->values()
            ->all();

        if ($selectedIds === $correctIds) {
            return (float) $question->marks;
        }

        if (! (bool) data_get($attempt->exam?->settings ?? [], 'negative_marking', false)) {
            return 0.0;
        }

        $negativeMark = data_get($attempt->exam?->settings ?? [], 'negative_mark_value');
        $negativeMark = $negativeMark !== null ? (float) $negativeMark : (float) ($question->negative_marks ?? 0);

        return $negativeMark > 0 ? -1 * $negativeMark : 0.0;
    }

    private function ensureCertificate(CandidateExamAttempt $attempt): void
    {
        if (! $attempt->candidate_id || Certificate::query()->where('candidate_exam_attempt_id', $attempt->id)->exists()) {
            return;
        }

        $hash = Str::ulid()->toBase32();

        Certificate::query()->create([
            'exam_id' => $attempt->exam_id,
            'organization_id' => $attempt->exam?->organization_id,
            'professional_school_id' => $attempt->exam?->professional_school_id,
            'cbt_center_id' => $attempt->exam?->cbt_center_id,
            'candidate_id' => $attempt->candidate_id,
            'candidate_exam_attempt_id' => $attempt->id,
            'serial_number' => 'CERT-'.now()->format('Ymd').'-'.Str::upper(Str::random(8)),
            'verification_hash' => hash('sha256', $hash),
            'verification_code' => $hash,
            'status' => Certificate::STATUS_ISSUED,
            'issued_at' => now(),
            'metadata' => [
                'score' => $attempt->score,
                'total_marks' => $attempt->total_marks,
                'percentage' => $attempt->percentage,
            ],
        ]);
    }

    private function grade(float $percentage): string
    {
        return match (true) {
            $percentage >= 70 => 'A',
            $percentage >= 60 => 'B',
            $percentage >= 50 => 'C',
            $percentage >= 45 => 'D',
            $percentage >= 40 => 'E',
            default => 'F',
        };
    }
}
