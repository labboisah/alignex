<?php

namespace Tests\Feature;

use App\Http\Resources\CandidatePaperResource;
use App\Models\Candidate;
use App\Models\CandidateExamAttempt;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\Organization;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\QuestionOption;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExamPaperGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_preview_and_generate_candidate_specific_papers(): void
    {
        [$admin, $exam, $candidate] = $this->examWithCandidateAndQuestions();

        $this->actingAs($admin)
            ->get("/exams/{$exam->id}/papers")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ExamPapers/Show')
                ->where('preview.assigned_candidates', 1)
                ->where('preview.required_questions', 2)
                ->where('preview.subjects.0.available_questions', 3)
            );

        $this->actingAs($admin)
            ->post("/exams/{$exam->id}/papers/generate")
            ->assertRedirect();

        $attempt = CandidateExamAttempt::query()
            ->where('exam_id', $exam->id)
            ->where('candidate_id', $candidate->id)
            ->firstOrFail();

        $this->assertSame(CandidateExamAttempt::STATUS_NOT_STARTED, $attempt->status);
        $this->assertSame(2, $attempt->total_questions);
        $this->assertDatabaseCount('candidate_papers', 2);

        $this->actingAs($admin)
            ->post("/exams/{$exam->id}/papers/generate")
            ->assertRedirect();

        $this->assertDatabaseCount('candidate_papers', 2);
    }

    public function test_generation_is_blocked_after_exam_starts(): void
    {
        [$admin, $exam] = $this->examWithCandidateAndQuestions();
        $exam->update(['starts_at' => now()->subMinute()]);

        $this->actingAs($admin)
            ->post("/exams/{$exam->id}/papers/generate")
            ->assertSessionHasErrors('exam');
    }

    public function test_candidate_paper_resource_does_not_expose_correct_answers(): void
    {
        [$admin, $exam] = $this->examWithCandidateAndQuestions();

        $this->actingAs($admin)->post("/exams/{$exam->id}/papers/generate");

        $paper = CandidateExamAttempt::query()
            ->where('exam_id', $exam->id)
            ->firstOrFail()
            ->papers()
            ->with('question.options')
            ->firstOrFail();

        $payload = CandidatePaperResource::make($paper)->resolve();

        $this->assertArrayHasKey('options', $payload);
        $this->assertArrayNotHasKey('is_correct', $payload['options'][0]);
    }

    private function examWithCandidateAndQuestions(): array
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->create([
            'role' => User::ROLE_ORGANIZATION_ADMIN,
            'organization_id' => $organization->id,
        ]);
        $subject = Subject::factory()->create([
            'organization_id' => $organization->id,
            'school_id' => null,
            'center_id' => null,
        ]);
        $exam = Exam::factory()->create([
            'organization_id' => $organization->id,
            'school_id' => null,
            'center_id' => null,
            'starts_at' => now()->addDay(),
            'settings' => [
                'shuffle_questions' => true,
                'shuffle_options' => true,
            ],
        ]);
        ExamSubject::factory()->create([
            'exam_id' => $exam->id,
            'subject_id' => $subject->id,
            'question_count' => 2,
            'marks_per_question' => 1,
            'total_marks' => 2,
            'difficulty_distribution' => ['easy' => 1, 'medium' => 1],
            'selection_rules' => null,
        ]);
        $candidate = Candidate::factory()->create([
            'organization_id' => $organization->id,
            'school_id' => null,
            'center_id' => null,
        ]);
        $exam->candidates()->attach($candidate->id, ['status' => 'assigned']);
        $bank = QuestionBank::factory()->create([
            'organization_id' => $organization->id,
            'school_id' => null,
            'center_id' => null,
            'subject_id' => $subject->id,
        ]);

        foreach ([
            'easy' => Question::STATUS_DRAFT,
            'medium' => Question::STATUS_REVIEW,
            'hard' => Question::STATUS_APPROVED,
        ] as $difficulty => $status) {
            $question = Question::factory()->create([
                'question_bank_id' => $bank->id,
                'subject_id' => $subject->id,
                'topic_id' => null,
                'difficulty' => $difficulty,
                'marks' => 1,
                'status' => $status,
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

        return [$admin, $exam, $candidate];
    }
}
