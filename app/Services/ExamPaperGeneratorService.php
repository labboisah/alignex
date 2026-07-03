<?php

namespace App\Services;

use App\Models\Candidate;
use App\Models\CandidateExamAttempt;
use App\Models\Exam;
use App\Models\ExamParticipant;
use App\Models\Question;
use App\Models\Student;
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
        app(ExamParticipantAssignmentService::class)->syncFromExamSettings($exam);
        $exam->loadMissing(['examSubjects.subject', 'candidates', 'participants', 'attempts.papers']);

        $subjectSummaries = $exam->examSubjects
            ->sortBy('display_order')
            ->values()
            ->map(fn ($examSubject) => $this->subjectSummary($exam, $examSubject));

        return [
            'assigned_candidates' => $exam->participants->count() ?: $exam->candidates->count(),
            'assigned_participants' => $exam->participants->count(),
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
            ->with(['candidate', 'examParticipant', 'papers'])
            ->latest()
            ->get()
            ->map(fn ($attempt) => [
                'attempt_id' => $attempt->id,
                'candidate_name' => trim($attempt->candidate?->first_name.' '.$attempt->candidate?->last_name) ?: ($attempt->examParticipant?->participant_type.' '.$attempt->examParticipant?->participant_id),
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
            app(ExamParticipantAssignmentService::class)->syncFromExamSettings($exam);
            $exam->loadMissing(['examSubjects.subject', 'candidates', 'participants']);
            $created = 0;
            $skipped = 0;

            $participants = $exam->participants->isNotEmpty()
                ? $exam->participants
                : $exam->candidates->map(fn (Candidate $candidate) => ExamParticipant::query()->firstOrCreate(
                    ['exam_id' => $exam->id, 'participant_type' => ExamParticipant::TYPE_CANDIDATE, 'participant_id' => $candidate->id],
                    ['status' => ExamParticipant::STATUS_ASSIGNED]
                ));

            foreach ($participants as $participant) {
                $attempt = $this->attemptFor($exam, $participant);

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
                        'exam_participant_id' => $participant->id,
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
    private function subjectSummary(Exam $exam, $examSubject): array
    {
        $query = $this->questionQuery($exam, $examSubject);
        $available = (clone $query)->count();
        $statusCounts = $this->baseQuestionQuery($exam, $examSubject)
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

    private function attemptFor(Exam $exam, ExamParticipant $participant): CandidateExamAttempt
    {
        $candidateId = $participant->participant_type === ExamParticipant::TYPE_CANDIDATE
            ? $participant->participant_id
            : Student::query()->whereKey($participant->participant_id)->value('candidate_id');

        if (! $candidateId) {
            throw ValidationException::withMessages(['candidates' => 'One or more assigned students could not be linked to candidate records. Re-save the exam group assignment and try again.']);
        }

        return CandidateExamAttempt::query()->firstOrCreate(
            [
                'exam_id' => $exam->id,
                'candidate_id' => $candidateId,
                'exam_participant_id' => $participant->id,
                'attempt_number' => 1,
            ],
            [
                'participant_type' => $participant->participant_type,
                'participant_id' => $participant->participant_id,
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
            ->flatMap(fn ($examSubject) => $this->questionsForSubject($exam, $examSubject));

        return (bool) data_get($exam->settings ?? [], 'shuffle_questions', false)
            ? $questions->shuffle()->values()
            : $questions->values();
    }

    /**
     * @return Collection<int, Question>
     */
    private function questionsForSubject(Exam $exam, $examSubject): Collection
    {
        $difficultyDistribution = $examSubject->difficulty_distribution ?? [];
        $query = $this->questionQuery($exam, $examSubject)->with('options');

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

    private function questionQuery(Exam $exam, $examSubject)
    {
        return $this->baseQuestionQuery($exam, $examSubject)
            ->whereIn('status', self::USABLE_QUESTION_STATUSES);
    }

    private function baseQuestionQuery(Exam $exam, $examSubject)
    {
        $topicIds = $exam->effectiveOwnerType() === Exam::OWNER_SECONDARY_SCHOOL
            ? []
            : data_get($examSubject->selection_rules ?? [], 'topic_ids', []);
        $bankIds = collect(data_get($examSubject->selection_rules ?? [], 'question_bank_ids', []))
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();

        if ($bankIds === []) {
            $bankIds = [$examSubject->question_bank_id ?? $exam->question_bank_id];
        }

        return Question::query()
            ->whereIn('question_bank_id', array_filter($bankIds))
            ->where('subject_id', $examSubject->subject_id)
            ->when($topicIds !== [], fn ($query) => $query->whereIn('topic_id', $topicIds));
    }
}
