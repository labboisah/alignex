<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\CandidateExamAttempt;
use App\Models\CandidatePaper;
use App\Models\Exam;
use App\Models\OfflineActivationCode;
use App\Models\OfflineServerActivation;
use App\Models\Organization;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\User;
use App\Services\OfflinePaperProofService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OfflineResultUploadTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $admin = User::factory()->create(['role' => User::ROLE_ORGANIZATION_ADMIN, 'organization_id' => Organization::factory(), 'password' => Hash::make('upload-password')]);
        $exam = Exam::factory()->create(['organization_id' => $admin->organization_id, 'created_by' => $admin->id, 'mode' => 'traditional', 'exam_mode' => 'traditional', 'status' => 'completed', 'starts_at' => now()->subDays(3), 'ends_at' => now()->subDays(2), 'settings' => ['show_result_immediately' => false], 'pass_mark' => 3]);
        $candidate = Candidate::factory()->create(['organization_id' => $exam->organization_id]);
        $exam->candidates()->attach($candidate->id);
        $attempt = CandidateExamAttempt::factory()->create(['exam_id' => $exam->id, 'candidate_id' => $candidate->id, 'total_marks' => 999]);
        $question = Question::factory()->create(['question_type' => 'single_choice', 'marks' => 2]);
        $correct = QuestionOption::factory()->create(['question_id' => $question->id, 'label' => 'A', 'is_correct' => true]);
        $wrong = QuestionOption::factory()->create(['question_id' => $question->id, 'label' => 'B', 'is_correct' => false]);
        CandidatePaper::create(['attempt_id' => $attempt->id, 'question_id' => $question->id, 'question_order' => 1, 'marks' => 5, 'option_order' => [$correct->id, $wrong->id]]);
        $code = OfflineActivationCode::create(['created_by_user_id' => $admin->id, 'label' => 'Test', 'code_hash' => Hash::make('test-code'), 'status' => 'active', 'max_activations' => 1]);
        $activation = OfflineServerActivation::create(['offline_activation_code_id' => $code->id, 'organization_id' => $admin->organization_id, 'device_id' => 'test-device', 'admin_email' => $admin->email, 'center_name' => 'Test center', 'license_key' => 'test-license', 'status' => 'activated', 'activated_at' => now()->subDays(4), 'expires_at' => now()->addYear()]);
        $headers = ['Authorization' => 'Bearer test-license', 'X-AlignEx-Admin-Email' => $admin->email, 'X-AlignEx-Admin-Password' => 'upload-password', 'X-AlignEx-Device-Id' => 'test-device'];
        $proofs = app(OfflinePaperProofService::class);
        $payload = [
            'contract' => 'alignex.offline-result.v1', 'upload_id' => 'test-upload', 'package_id' => 'test-package',
            'exam_id' => $exam->id, 'candidate_id' => $candidate->id, 'attempt_id' => $attempt->id,
            'upload_proof' => $proofs->issue($attempt, (string) $activation->id, 'test-package'),
            'status' => 'submitted', 'started_at' => now()->subDays(2)->subHour()->toISOString(), 'submitted_at' => now()->subDays(2)->toISOString(),
            'local_score' => 999, 'paper' => $proofs->paper($attempt),
            'answers' => [['question_id' => $question->id, 'selected_option_ids' => [$correct->id], 'answer_text' => null, 'saved_at' => now()->subDays(2)->subMinute()->toISOString()]],
            'events' => [['event_type' => 'tab_blur', 'severity' => 'warning', 'message' => 'Window lost focus', 'occurred_at' => now()->subDays(2)->subMinutes(2)->toISOString()]],
        ];

        return compact('admin', 'exam', 'candidate', 'attempt', 'question', 'correct', 'wrong', 'activation', 'headers', 'payload');
    }

    public function test_late_upload_uses_paper_marks_and_release_controls_and_is_idempotent(): void
    {
        extract($this->fixture());
        $first = $this->postJson('/api/offline/results', $payload, $headers)->assertOk()->assertJsonPath('receipt.visibility', 'held')->assertJsonPath('receipt.score_difference', -994);
        $this->assertEquals(5, $attempt->fresh()->score);
        $this->assertEquals(5, $attempt->fresh()->total_marks);
        $this->assertSame('completed', $exam->fresh()->status);
        $this->postJson('/api/offline/results', $payload, $headers)->assertOk()->assertJsonPath('receipt.id', $first->json('receipt.id'));
        $this->assertDatabaseCount('offline_result_receipts', 1);
        $this->assertDatabaseCount('candidate_answers', 1);
        $this->assertDatabaseCount('proctoring_events', 1);
        $this->postJson('/api/candidate/result', ['exam_code' => $exam->code, 'registration_number' => $candidate->candidate_number])->assertUnprocessable();
        $this->actingAs($admin)->post('/results/exams/'.$exam->id.'/release', ['released' => true])->assertRedirect();
        $this->postJson('/api/candidate/result', ['exam_code' => $exam->code, 'registration_number' => $candidate->candidate_number])->assertOk()->assertJsonMissingPath('result.answers');
        $this->postJson('/api/offline/results', $payload, $headers)->assertOk()->assertJsonPath('receipt.visibility', 'released');
        $this->actingAs($admin)->post('/results/exams/'.$exam->id.'/release', ['released' => false])->assertRedirect();
        $this->postJson('/api/candidate/result', ['exam_code' => $exam->code, 'registration_number' => $candidate->candidate_number])->assertUnprocessable();
        $this->assertDatabaseHas('exam_audit_logs', ['event_type' => 'offline_result_uploaded']);
        $this->assertDatabaseHas('exam_audit_logs', ['event_type' => 'results_released']);
    }

    public function test_altered_retry_cannot_replace_an_accepted_result(): void
    {
        extract($this->fixture());
        $this->postJson('/api/offline/results', $payload, $headers)->assertOk();
        $payload['answers'][0]['selected_option_ids'] = [$wrong->id];
        $this->postJson('/api/offline/results', $payload, $headers)->assertConflict();
        $this->assertEquals(5, $attempt->fresh()->score);
    }

    public function test_online_activity_and_changed_papers_are_conflicts(): void
    {
        extract($this->fixture());
        $attempt->update(['status' => 'in_progress', 'started_at' => now()]);
        $this->postJson('/api/offline/results', $payload, $headers)->assertConflict();
        $attempt->update(['status' => 'not_started', 'started_at' => null]);
        $correct->update(['is_correct' => false]);
        $this->postJson('/api/offline/results', $payload, $headers)->assertConflict();
        $this->assertDatabaseCount('candidate_answers', 0);
    }

    public function test_changed_scoring_policy_and_tampered_proofs_are_rejected(): void
    {
        extract($this->fixture());
        $exam->update(['settings' => ['negative_marking' => true, 'negative_mark_value' => 2]]);
        $this->postJson('/api/offline/results', $payload, $headers)->assertConflict();
        $payload['upload_proof'] = 'tampered';
        $this->postJson('/api/offline/results', $payload, $headers)->assertConflict();
    }

    public function test_legacy_package_maps_uniquely_and_does_not_trust_local_score(): void
    {
        extract($this->fixture());
        $payload['attempt_id'] = null;
        $payload['upload_proof'] = null;
        $this->postJson('/api/offline/results', $payload, $headers)->assertOk()->assertJsonPath('receipt.legacy_package', true);
        $this->assertEquals(5, $attempt->fresh()->score);
    }

    public function test_legacy_multiple_attempts_are_rejected(): void
    {
        extract($this->fixture());
        CandidateExamAttempt::factory()->create(['exam_id' => $exam->id, 'candidate_id' => $candidate->id, 'attempt_number' => 2]);
        $payload['attempt_id'] = null;
        $payload['upload_proof'] = null;
        $this->postJson('/api/offline/results', $payload, $headers)->assertConflict();
    }

    public function test_disqualification_is_preserved_and_never_shown_as_a_result(): void
    {
        extract($this->fixture());
        $payload['status'] = 'disqualified';
        $exam->update(['settings' => ['show_result_immediately' => true]]);
        $this->postJson('/api/offline/results', $payload, $headers)->assertOk()->assertJsonPath('receipt.visibility', 'disqualified')->assertJsonPath('receipt.official_score', null);
        $this->assertSame('disqualified', $attempt->fresh()->status);
        $this->postJson('/api/candidate/result', ['exam_code' => $exam->code, 'registration_number' => $candidate->candidate_number])->assertUnprocessable();
    }

    public function test_credentials_device_owner_and_activation_are_enforced(): void
    {
        extract($this->fixture());
        $this->postJson('/api/offline/results', $payload)->assertUnauthorized();
        $this->postJson('/api/offline/results', $payload, array_merge($headers, ['X-AlignEx-Admin-Password' => 'bad']))->assertUnauthorized();
        $this->postJson('/api/offline/results', $payload, array_merge($headers, ['X-AlignEx-Device-Id' => 'other-device']))->assertUnauthorized();
        $other = User::factory()->create(['role' => User::ROLE_ORGANIZATION_ADMIN, 'password' => Hash::make('upload-password')]);
        $activation->update(['admin_email' => $other->email]);
        $this->postJson('/api/offline/results', $payload, array_merge($headers, ['X-AlignEx-Admin-Email' => $other->email]))->assertForbidden();
        $activation->update(['admin_email' => $admin->email, 'status' => 'revoked']);
        $this->postJson('/api/offline/results', $payload, $headers)->assertForbidden();
        $this->assertDatabaseCount('offline_result_receipts', 0);
    }

    public function test_invalid_answer_rolls_back_and_future_timestamps_fail_validation(): void
    {
        extract($this->fixture());
        $payload['answers'][0]['selected_option_ids'] = [(string) str()->ulid()];
        $this->postJson('/api/offline/results', $payload, $headers)->assertUnprocessable();
        $this->assertSame('not_started', $attempt->fresh()->status);
        $this->assertDatabaseCount('candidate_answers', 0);
        $payload['submitted_at'] = now()->addDay()->toISOString();
        $this->postJson('/api/offline/results', $payload, $headers)->assertUnprocessable();
    }

    public function test_a_new_download_cannot_bypass_its_proof_by_claiming_legacy_format(): void
    {
        extract($this->fixture());
        DB::table('offline_paper_exports')->insert(['attempt_id' => $attempt->id, 'activation_id' => $activation->id, 'package_id' => $payload['package_id'], 'created_at' => now(), 'updated_at' => now()]);
        $payload['upload_proof'] = null;
        $payload['attempt_id'] = null;
        $payload['package_id'] = 'pretend-legacy';
        $this->postJson('/api/offline/results', $payload, $headers)->assertConflict();
    }

    public function test_auto_submission_applies_decimal_negative_marks_and_unanswered_papers(): void
    {
        extract($this->fixture());
        $exam->update(['settings' => ['negative_marking' => true, 'negative_mark_value' => 0.75]]);
        $payload['status'] = 'auto_submitted';
        $payload['answers'][0]['selected_option_ids'] = [$wrong->id];
        $payload['upload_proof'] = app(OfflinePaperProofService::class)->issue($attempt->fresh(), (string) $activation->id, $payload['package_id']);
        $this->postJson('/api/offline/results', $payload, $headers)->assertOk();
        $this->assertEquals(-0.75, $attempt->fresh()->score);
        $this->assertNotNull($attempt->fresh()->auto_submitted_at);
    }

    public function test_unanswered_submission_keeps_full_available_marks_and_latest_retake_is_selected(): void
    {
        extract($this->fixture());
        $payload['answers'] = [];
        $exam->update(['settings' => ['show_result_immediately' => true]]);
        $this->postJson('/api/offline/results', $payload, $headers)->assertOk();
        $this->assertEquals(0, $attempt->fresh()->score);
        $this->assertEquals(5, $attempt->fresh()->total_marks);
        $retake = CandidateExamAttempt::factory()->create(['exam_id' => $exam->id, 'candidate_id' => $candidate->id, 'attempt_number' => 2, 'status' => 'submitted', 'score' => 4, 'total_marks' => 5, 'submitted_at' => now()]);
        $this->postJson('/api/candidate/result', ['exam_code' => $exam->code, 'registration_number' => $candidate->candidate_number])->assertOk()->assertJsonPath('result.attempt_id', $retake->id);
    }
}
