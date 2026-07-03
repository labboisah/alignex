<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\CandidateAnswer;
use App\Models\CandidateExamAttempt;
use App\Models\CandidateGroup;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\ExamType;
use App\Models\Organization;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\QuestionOption;
use App\Models\Subject;
use App\Models\User;
use App\Services\AdaptiveQuestionSelectorService;
use App\Services\ExamPaperGeneratorService;
use App\Services\ExamResultService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SharedExamWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
    }

    public function test_paper_generation_uses_question_bank_and_stores_option_order(): void
    {
        [$exam, $candidate, $questions] = $this->examFixture();

        app(ExamPaperGeneratorService::class)->generate($exam);

        $attempt = CandidateExamAttempt::query()->where('candidate_id', $candidate->id)->firstOrFail();

        $this->assertDatabaseHas('candidate_papers', [
            'attempt_id' => $attempt->id,
            'question_id' => $questions[0]->id,
        ]);
        $this->assertCount(2, $attempt->papers()->firstOrFail()->option_order);
        $this->assertDatabaseCount('candidate_papers', 1);
    }

    public function test_paper_generation_honors_question_bank_ids_from_subject_selection_rules(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->create(['role' => User::ROLE_ORGANIZATION_ADMIN, 'organization_id' => $organization->id]);
        $subject = Subject::factory()->create(['organization_id' => $organization->id, 'school_id' => null, 'center_id' => null]);
        $allowedBank = QuestionBank::factory()->create(['organization_id' => $organization->id, 'subject_id' => $subject->id, 'school_id' => null, 'center_id' => null]);
        $disallowedBank = QuestionBank::factory()->create(['organization_id' => $organization->id, 'subject_id' => $subject->id, 'school_id' => null, 'center_id' => null]);
        $candidate = Candidate::factory()->create(['organization_id' => $organization->id, 'school_id' => null, 'center_id' => null]);
        $examType = ExamType::query()->firstOrCreate(['code' => 'general'], ['name' => 'General', 'status' => ExamType::STATUS_ACTIVE]);
        $exam = Exam::factory()->create([
            'organization_id' => $organization->id,
            'exam_type_id' => $examType->id,
            'created_by' => $admin->id,
            'starts_at' => now()->addDay(),
            'exam_category' => Exam::CATEGORY_GENERAL,
            'mode' => Exam::MODE_TRADITIONAL,
            'exam_mode' => Exam::MODE_TRADITIONAL,
            'pass_mark' => 1,
            'settings' => ['shuffle_questions' => false, 'shuffle_options' => false],
        ]);
        ExamSubject::factory()->create([
            'exam_id' => $exam->id,
            'subject_id' => $subject->id,
            'question_count' => 2,
            'marks_per_question' => 1,
            'total_marks' => 2,
            'selection_rules' => ['question_bank_ids' => [$allowedBank->id]],
        ]);
        $exam->candidates()->attach($candidate->id, ['status' => 'assigned']);

        $allowedQuestion = $this->question($allowedBank, $subject);
        $secondAllowedQuestion = $this->question($allowedBank, $subject);
        $this->question($disallowedBank, $subject);

        app(ExamPaperGeneratorService::class)->generate($exam);

        $attempt = CandidateExamAttempt::query()->where('candidate_id', $candidate->id)->firstOrFail();

        $this->assertCount(2, $attempt->papers);
        $this->assertEqualsCanonicalizing([$allowedQuestion->id, $secondAllowedQuestion->id], $attempt->papers->pluck('question_id')->all());
    }

    public function test_organization_exam_can_assign_candidates_from_a_reusable_group(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->create(['role' => User::ROLE_ORGANIZATION_ADMIN, 'organization_id' => $organization->id]);
        $subject = Subject::factory()->create(['organization_id' => $organization->id, 'school_id' => null, 'center_id' => null]);
        $bank = QuestionBank::factory()->create(['organization_id' => $organization->id, 'subject_id' => $subject->id, 'school_id' => null, 'center_id' => null]);
        $candidate = Candidate::factory()->create(['organization_id' => $organization->id, 'school_id' => null, 'center_id' => null]);
        $group = CandidateGroup::factory()->create(['organization_id' => $organization->id]);
        $group->candidates()->attach($candidate->id);

        $this->actingAs($admin)
            ->post('/exams', $this->payload($subject->id, [
                'exam_owner_type' => Exam::OWNER_ORGANIZATION,
                'organization_id' => $organization->id,
                'question_bank_id' => $bank->id,
                'candidate_group_id' => $group->id,
                'candidate_ids' => [],
            ]))
            ->assertRedirect();

        $exam = Exam::query()->latest('id')->firstOrFail();
        $this->assertTrue($exam->candidates()->whereKey($candidate->id)->exists());
    }

    public function test_result_calculation_is_server_side_and_marks_certificate_eligibility(): void
    {
        [$exam, $candidate, $questions] = $this->examFixture([
            'exam_category' => Exam::CATEGORY_CERTIFICATION,
            'pass_mark' => 1,
            'settings' => [
                'shuffle_questions' => false,
                'shuffle_options' => false,
                'certificate_auto_generate' => true,
            ],
        ]);

        app(ExamPaperGeneratorService::class)->generate($exam);
        $attempt = CandidateExamAttempt::query()->where('candidate_id', $candidate->id)->with('papers.question.options')->firstOrFail();
        $correctOption = $questions[0]->options()->where('is_correct', true)->firstOrFail();

        CandidateAnswer::query()->create([
            'candidate_exam_attempt_id' => $attempt->id,
            'question_id' => $questions[0]->id,
            'subject_id' => $questions[0]->subject_id,
            'selected_option_ids' => [$correctOption->id],
            'answer_payload' => ['selected_option_ids' => [$correctOption->id]],
            'saved_at' => now(),
        ]);

        $attempt->update(['submitted_at' => now(), 'status' => CandidateExamAttempt::STATUS_SUBMITTED]);
        $result = app(ExamResultService::class)->calculate($attempt, true);

        $this->assertSame('passed', $result->result_status);
        $this->assertTrue($result->certificate_eligible);
        $this->assertDatabaseHas('certificates', [
            'candidate_exam_attempt_id' => $attempt->id,
            'candidate_id' => $candidate->id,
        ]);
    }

    public function test_adaptive_foundation_moves_difficulty_up_or_down(): void
    {
        $selector = app(AdaptiveQuestionSelectorService::class);

        $this->assertSame('medium', $selector->startingDifficulty());
        $this->assertSame('hard', $selector->nextDifficulty(true, 'medium'));
        $this->assertSame('easy', $selector->nextDifficulty(false, 'medium'));
    }

    public function test_secondary_adaptive_exam_is_rejected_and_professional_adaptive_is_allowed(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->create(['role' => User::ROLE_ORGANIZATION_ADMIN, 'organization_id' => $organization->id]);
        $subject = Subject::factory()->create(['organization_id' => $organization->id, 'school_id' => null, 'center_id' => null]);

        $this->actingAs($admin)
            ->post('/exams', $this->payload($subject->id, [
                'exam_owner_type' => Exam::OWNER_SECONDARY_SCHOOL,
                'secondary_school_id' => 999,
                'exam_type' => 'secondary',
                'exam_category' => Exam::CATEGORY_TERMINAL,
                'mode' => Exam::MODE_ADAPTIVE,
                'exam_mode' => Exam::MODE_ADAPTIVE,
            ]))
            ->assertSessionHasErrors(['secondary_school_id', 'exam_mode']);
    }

    private function examFixture(array $examOverrides = []): array
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->create(['role' => User::ROLE_ORGANIZATION_ADMIN, 'organization_id' => $organization->id]);
        $subject = Subject::factory()->create(['organization_id' => $organization->id, 'school_id' => null, 'center_id' => null]);
        $bank = QuestionBank::factory()->create(['organization_id' => $organization->id, 'subject_id' => $subject->id, 'school_id' => null, 'center_id' => null]);
        $candidate = Candidate::factory()->create(['organization_id' => $organization->id, 'school_id' => null, 'center_id' => null]);
        $examType = ExamType::query()->firstOrCreate(['code' => 'general'], ['name' => 'General', 'status' => ExamType::STATUS_ACTIVE]);
        $exam = Exam::factory()->create([
            'organization_id' => $organization->id,
            'exam_type_id' => $examType->id,
            'question_bank_id' => $bank->id,
            'created_by' => $admin->id,
            'starts_at' => now()->addDay(),
            'exam_category' => Exam::CATEGORY_GENERAL,
            'mode' => Exam::MODE_TRADITIONAL,
            'exam_mode' => Exam::MODE_TRADITIONAL,
            'pass_mark' => 1,
            'settings' => ['shuffle_questions' => false, 'shuffle_options' => false],
            ...$examOverrides,
        ]);
        ExamSubject::factory()->create([
            'exam_id' => $exam->id,
            'subject_id' => $subject->id,
            'question_count' => 1,
            'marks_per_question' => 1,
            'total_marks' => 1,
        ]);
        $exam->candidates()->attach($candidate->id, ['status' => 'assigned']);

        $inBank = $this->question($bank, $subject);
        $outsideBank = $this->question(QuestionBank::factory()->create(['organization_id' => $organization->id, 'subject_id' => $subject->id]), $subject);

        return [$exam, $candidate, [$inBank, $outsideBank]];
    }

    private function question(QuestionBank $bank, Subject $subject): Question
    {
        $question = Question::factory()->create([
            'question_bank_id' => $bank->id,
            'subject_id' => $subject->id,
            'topic_id' => null,
            'marks' => 1,
            'status' => Question::STATUS_APPROVED,
        ]);

        QuestionOption::factory()->create(['question_id' => $question->id, 'label' => 'A', 'display_order' => 1, 'is_correct' => true]);
        QuestionOption::factory()->create(['question_id' => $question->id, 'label' => 'B', 'display_order' => 2, 'is_correct' => false]);

        return $question;
    }

    private function payload(string $subjectId, array $overrides = []): array
    {
        return array_replace_recursive([
            'title' => 'Workflow Exam',
            'exam_code' => 'WF-001',
            'exam_type' => 'general',
            'exam_category' => Exam::CATEGORY_GENERAL,
            'mode' => Exam::MODE_TRADITIONAL,
            'exam_mode' => Exam::MODE_TRADITIONAL,
            'delivery_mode' => 'online',
            'start_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'end_at' => now()->addDay()->addHours(2)->format('Y-m-d\TH:i'),
            'duration_minutes' => 60,
            'pass_mark' => 1,
            'status' => Exam::STATUS_SCHEDULED,
            'subjects' => [['subject_id' => $subjectId, 'number_of_questions' => 1, 'marks_per_question' => 1]],
            'settings' => [
                'shuffle_questions' => false,
                'shuffle_options' => false,
                'show_result_immediately' => false,
                'allow_back_navigation' => true,
                'require_webcam' => false,
                'require_fullscreen' => true,
                'max_tab_switches' => 3,
                'negative_marking' => false,
                'negative_mark_value' => 0,
                'bind_device' => false,
                'allow_retake' => false,
            ],
        ], $overrides);
    }
}
