<?php

namespace Tests\Support;

use App\Models\AdaptiveDecision;
use App\Models\AdaptivePoolItem;
use App\Models\Candidate;
use App\Models\CandidateExamAttempt;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\Organization;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\QuestionOption;
use App\Models\Subject;
use App\Models\User;
use App\Services\AdaptiveAttemptPreparationService;
use App\Services\AdaptiveLifecycleService;
use App\Services\AdaptivePreparationService;
use App\Services\AdaptiveRolloutService;

trait AdaptiveFixtures
{
    private function start(CandidateExamAttempt $attempt): array
    {
        return app(AdaptiveLifecycleService::class)->execute($attempt, 'start');
    }

    private function commit(CandidateExamAttempt $attempt, array $state, bool $correct, string $key): array
    {
        return app(AdaptiveLifecycleService::class)->execute($attempt, 'commit', $this->answerData($state, $correct, $key));
    }

    private function answerData(array $state, bool $correct, string $key): array
    {
        $decision = AdaptiveDecision::where('question_id', $state['current_item']['question_id'])->orderByDesc('id')->firstOrFail();
        $item = AdaptivePoolItem::findOrFail($decision->pool_item_id);
        $option = collect($item->content['options'])->firstWhere('is_correct', $correct);

        return ['question_id' => $state['current_item']['question_id'], 'selected_option_ids' => [$option['id']],
            'state_version' => $state['state_version'], 'idempotency_key' => $key];
    }

    private function fixture(bool $permit = true, int $areas = 1, array $settings = [], int $poolPerBand = 3, int $questionsPerArea = 3): array
    {
        $this->freezeTime();
        if ($permit) {
            $this->partialMock(AdaptiveRolloutService::class, fn ($mock) => $mock->shouldReceive('ensureDeliveryAllowed')->andReturnNull());
        }
        $organization = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organization->id]);
        $exam = Exam::factory()->create([
            'organization_id' => $organization->id, 'exam_owner_type' => 'organization', 'exam_owner_id' => $organization->id,
            'mode' => 'adaptive', 'exam_mode' => 'adaptive', 'exam_category' => 'assessment', 'delivery_mode' => 'online',
            'status' => 'draft', 'starts_at' => now()->subMinute(), 'ends_at' => now()->addHour(),
            'duration_minutes' => 30, 'total_marks' => 2 * $questionsPerArea * $areas, 'pass_mark' => $questionsPerArea * $areas,
            'settings' => ['progressive_remediation_enabled' => true, 'recovery_penalty_percent' => 10,
                'max_scored_levels' => 3, 'min_level_budget' => '0.01', 'mastery_threshold_percent' => 70,
                'min_evidence_per_area' => 1, 'level_duration_minutes' => 30,
                'progression_closes_at' => now()->addDays(2)->toDateTimeString(), 'level_cooldown_minutes' => 0,
                'allow_unscored_remediation' => false, ...$settings],
        ]);
        for ($area = 1; $area <= $areas; $area++) {
            $subject = Subject::factory()->create(['organization_id' => $organization->id]);
            $bank = QuestionBank::factory()->create(['organization_id' => $organization->id, 'subject_id' => $subject->id,
                'owner_type' => 'organization', 'owner_id' => $organization->id, 'status' => 'active']);
            ExamSubject::factory()->create(['exam_id' => $exam->id, 'subject_id' => $subject->id, 'question_bank_id' => $bank->id,
                'question_count' => $questionsPerArea, 'marks_per_question' => 2, 'total_marks' => 2 * $questionsPerArea, 'display_order' => $area,
                'selection_rules' => ['question_bank_ids' => [$bank->id]]]);
            foreach (['easy', 'medium', 'hard'] as $band) {
                for ($i = 0; $i < $poolPerBand; $i++) {
                    $question = Question::factory()->create(['question_bank_id' => $bank->id, 'subject_id' => $subject->id,
                        'topic_id' => null, 'difficulty' => $band, 'status' => 'approved', 'question_type' => 'single_choice', 'marks' => 2]);
                    foreach (['A', 'B'] as $label) {
                        QuestionOption::factory()->create(['question_id' => $question->id, 'label' => $label, 'is_correct' => $label === 'A']);
                    }
                }
            }
        }
        $candidate = Candidate::factory()->create(['organization_id' => $organization->id]);
        $exam->candidates()->attach($candidate->id);
        $snapshot = app(AdaptivePreparationService::class)->prepare($exam, $user->id);
        $this->assertTrue($snapshot->ready);
        $attempt = CandidateExamAttempt::factory()->create(['exam_id' => $exam->id, 'candidate_id' => $candidate->id,
            'status' => 'not_started', 'started_at' => null, 'attempt_number' => 1]);
        app(AdaptiveAttemptPreparationService::class)->bind($attempt, $snapshot);
        $exam->update(['status' => 'active']);

        return [$attempt, $snapshot];
    }
}
