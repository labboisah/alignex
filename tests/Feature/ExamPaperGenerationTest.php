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

    public function test_selected_difficulty_excludes_other_questions(): void
    {
        [$admin, $exam] = $this->examWithCandidateAndQuestions();
        foreach (['easy', 'medium', 'hard'] as $difficulty) {
            $exam->examSubjects()->update(['question_count' => 1, 'difficulty_distribution' => [$difficulty => 1]]);
            $this->actingAs($admin)->post("/exams/{$exam->id}/papers/generate")->assertSessionHasNoErrors();
            $attempt = $exam->attempts()->firstOrFail();
            $this->assertSame([$difficulty], $attempt->papers()->with('question')->get()->pluck('question.difficulty')->all());
            $attempt->papers()->delete();
        }
    }

    public function test_insufficient_selected_difficulty_does_not_fall_back_to_other_questions(): void
    {
        [$admin, $exam] = $this->examWithCandidateAndQuestions();
        $exam->examSubjects()->update(['difficulty_distribution' => ['easy' => 2]]);
        $this->actingAs($admin)->post("/exams/{$exam->id}/papers/generate")->assertSessionHasErrors('questions');
        $this->assertDatabaseCount('candidate_papers', 0);
    }

    public function test_configured_marks_are_snapshotted_used_for_scoring_and_shown_to_candidates(): void
    {
        [$admin, $exam] = $this->examWithCandidateAndQuestions();
        $exam->examSubjects()->update(['marks_per_question' => 2.5, 'total_marks' => 5]);
        $exam->update(['total_marks' => 5, 'pass_mark' => 3]);
        $this->actingAs($admin)->post("/exams/{$exam->id}/papers/generate")->assertSessionHasNoErrors();
        $attempt = $exam->attempts()->with('papers.question.options')->firstOrFail();
        $this->assertEquals(5, $attempt->total_marks);
        $this->assertSame(['2.50', '2.50'], $attempt->papers->pluck('marks')->all());

        $paper = $attempt->papers->first();
        $this->assertSame(2.5, CandidatePaperResource::make($paper)->resolve()['marks']);
        $paper->question->update(['marks' => 99]);
        $answer = $attempt->answers()->create([
            'question_id' => $paper->question_id,
            'selected_option_ids' => $paper->question->options->where('is_correct', true)->pluck('id')->all(),
        ]);
        $result = app(\App\Services\ExamResultService::class)->calculate($attempt);
        $this->assertEquals(2.5, $result->score);
        $this->assertEquals(5, $result->total_marks);
        $this->assertEquals(50, $result->percentage);
        $this->assertSame('failed', $result->result_status);
        $this->assertEquals(2.5, $answer->fresh()->score_awarded);
    }

    public function test_legacy_papers_keep_question_mark_scoring(): void
    {
        [$admin, $exam] = $this->examWithCandidateAndQuestions();
        $this->actingAs($admin)->post("/exams/{$exam->id}/papers/generate")->assertSessionHasNoErrors();
        $attempt = $exam->attempts()->firstOrFail();
        $attempt->papers()->update(['marks' => null]);
        $paper = $attempt->papers()->with('question.options')->firstOrFail();
        $answer = $attempt->answers()->create([
            'question_id' => $paper->question_id,
            'selected_option_ids' => $paper->question->options->where('is_correct', true)->pluck('id')->all(),
        ]);
        $this->assertEquals($paper->question->marks, app(\App\Services\ExamResultService::class)->scoreAnswer($attempt, $answer));
        $this->assertEquals($paper->question->marks, CandidatePaperResource::make($paper)->resolve()['marks']);
    }

    public function test_readiness_is_read_only_and_requires_complete_matching_papers(): void
    {
        [$admin, $exam] = $this->examWithCandidateAndQuestions();
        $exam->update(['total_marks' => 2, 'pass_mark' => 1, 'duration_minutes' => 60, 'ends_at' => now()->addDay()->addHours(2), 'status' => Exam::STATUS_DRAFT]);
        $service = app(\App\Services\ExamReadinessService::class);
        $before = $exam->participants()->count();
        $readiness = $service->inspect($exam);
        $this->assertFalse($readiness['ready']);
        $this->assertSame($before, $exam->participants()->count());
        $this->assertFalse(collect($readiness['checks'])->firstWhere('id', 'papers')['ready']);

        $this->actingAs($admin)->post("/exams/{$exam->id}/papers/generate")->assertSessionHasNoErrors();
        $this->assertTrue($service->inspect($exam->fresh())['ready']);
        $this->actingAs($admin)->get("/exams/{$exam->id}")->assertOk()->assertInertia(fn (Assert $page) => $page->where('readiness.ready', true));
        $row = $exam->examSubjects()->firstOrFail();
        $payload = [
            'title' => $exam->title, 'exam_code' => $exam->code, 'exam_type' => 'secondary',
            'mode' => 'traditional', 'delivery_mode' => 'online', 'status' => Exam::STATUS_SCHEDULED,
            'start_at' => $exam->starts_at->format('Y-m-d H:i:s'), 'end_at' => $exam->ends_at->format('Y-m-d H:i:s'),
            'duration_minutes' => 60, 'pass_mark' => 1, 'question_bank_id' => $exam->question_bank_id,
            'candidate_ids' => $exam->candidates->pluck('id')->all(),
            'subjects' => [['subject_id' => $row->subject_id, 'question_bank_id' => $row->question_bank_id,
                'number_of_questions' => 2, 'marks_per_question' => 1, 'difficulty_distribution' => ['easy' => 1, 'medium' => 1]]],
            'settings' => ['shuffle_questions' => true, 'shuffle_options' => true, 'show_result_immediately' => false,
                'allow_back_navigation' => true, 'require_webcam' => false, 'require_fullscreen' => true,
                'max_tab_switches' => 3, 'negative_marking' => false, 'bind_device' => false, 'allow_retake' => false],
        ];
        $this->actingAs($admin)->patch("/exams/{$exam->id}", $payload)->assertSessionHasNoErrors();
        $this->assertSame(Exam::STATUS_SCHEDULED, $exam->fresh()->status);
        $payload['subjects'][0]['difficulty_distribution'] = ['hard' => 2];
        $this->actingAs($admin)->patch("/exams/{$exam->id}", $payload)->assertSessionHasErrors('status');
        $this->assertSame(['easy' => 1, 'medium' => 1], $exam->examSubjects()->firstOrFail()->difficulty_distribution);
        $exam->update(['pass_mark' => 3]);
        $this->assertFalse(collect($service->inspect($exam->fresh())['checks'])->firstWhere('id', 'marks')['ready']);
        $exam->update(['pass_mark' => 1, 'duration_minutes' => 300]);
        $this->assertFalse(collect($service->inspect($exam->fresh())['checks'])->firstWhere('id', 'timing')['ready']);
        $exam->update(['duration_minutes' => 60]);
        $exam->attempts()->firstOrFail()->papers()->firstOrFail()->delete();
        $this->assertFalse(collect($service->inspect($exam->fresh())['checks'])->firstWhere('id', 'papers')['ready']);
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

        $exam->update(['question_bank_id' => $bank->id]);
        $exam->examSubjects()->update(['question_bank_id' => $bank->id]);

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
