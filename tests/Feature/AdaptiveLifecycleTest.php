<?php

namespace Tests\Feature;

use App\Models\AdaptiveAreaBalance;
use App\Models\AdaptiveAttemptState;
use App\Models\AdaptiveDecision;
use App\Models\AdaptiveLevel;
use App\Models\AdaptiveLevelRun;
use App\Models\AdaptiveMarkEntry;
use App\Models\AdaptiveProgression;
use App\Models\AdaptiveResponse;
use App\Models\Candidate;
use App\Models\CandidateExamAttempt;
use App\Models\Question;
use App\Services\AdaptiveAttemptPreparationService;
use App\Services\AdaptiveLedgerService;
use App\Services\AdaptiveLifecycleService;
use App\Services\CandidateExamSessionService;
use App\Services\ExamResultService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdaptiveLifecycleTest extends TestCase
{
    use RefreshDatabase, \Tests\Support\AdaptiveFixtures;

    public function test_start_is_contained_until_rollout_is_approved(): void
    {
        [$attempt] = $this->fixture(false);
        $this->expectException(ValidationException::class);
        app(AdaptiveLifecycleService::class)->execute($attempt, 'start');
    }

    public function test_initial_item_is_private_stable_on_resume_and_moves_difficulty_after_commit(): void
    {
        [$attempt] = $this->fixture();
        $first = $this->start($attempt);
        $this->assertNotNull($first['current_item']);
        $this->assertArrayNotHasKey('questions', $first);
        $this->assertArrayNotHasKey('difficulty', $first['current_item']);
        $this->assertArrayNotHasKey('is_correct', $first['current_item']['options'][0]);
        $this->assertNull($first['result']);
        $this->assertSame($first, app(AdaptiveLifecycleService::class)->execute($attempt, 'read'));
        $this->assertSame('medium', AdaptiveDecision::firstOrFail()->decision['difficulty']);
        $second = $this->commit($attempt, $first, true, 'one');
        $this->assertSame('hard', AdaptiveDecision::orderByDesc('id')->firstOrFail()->decision['difficulty']);
        $this->assertNotSame($first['current_item']['question_id'], $second['current_item']['question_id']);
        $this->assertDatabaseCount('candidate_papers', 0);
    }

    public function test_wrong_response_moves_to_easy_and_snapshot_edits_do_not_change_scoring(): void
    {
        [$attempt] = $this->fixture();
        $first = $this->start($attempt);
        $question = Question::findOrFail($first['current_item']['question_id']);
        $question->options()->update(['is_correct' => false]);
        $next = $this->commit($attempt, $first, false, 'wrong');
        $this->assertSame('easy', AdaptiveDecision::orderByDesc('id')->firstOrFail()->decision['difficulty']);
        $this->assertSame(0, (int) AdaptiveProgression::firstOrFail()->earned_units);
        $this->commit($attempt, $next, true, 'right');
        $this->assertSame(200, (int) AdaptiveProgression::firstOrFail()->earned_units);
    }

    public function test_draft_is_resumable_and_commit_retry_neither_advances_nor_earns_twice(): void
    {
        [$attempt] = $this->fixture();
        $first = $this->start($attempt);
        $data = $this->answerData($first, true, 'commit-once');
        $draft = app(AdaptiveLifecycleService::class)->execute($attempt, 'draft', $data);
        $this->assertSame($data['selected_option_ids'], $draft['selected_option_ids']);
        $this->assertSame(0, (int) AdaptiveProgression::firstOrFail()->earned_units);
        $data['state_version'] = $draft['state_version'];
        $next = app(AdaptiveLifecycleService::class)->execute($attempt, 'commit', $data);
        $retry = app(AdaptiveLifecycleService::class)->execute($attempt, 'commit', $data);
        $this->assertSame($next, $retry);
        $this->assertDatabaseCount('adaptive_decisions', 2);
        $this->assertSame(1, AdaptiveMarkEntry::where('kind', 'earned')->count());
        $data['selected_option_ids'] = [];
        $this->expectException(ValidationException::class);
        app(AdaptiveLifecycleService::class)->execute($attempt, 'commit', $data);
    }

    public function test_stale_version_and_unissued_questions_cannot_commit(): void
    {
        [$attempt] = $this->fixture();
        $first = $this->start($attempt);
        $data = $this->answerData($first, true, 'stale');
        $data['state_version'] = 99;
        try {
            app(AdaptiveLifecycleService::class)->execute($attempt, 'commit', $data);
            $this->fail('Stale state must fail');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('state_version', $e->errors());
        }
        $data['state_version'] = $first['state_version'];
        $data['question_id'] = 'unissued';
        $this->expectException(ValidationException::class);
        app(AdaptiveLifecycleService::class)->execute($attempt, 'commit', $data);
    }

    public function test_progressive_levels_reconcile_and_do_not_repeat_questions_or_charge_twice(): void
    {
        [$attempt] = $this->fixture();
        $state = $this->start($attempt);
        for ($i = 0; $i < 3; $i++) {
            $state = $this->commit($attempt, $state, $i === 0, 'l1-'.$i);
        }
        $this->assertTrue($state['submitted']);
        $this->assertSame(400, (int) AdaptiveProgression::firstOrFail()->recoverable_units);
        $next = app(AdaptiveLifecycleService::class)->execute($attempt, 'next-level', ['idempotency_key' => 'level-two']);
        $repeat = app(AdaptiveLifecycleService::class)->execute($attempt, 'next-level', ['idempotency_key' => 'level-two']);
        $this->assertSame($next['attempt']['id'], $repeat['attempt']['id']);
        $this->assertSame(40, (int) AdaptiveProgression::firstOrFail()->penalty_units);
        $second = CandidateExamAttempt::findOrFail($next['attempt']['id']);
        for ($i = 0; $i < 3; $i++) {
            $next = $this->commit($second, $next, $i === 0, 'l2-'.$i);
        }
        $thirdState = app(AdaptiveLifecycleService::class)->execute($second, 'next-level', ['idempotency_key' => 'level-three']);
        $third = CandidateExamAttempt::findOrFail($thirdState['attempt']['id']);
        for ($i = 0; $i < 3; $i++) {
            $thirdState = $this->commit($third, $thirdState, $i === 0, 'l3-'.$i);
        }
        $p = AdaptiveProgression::firstOrFail();
        $this->assertSame(392, (int) $p->earned_units);
        $this->assertSame(64, (int) $p->penalty_units);
        $this->assertSame(144, (int) $p->closed_units);
        $this->assertSame(0, (int) $p->recoverable_units);
        $this->assertSame('level_cap', $p->stop_reason);
        $this->assertSame(9, AdaptiveDecision::distinct()->count('question_id'));
        app(AdaptiveLedgerService::class)->reconcile($p);
    }

    public function test_only_weak_areas_reappear_and_mastered_area_cannot_be_spent(): void
    {
        [$attempt] = $this->fixture(areas: 2);
        $state = $this->start($attempt);
        for ($i = 0; $i < 6; $i++) {
            $state = $this->commit($attempt, $state, $i < 3, 'answer-'.$i);
        }
        $next = app(AdaptiveLifecycleService::class)->execute($attempt, 'next-level', ['idempotency_key' => 'weak-only']);
        $run = AdaptiveLevelRun::orderByDesc('id')->firstOrFail();
        $this->assertSame(['row:2'], array_keys($run->area_plan));
        $this->assertSame(600, (int) AdaptiveAreaBalance::where('area_key', 'row:1')->firstOrFail()->earned_units);
        $this->assertSame(60, (int) AdaptiveAreaBalance::where('area_key', 'row:2')->firstOrFail()->penalty_units);
    }

    public function test_expiry_finalizes_committed_answers_and_ignores_late_answer(): void
    {
        [$attempt] = $this->fixture();
        $state = $this->start($attempt);
        $state = $this->commit($attempt, $state, true, 'before');
        $this->travel(31)->minutes();
        $expired = $this->commit($attempt, $state, true, 'late');
        $this->assertTrue($expired['submitted']);
        $this->assertSame('auto_submitted', $expired['attempt']['status']);
        $this->assertSame(1, AdaptiveResponse::whereNotNull('committed_at')->count());
        $this->assertSame(200, (int) AdaptiveProgression::firstOrFail()->earned_units);
    }

    public function test_submit_does_not_commit_a_draft_and_late_writes_do_not_change_results(): void
    {
        [$attempt] = $this->fixture();
        $state = $this->start($attempt);
        app(AdaptiveLifecycleService::class)->execute($attempt, 'draft', $this->answerData($state, true, 'draft'));
        app(AdaptiveLifecycleService::class)->execute($attempt, 'submit');
        $this->commit($attempt, $state, true, 'late');
        $this->assertSame(0, AdaptiveResponse::whereNotNull('committed_at')->count());
        $this->assertSame(0, (int) AdaptiveProgression::firstOrFail()->earned_units);
    }

    public function test_disqualification_closes_remaining_budget_and_disallows_recovery(): void
    {
        [$attempt] = $this->fixture();
        $state = $this->start($attempt);
        $attempt->update(['status' => 'disqualified', 'disqualified_at' => now()]);
        $closed = $this->commit($attempt, $state, true, 'late');
        $this->assertSame('disqualified', $closed['attempt']['status']);
        $this->assertSame(600, (int) AdaptiveProgression::firstOrFail()->closed_units);
        $this->assertNull($closed['result']);
    }

    public function test_api_validates_commit_and_never_returns_future_items_or_unreleased_budgets(): void
    {
        [$attempt] = $this->fixture();
        $state = $this->start($attempt);
        $headers = ['Authorization' => 'Bearer '.app(CandidateExamSessionService::class)->makeToken($attempt)];
        $this->postJson('/api/candidate/answer', ['question_id' => $state['current_item']['question_id']], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors(['commit', 'state_version']);
        $this->postJson('/api/candidate/answer', [...$this->answerData($state, true, 'api'), 'commit' => true], $headers)
            ->assertOk()->assertJsonMissingPath('questions')->assertJsonMissingPath('score')->assertJsonPath('result', null)
            ->assertJsonMissingPath('current_item.options.0.is_correct');
        $this->getJson('/api/candidate/exam', $headers)->assertOk()->assertJsonPath('committed_questions', 1);
    }

    public function test_result_release_requires_closed_aggregate_and_practice_never_adds_credit(): void
    {
        [$attempt] = $this->fixture(settings: ['max_scored_levels' => 1, 'allow_unscored_remediation' => true]);
        $attempt->exam->update(['result_release_settings' => ['release_mode' => 'released']]);
        $state = $this->start($attempt);
        $this->assertNull($state['result']);
        for ($i = 0; $i < 3; $i++) {
            $state = $this->commit($attempt, $state, $i === 0, 'final-'.$i);
        }
        $this->assertSame('2.00', $state['result']['score']);
        $practice = app(AdaptiveLifecycleService::class)->execute($attempt, 'next-level', ['idempotency_key' => 'practice', 'practice' => true]);
        $practiceAttempt = CandidateExamAttempt::findOrFail($practice['attempt']['id']);
        $this->assertTrue($practice['is_practice']);
        for ($i = 0; $i < 3; $i++) {
            $practice = $this->commit($practiceAttempt, $practice, true, 'practice-'.$i);
        }
        $this->assertSame(200, (int) AdaptiveProgression::firstOrFail()->earned_units);
        $this->assertSame(0, (int) AdaptiveProgression::firstOrFail()->penalty_units);
        $this->assertSame('2.00', $practice['result']['score']);
    }

    public function test_minimum_budget_and_hundred_percent_close_without_charging_a_failed_start(): void
    {
        [$attempt] = $this->fixture(settings: ['recovery_penalty_percent' => 100]);
        $this->start($attempt);
        app(AdaptiveLifecycleService::class)->execute($attempt, 'submit');
        $closed = app(AdaptiveLifecycleService::class)->execute($attempt, 'next-level', ['idempotency_key' => 'no-budget']);
        $this->assertTrue($closed['submitted']);
        $this->assertDatabaseCount('adaptive_levels', 1);
        $this->assertSame(0, (int) AdaptiveProgression::firstOrFail()->penalty_units);
        $this->assertSame(600, (int) AdaptiveProgression::firstOrFail()->closed_units);
    }

    public function test_insufficient_fresh_pool_rolls_back_next_level_and_penalty(): void
    {
        [$attempt] = $this->fixture(settings: ['max_scored_levels' => 1, 'allow_unscored_remediation' => true], poolPerBand: 1);
        $state = $this->start($attempt);
        for ($i = 0; $i < 3; $i++) {
            $state = $this->commit($attempt, $state, false, 'use-'.$i);
        }
        try {
            app(AdaptiveLifecycleService::class)->execute($attempt, 'next-level', ['idempotency_key' => 'empty', 'practice' => true]);
            $this->fail('Empty pool must block');
        } catch (ValidationException) {
            $this->assertDatabaseCount('adaptive_levels', 1);
            $this->assertDatabaseCount('candidate_exam_attempts', 1);
            $this->assertSame(0, (int) AdaptiveProgression::firstOrFail()->penalty_units);
        }
    }

    public function test_exact_percentage_example_and_half_up_rounding(): void
    {
        $ledger = app(AdaptiveLedgerService::class);
        $this->assertSame(600, $ledger->penalty(6000, 1000));
        $this->assertSame(340, $ledger->penalty(3400, 1000));
        $this->assertSame(206, $ledger->penalty(2060, 1000));
        $this->assertSame(1, $ledger->penalty(1, 5000));
        $this->assertSame(0, $ledger->penalty(100, 0));
        $this->assertSame(['a' => 1, 'b' => 0], $ledger->allocate(1, ['b' => 1, 'a' => 1]));
    }

    public function test_two_candidates_receive_different_difficulty_paths_on_the_same_exam(): void
    {
        [$first, $snapshot] = $this->fixture();
        $candidate = Candidate::factory()->create(['organization_id' => $first->exam->organization_id]);
        $first->exam->candidates()->attach($candidate->id);
        $second = CandidateExamAttempt::factory()->create(['exam_id' => $first->exam_id, 'candidate_id' => $candidate->id,
            'status' => 'not_started', 'started_at' => null, 'attempt_number' => 1]);
        app(AdaptiveAttemptPreparationService::class)->bind($second, $snapshot);
        $a = $this->commit($first, $this->start($first), true, 'a');
        $b = $this->commit($second, $this->start($second), false, 'b');
        $aDecision = AdaptiveDecision::where('question_id', $a['current_item']['question_id'])->orderByDesc('id')->firstOrFail();
        $bDecision = AdaptiveDecision::where('question_id', $b['current_item']['question_id'])->orderByDesc('id')->firstOrFail();
        $this->assertSame('hard', $aDecision->decision['difficulty']);
        $this->assertSame('easy', $bDecision->decision['difficulty']);
        $this->assertNotSame($a['current_item']['question_id'], $b['current_item']['question_id']);
    }

    public function test_api_requires_commit_key_and_candidate_assignment_and_checks_bound_device(): void
    {
        [$attempt] = $this->fixture(settings: ['bind_device' => true]);
        $state = app(AdaptiveLifecycleService::class)->execute($attempt, 'start', ['device_fingerprint' => 'bound']);
        $headers = ['Authorization' => 'Bearer '.app(CandidateExamSessionService::class)->makeToken($attempt)];
        $data = $this->answerData($state, true, 'key');
        unset($data['idempotency_key']);
        $this->postJson('/api/candidate/answer', [...$data, 'commit' => true, 'device_fingerprint' => 'bound'], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('idempotency_key');
        $this->getJson('/api/candidate/exam?device_fingerprint=another', $headers)->assertUnprocessable()->assertJsonValidationErrors('device_fingerprint');
        $attempt->exam->candidates()->detach($attempt->candidate_id);
        $this->getJson('/api/candidate/exam?device_fingerprint=bound', $headers)->assertForbidden();
        $this->assertSame(0, AdaptiveResponse::whereNotNull('committed_at')->count());
    }

    public function test_server_sweep_finalizes_an_expired_disconnected_level(): void
    {
        [$attempt] = $this->fixture();
        $this->start($attempt);
        $this->travel(31)->minutes();
        $this->artisan('adaptive:expire')->assertSuccessful();
        $this->assertSame('auto_submitted', $attempt->fresh()->status);
        $version = AdaptiveAttemptState::where('attempt_id', $attempt->id)->firstOrFail()->state_version;
        $this->artisan('adaptive:expire')->assertSuccessful();
        $this->assertSame($version, AdaptiveAttemptState::where('attempt_id', $attempt->id)->firstOrFail()->state_version);
    }

    public function test_cooldown_rejects_preview_without_spending_then_zero_percent_can_start(): void
    {
        [$attempt] = $this->fixture(settings: ['level_cooldown_minutes' => 5, 'recovery_penalty_percent' => 0]);
        $this->start($attempt);
        app(AdaptiveLifecycleService::class)->execute($attempt, 'submit');
        try {
            app(AdaptiveLifecycleService::class)->execute($attempt, 'next-level', ['idempotency_key' => 'waiting']);
            $this->fail('Cooldown must be enforced');
        } catch (ValidationException) {
            $this->assertDatabaseCount('adaptive_levels', 1);
            $this->assertSame(0, (int) AdaptiveProgression::firstOrFail()->penalty_units);
        }
        $this->travel(6)->minutes();
        $state = app(AdaptiveLifecycleService::class)->execute($attempt, 'next-level', ['idempotency_key' => 'waiting']);
        $this->assertSame(2, $state['level']);
        $this->assertSame(600, (int) AdaptiveLevel::orderByDesc('id')->firstOrFail()->available_units);
        $this->assertSame(0, AdaptiveMarkEntry::where('kind', 'penalty')->count());
    }

    public function test_ledger_rejects_spending_more_than_an_area_owns(): void
    {
        [$attempt] = $this->fixture(areas: 2);
        $this->start($attempt);
        try {
            DB::transaction(fn () => app(AdaptiveLedgerService::class)->post(
                AdaptiveProgression::firstOrFail(), AdaptiveLevel::firstOrFail(),
                'row:1', 'earned', 601, 'overspend'));
            $this->fail('Cross-area overspending must fail');
        } catch (\LogicException) {
            $this->assertSame(0, (int) AdaptiveProgression::firstOrFail()->earned_units);
            $this->assertSame(1200, (int) AdaptiveProgression::firstOrFail()->recoverable_units);
        }
    }

    public function test_adaptive_result_lookup_uses_closed_aggregate_and_traditional_recalculation_cannot_overwrite_it(): void
    {
        [$attempt] = $this->fixture(settings: ['max_scored_levels' => 1]);
        $state = $this->start($attempt);
        for ($i = 0; $i < 3; $i++) {
            $state = $this->commit($attempt, $state, $i === 0, 'release-'.$i);
        }
        $payload = ['exam_code' => $attempt->exam->code, 'registration_number' => $attempt->candidate->candidate_number];
        $this->postJson('/api/candidate/result', $payload)->assertUnprocessable();
        $attempt->exam->update(['result_release_settings' => ['release_mode' => 'released']]);
        $this->postJson('/api/candidate/result', $payload)->assertOk()->assertJsonPath('result.score', '2.00')
            ->assertJsonPath('result.total_marks', '6.00')->assertJsonPath('result.result_type', 'adaptive_recovery_aggregate');
        app(ExamResultService::class)->calculate($attempt, true);
        $this->assertSame('2.00', $attempt->fresh()->score);
        $this->assertFalse($attempt->fresh()->certificate_eligible);
    }

    public function test_adaptive_login_requires_explicit_start_and_resumes_latest_recovery_after_initial_window(): void
    {
        [$attempt] = $this->fixture();
        $loginData = ['exam_code' => $attempt->exam->code, 'registration_number' => $attempt->candidate->candidate_number, 'device_fingerprint' => 'device'];
        $this->postJson('/api/candidate/login', $loginData)->assertOk()->assertJsonPath('current_item', null)->assertJsonPath('attempt.status', 'not_started');
        $this->assertNull($attempt->fresh()->started_at);
        $this->start($attempt);
        app(AdaptiveLifecycleService::class)->execute($attempt, 'submit');
        $this->travel(61)->minutes();
        $attempt->exam->update(['status' => 'completed']);
        $next = app(AdaptiveLifecycleService::class)->execute($attempt, 'next-level', ['idempotency_key' => 'late-recovery']);
        $this->postJson('/api/candidate/login', $loginData)->assertOk()->assertJsonPath('attempt.id', $next['attempt']['id'])
            ->assertJsonPath('level', 2)->assertJsonPath('current_item.question_id', $next['current_item']['question_id']);
    }

    public function test_authorized_login_provisions_one_initial_attempt_without_starting_or_resetting_history(): void
    {
        [$first] = $this->fixture();
        $candidate = Candidate::factory()->create(['organization_id' => $first->exam->organization_id]);
        $first->exam->candidates()->attach($candidate->id);
        $data = ['exam_code' => $first->exam->code, 'registration_number' => $candidate->candidate_number, 'device_fingerprint' => 'device'];
        $login = $this->postJson('/api/candidate/login', $data)->assertOk()->assertJsonPath('current_item', null)
            ->assertJsonPath('attempt.status', 'not_started');
        $this->postJson('/api/candidate/login', $data)->assertOk()->assertJsonPath('attempt.id', $login->json('attempt.id'));
        $this->assertSame(1, CandidateExamAttempt::where('candidate_id', $candidate->id)->count());
        $attempt = CandidateExamAttempt::findOrFail($login->json('attempt.id'));
        $this->assertNull($attempt->started_at);
        $this->assertSame(0, $attempt->papers()->count());
        $this->assertSame(0, (int) AdaptiveProgression::where('candidate_id', $candidate->id)->firstOrFail()->penalty_units);
    }
}
