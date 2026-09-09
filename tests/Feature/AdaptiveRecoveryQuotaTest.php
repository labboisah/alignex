<?php

namespace Tests\Feature;

use App\Models\AdaptiveLevel;
use App\Models\AdaptiveLevelRun;
use App\Models\AdaptiveProgression;
use App\Models\CandidateExamAttempt;
use App\Services\AdaptiveLedgerService;
use App\Services\AdaptiveLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdaptiveRecoveryQuotaTest extends TestCase
{
    use RefreshDatabase, \Tests\Support\AdaptiveFixtures;

    private function complete(CandidateExamAttempt $attempt, array $state, int $correct): array
    {
        $run = AdaptiveLevelRun::where('level_id', AdaptiveLevel::where('attempt_id', $attempt->id)->value('id'))->firstOrFail();
        $count = array_sum(array_column($run->area_plan, 'question_count'));
        for ($i = 0; $i < $count; $i++) {
            $state = $this->commit($attempt, $state, $i < $correct, $attempt->id.'-answer-'.$i);
        }

        return $state;
    }

    private function next(CandidateExamAttempt $attempt, string $key): array
    {
        $state = app(AdaptiveLifecycleService::class)->execute($attempt, 'next-level', ['idempotency_key' => $key]);
        $next = CandidateExamAttempt::findOrFail($state['attempt']['id']);

        return [$next, $state];
    }

    public function test_zero_penalty_follows_28_21_11_6_questions(): void
    {
        [$attempt] = $this->fixture(settings: ['recovery_penalty_percent' => 0, 'max_scored_levels' => 4], poolPerBand: 38, questionsPerArea: 28);
        $state = $this->complete($attempt, $this->start($attempt), 7);
        $this->assertSame(21, $state['recovery']['next_question_count']);
        [$second, $state] = $this->next($attempt, 'second');
        $this->assertSame(21, (int) $second->total_questions);
        $state = $this->complete($second, $state, 10);
        $this->assertSame(11, $state['recovery']['next_question_count']);
        [$third, $state] = $this->next($second, 'third');
        $this->assertSame(11, (int) $third->total_questions);
        $state = $this->complete($third, $state, 5);
        $this->assertSame(6, $state['recovery']['next_question_count']);
        [$fourth] = $this->next($third, 'fourth');
        $this->assertSame(6, (int) $fourth->total_questions);
        app(AdaptiveLedgerService::class)->reconcile(AdaptiveProgression::firstOrFail());
    }

    public function test_percentage_only_reduces_marks_and_failure_counts_follow_28_21_11_6(): void
    {
        [$attempt] = $this->fixture(settings: ['max_scored_levels' => 4], poolPerBand: 38, questionsPerArea: 28);
        $state = $this->complete($attempt, $this->start($attempt), 7);
        $this->assertSame(21, $state['recovery']['next_question_count']);
        $this->assertSame('37.80', $state['recovery']['next_available_marks']);
        [$second, $state] = $this->next($attempt, 'second');
        $level = AdaptiveLevel::where('attempt_id', $second->id)->firstOrFail();
        $this->assertSame(21, (int) $second->total_questions);
        $this->assertSame(3780, (int) $level->available_units);
        $this->assertSame(420, (int) $level->penalty_units);
        [$same] = $this->next($attempt, 'second');
        $this->assertSame($second->id, $same->id);
        $this->assertSame(420, (int) AdaptiveProgression::firstOrFail()->penalty_units);
        $state = $this->complete($second, $state, 10);
        $this->assertSame(1800, (int) $level->fresh()->earned_units);
        $this->assertSame(11, $state['recovery']['next_question_count']);
        $this->assertSame('17.82', $state['recovery']['next_available_marks']);
        [$third, $state] = $this->next($second, 'third');
        $this->assertSame(11, (int) $third->total_questions);
        $state = $this->complete($third, $state, 5);
        $this->assertSame(6, $state['recovery']['next_question_count']);
        $this->assertSame('8.76', $state['recovery']['next_available_marks']);
        [$fourth] = $this->next($third, 'fourth');
        $this->assertSame(6, (int) $fourth->total_questions);
        app(AdaptiveLedgerService::class)->reconcile(AdaptiveProgression::firstOrFail());
    }

    public function test_high_penalty_does_not_reduce_incorrect_or_unanswered_question_count(): void
    {
        [$attempt] = $this->fixture(settings: ['recovery_penalty_percent' => 90]);
        $state = $this->commit($attempt, $this->start($attempt), true, 'one-correct');
        $state = app(AdaptiveLifecycleService::class)->execute($attempt, 'submit');
        $this->assertSame(2, $state['recovery']['next_question_count']);
        $this->assertSame('0.40', $state['recovery']['next_available_marks']);
        [$next] = $this->next($attempt, 'high-penalty');
        $this->assertSame(2, (int) $next->total_questions);
    }
}
