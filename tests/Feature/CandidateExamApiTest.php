<?php

namespace Tests\Feature;

use App\Events\ExamMonitorEvent;
use App\Models\Candidate;
use App\Models\CandidateAnswer;
use App\Models\CandidateExamAttempt;
use App\Models\CandidatePerformanceProfile;
use App\Models\Exam;
use App\Models\ExamAuditLog;
use App\Models\ExamSubject;
use App\Models\Organization;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\QuestionOption;
use App\Models\Subject;
use App\Services\ExamPaperGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CandidateExamApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_candidate_can_login_fetch_paper_save_answer_and_submit(): void
    {
        [$exam, $candidate] = $this->activeExamWithPaper();

        $login = $this->postJson('/api/candidate/login', [
            'exam_code' => $exam->code,
            'registration_number' => $candidate->candidate_number,
            'device_fingerprint' => 'device-one',
        ])
            ->assertOk()
            ->assertJsonPath('candidate.registration_number', $candidate->candidate_number)
            ->assertJsonMissing(['is_correct' => true]);

        $token = $login->json('exam_token');
        $question = $login->json('questions.0');
        $optionId = $question['options'][0]['id'];

        $this->getJson('/api/candidate/exam', ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonMissing(['is_correct' => true]);

        $this->postJson('/api/candidate/answer', [
            'question_id' => $question['question_id'],
            'selected_option_ids' => [$optionId],
            'is_flagged' => true,
            'time_spent_seconds' => 12,
            'device_fingerprint' => 'device-one',
        ], ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonPath('saved', true);

        $this->assertDatabaseHas('candidate_answers', [
            'candidate_exam_attempt_id' => $login->json('attempt.id'),
            'question_id' => $question['question_id'],
            'is_flagged' => true,
            'time_spent_seconds' => 12,
            'device_fingerprint' => 'device-one',
        ]);

        $this->postJson('/api/candidate/submit', [], ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonPath('submitted', true)
            ->assertJsonPath('score', '1.00');

        $this->assertDatabaseHas('candidate_exam_attempts', [
            'id' => $login->json('attempt.id'),
            'status' => 'submitted',
            'score' => 1,
        ]);
        $this->assertDatabaseHas('candidate_answers', [
            'candidate_exam_attempt_id' => $login->json('attempt.id'),
            'question_id' => $question['question_id'],
            'score_awarded' => 1,
        ]);
        $this->assertDatabaseHas('exam_audit_logs', ['event_type' => 'login_success']);
        $this->assertDatabaseHas('exam_audit_logs', ['event_type' => 'answer_saved']);
        $this->assertDatabaseHas('exam_audit_logs', ['event_type' => 'submission']);
        $this->assertGreaterThan(0, CandidatePerformanceProfile::query()
            ->where('candidate_id', $candidate->id)
            ->where('exam_id', $exam->id)
            ->count());

        $this->postJson('/api/candidate/answer', [
            'question_id' => $question['question_id'],
            'selected_option_ids' => [$optionId],
        ], ['Authorization' => "Bearer {$token}"])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('exam');
    }

    public function test_candidate_login_requires_active_exam_assignment_and_open_attempt(): void
    {
        [$exam, $candidate] = $this->activeExamWithPaper();
        $exam->update(['status' => Exam::STATUS_SCHEDULED]);

        $this->postJson('/api/candidate/login', [
            'exam_code' => $exam->code,
            'registration_number' => $candidate->candidate_number,
            'device_fingerprint' => 'device-one',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('exam_code');

        $this->assertDatabaseHas('exam_audit_logs', ['event_type' => 'login_failed']);
    }

    public function test_candidate_cannot_login_after_exam_end_time(): void
    {
        [$exam, $candidate] = $this->activeExamWithPaper();
        $exam->update(['ends_at' => now()->subMinute()]);

        $this->postJson('/api/candidate/login', [
            'exam_code' => $exam->code,
            'registration_number' => $candidate->candidate_number,
            'device_fingerprint' => 'device-one',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('exam_code');

        $this->assertDatabaseHas('exam_audit_logs', ['event_type' => 'login_failed']);
    }

    public function test_device_binding_blocks_different_device(): void
    {
        [$exam, $candidate] = $this->activeExamWithPaper(['bind_device' => true]);

        $this->postJson('/api/candidate/login', [
            'exam_code' => $exam->code,
            'registration_number' => $candidate->candidate_number,
            'device_fingerprint' => 'device-one',
        ])->assertOk();

        $this->postJson('/api/candidate/login', [
            'exam_code' => $exam->code,
            'registration_number' => $candidate->candidate_number,
            'device_fingerprint' => 'device-two',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('device_fingerprint');
    }

    public function test_submission_applies_negative_marking_server_side(): void
    {
        [$exam, $candidate] = $this->activeExamWithPaper([
            'negative_marking' => true,
            'negative_mark_value' => 0.25,
        ]);

        $login = $this->postJson('/api/candidate/login', [
            'exam_code' => $exam->code,
            'registration_number' => $candidate->candidate_number,
            'device_fingerprint' => 'device-one',
        ])->assertOk();

        $token = $login->json('exam_token');
        $question = $login->json('questions.0');
        $wrongOptionId = collect($question['options'])->firstWhere('label', 'B')['id'];

        $this->postJson('/api/candidate/answer', [
            'question_id' => $question['question_id'],
            'selected_option_ids' => [$wrongOptionId],
        ], ['Authorization' => "Bearer {$token}"])->assertOk();

        $this->postJson('/api/candidate/submit', [], ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonPath('score', '-0.25');

        $this->assertDatabaseHas('candidate_answers', [
            'candidate_exam_attempt_id' => $login->json('attempt.id'),
            'question_id' => $question['question_id'],
            'score_awarded' => -0.25,
        ]);
    }

    public function test_expired_attempt_is_auto_submitted_server_side(): void
    {
        [$exam, $candidate] = $this->activeExamWithPaper();

        $login = $this->postJson('/api/candidate/login', [
            'exam_code' => $exam->code,
            'registration_number' => $candidate->candidate_number,
            'device_fingerprint' => 'device-one',
        ])->assertOk();

        CandidateExamAttempt::query()
            ->whereKey($login->json('attempt.id'))
            ->update(['server_due_at' => now()->subSecond()]);

        $this->postJson('/api/candidate/answer', [
            'question_id' => $login->json('questions.0.question_id'),
            'selected_option_ids' => [$login->json('questions.0.options.0.id')],
        ], ['Authorization' => "Bearer {$login->json('exam_token')}"])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('exam');

        $this->assertDatabaseHas('candidate_exam_attempts', [
            'id' => $login->json('attempt.id'),
            'status' => 'auto_submitted',
        ]);
        $this->assertDatabaseHas('exam_audit_logs', ['event_type' => 'auto_submit']);
    }

    public function test_candidate_actions_broadcast_monitor_events(): void
    {
        [$exam, $candidate] = $this->activeExamWithPaper();
        Event::fake([ExamMonitorEvent::class]);

        $login = $this->postJson('/api/candidate/login', [
            'exam_code' => $exam->code,
            'registration_number' => $candidate->candidate_number,
            'device_fingerprint' => 'device-one',
        ])->assertOk();

        $token = $login->json('exam_token');
        $this->postJson('/api/candidate/answer', [
            'question_id' => $login->json('questions.0.question_id'),
            'selected_option_ids' => [$login->json('questions.0.options.0.id')],
        ], ['Authorization' => "Bearer {$token}"])->assertOk();

        $this->postJson('/api/candidate/event', [
            'event_type' => 'focus_loss',
            'metadata' => ['severity' => 'warning'],
        ], ['Authorization' => "Bearer {$token}"])->assertOk();

        $this->postJson('/api/candidate/submit', [], ['Authorization' => "Bearer {$token}"])->assertOk();

        Event::assertDispatched(ExamMonitorEvent::class, fn (ExamMonitorEvent $event) => $event->examId === $exam->id && $event->type === 'login');
        Event::assertDispatched(ExamMonitorEvent::class, fn (ExamMonitorEvent $event) => $event->examId === $exam->id && $event->type === 'answer_saved');
        Event::assertDispatched(ExamMonitorEvent::class, fn (ExamMonitorEvent $event) => $event->examId === $exam->id && $event->type === 'suspicious');
        Event::assertDispatched(ExamMonitorEvent::class, fn (ExamMonitorEvent $event) => $event->examId === $exam->id && $event->type === 'submitted');
        $this->assertDatabaseHas('proctoring_events', [
            'candidate_exam_attempt_id' => $login->json('attempt.id'),
            'event_type' => 'focus_loss',
            'severity' => 'warning',
        ]);
    }

    public function test_tab_switch_limit_disqualifies_attempt_server_side(): void
    {
        [$exam, $candidate] = $this->activeExamWithPaper(['max_tab_switches' => 1]);
        Event::fake([ExamMonitorEvent::class]);

        $login = $this->postJson('/api/candidate/login', [
            'exam_code' => $exam->code,
            'registration_number' => $candidate->candidate_number,
            'device_fingerprint' => 'device-one',
        ])->assertOk();

        $token = $login->json('exam_token');

        $this->postJson('/api/candidate/event', [
            'event_type' => 'tab_switch',
            'metadata' => ['severity' => 'warning'],
        ], ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonPath('disqualified', false)
            ->assertJsonPath('tab_switch_count', 1);

        $this->postJson('/api/candidate/event', [
            'event_type' => 'tab_switch',
            'metadata' => ['severity' => 'warning'],
        ], ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonPath('disqualified', true)
            ->assertJsonPath('tab_switch_count', 2);

        $this->assertDatabaseHas('candidate_exam_attempts', [
            'id' => $login->json('attempt.id'),
            'status' => CandidateExamAttempt::STATUS_DISQUALIFIED,
            'disqualification_reason' => 'Maximum tab switches exceeded.',
        ]);
        $this->assertDatabaseCount('proctoring_events', 2);
        $this->assertDatabaseHas('exam_audit_logs', ['event_type' => 'disqualified']);

        $this->postJson('/api/candidate/answer', [
            'question_id' => $login->json('questions.0.question_id'),
            'selected_option_ids' => [$login->json('questions.0.options.0.id')],
        ], ['Authorization' => "Bearer {$token}"])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('exam');

        Event::assertDispatched(ExamMonitorEvent::class, fn (ExamMonitorEvent $event) => $event->examId === $exam->id && $event->type === 'disqualified');
    }

    private function activeExamWithPaper(array $settings = []): array
    {
        $organization = Organization::factory()->create();
        $subject = Subject::factory()->create(['organization_id' => $organization->id]);
        $exam = Exam::factory()->create([
            'organization_id' => $organization->id,
            'status' => Exam::STATUS_ACTIVE,
            'starts_at' => now()->addMinutes(5),
            'ends_at' => now()->addHours(2),
            'duration_minutes' => 60,
            'settings' => array_replace([
                'shuffle_questions' => false,
                'shuffle_options' => false,
                'bind_device' => false,
                'allow_back_navigation' => true,
            ], $settings),
        ]);
        ExamSubject::factory()->create([
            'exam_id' => $exam->id,
            'subject_id' => $subject->id,
            'question_count' => 2,
            'marks_per_question' => 1,
            'total_marks' => 2,
            'difficulty_distribution' => null,
            'selection_rules' => null,
        ]);
        $candidate = Candidate::factory()->create([
            'organization_id' => $organization->id,
            'candidate_number' => 'REG-100',
            'phone' => '08030000000',
        ]);
        $exam->candidates()->attach($candidate->id, ['status' => 'assigned']);
        $bank = QuestionBank::factory()->create([
            'organization_id' => $organization->id,
            'subject_id' => $subject->id,
        ]);

        for ($questionIndex = 1; $questionIndex <= 3; $questionIndex++) {
            $question = Question::factory()->create([
                'question_bank_id' => $bank->id,
                'subject_id' => $subject->id,
                'topic_id' => null,
                'status' => Question::STATUS_APPROVED,
            ]);

            foreach (['A', 'B', 'C', 'D'] as $index => $label) {
                QuestionOption::factory()->create([
                    'question_id' => $question->id,
                    'label' => $label,
                    'display_order' => $index + 1,
                    'is_correct' => $label === 'A',
                ]);
            }
        }

        app(ExamPaperGeneratorService::class)->generate($exam);

        return [$exam->refresh(), $candidate];
    }
}
