<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\CandidateExamAttempt;
use App\Models\CandidatePaper;
use App\Models\Exam;
use App\Models\Organization;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\QuestionOption;
use App\Models\Subject;
use App\Models\User;
use App\Services\AdaptiveRolloutService;
use App\Services\CandidateExamSessionService;
use App\Services\ExamPaperGeneratorService;
use App\Support\ExamOwnershipRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdaptiveRolloutTest extends TestCase
{
    use RefreshDatabase;

    public function test_adaptive_draft_is_saved_but_publication_is_blocked_even_for_allowlisted_owner(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create(['role' => User::ROLE_ORGANIZATION_ADMIN, 'organization_id' => $organization->id]);
        $subject = Subject::factory()->create(['organization_id' => $organization->id]);
        $bank = QuestionBank::factory()->create(['organization_id' => $organization->id, 'subject_id' => $subject->id]);
        $candidate = Candidate::factory()->create(['organization_id' => $organization->id]);
        $payload = [
            'title' => 'Adaptive draft', 'exam_code' => 'ADAPT-DRAFT', 'exam_type' => 'assessment',
            'exam_category' => 'assessment', 'mode' => 'adaptive', 'exam_mode' => 'adaptive',
            'delivery_mode' => 'online', 'start_at' => now()->addDay()->toDateTimeString(),
            'end_at' => now()->addDays(2)->toDateTimeString(), 'duration_minutes' => 30,
            'pass_mark' => 1, 'status' => 'draft', 'question_bank_id' => $bank->id, 'candidate_ids' => [$candidate->id],
            'subjects' => [['subject_id' => $subject->id, 'number_of_questions' => 2, 'marks_per_question' => 1]],
            'settings' => array_fill_keys(['shuffle_questions', 'shuffle_options', 'show_result_immediately', 'allow_back_navigation', 'require_webcam', 'require_fullscreen', 'negative_marking', 'bind_device', 'allow_retake'], false) + ['max_tab_switches' => 0],
        ];
        $this->actingAs($user)->post('/exams', $payload)->assertSessionHasNoErrors()->assertRedirect();
        $exam = Exam::where('code', 'ADAPT-DRAFT')->firstOrFail();
        config(['adaptive.pilot_enabled' => true, 'adaptive.pilot_owners' => ['organization:'.$organization->id]]);
        $status = app(AdaptiveRolloutService::class)->status($exam);
        $this->assertTrue($status['owner_allowlisted']);
        $this->assertFalse($status['runtime_ready']);
        foreach (['scheduled', 'active'] as $state) {
            $this->patch('/exams/'.$exam->id, [...$payload, 'status' => $state])->assertSessionHasErrors('exam');
            $this->assertSame('draft', $exam->fresh()->status);
        }
        $this->post('/exams', [...$payload, 'exam_code' => 'BLOCKED', 'status' => 'active'])->assertSessionHasErrors('exam');
        $this->assertDatabaseMissing('exams', ['code' => 'BLOCKED']);
    }

    public function test_new_adaptive_login_and_previously_issued_token_are_blocked(): void
    {
        [$exam, $attempt] = $this->legacyAttempt(false);
        $this->postJson('/api/candidate/login', [
            'exam_code' => $exam->code, 'registration_number' => $attempt->candidate->candidate_number, 'device_fingerprint' => 'device',
        ])->assertUnprocessable()->assertJsonValidationErrors('exam');
        $token = app(CandidateExamSessionService::class)->makeToken($attempt);
        foreach (['/api/candidate/start', '/api/candidate/answer'] as $url) {
            $this->withToken($token)->postJson($url, ['device_fingerprint' => 'device'])->assertUnprocessable()->assertJsonValidationErrors('exam');
        }
        $this->withToken($token)->getJson('/api/candidate/exam')->assertUnprocessable()->assertJsonValidationErrors('exam');
        $this->assertNull($attempt->fresh()->started_at);
        $this->assertDatabaseHas('exam_audit_logs', ['candidate_exam_attempt_id' => $attempt->id, 'event_type' => 'adaptive_start_blocked']);
    }

    public function test_started_legacy_attempt_keeps_paper_can_resume_answer_and_submit(): void
    {
        [$exam, $attempt, $paper, $option] = $this->legacyAttempt(true);
        $due = $attempt->server_due_at->toISOString();
        $response = $this->postJson('/api/candidate/login', [
            'exam_code' => $exam->code, 'registration_number' => $attempt->candidate->candidate_number, 'device_fingerprint' => 'device',
        ])->assertOk();
        $token = $response->json('exam_token');
        $this->assertSame($paper->question_id, $response->json('questions.0.question_id'));
        $this->withToken($token)->postJson('/api/candidate/answer', [
            'question_id' => $paper->question_id, 'selected_option_ids' => [$option->id],
        ])->assertOk();
        $this->withToken($token)->postJson('/api/candidate/submit')->assertOk()->assertJsonPath('score', '2.00');
        $this->assertSame($due, $attempt->fresh()->server_due_at->toISOString());
        $this->assertSame('adaptive', $exam->fresh()->effectiveMode());
        $this->assertSame(1, $attempt->papers()->count());
    }

    public function test_paper_generation_is_blocked_for_inconsistent_adaptive_mode_fields(): void
    {
        [$exam] = $this->legacyAttempt(false);
        $exam->update(['exam_mode' => 'traditional']);
        $this->expectException(ValidationException::class);
        app(ExamPaperGeneratorService::class)->generate($exam);
    }

    public function test_inventory_is_read_only_and_reports_started_and_unstarted_attempts(): void
    {
        [$exam, $attempt] = $this->legacyAttempt(true);
        $before = $attempt->fresh()->getAttributes();
        $this->artisan('adaptive:inventory')->expectsOutputToContain('organization:')->assertSuccessful();
        $this->assertSame($before, $attempt->fresh()->getAttributes());
        $this->assertSame('adaptive', $exam->fresh()->mode);
    }

    public function test_owner_allowlist_is_exact_and_secondary_terminal_remains_traditional(): void
    {
        $service = app(AdaptiveRolloutService::class);
        config(['adaptive.pilot_enabled' => true, 'adaptive.pilot_owners' => ['organization:1']]);
        foreach (Exam::OWNER_TYPES as $owner) {
            $exam = new Exam(['exam_owner_type' => $owner, 'exam_owner_id' => 1, 'exam_category' => 'assessment']);
            $this->assertSame($owner === 'organization', $service->status($exam)['owner_allowlisted']);
            $this->assertFalse($service->status($exam)['can_publish']);
        }
        $this->assertTrue(ExamOwnershipRules::isValid('secondary_school', 'terminal', 'traditional'));
        $this->assertFalse(ExamOwnershipRules::isValid('secondary_school', 'terminal', 'adaptive'));
        $this->assertFalse(ExamOwnershipRules::isValid('secondary_school', 'assessment', 'adaptive'));
    }

    public function test_started_papers_cannot_be_converted_to_another_mode(): void
    {
        [$exam] = $this->legacyAttempt(true);
        $proposed = new Exam([...$exam->getAttributes(), 'mode' => 'traditional', 'exam_mode' => 'traditional']);
        $this->expectException(ValidationException::class);
        app(AdaptiveRolloutService::class)->ensureSaveAllowed($proposed, $exam);
    }

    private function legacyAttempt(bool $started): array
    {
        $organization = Organization::factory()->create();
        $exam = Exam::factory()->create([
            'organization_id' => $organization->id, 'exam_owner_type' => 'organization', 'exam_owner_id' => $organization->id,
            'mode' => 'adaptive', 'exam_mode' => 'adaptive', 'status' => 'active',
            'starts_at' => now()->subMinute(), 'ends_at' => now()->addHour(), 'settings' => [],
        ]);
        $candidate = Candidate::factory()->create(['organization_id' => $organization->id]);
        $exam->candidates()->attach($candidate->id);
        $attempt = CandidateExamAttempt::factory()->create([
            'exam_id' => $exam->id, 'candidate_id' => $candidate->id,
            'status' => $started ? 'in_progress' : 'not_started',
            'started_at' => $started ? now()->subMinute() : null,
            'server_due_at' => $started ? now()->addMinutes(29) : null,
            'total_questions' => 1, 'total_marks' => 2,
        ]);
        $question = Question::factory()->create(['marks' => 2, 'question_type' => Question::TYPE_SINGLE_CHOICE]);
        $option = QuestionOption::factory()->create(['question_id' => $question->id, 'is_correct' => true]);
        $paper = CandidatePaper::query()->create(['attempt_id' => $attempt->id, 'question_id' => $question->id, 'question_order' => 1, 'option_order' => [$option->id]]);

        return [$exam, $attempt, $paper, $option];
    }
}
