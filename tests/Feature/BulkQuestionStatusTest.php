<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkQuestionStatusTest extends TestCase
{
    use RefreshDatabase;

    private function setupBank(): array
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organization->id, 'role' => User::ROLE_ORGANIZATION_ADMIN]);
        $bank = QuestionBank::factory()->create(['organization_id' => $organization->id, 'school_id' => null]);
        $questions = Question::factory()->count(3)->create(['question_bank_id' => $bank->id, 'subject_id' => null, 'topic_id' => null, 'status' => 'draft']);

        return [$user, $questions];
    }

    public function test_selected_questions_support_every_status_without_changing_unselected_content(): void
    {
        [$user, $questions] = $this->setupBank();
        $originalStem = $questions[0]->stem;
        foreach (['review', 'approved', 'rejected', 'archived', 'draft'] as $status) {
            $this->actingAs($user)->from('/questions')->patch('/questions/bulk-status', [
                'question_ids' => [$questions[0]->id, $questions[1]->id], 'status' => $status,
            ])->assertRedirect('/questions')->assertSessionHasNoErrors();
            $this->assertSame($status, $questions[0]->fresh()->status);
            $this->assertSame($status, $questions[1]->fresh()->status);
            $this->assertSame('draft', $questions[2]->fresh()->status);
            $this->assertSame($originalStem, $questions[0]->fresh()->stem);
        }
    }

    public function test_cross_owner_selection_is_denied_without_partial_changes(): void
    {
        [$user, $questions] = $this->setupBank();
        [, $other] = $this->setupBank();
        $this->actingAs($user)->patch('/questions/bulk-status', [
            'question_ids' => [$questions[0]->id, $other[0]->id], 'status' => 'approved',
        ])->assertForbidden();
        $this->assertSame('draft', $questions[0]->fresh()->status);
        $this->assertSame('draft', $other[0]->fresh()->status);
    }

    public function test_missing_and_deleted_selections_do_not_partially_update(): void
    {
        [$user, $questions] = $this->setupBank();
        $questions[1]->delete();
        foreach ([$questions[1]->id, 'missing-question'] as $unavailable) {
            $this->actingAs($user)->patch('/questions/bulk-status', [
                'question_ids' => [$questions[0]->id, $unavailable], 'status' => 'approved',
            ])->assertSessionHasErrors('question_ids');
            $this->assertSame('draft', $questions[0]->fresh()->status);
        }
    }

    public function test_invalid_status_empty_duplicate_and_guest_requests_are_rejected(): void
    {
        [$user, $questions] = $this->setupBank();
        $this->patch('/questions/bulk-status', ['question_ids' => [$questions[0]->id], 'status' => 'approved'])->assertRedirect('/login');
        $this->actingAs($user)->patch('/questions/bulk-status', ['question_ids' => [$questions[0]->id], 'status' => 'published'])->assertSessionHasErrors('status');
        $this->patch('/questions/bulk-status', ['question_ids' => [], 'status' => 'approved'])->assertSessionHasErrors('question_ids');
        $this->patch('/questions/bulk-status', ['question_ids' => [$questions[0]->id, $questions[0]->id], 'status' => 'approved'])->assertSessionHasErrors('question_ids.0');
        $this->assertSame('draft', $questions[0]->fresh()->status);
    }
}
