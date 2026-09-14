<?php

namespace Tests\Feature;

use App\Events\ExamMonitorEvent;
use App\Models\Candidate;
use App\Models\CandidateExamAttempt;
use App\Models\CandidatePaper;
use App\Models\Exam;
use App\Models\Organization;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\User;
use App\Services\CandidateExamSessionService;
use App\Services\CandidateRetakeService;
use App\Services\ExamMonitorService;
use App\Services\RecruitmentService;
use App\Services\ResultManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CandidateRetakeTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        Event::fake([ExamMonitorEvent::class]);
        $this->travelTo(now()->startOfMinute());
        $organization = Organization::factory()->create();
        $admin = User::factory()->create(['role' => User::ROLE_ORGANIZATION_ADMIN, 'organization_id' => $organization->id]);
        $exam = Exam::factory()->create([
            'organization_id' => $organization->id, 'created_by' => $admin->id,
            'mode' => 'traditional', 'exam_mode' => 'traditional', 'status' => 'completed',
            'starts_at' => now()->subDays(2), 'ends_at' => now()->subDay(),
            'duration_minutes' => 60, 'total_marks' => 5, 'pass_mark' => 3,
            'settings' => ['show_result_immediately' => false, 'bind_device' => true, 'allow_retake' => false],
        ]);
        $candidate = Candidate::factory()->create(['organization_id' => $organization->id]);
        $exam->candidates()->attach($candidate->id);
        $previous = CandidateExamAttempt::factory()->create([
            'exam_id' => $exam->id, 'candidate_id' => $candidate->id,
            'status' => 'submitted', 'started_at' => now()->subDays(2),
            'submitted_at' => now()->subDays(2)->addHour(), 'score' => 5, 'total_marks' => 5,
            'result_hash' => 'OLD-RESULT', 'device_fingerprint' => 'old-device',
        ]);
        $question = Question::factory()->create(['question_type' => 'single_choice', 'marks' => 2, 'topic_id' => null]);
        $correct = QuestionOption::factory()->create(['question_id' => $question->id, 'label' => 'A', 'display_order' => 1, 'is_correct' => true]);
        $wrong = QuestionOption::factory()->create(['question_id' => $question->id, 'label' => 'B', 'display_order' => 2, 'is_correct' => false]);
        CandidatePaper::create(['attempt_id' => $previous->id, 'question_id' => $question->id, 'question_order' => 1, 'option_order' => [$correct->id, $wrong->id], 'marks' => 5]);
        $previous->answers()->create(['question_id' => $question->id, 'subject_id' => $question->subject_id, 'selected_option_ids' => [$correct->id], 'score_awarded' => 5, 'saved_at' => now()->subDays(2), 'submitted_at' => now()->subDays(2)->addHour()]);
        $data = ['starts_at' => now()->addHour()->toISOString(), 'ends_at' => now()->addHours(3)->toISOString(), 'duration_minutes' => 30, 'reason' => 'Approved candidate retake.'];

        return compact('admin', 'exam', 'candidate', 'previous', 'question', 'correct', 'wrong', 'data');
    }

    private function login(Exam $exam, Candidate $candidate, bool $withCode = true)
    {
        return $this->postJson('/api/candidate/login', [
            ...($withCode ? ['exam_code' => $exam->code] : []),
            'registration_number' => $candidate->candidate_number, 'device_fingerprint' => 'retake-device',
        ]);
    }

    public function test_admin_schedules_a_fresh_paper_without_changing_the_exam_or_old_result(): void
    {
        extract($this->fixture());
        $this->actingAs($admin)->from('/results/exams/'.$exam->id)
            ->post('/exams/attempts/'.$previous->id.'/retake', $data)->assertRedirect()->assertSessionHasNoErrors();
        $retake = $exam->attempts()->where('attempt_number', 2)->firstOrFail();
        $this->assertEquals($previous->id, $retake->retake_of_attempt_id);
        $this->assertEquals($admin->id, $retake->retake_scheduled_by);
        $this->assertEquals(5, $retake->papers()->first()->marks);
        $this->assertEquals(0, $retake->answers()->count());
        $this->assertNull($retake->score);
        $this->assertNull($retake->device_fingerprint);
        $this->assertNull($retake->server_due_at);
        $this->assertEquals(5, $previous->fresh()->score);
        $this->assertEquals('completed', $exam->fresh()->status);
        $this->assertEquals([$previous->id], app(ResultManagementService::class)->queryForExam($exam)->pluck('id')->all());
        $this->assertDatabaseHas('exam_audit_logs', ['event_type' => 'retake_scheduled', 'actor_user_id' => $admin->id]);
        $this->get('/results/exams/'.$exam->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('retake_candidates.0.pending_id', $retake->id)->where('retake_candidates.0.can_cancel', true));
    }

    public function test_permissions_and_validation_protect_scheduling(): void
    {
        extract($this->fixture());
        $url = '/exams/attempts/'.$previous->id.'/retake';
        $this->post($url, $data)->assertRedirect('/login');
        $other = User::factory()->create(['role' => User::ROLE_ORGANIZATION_ADMIN, 'organization_id' => Organization::factory()]);
        $this->actingAs($other)->post($url, $data)->assertForbidden();
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR, 'organization_id' => $exam->organization_id]);
        $this->actingAs($supervisor)->post($url, $data)->assertForbidden();
        $this->actingAs($admin)->post($url, [...$data, 'starts_at' => now()->subMinute()->toISOString()])->assertRedirect()->assertSessionHasErrors('starts_at');
        $this->post($url, [...$data, 'ends_at' => $data['starts_at']])->assertRedirect()->assertSessionHasErrors('ends_at');
        $this->post($url, [...$data, 'duration_minutes' => 1441])->assertRedirect()->assertSessionHasErrors('duration_minutes');
        $this->post($url, [...$data, 'reason' => ''])->assertRedirect()->assertSessionHasErrors('reason');
        $this->post($url, [...$data, 'duration_minutes' => 121])->assertRedirect()->assertSessionHasErrors('retake');
        $this->assertEquals(1, $exam->attempts()->count());
    }

    public function test_retake_uses_its_own_window_and_reconnect_preserves_the_due_time(): void
    {
        extract($this->fixture());
        $retake = app(CandidateRetakeService::class)->schedule($previous, $data, $admin);
        $login = $this->login($exam, $candidate, false)->assertOk()
            ->assertJsonPath('can_start', false)->assertJsonCount(0, 'questions')
            ->assertJsonPath('exam.duration_minutes', 30)->assertJsonPath('exam.starts_at', $data['starts_at']);
        $headers = ['Authorization' => 'Bearer '.$login->json('exam_token')];
        $this->postJson('/api/candidate/start', ['device_fingerprint' => 'retake-device'], $headers)->assertUnprocessable();
        $this->postJson('/api/candidate/submit', [], $headers)->assertUnprocessable();
        $this->travel(60)->minutes();
        $started = $this->postJson('/api/candidate/start', ['device_fingerprint' => 'retake-device'], $headers)
            ->assertOk()->assertJsonPath('attempt.status', 'in_progress')->assertJsonCount(1, 'questions')
            ->assertJsonMissingPath('questions.0.options.0.is_correct')->assertJsonMissingPath('questions.0.correct_option_ids');
        $due = $started->json('attempt.server_due_at');
        $this->travel(5)->minutes();
        $this->login($exam, $candidate)->assertOk()->assertJsonPath('attempt.server_due_at', $due);
        $this->assertEquals($retake->id, app(ExamMonitorService::class)->rows($exam)[0]['attempt_id']);
        $this->assertCount(1, app(ExamMonitorService::class)->rows($exam));
        $this->assertEquals(5, $previous->fresh()->score);
        $this->travel(25)->minutes();
        $this->getJson('/api/candidate/exam', $headers)->assertOk()->assertJsonPath('attempt.status', 'auto_submitted');
        $this->assertEquals('auto_submitted', $retake->fresh()->status);
        $this->assertEquals(0, $retake->fresh()->score);
    }

    public function test_completed_lower_retake_replaces_all_current_results_but_keeps_history(): void
    {
        extract($this->fixture());
        $this->grantPlanFeatures($exam->organization, ['csv_export', 'pdf_export']);
        $retake = app(CandidateRetakeService::class)->schedule($previous, $data, $admin);
        $this->travel(60)->minutes();
        $login = $this->login($exam, $candidate)->assertOk();
        $headers = ['Authorization' => 'Bearer '.$login->json('exam_token')];
        $this->postJson('/api/candidate/answer', ['question_id' => $question->id, 'selected_option_ids' => [$wrong->id]], $headers)->assertOk();
        $this->postJson('/api/candidate/submit', [], $headers)->assertOk()->assertJsonMissingPath('score');
        $this->assertEquals(0, $retake->fresh()->score);
        $this->assertEquals(5, $previous->fresh()->score);
        $this->assertEquals(1, $previous->answers()->count());
        $this->postJson('/api/candidate/result', ['exam_code' => $exam->code, 'registration_number' => $candidate->candidate_number])->assertUnprocessable();
        $this->actingAs($admin)->post('/results/exams/'.$exam->id.'/release', ['released' => true])->assertRedirect();
        $this->postJson('/api/candidate/result', ['exam_code' => $exam->code, 'registration_number' => $candidate->candidate_number])
            ->assertOk()->assertJsonPath('result.attempt_id', $retake->id)->assertJsonPath('result.score', 0);
        $this->get('/results/exams/'.$exam->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('rows', 1)->where('rows.0.attempt_id', $retake->id)->where('dashboard.summary.total', 1));
        $this->get('/results')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.summary.total', 1)->where('exams.0.submitted_attempts_count', 1));
        $csv = $this->get('/results/exams/'.$exam->id.'/export.csv')->assertOk()->getContent();
        $records = array_map('str_getcsv', explode("\n", trim($csv)));
        $this->assertCount(2, $records);
        $this->assertEquals('0', $records[1][4]);
        $this->get('/results/exams/'.$exam->id.'/summary.pdf')->assertOk()->assertSee('Submitted Candidates: 1');
        $this->get('/results/attempts/'.$previous->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('result.score', 5)->has('attempt_history', 2)
            ->where('attempt_history.0.is_current', true)->where('attempt_history.1.is_current', false));
        $this->postJson('/api/results/verify', ['hash' => 'OLD-RESULT'])->assertOk()->assertJsonPath('valid', false);
        $this->postJson('/api/candidate/start', ['device_fingerprint' => 'retake-device'], $headers)->assertUnprocessable();
    }

    public function test_cancellation_blocks_existing_tokens_and_allows_a_new_schedule(): void
    {
        extract($this->fixture());
        $retake = app(CandidateRetakeService::class)->schedule($previous, $data, $admin);
        $login = $this->login($exam, $candidate)->assertOk();
        $headers = ['Authorization' => 'Bearer '.$login->json('exam_token')];
        $other = User::factory()->create(['role' => User::ROLE_ORGANIZATION_ADMIN, 'organization_id' => Organization::factory()]);
        $this->actingAs($other)->post('/exams/attempts/'.$retake->id.'/retake/cancel')->assertForbidden();
        $this->actingAs($admin)->post('/exams/attempts/'.$retake->id.'/retake/cancel')->assertRedirect()->assertSessionHasNoErrors();
        $this->getJson('/api/candidate/exam', $headers)->assertUnprocessable();
        $this->travel(60)->minutes();
        $this->postJson('/api/candidate/start', ['device_fingerprint' => 'retake-device'], $headers)->assertUnprocessable();
        $this->assertEquals([$previous->id], app(ResultManagementService::class)->queryForExam($exam)->pluck('id')->all());
        $next = app(CandidateRetakeService::class)->schedule($previous, [...$data, 'starts_at' => now()->addMinute()->toISOString()], $admin);
        $this->assertEquals(3, $next->attempt_number);
        $this->assertDatabaseHas('exam_audit_logs', ['event_type' => 'retake_cancelled', 'candidate_exam_attempt_id' => $retake->id]);
    }

    public function test_duplicate_schedules_and_cancelling_started_attempts_are_rejected(): void
    {
        extract($this->fixture());
        $this->actingAs($admin)->post('/exams/attempts/'.$previous->id.'/retake', $data)->assertSessionHasNoErrors();
        $this->post('/exams/attempts/'.$previous->id.'/retake', $data)->assertRedirect()->assertSessionHasErrors('retake');
        $retake = $exam->attempts()->where('attempt_number', 2)->firstOrFail();
        $this->travel(60)->minutes();
        $this->login($exam, $candidate)->assertOk();
        $this->post('/exams/attempts/'.$retake->id.'/retake/cancel')->assertRedirect()->assertSessionHasErrors('retake');
        $this->assertEquals(2, $exam->attempts()->count());
    }

    public function test_missed_retake_does_not_change_result_and_other_candidates_stay_closed(): void
    {
        extract($this->fixture());
        $retake = app(CandidateRetakeService::class)->schedule($previous, $data, $admin);
        $other = Candidate::factory()->create(['organization_id' => $exam->organization_id]);
        $exam->candidates()->attach($other->id);
        CandidateExamAttempt::factory()->create(['exam_id' => $exam->id, 'candidate_id' => $other->id, 'status' => 'submitted']);
        $this->travel(60)->minutes();
        $this->login($exam, $other)->assertUnprocessable();
        $this->travel(120)->minutes();
        $this->login($exam, $candidate)->assertUnprocessable();
        $this->assertEquals('not_started', $retake->fresh()->status);
        $this->assertTrue(CandidateExamAttempt::whereKey($previous->id)->currentResult()->exists());
        $next = app(CandidateRetakeService::class)->schedule($previous, [...$data, 'starts_at' => now()->addHour()->toISOString(), 'ends_at' => now()->addHours(2)->toISOString()], $admin);
        $this->assertEquals(3, $next->attempt_number);
    }

    public function test_late_start_is_capped_at_retake_close(): void
    {
        extract($this->fixture());
        $retake = app(CandidateRetakeService::class)->schedule($previous, $data, $admin);
        $this->travel(170)->minutes();
        $this->login($exam, $candidate)->assertOk()->assertJsonPath('attempt.server_due_at', $data['ends_at']);
        $this->assertEquals($retake->retake_ends_at, $retake->fresh()->server_due_at);
    }

    public function test_adaptive_cancelled_and_unassigned_exams_cannot_receive_retakes(): void
    {
        extract($this->fixture());
        $url = '/exams/attempts/'.$previous->id.'/retake';
        $this->actingAs($admin);
        $exam->update(['mode' => 'adaptive', 'exam_mode' => 'adaptive']);
        $this->post($url, $data)->assertRedirect()->assertSessionHasErrors('retake');
        $exam->update(['mode' => 'traditional', 'exam_mode' => 'traditional', 'status' => 'cancelled']);
        $this->post($url, $data)->assertRedirect()->assertSessionHasErrors('retake');
        $exam->update(['status' => 'completed']);
        $exam->candidates()->detach($candidate->id);
        $this->post($url, $data)->assertRedirect()->assertSessionHasErrors('retake');
        $this->assertEquals(1, $exam->attempts()->count());
    }

    public function test_ending_original_exam_does_not_complete_a_pending_retake(): void
    {
        extract($this->fixture());
        $exam->update(['status' => 'active', 'ends_at' => now()->addDay()]);
        $retake = app(CandidateRetakeService::class)->schedule($previous, $data, $admin);
        app(ExamMonitorService::class)->endExam($exam, $admin);
        $this->assertEquals('not_started', $retake->fresh()->status);
        $this->assertNull($retake->fresh()->score);
        $this->assertTrue(CandidateExamAttempt::whereKey($previous->id)->currentResult()->exists());
    }

    public function test_disqualified_retake_keeps_the_original_result_and_can_be_retaken_again(): void
    {
        extract($this->fixture());
        $retake = app(CandidateRetakeService::class)->schedule($previous, $data, $admin);
        $retake->update(['status' => 'disqualified', 'disqualified_at' => now()]);
        $this->assertTrue(CandidateExamAttempt::whereKey($previous->id)->currentResult()->exists());
        $next = app(CandidateRetakeService::class)->schedule($retake, $data, $admin);
        $this->assertEquals(3, $next->attempt_number);
        $ranking = app(RecruitmentService::class)->ranking($exam);
        $this->assertCount(1, $ranking);
        $this->assertEquals($previous->id, $ranking[0]['attempt_id']);
    }

    public function test_old_tokens_and_supervisor_reset_cannot_reopen_retake_history(): void
    {
        extract($this->fixture());
        $exam->update(['status' => 'active', 'ends_at' => now()->addDay()]);
        app(CandidateRetakeService::class)->schedule($previous, $data, $admin);
        $headers = ['Authorization' => 'Bearer '.app(CandidateExamSessionService::class)->makeToken($previous)];
        $this->postJson('/api/candidate/start', ['device_fingerprint' => 'old-device'], $headers)->assertUnprocessable();
        $this->actingAs($admin)->postJson('/exams/'.$exam->id.'/monitor/attempts/'.$previous->id.'/reset', ['reason' => 'Reset attempt'])->assertUnprocessable();
        $this->assertEquals(5, $previous->fresh()->score);
        $this->assertEquals('OLD-RESULT', $previous->fresh()->result_hash);
    }
}
