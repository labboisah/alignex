<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\CandidateAnswer;
use App\Models\CandidateExamAttempt;
use App\Models\Center;
use App\Models\Exam;
use App\Models\ExamAuditLog;
use App\Models\Organization;
use App\Models\ProctoringEvent;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExamMonitorTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_can_monitor_assigned_center_exam(): void
    {
        [$exam, $attempt] = $this->examWithAttempt();
        $supervisor = User::factory()->create([
            'role' => User::ROLE_SUPERVISOR,
            'center_id' => $exam->center_id,
        ]);

        $this->actingAs($supervisor)
            ->get("/exams/{$exam->id}/monitor")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ExamMonitor/Show')
                ->where('summary.total_candidates', 1)
                ->where('summary.logged_in', 1)
                ->where('summary.suspicious', 1)
                ->where('rows.0.registration_number', $attempt->candidate->candidate_number)
            );

        $this->actingAs($supervisor)
            ->getJson("/exams/{$exam->id}/monitor/summary")
            ->assertOk()
            ->assertJsonPath('total_candidates', 1)
            ->assertJsonPath('active', 1);

        $this->actingAs($supervisor)
            ->getJson("/exams/{$exam->id}/monitor/rows")
            ->assertOk()
            ->assertJsonPath('rows.0.answered_questions', 1);

        $this->actingAs($supervisor)
            ->getJson("/exams/{$exam->id}/monitor/feed")
            ->assertOk()
            ->assertJsonPath('feed.0.type', 'suspicious');
    }

    public function test_supervisor_can_reset_candidate_attempt_without_deleting_saved_answers(): void
    {
        [$exam, $attempt] = $this->examWithAttempt();
        $attempt->update([
            'status' => CandidateExamAttempt::STATUS_SUBMITTED,
            'submitted_at' => now(),
            'score' => 1,
            'result_hash' => 'ABC123',
        ]);
        $attempt->answers()->update([
            'submitted_at' => now(),
            'score_awarded' => 1,
            'scored_at' => now(),
        ]);
        $supervisor = User::factory()->create([
            'role' => User::ROLE_SUPERVISOR,
            'center_id' => $exam->center_id,
        ]);

        $this->actingAs($supervisor)
            ->postJson("/exams/{$exam->id}/monitor/attempts/{$attempt->id}/reset", [
                'reason' => 'Device changed during exam.',
            ])
            ->assertOk()
            ->assertJsonPath('reset', true)
            ->assertJsonPath('row.status', 'active')
            ->assertJsonPath('row.answered_questions', 1);

        $attempt->refresh();
        $answer = $attempt->answers()->firstOrFail();
        $this->assertSame(CandidateExamAttempt::STATUS_IN_PROGRESS, $attempt->status);
        $this->assertNull($attempt->submitted_at);
        $this->assertNull($attempt->score);
        $this->assertNull($attempt->result_hash);
        $this->assertSame(['option-1'], $answer->selected_option_ids);
        $this->assertNull($answer->submitted_at);
        $this->assertNull($answer->score_awarded);
        $this->assertDatabaseHas('exam_audit_logs', [
            'candidate_exam_attempt_id' => $attempt->id,
            'event_type' => 'candidate_reset',
            'actor_user_id' => $supervisor->id,
        ]);
        $this->assertSame('device_change', ExamAuditLog::query()
            ->where('candidate_exam_attempt_id', $attempt->id)
            ->where('event_type', 'candidate_reset')
            ->firstOrFail()
            ->metadata['reset_reason_category']);
    }

    public function test_supervisor_cannot_reset_candidate_after_exam_end_time(): void
    {
        [$exam, $attempt] = $this->examWithAttempt();
        $exam->update(['ends_at' => now()->subMinute()]);
        $supervisor = User::factory()->create([
            'role' => User::ROLE_SUPERVISOR,
            'center_id' => $exam->center_id,
        ]);

        $this->actingAs($supervisor)
            ->postJson("/exams/{$exam->id}/monitor/attempts/{$attempt->id}/reset", [
                'reason' => 'Device changed during exam.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('exam');

        $this->assertSame(CandidateExamAttempt::STATUS_IN_PROGRESS, $attempt->refresh()->status);
    }

    public function test_supervisor_cannot_monitor_unassigned_exam(): void
    {
        [$exam] = $this->examWithAttempt();
        $otherCenter = Center::factory()->create();
        $supervisor = User::factory()->create([
            'role' => User::ROLE_SUPERVISOR,
            'center_id' => $otherCenter->id,
        ]);

        $this->actingAs($supervisor)
            ->get("/exams/{$exam->id}/monitor")
            ->assertForbidden();
    }

    private function examWithAttempt(): array
    {
        $organization = Organization::factory()->create();
        $center = Center::factory()->create();
        $subject = Subject::factory()->create(['organization_id' => $organization->id]);
        $exam = Exam::factory()->create([
            'organization_id' => $organization->id,
            'center_id' => $center->id,
            'status' => Exam::STATUS_ACTIVE,
        ]);
        $candidate = Candidate::factory()->create([
            'organization_id' => $organization->id,
            'center_id' => $center->id,
            'candidate_number' => 'MON-001',
        ]);
        $exam->candidates()->attach($candidate->id, ['status' => 'assigned']);
        $bank = QuestionBank::factory()->create(['organization_id' => $organization->id, 'subject_id' => $subject->id]);
        $question = Question::factory()->create([
            'question_bank_id' => $bank->id,
            'subject_id' => $subject->id,
        ]);
        $attempt = CandidateExamAttempt::factory()->create([
            'candidate_id' => $candidate->id,
            'exam_id' => $exam->id,
            'center_id' => $center->id,
            'status' => CandidateExamAttempt::STATUS_IN_PROGRESS,
            'started_at' => now()->subMinutes(10),
            'server_due_at' => now()->addMinutes(50),
            'total_questions' => 2,
            'total_marks' => 2,
            'ip_address' => '127.0.0.1',
        ]);
        CandidateAnswer::factory()->create([
            'candidate_exam_attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'subject_id' => $subject->id,
            'selected_option_ids' => ['option-1'],
            'saved_at' => now(),
        ]);
        ExamAuditLog::factory()->create([
            'exam_id' => $exam->id,
            'candidate_exam_attempt_id' => $attempt->id,
            'event_type' => 'login_success',
            'occurred_at' => now()->subMinutes(9),
        ]);
        ProctoringEvent::factory()->create([
            'exam_id' => $exam->id,
            'candidate_exam_attempt_id' => $attempt->id,
            'candidate_id' => $candidate->id,
            'center_id' => $center->id,
            'severity' => 'warning',
            'event_type' => 'focus_loss',
            'occurred_at' => now(),
        ]);

        return [$exam->refresh(), $attempt->refresh()->load('candidate')];
    }
}
