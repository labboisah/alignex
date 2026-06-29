<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\CandidateAnswer;
use App\Models\CandidateExamAttempt;
use App\Models\Center;
use App\Models\Exam;
use App\Models\ExamAuditLog;
use App\Models\ExamSession;
use App\Models\ExamSubject;
use App\Models\ExamType;
use App\Models\Organization;
use App\Models\ProctoringEvent;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\QuestionOption;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CbtSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_cbt_tables_are_created(): void
    {
        foreach ([
            'exam_types',
            'exams',
            'subjects',
            'topics',
            'question_banks',
            'questions',
            'question_options',
            'candidates',
            'exam_sessions',
            'exam_subjects',
            'candidate_exam_attempts',
            'candidate_answers',
            'exam_audit_logs',
            'proctoring_events',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Expected {$table} table to exist.");
        }
    }

    public function test_status_defaults_match_recommended_exam_runtime_states(): void
    {
        $exam = Exam::factory()->create(['status' => Exam::STATUS_DRAFT]);
        $session = ExamSession::factory()->create(['exam_id' => $exam->id]);
        $attempt = CandidateExamAttempt::factory()->create([
            'exam_id' => $exam->id,
            'exam_session_id' => $session->id,
        ]);

        $this->assertSame('draft', $exam->status);
        $this->assertSame('pending', $session->status);
        $this->assertSame('not_started', $attempt->status);
    }

    public function test_new_cbt_models_use_ulid_primary_keys(): void
    {
        $examType = ExamType::factory()->create();
        $subject = Subject::factory()->create();
        $topic = Topic::factory()->create(['subject_id' => $subject->id]);
        $questionBank = QuestionBank::factory()->create(['subject_id' => $subject->id]);
        $question = Question::factory()->create([
            'question_bank_id' => $questionBank->id,
            'subject_id' => $subject->id,
            'topic_id' => $topic->id,
        ]);

        foreach ([$examType, $subject, $topic, $questionBank, $question] as $model) {
            $this->assertMatchesRegularExpression('/^[0-9a-hjkmnp-tv-z]{26}$/', $model->id);
        }
    }

    public function test_core_relationships_can_be_persisted_and_loaded(): void
    {
        $organization = Organization::factory()->create();
        $center = Center::factory()->create();
        $creator = User::factory()->create(['organization_id' => $organization->id]);
        $examType = ExamType::factory()->create();
        $subject = Subject::factory()->create(['organization_id' => $organization->id]);
        $topic = Topic::factory()->create(['subject_id' => $subject->id]);
        $questionBank = QuestionBank::factory()->create([
            'organization_id' => $organization->id,
            'subject_id' => $subject->id,
            'created_by' => $creator->id,
        ]);
        $question = Question::factory()->create([
            'question_bank_id' => $questionBank->id,
            'subject_id' => $subject->id,
            'topic_id' => $topic->id,
            'created_by' => $creator->id,
        ]);
        $option = QuestionOption::factory()->create([
            'question_id' => $question->id,
            'label' => 'A',
            'display_order' => 1,
            'is_correct' => true,
        ]);
        $exam = Exam::factory()->create([
            'organization_id' => $organization->id,
            'exam_type_id' => $examType->id,
            'created_by' => $creator->id,
        ]);
        $examSubject = ExamSubject::factory()->create([
            'exam_id' => $exam->id,
            'subject_id' => $subject->id,
        ]);
        $session = ExamSession::factory()->create([
            'exam_id' => $exam->id,
            'center_id' => $center->id,
        ]);
        $candidate = Candidate::factory()->create(['organization_id' => $organization->id]);
        $attempt = CandidateExamAttempt::factory()->create([
            'candidate_id' => $candidate->id,
            'exam_id' => $exam->id,
            'exam_session_id' => $session->id,
            'center_id' => $center->id,
        ]);
        $answer = CandidateAnswer::factory()->create([
            'candidate_exam_attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'subject_id' => $subject->id,
            'selected_option_ids' => [$option->id],
        ]);
        $auditLog = ExamAuditLog::factory()->create([
            'exam_id' => $exam->id,
            'exam_session_id' => $session->id,
            'candidate_exam_attempt_id' => $attempt->id,
            'actor_user_id' => $creator->id,
        ]);
        $proctoringEvent = ProctoringEvent::factory()->create([
            'exam_id' => $exam->id,
            'exam_session_id' => $session->id,
            'candidate_exam_attempt_id' => $attempt->id,
            'candidate_id' => $candidate->id,
            'center_id' => $center->id,
        ]);

        $this->assertTrue($organization->exams()->whereKey($exam->id)->exists());
        $this->assertTrue($subject->topics()->whereKey($topic->id)->exists());
        $this->assertTrue($questionBank->questions()->whereKey($question->id)->exists());
        $this->assertTrue($question->options()->whereKey($option->id)->exists());
        $this->assertTrue($exam->examSubjects()->whereKey($examSubject->id)->exists());
        $this->assertTrue($exam->sessions()->whereKey($session->id)->exists());
        $this->assertTrue($candidate->attempts()->whereKey($attempt->id)->exists());
        $this->assertTrue($attempt->answers()->whereKey($answer->id)->exists());
        $this->assertTrue($exam->auditLogs()->whereKey($auditLog->id)->exists());
        $this->assertTrue($exam->proctoringEvents()->whereKey($proctoringEvent->id)->exists());
    }
}
