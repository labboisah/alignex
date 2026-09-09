<?php

namespace Tests\Feature;

use App\Models\AdaptiveAttemptState;
use App\Models\AdaptiveLevel;
use App\Models\AdaptivePilotControl;
use App\Models\Candidate;
use App\Models\CandidateExamAttempt;
use App\Models\Question;
use App\Services\AdaptiveAttemptPreparationService;
use App\Services\AdaptiveLifecycleService;
use App\Services\AdaptiveRolloutService;
use App\Services\ExamPaperGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdaptiveLearningExperienceTest extends TestCase
{
    use RefreshDatabase, \Tests\Support\AdaptiveFixtures;

    public function test_completed_levels_show_aggregate_feedback_and_offer_weakness_recovery(): void
    {
        [$attempt] = $this->fixture(true, 1, ['adaptive_show_level_feedback' => true]);
        $state = $this->start($attempt);
        $this->assertNull($state['learning_progress']);
        $state = $this->commit($attempt, $state, true, 'learning-answer-1');
        $this->assertNull($state['learning_progress']);
        $state = app(AdaptiveLifecycleService::class)->execute($attempt, 'submit');
        $this->assertSame('2.00', $state['learning_progress']['levels'][0]['score']);
        $this->assertSame('6.00', $state['learning_progress']['levels'][0]['available_marks']);
        $this->assertSame('Needs more practice', $state['learning_progress']['areas'][0]['status']);
        $this->assertTrue($state['recovery']['can_start']);
        $this->assertNull($state['result']);
        $json = json_encode($state['learning_progress']);
        foreach (['question_id', 'selected_options', 'is_correct', 'correct_answer', 'content_hash'] as $secret) {
            $this->assertStringNotContainsString($secret, $json);
        }
        $next = app(AdaptiveLifecycleService::class)->execute($attempt, 'next-level', ['practice' => false, 'idempotency_key' => 'learning-next-level']);
        $this->assertSame(2, $next['level']);
        $this->assertNull($next['learning_progress']);
    }

    public function test_existing_feedback_release_choice_is_preserved(): void
    {
        [$attempt] = $this->fixture(true, 1, ['adaptive_show_level_feedback' => false]);
        $this->start($attempt);
        $state = app(AdaptiveLifecycleService::class)->execute($attempt, 'submit');
        $this->assertNull($state['learning_progress']);
    }

    public function test_database_approval_cannot_make_an_unbound_attempt_traditional(): void
    {
        [$attempt] = $this->fixture(false);
        AdaptiveAttemptState::where('attempt_id', $attempt->id)->delete();
        AdaptivePilotControl::create(['exam_id' => $attempt->exam_id, 'owner_key' => app(AdaptiveRolloutService::class)->ownerKey($attempt->exam), 'online_enabled' => true, 'offline_enabled' => false, 'purpose' => 'Approved test exam', 'updated_by' => $attempt->exam->created_by]);
        $this->expectException(ValidationException::class);
        app(AdaptiveRolloutService::class)->ensureAttemptAccess($attempt);
    }

    public function test_unused_fixed_papers_are_replaced_without_starting_the_level(): void
    {
        [$original] = $this->fixture();
        $candidate = Candidate::factory()->create(['organization_id' => $original->exam->organization_id]);
        $original->exam->candidates()->attach($candidate->id);
        $attempt = CandidateExamAttempt::factory()->create([
            'exam_id' => $original->exam_id, 'candidate_id' => $candidate->id,
            'status' => 'not_started', 'started_at' => null, 'submitted_at' => null,
            'auto_submitted_at' => null, 'disqualified_at' => null, 'server_due_at' => null,
        ]);
        $attempt->papers()->create(['question_id' => Question::first()->id, 'question_order' => 1, 'option_order' => []]);
        $prepared = app(AdaptiveAttemptPreparationService::class)->prepareAssignedCandidate($original->exam->fresh(), $candidate);
        $this->assertSame($attempt->id, $prepared->id);
        $this->assertNull($prepared->fresh()->started_at);
        $this->assertSame(0, $prepared->papers()->count());
        $this->assertTrue(AdaptiveAttemptState::where('attempt_id', $prepared->id)->exists());
        app(AdaptiveAttemptPreparationService::class)->prepareAssignedCandidate($original->exam->fresh(), $candidate);
        $this->assertSame(1, AdaptiveLevel::where('attempt_id', $prepared->id)->count());
    }

    public function test_adaptive_fixed_paper_generation_is_rejected(): void
    {
        [$attempt] = $this->fixture();
        $this->expectException(ValidationException::class);
        app(ExamPaperGeneratorService::class)->generate($attempt->exam);
    }

    public function test_attempt_with_saved_answers_is_never_converted(): void
    {
        [$original] = $this->fixture();
        $candidate = Candidate::factory()->create(['organization_id' => $original->exam->organization_id]);
        $original->exam->candidates()->attach($candidate->id);
        $attempt = CandidateExamAttempt::factory()->create([
            'exam_id' => $original->exam_id, 'candidate_id' => $candidate->id,
            'status' => 'not_started', 'started_at' => null,
        ]);
        $question = Question::first();
        $attempt->papers()->create(['question_id' => $question->id, 'question_order' => 1, 'option_order' => []]);
        $answer = $attempt->answers()->create(['question_id' => $question->id, 'subject_id' => $question->subject_id, 'selected_option_ids' => []]);
        try {
            app(AdaptiveAttemptPreparationService::class)->prepareAssignedCandidate($original->exam->fresh(), $candidate);
            $this->fail('Saved answers must prevent conversion.');
        } catch (ValidationException $e) {
            $this->assertSame(1, $attempt->papers()->count());
            $this->assertNotNull($answer->fresh());
            $this->assertFalse(AdaptiveAttemptState::where('attempt_id', $attempt->id)->exists());
        }
    }

    public function test_string_duration_and_cooldown_settings_work_through_recovery(): void
    {
        [$attempt] = $this->fixture(true, 1, [
            'level_duration_minutes' => '25',
            'level_cooldown_minutes' => '5',
        ]);
        $this->start($attempt);
        $this->assertSame(now()->addMinutes(25)->toDateTimeString(), $attempt->fresh()->server_due_at->toDateTimeString());
        $state = app(AdaptiveLifecycleService::class)->execute($attempt, 'submit');
        $this->assertFalse($state['recovery']['can_start']);
        $this->assertSame(now()->addMinutes(5)->startOfSecond()->toISOString(), $state['recovery']['available_at']);
        try {
            app(AdaptiveLifecycleService::class)->execute($attempt, 'next-level', ['practice' => false, 'idempotency_key' => 'too-early']);
            $this->fail('The configured cooldown must be enforced.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('cooldown', $e->getMessage());
        }
        $this->travel(5)->minutes();
        $next = app(AdaptiveLifecycleService::class)->execute($attempt, 'next-level', ['practice' => false, 'idempotency_key' => 'after-cooldown']);
        $this->assertSame(2, $next['level']);
    }
}
