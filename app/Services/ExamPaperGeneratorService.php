<?php

namespace App\Services;

use App\Models\Candidate;
use App\Models\CandidateExamAttempt;
use App\Models\Exam;
use App\Models\Question;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ExamPaperGeneratorService
{
    private const USABLE_QUESTION_STATUSES = [
        Question::STATUS_DRAFT,
        Question::STATUS_REVIEW,
        Question::STATUS_APPROVED,
    ];

    /**
     * @return array<string, mixed>
     */
    public function preview(Exam $exam): array
    {
        $exam->loadMissing(['examSubjects.subject', 'candidates', 'attempts.papers']);

        $subjectSummaries = $exam->examSubjects
            ->sortBy('display_order')
            ->values()
            ->map(fn ($examSubject) => $this->subjectSummary($examSubject));

        return [
            'assigned_candidates' => $exam->candidates->count(),
            'generated_papers' => $exam->attempts->filter(fn ($attempt) => $attempt->papers->isNotEmpty())->count(),
            'required_questions' => $exam->examSubjects->sum('question_count'),
            'available_questions' => $subjectSummaries->sum('available_questions'),
            'subjects' => $subjectSummaries,
            'warnings' => $subjectSummaries
                ->filter(fn ($summary) => $summary['available_questions'] < $summary['required_questions'] || $summary['insufficient_difficulties'] !== [])
                ->values(),
            'shuffle_questions' => (bool) data_get($exam->settings ?? [], 'shuffle_questions', false),
            'shuffle_options' => (bool) data_get($exam->settings ?? [], 'shuffle_options', false),
            'can_generate' => $this->canGenerate($exam),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function generatedSummary(Exam $exam): array
    {
        return CandidateExamAttempt::query()
            ->where('exam_id', $exam->id)
            ->with(['candidate', 'papers'])
            ->latest()
            ->get()
            ->map(fn ($attempt) => [
                'attempt_id' => $attempt->id,
                'candidate_name' => trim($attempt->candidate?->first_name.' '.$attempt->candidate?->last_name),
                'registration_number' => $attempt->candidate?->candidate_number,
                'paper_generated' => $attempt->papers->isNotEmpty(),
                'questions_count' => $attempt->total_questions,
                'generated_at' => $attempt->papers->min('created_at')?->toISOString(),
                'status' => $attempt->status,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function generate(Exam $exam): array
    {
        if (! $this->canGenerate($exam)) {
            throw ValidationException::withMessages(['exam' => 'Papers cannot be regenerated after the exam or a candidate attempt has started.']);
        }

        $preview = $this->preview($exam);

        if (($preview['assigned_candidates'] ?? 0) < 1) {
            throw ValidationException::withMessages(['candidates' => 'Assign candidates before generating papers.']);
        }

        if (count($preview['warnings']) > 0) {
            throw ValidationException::withMessages(['questions' => 'There are not enough usable questions for the selected exam settings.']);
        }

        return DB::transaction(function () use ($exam): array {
            $exam->loadMissing(['examSubjects.subject', 'candidates']);
            $created = 0;
            $skipped = 0;

            foreach ($exam->candidates as $candidate) {
                $attempt = $this->attemptFor($exam, $candidate);

                if ($attempt->papers()->exists()) {
                    $skipped++;
                    continue;
                }

                $questions = $this->questionsForExam($exam);
                $questionOrder = 1;

                foreach ($questions as $question) {
                    $optionIds = $question->options
                        ->sortBy('display_order')
                        ->pluck('id')
                        ->values();

                    if ((bool) data_get($exam->settings ?? [], 'shuffle_options', false)) {
                        $optionIds = $optionIds->shuffle()->values();
                    }

                    $attempt->papers()->create([
                        'question_id' => $question->id,
                        'question_order' => $questionOrder++,
                        'option_order' => $optionIds->all(),
                    ]);
                }

                $attempt->update([
                    'total_questions' => $questions->count(),
                    'total_marks' => $questions->sum(fn ($question) => (float) $question->marks),
                ]);

                $created++;
            }

            return [
                'created' => $created,
                'skipped' => $skipped,
                'summary' => $this->generatedSummary($exam),
            ];
        });
    }

    private function canGenerate(Exam $exam): bool
    {
        if ($exam->starts_at && now()->greaterThanOrEqualTo($exam->starts_at)) {
            return false;
        }

        return ! $exam->attempts()
            ->whereNotNull('started_at')
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function subjectSummary($examSubject): array
    {
        $query = $this->questionQuery($examSubject);
        $available = (clone $query)->count();
        $statusCounts = $this->baseQuestionQuery($examSubject)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($count) => (int) $count)
            ->all();
        $difficultyDistribution = $examSubject->difficulty_distribution ?? [];
        $insufficientDifficulties = collect($difficultyDistribution)
            ->filter(fn ($count, $difficulty) => (clone $query)->where('difficulty', $difficulty)->count() < (int) $count)
            ->map(fn ($count, $difficulty) => [
                'difficulty' => $difficulty,
                'required' => (int) $count,
                'available' => (clone $query)->where('difficulty', $difficulty)->count(),
            ])
            ->values()
            ->all();

        return [
            'subject_id' => $examSubject->subject_id,
            'subject_name' => $examSubject->subject?->name,
            'required_questions' => $examSubject->question_count,
            'available_questions' => $available,
            'status_counts' => $statusCounts,
            'difficulty_distribution' => $difficultyDistribution,
            'insufficient_difficulties' => $insufficientDifficulties,
        ];
    }

    private function attemptFor(Exam $exam, Candidate $candidate): CandidateExamAttempt
    {
        return CandidateExamAttempt::query()->firstOrCreate(
            [
                'exam_id' => $exam->id,
                'candidate_id' => $candidate->id,
                'attempt_number' => 1,
            ],
            [
                'center_id' => $exam->center_id,
                'access_code_hash' => Hash::make(Str::random(32)),
                'status' => CandidateExamAttempt::STATUS_NOT_STARTED,
                'total_questions' => 0,
                'total_marks' => 0,
            ]
        );
    }

    /**
     * @return Collection<int, Question>
     */
    private function questionsForExam(Exam $exam): Collection
    {
        $questions = $exam->examSubjects
            ->sortBy('display_order')
            ->values()
            ->flatMap(fn ($examSubject) => $this->questionsForSubject($examSubject));

        return (bool) data_get($exam->settings ?? [], 'shuffle_questions', false)
            ? $questions->shuffle()->values()
            : $questions->values();
    }

    /**
     * @return Collection<int, Question>
     */
    private function questionsForSubject($examSubject): Collection
    {
        $difficultyDistribution = $examSubject->difficulty_distribution ?? [];
        $query = $this->questionQuery($examSubject)->with('options');

        if ($difficultyDistribution !== []) {
            return collect($difficultyDistribution)
                ->flatMap(fn ($count, $difficulty) => (clone $query)
                    ->where('difficulty', $difficulty)
                    ->inRandomOrder()
                    ->limit((int) $count)
                    ->get());
        }

        return $query
            ->inRandomOrder()
            ->limit((int) $examSubject->question_count)
            ->get();
    }

    private function questionQuery($examSubject)
    {
        return $this->baseQuestionQuery($examSubject)
            ->whereIn('status', self::USABLE_QUESTION_STATUSES);
    }

    private function baseQuestionQuery($examSubject)
    {
        $topicIds = data_get($examSubject->selection_rules ?? [], 'topic_ids', []);

        return Question::query()
            ->where('subject_id', $examSubject->subject_id)
            ->when($topicIds !== [], fn ($query) => $query->whereIn('topic_id', $topicIds));
    }
}
