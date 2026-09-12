<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamParticipant;
use Illuminate\Validation\ValidationException;

class ExamReadinessService
{
    public function inspect(Exam $exam): array
    {
        $exam->load(['examSubjects.subject', 'participants', 'candidates', 'attempts.papers.question']);
        $edit = "/exams/{$exam->id}/edit";
        $adaptive = $exam->effectiveMode() === Exam::MODE_ADAPTIVE;
        $rows = $exam->examSubjects;
        $count = (int) $rows->sum('question_count');
        $total = round($rows->sum(fn ($row) => $row->question_count * (float) $row->marks_per_question), 2);
        $checks = [];
        $add = function (string $id, string $label, bool $ready, string $message, string $href) use (&$checks): void {
            $checks[] = compact('id', 'label', 'ready', 'message', 'href');
        };
        $add('marks', 'Question counts and marks', $count > 0 && $rows->every(fn ($row) => $row->question_count > 0 && $row->marks_per_question > 0)
            && $total > 0 && abs($total - (float) $exam->total_marks) < 0.005 && $exam->pass_mark >= 0 && $exam->pass_mark <= $total,
            "{$count} questions; {$total} total marks. Pass mark must be between zero and the total.", $edit);
        $timing = $exam->starts_at && $exam->ends_at && $exam->ends_at->isFuture() && $exam->starts_at->lt($exam->ends_at)
            && $exam->duration_minutes > 0 && $exam->starts_at->diffInSeconds($exam->ends_at) >= $exam->duration_minutes * 60;
        $add('timing', 'Exam window and duration', (bool) $timing, 'Set an unexpired exam window long enough for the full exam duration.', $edit);

        $participants = $exam->participants->where('status', '!=', ExamParticipant::STATUS_CANCELLED);
        $assigned = $exam->participants->isNotEmpty() ? $participants->count() : $exam->candidates->count();
        $add('participants', 'Candidate assignments', $assigned > 0, "{$assigned} participants assigned. Save or refresh the selected group before publishing.", $edit);

        if ($adaptive) {
            $inspection = app(AdaptivePreparationService::class)->inspect($exam);
            $ready = (bool) $inspection['readiness']['ready'];
            $add('questions', 'Adaptive question readiness', $ready,
                $ready ? 'The adaptive question pool meets the configured requirements.' : implode(' ', $inspection['readiness']['warnings']),
                "/exams/{$exam->id}/adaptive");
        } else {
            $summaries = app(ExamPaperGeneratorService::class)->availability($exam);
            $shortages = $summaries->filter(fn ($row) => $row['available_questions'] < $row['required_questions'] || $row['insufficient_difficulties'] !== []);
            $add('questions', 'Question banks and difficulty', $count > 0 && $shortages->isEmpty(),
                $shortages->isEmpty() ? 'Enough usable questions match the selected banks and difficulty counts.' : 'Not enough matching questions for: '.$shortages->pluck('subject_name')->implode(', ').'.',
                "/exams/{$exam->id}/papers");
            $complete = function ($attempt) use ($exam, $count, $total): bool {
                return $attempt && $count > 0 && $attempt->papers->count() === $count
                    && abs($attempt->papers->sum(fn ($paper) => $paper->scoringMarks()) - $total) < 0.005
                    && abs((float) $attempt->total_marks - $total) < 0.005
                    && app(ExamPaperGeneratorService::class)->matchesConfiguration($exam, $attempt->papers);
            };
            $prepared = $exam->participants->isNotEmpty()
                ? $participants->filter(fn ($participant) => $exam->attempts->contains(fn ($attempt) => (string) $attempt->exam_participant_id === (string) $participant->id && $complete($attempt)))->count()
                : $exam->candidates->filter(fn ($candidate) => $exam->attempts->contains(fn ($attempt) => (string) $attempt->candidate_id === (string) $candidate->id && $complete($attempt)))->count();
            $add('papers', 'Generated candidate papers', $assigned > 0 && $prepared === $assigned,
                "{$prepared} of {$assigned} participants have complete papers with matching marks.", "/exams/{$exam->id}/papers");
        }
        if (in_array($exam->delivery_mode, ['offline', 'hybrid'], true)) {
            $add('offline', 'Offline delivery', ! $adaptive && ! collect($checks)->contains(fn ($check) => ! $check['ready']),
                $adaptive ? 'Standard offline apps require a traditional exam. Use online delivery for this adaptive exam.'
                    : 'Generate complete papers before downloading them to the offline server. This check does not verify downloads on a center device.', $edit);
        }
        return [
            'ready' => ! collect($checks)->contains(fn ($check) => ! $check['ready']),
            'checks' => $checks,
            'device_requirements' => [
                'fullscreen' => (bool) data_get($exam->settings, 'require_fullscreen', false),
                'camera' => (bool) data_get($exam->settings, 'require_webcam', false),
            ],
        ];
    }

    public function ensureReady(Exam $exam): void
    {
        $readiness = $this->inspect($exam);
        if (! $readiness['ready']) {
            throw ValidationException::withMessages(['status' => 'Save as Draft and complete the readiness checklist: '.collect($readiness['checks'])->where('ready', false)->pluck('label')->implode(', ').'.']);
        }
    }
}
