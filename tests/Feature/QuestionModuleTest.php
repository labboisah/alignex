<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class QuestionModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_organization_admin_can_create_preview_edit_and_delete_question_with_options(): void
    {
        Storage::fake('public');

        $organization = Organization::factory()->create();
        $admin = User::factory()->create([
            'role' => User::ROLE_ORGANIZATION_ADMIN,
            'organization_id' => $organization->id,
        ]);
        $subject = Subject::factory()->create(['organization_id' => $organization->id, 'school_id' => null]);
        $topic = Topic::factory()->create(['subject_id' => $subject->id]);
        $bank = QuestionBank::factory()->create([
            'organization_id' => $organization->id,
            'school_id' => null,
            'subject_id' => $subject->id,
        ]);

        $this->actingAs($admin)
            ->post('/questions', [
                'question_bank_id' => $bank->id,
                'subject_id' => $subject->id,
                'topic_id' => $topic->id,
                'difficulty' => 'medium',
                'marks' => '2',
                'stem' => 'What is 2 + 2?',
                'image' => UploadedFile::fake()->image('question.png'),
                'explanation' => 'Two pairs make four.',
                'status' => Question::STATUS_DRAFT,
                'options' => [
                    ['label' => 'A', 'option_text' => '3', 'is_correct' => false],
                    ['label' => 'B', 'option_text' => '4', 'is_correct' => true],
                    ['label' => 'C', 'option_text' => '', 'is_correct' => false],
                    ['label' => 'D', 'option_text' => '', 'is_correct' => false],
                    ['label' => 'E', 'option_text' => '', 'is_correct' => false],
                ],
            ])
            ->assertRedirect('/questions');

        $question = Question::query()->where('stem', 'What is 2 + 2?')->firstOrFail();
        $question->load('options');
        $this->assertNotNull($question->image_path);
        $this->assertCount(2, $question->options);
        $this->assertTrue($question->options()->where('label', 'B')->firstOrFail()->is_correct);

        $this->actingAs($admin)
            ->get("/questions/{$question->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Questions/Show')
                ->where('question.data.options.1.is_correct', true)
            );

        $this->actingAs($admin)
            ->post("/questions/{$question->id}", [
                '_method' => 'patch',
                'question_bank_id' => $bank->id,
                'subject_id' => $subject->id,
                'topic_id' => $topic->id,
                'difficulty' => 'hard',
                'marks' => '3',
                'stem' => 'Updated question?',
                'remove_image' => true,
                'explanation' => 'Updated explanation.',
                'status' => Question::STATUS_REVIEW,
                'options' => [
                    ['label' => 'A', 'option_text' => 'Alpha', 'is_correct' => true],
                    ['label' => 'B', 'option_text' => 'Beta', 'is_correct' => false],
                ],
            ])
            ->assertRedirect(route('questions.show', $question, absolute: false));

        $question->refresh();
        $this->assertNull($question->image_path);
        $this->assertSame(Question::STATUS_REVIEW, $question->status);

        $this->actingAs($admin)->delete("/questions/{$question->id}")->assertRedirect('/questions');
        $this->assertSoftDeleted('questions', ['id' => $question->id]);
    }

    public function test_question_requires_two_options_and_one_correct_answer(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->create([
            'role' => User::ROLE_ORGANIZATION_ADMIN,
            'organization_id' => $organization->id,
        ]);
        $subject = Subject::factory()->create(['organization_id' => $organization->id, 'school_id' => null]);
        $bank = QuestionBank::factory()->create([
            'organization_id' => $organization->id,
            'school_id' => null,
            'subject_id' => $subject->id,
        ]);

        $this->actingAs($admin)
            ->post('/questions', [
                'question_bank_id' => $bank->id,
                'subject_id' => $subject->id,
                'difficulty' => 'easy',
                'marks' => '1',
                'stem' => 'Incomplete?',
                'status' => Question::STATUS_DRAFT,
                'options' => [
                    ['label' => 'A', 'option_text' => 'Only one', 'is_correct' => false],
                    ['label' => 'B', 'option_text' => '', 'is_correct' => false],
                ],
            ])
            ->assertSessionHasErrors('options');
    }

    public function test_question_bank_scope_is_enforced(): void
    {
        $ownOrganization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();
        $admin = User::factory()->create([
            'role' => User::ROLE_ORGANIZATION_ADMIN,
            'organization_id' => $ownOrganization->id,
        ]);
        $subject = Subject::factory()->create(['organization_id' => $otherOrganization->id, 'school_id' => null]);
        $bank = QuestionBank::factory()->create([
            'organization_id' => $otherOrganization->id,
            'school_id' => null,
            'subject_id' => $subject->id,
        ]);

        $this->actingAs($admin)
            ->post('/questions', [
                'question_bank_id' => $bank->id,
                'subject_id' => $subject->id,
                'difficulty' => 'easy',
                'marks' => '1',
                'stem' => 'Out of scope?',
                'status' => Question::STATUS_DRAFT,
                'options' => [
                    ['label' => 'A', 'option_text' => 'Yes', 'is_correct' => true],
                    ['label' => 'B', 'option_text' => 'No', 'is_correct' => false],
                ],
            ])
            ->assertSessionHasErrors('question_bank_id');
    }

    public function test_questions_and_options_can_be_imported_from_csv_template(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->create([
            'role' => User::ROLE_ORGANIZATION_ADMIN,
            'organization_id' => $organization->id,
        ]);
        $subject = Subject::factory()->create([
            'organization_id' => $organization->id,
            'school_id' => null,
            'code' => 'MATH',
        ]);
        Topic::factory()->create([
            'subject_id' => $subject->id,
            'code' => 'ALG',
        ]);
        QuestionBank::factory()->create([
            'organization_id' => $organization->id,
            'school_id' => null,
            'subject_id' => $subject->id,
            'code' => 'MATH-MAIN',
        ]);

        $csv = UploadedFile::fake()->createWithContent(
            'questions.csv',
            "difficulty,marks,question_text,explanation,status,option_a,option_b,option_c,option_d,option_e,correct_answer\nmedium,1,What is 2 + 2?,Two pairs make four.,draft,3,4,5,,,B\n"
        );

        $this->actingAs($admin)
            ->post('/questions/import', [
                'subject_id' => $subject->id,
                'question_bank_id' => QuestionBank::query()->where('code', 'MATH-MAIN')->firstOrFail()->id,
                'topic_id' => Topic::query()->where('code', 'ALG')->firstOrFail()->id,
                'file' => $csv,
            ])
            ->assertRedirect();

        $question = Question::query()->where('stem', 'What is 2 + 2?')->firstOrFail();

        $this->assertSame('MATH', $question->subject->code);
        $this->assertCount(3, $question->options);
        $this->assertTrue($question->options()->where('label', 'B')->firstOrFail()->is_correct);
    }
}
