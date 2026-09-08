<?php

namespace Tests\Feature;

use App\Models\AdaptiveMarkEntry;
use App\Models\AdaptiveProgression;
use App\Models\CandidateExamAttempt;
use App\Models\Organization;
use App\Models\User;
use App\Services\AdaptiveLedgerService;
use App\Services\AdaptiveLifecycleService;
use App\Services\CandidateExamSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdaptiveExperienceTest extends TestCase
{
    use RefreshDatabase, \Tests\Support\AdaptiveFixtures;

    public function test_candidate_context_uses_frozen_controls_without_exposing_marks_or_future_items(): void
    {
        [$attempt] = $this->fixture(settings: ['require_fullscreen' => true, 'require_webcam' => true]);
        $attempt->exam->update(['settings' => []]);
        $state = app(AdaptiveLifecycleService::class)->execute($attempt, 'read');
        $this->assertSame($attempt->candidate->candidate_number, $state['candidate']['registration_number']);
        $this->assertTrue($state['can_start']);
        $this->assertTrue($state['exam']['settings']['require_fullscreen']);
        $this->assertTrue($state['exam']['settings']['require_webcam']);
        $this->assertNull($state['current_item']);
        $this->assertNull($state['result']);
        foreach (['recoverable_units', 'earned_units', 'is_correct', 'rubric', 'area_plan', 'content_hash'] as $private) {
            $this->assertStringNotContainsString('"'.$private.'"', json_encode($state));
        }
    }

    public function test_recovery_preview_obeys_cooldown_and_does_not_post_penalties(): void
    {
        [$attempt] = $this->fixture(settings: ['level_cooldown_minutes' => 10]);
        $this->start($attempt);
        $state = app(AdaptiveLifecycleService::class)->execute($attempt, 'submit');
        $this->assertFalse($state['recovery']['can_start']);
        $this->assertStringContainsString('cooldown', $state['recovery']['message']);
        $entries = AdaptiveMarkEntry::count();
        $this->travel(11)->minutes();
        $state = app(AdaptiveLifecycleService::class)->execute($attempt, 'read');
        $this->assertTrue($state['recovery']['can_start']);
        $this->assertEquals(10, $state['recovery']['penalty_percent']);
        $this->assertSame($entries, AdaptiveMarkEntry::count());
        $this->assertNull($state['result']);
    }

    public function test_monitor_counts_latest_level_once_retains_history_and_blocks_other_owners(): void
    {
        [$attempt] = $this->fixture();
        $this->start($attempt);
        app(AdaptiveLifecycleService::class)->execute($attempt, 'submit');
        $next = app(AdaptiveLifecycleService::class)->execute($attempt, 'next-level', ['idempotency_key' => 'monitor-next']);
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR, 'organization_id' => $attempt->exam->organization_id]);
        $this->actingAs($supervisor)->getJson("/exams/{$attempt->exam_id}/monitor/rows")
            ->assertOk()->assertJsonCount(1, 'rows')
            ->assertJsonPath('rows.0.attempt_id', $next['attempt']['id'])
            ->assertJsonPath('rows.0.adaptive.level', 2)
            ->assertJsonCount(2, 'rows.0.adaptive.history')
            ->assertJsonPath('rows.0.adaptive.history.0.stop_reason', 'submitted');
        $this->getJson("/exams/{$attempt->exam_id}/monitor/summary")->assertJsonPath('active', 1)->assertJsonPath('submitted', 0);
        $resetResponse = $this->postJson("/exams/{$attempt->exam_id}/monitor/attempts/{$next['attempt']['id']}/reset", ['reason' => 'Device recovery request.']);
        $this->assertSame(422, $resetResponse->status(), $resetResponse->getContent());
        $outsider = User::factory()->create(['role' => User::ROLE_SUPERVISOR, 'organization_id' => Organization::factory()]);
        $this->actingAs($outsider)->getJson("/exams/{$attempt->exam_id}/monitor/rows")->assertForbidden();
    }

    public function test_supervisor_end_finalizes_and_closes_adaptive_budget_without_rewriting_history(): void
    {
        [$attempt] = $this->fixture();
        $state = $this->start($attempt);
        $this->commit($attempt, $state, true, 'earned');
        $actor = User::factory()->create(['role' => User::ROLE_ORGANIZATION_ADMIN, 'organization_id' => $attempt->exam->organization_id]);
        $this->actingAs($actor)->postJson("/exams/{$attempt->exam_id}/monitor/end")->assertOk();
        $this->assertSame('auto_submitted', $attempt->fresh()->status);
        $progression = AdaptiveProgression::firstOrFail();
        $this->assertSame('closed', $progression->status);
        $this->assertSame('supervisor_end', $progression->stop_reason);
        $this->assertEquals(200, $progression->earned_units);
        $this->assertEquals(400, $progression->closed_units);
        app(AdaptiveLedgerService::class)->reconcile($progression);
        $count = AdaptiveMarkEntry::count();
        $this->postJson("/exams/{$attempt->exam_id}/monitor/end")->assertOk();
        $this->assertSame($count, AdaptiveMarkEntry::count());
        $this->assertDatabaseHas('exam_audit_logs', ['event_type' => 'adaptive_supervisor_end']);
    }

    public function test_supervisor_can_close_a_prepared_level_without_issuing_a_question(): void
    {
        [$attempt] = $this->fixture();
        $actor = User::factory()->create(['role' => User::ROLE_ORGANIZATION_ADMIN, 'organization_id' => $attempt->exam->organization_id]);
        $this->actingAs($actor)->postJson("/exams/{$attempt->exam_id}/monitor/end")->assertOk();
        $this->assertSame('auto_submitted', $attempt->fresh()->status);
        $this->assertDatabaseCount('adaptive_decisions', 0);
        $this->assertEquals(600, AdaptiveProgression::firstOrFail()->closed_units);
    }

    public function test_supervisor_end_blocks_practice_even_when_scoring_was_already_closed(): void
    {
        [$attempt] = $this->fixture(settings: ['max_scored_levels' => 1, 'allow_unscored_remediation' => true]);
        $this->start($attempt);
        $state = app(AdaptiveLifecycleService::class)->execute($attempt, 'submit');
        $this->assertTrue($state['recovery']['can_practice']);
        $actor = User::factory()->create(['role' => User::ROLE_ORGANIZATION_ADMIN, 'organization_id' => $attempt->exam->organization_id]);
        app(AdaptiveLifecycleService::class)->endBySupervisor($attempt, $actor);
        $state = app(AdaptiveLifecycleService::class)->execute($attempt, 'read');
        $this->assertFalse($state['recovery']['can_practice']);
        $this->expectException(ValidationException::class);
        app(AdaptiveLifecycleService::class)->execute($attempt, 'next-level', ['practice' => true, 'idempotency_key' => 'after-end']);
    }

    public function test_completed_practice_has_a_terminal_message_and_no_further_actions(): void
    {
        [$attempt] = $this->fixture(settings: ['max_scored_levels' => 1, 'allow_unscored_remediation' => true]);
        $this->start($attempt);
        app(AdaptiveLifecycleService::class)->execute($attempt, 'submit');
        $next = app(AdaptiveLifecycleService::class)->execute($attempt, 'next-level', ['practice' => true, 'idempotency_key' => 'practice-message']);
        $practice = CandidateExamAttempt::findOrFail($next['attempt']['id']);
        $state = app(AdaptiveLifecycleService::class)->execute($practice, 'submit');
        $this->assertSame('Unscored practice is complete. It does not change your result.', $state['recovery']['message']);
        $this->assertFalse($state['recovery']['can_start']);
        $this->assertFalse($state['recovery']['can_practice']);
    }

    public function test_tab_limit_uses_snapshot_and_disqualification_hides_recovery(): void
    {
        [$attempt] = $this->fixture(settings: ['max_tab_switches' => 1]);
        $this->start($attempt);
        $attempt->exam->update(['settings' => ['max_tab_switches' => 99]]);
        $token = app(CandidateExamSessionService::class)->makeToken($attempt);
        $headers = ['Authorization' => 'Bearer '.$token];
        $this->postJson('/api/candidate/event', ['event_type' => 'window_blur'], $headers)->assertOk()->assertJsonPath('disqualified', false);
        $this->postJson('/api/candidate/event', ['event_type' => 'window_blur'], $headers)->assertOk()->assertJsonPath('disqualified', true);
        $this->getJson('/api/candidate/exam', $headers)->assertOk()
            ->assertJsonPath('attempt.status', 'disqualified')
            ->assertJsonPath('current_item', null)->assertJsonPath('result', null)
            ->assertJsonPath('recovery.can_start', false)->assertJsonPath('recovery.can_practice', false);
    }
}
