<?php

namespace Tests\Feature;

use App\Models\AdaptivePilotControl;
use App\Models\Candidate;
use App\Models\CandidateExamAttempt;
use App\Models\CandidatePaper;
use App\Models\Exam;
use App\Models\OfflineServerActivation;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\User;
use App\Services\AdaptiveAttemptPreparationService;
use App\Services\AdaptiveRolloutService;
use App\Services\OfflineActivationGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdaptiveOfflineBoundaryTest extends TestCase
{
    use RefreshDatabase, \Tests\Support\AdaptiveFixtures;

    public static function contexts(): array
    {
        return array_map(fn ($context) => [$context], Exam::OWNER_TYPES);
    }

    #[DataProvider('contexts')]
    public function test_traditional_packages_keep_fixed_papers_in_all_contexts(string $context): void
    {
        $exam = Exam::factory()->create([
            'mode' => 'traditional', 'exam_mode' => 'traditional', 'exam_owner_type' => $context,
            'status' => 'active', 'starts_at' => now()->subMinute(), 'ends_at' => now()->addHour(),
        ]);
        $candidate = Candidate::factory()->create();
        $exam->candidates()->attach($candidate->id);
        $attempt = CandidateExamAttempt::factory()->create(['exam_id' => $exam->id, 'candidate_id' => $candidate->id]);
        $question = Question::factory()->create(['question_type' => 'single_choice', 'marks' => 2]);
        $option = QuestionOption::factory()->create(['question_id' => $question->id, 'is_correct' => true]);
        CandidatePaper::create(['attempt_id' => $attempt->id, 'question_id' => $question->id, 'question_order' => 1, 'marks' => 5, 'option_order' => [$option->id]]);
        $before = $attempt->fresh()->getAttributes();
        $this->syncAdmin();
        $this->getJson('/api/offline/exam-packages/'.$exam->code)->assertOk()
            ->assertJsonPath('package.manifest.package_contract', 'alignex.fixed-paper.v1')
            ->assertJsonPath('package.manifest.exam_mode', 'traditional')
            ->assertJsonPath('package.manifest.exam_context', $context)
            ->assertJsonPath('package.papers.0.questions.0.question_id', $question->id)
            ->assertJsonPath('package.questions.0.marks', 5)
            ->assertJsonCount(1, 'package.candidates');
        $this->assertSame($before, $attempt->fresh()->getAttributes());
    }

    public function test_online_allowlist_never_enables_adaptive_offline_export(): void
    {
        [$attempt] = $this->fixture(false);
        $exam = $attempt->exam;
        AdaptivePilotControl::create(['exam_id' => $exam->id, 'owner_key' => app(AdaptiveRolloutService::class)->ownerKey($exam), 'online_enabled' => true, 'offline_enabled' => false, 'purpose' => 'Boundary test diagnostic cohort.', 'updated_by' => $exam->created_by]);
        $this->assertTrue(app(AdaptiveRolloutService::class)->status($exam)['can_publish']);
        $this->syncAdmin();
        $this->getJson('/api/offline/exam-packages/'.$exam->code)->assertUnprocessable()
            ->assertJsonPath('code', 'adaptive_offline_unsupported')->assertJsonMissingPath('package');
        $this->assertDatabaseHas('exam_audit_logs', ['exam_id' => $exam->id, 'event_type' => 'adaptive_offline_export_blocked']);
    }

    public function test_frozen_adaptive_binding_blocks_export_after_mode_labels_change(): void
    {
        [$attempt] = $this->fixture(false);
        DB::table('exams')->where('id', $attempt->exam_id)->update(['mode' => 'traditional', 'exam_mode' => 'traditional']);
        $this->syncAdmin();
        $this->getJson('/api/offline/exam-packages/'.$attempt->exam->code)->assertUnprocessable()
            ->assertJsonPath('code', 'adaptive_offline_unsupported')->assertJsonMissingPath('package');
    }

    public function test_either_adaptive_label_is_rejected_even_without_runtime_state(): void
    {
        $this->syncAdmin();
        foreach (['mode', 'exam_mode'] as $field) {
            $exam = Exam::factory()->create(['mode' => 'traditional', 'exam_mode' => 'traditional', $field => 'adaptive', 'status' => 'active']);
            $this->getJson('/api/offline/exam-packages/'.$exam->code)->assertUnprocessable()
                ->assertJsonPath('code', 'adaptive_offline_unsupported')->assertJsonMissingPath('package');
        }
    }

    public function test_package_export_still_requires_activation_and_admin_credentials(): void
    {
        $this->getJson('/api/offline/exam-packages/UNKNOWN')->assertUnauthorized();
        $this->mock(OfflineActivationGuard::class, fn ($mock) => $mock
            ->shouldReceive('requireActive')->withAnyArgs()->andReturn(new OfflineServerActivation));
        $this->getJson('/api/offline/exam-packages/UNKNOWN')->assertUnauthorized();
    }

    public function test_unrelated_admin_cannot_export_or_discover_adaptive_capability(): void
    {
        [$attempt] = $this->fixture(false);
        $this->syncAdmin();
        $other = User::factory()->create(['role' => User::ROLE_ORGANIZATION_ADMIN, 'password' => 'password']);
        $this->withHeaders(['X-AlignEx-Admin-Email' => $other->email, 'X-AlignEx-Admin-Password' => 'password'])
            ->getJson('/api/offline/exam-packages/'.$attempt->exam->code)->assertForbidden()->assertJsonMissingPath('package');
        $this->assertDatabaseMissing('exam_audit_logs', ['exam_id' => $attempt->exam_id, 'event_type' => 'adaptive_offline_export_blocked']);
    }

    public function test_removing_exam_from_rollout_preserves_active_attempt_but_blocks_new_start(): void
    {
        [$attempt, $snapshot] = $this->fixture(false);
        $exam = $attempt->exam;
        AdaptivePilotControl::create(['exam_id' => $exam->id, 'owner_key' => app(AdaptiveRolloutService::class)->ownerKey($exam), 'online_enabled' => true, 'offline_enabled' => false, 'purpose' => 'Boundary test diagnostic cohort.', 'updated_by' => $exam->created_by]);
        $state = $this->start($attempt);
        $deadline = $attempt->fresh()->server_due_at->toISOString();
        AdaptivePilotControl::where('exam_id', $exam->id)->update(['online_enabled' => false]);
        $this->commit($attempt, $state, true, 'after-rollout-pause');
        $this->assertSame($deadline, $attempt->fresh()->server_due_at->toISOString());
        $this->assertSame('adaptive', $exam->fresh()->effectiveMode());

        $candidate = Candidate::factory()->create(['organization_id' => $exam->organization_id]);
        $exam->candidates()->attach($candidate->id);
        $next = CandidateExamAttempt::factory()->create(['exam_id' => $exam->id, 'candidate_id' => $candidate->id,
            'status' => 'not_started', 'started_at' => null]);
        app(AdaptiveAttemptPreparationService::class)->bind($next, $snapshot);
        try {
            $this->start($next);
            $this->fail('Paused exam allowed a new start.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('exam', $exception->errors());
        }
        $this->assertNull($next->fresh()->started_at);
    }

    private function syncAdmin(): void
    {
        $this->mock(OfflineActivationGuard::class, fn ($mock) => $mock
            ->shouldReceive('requireActive')->withAnyArgs()->andReturn(new OfflineServerActivation));
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'password' => 'password']);
        $this->withHeaders(['X-AlignEx-Admin-Email' => $admin->email, 'X-AlignEx-Admin-Password' => 'password']);
    }
}
