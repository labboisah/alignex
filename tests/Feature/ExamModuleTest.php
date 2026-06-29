<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\Organization;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExamModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_organization_admin_can_create_view_edit_and_cancel_exam(): void
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

        $payload = $this->payload($subject->id);

        $this->actingAs($admin)
            ->post('/exams', $payload)
            ->assertRedirect();

        $exam = Exam::query()->where('code', 'TERM-001')->firstOrFail();

        $this->assertSame($organization->id, $exam->organization_id);
        $this->assertSame('traditional', $exam->mode);
        $this->assertSame('online', $exam->delivery_mode);
        $this->assertEquals(50, (float) $exam->total_marks);
        $this->assertTrue($exam->settings['shuffle_questions']);
        $this->assertDatabaseHas('exam_subjects', [
            'exam_id' => $exam->id,
            'subject_id' => $subject->id,
            'question_count' => 25,
        ]);

        $this->actingAs($admin)
            ->get("/exams/{$exam->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Exams/Show')
                ->where('exam.data.exam_code', 'TERM-001')
                ->where('exam.data.subjects.0.number_of_questions', 25)
            );

        $updated = $this->payload($subject->id, [
            'title' => 'Updated Exam',
            'exam_code' => 'TERM-002',
            'pass_mark' => 30,
            'subjects' => [
                [
                    'subject_id' => $subject->id,
                    'number_of_questions' => 20,
                    'marks_per_question' => 3,
                    'duration_minutes' => 45,
                ],
            ],
        ]);

        $this->actingAs($admin)
            ->patch("/exams/{$exam->id}", $updated)
            ->assertRedirect(route('exams.show', $exam, absolute: false));

        $exam->refresh();
        $this->assertSame('Updated Exam', $exam->title);
        $this->assertEquals(60, (float) $exam->total_marks);

        $this->actingAs($admin)
            ->patch("/exams/{$exam->id}/cancel")
            ->assertRedirect();

        $this->assertSame(Exam::STATUS_CANCELLED, $exam->refresh()->status);
    }

    public function test_exam_subjects_must_belong_to_actor_scope(): void
    {
        $ownOrganization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();
        $admin = User::factory()->create([
            'role' => User::ROLE_ORGANIZATION_ADMIN,
            'organization_id' => $ownOrganization->id,
        ]);
        $outsideSubject = Subject::factory()->create([
            'organization_id' => $otherOrganization->id,
            'school_id' => null,
            'center_id' => null,
        ]);

        $this->actingAs($admin)
            ->post('/exams', $this->payload($outsideSubject->id))
            ->assertSessionHasErrors('subjects');
    }

    private function payload(string $subjectId, array $overrides = []): array
    {
        return array_replace_recursive([
            'title' => 'First Term Exam',
            'exam_code' => 'TERM-001',
            'exam_type' => 'secondary',
            'mode' => 'traditional',
            'delivery_mode' => 'online',
            'start_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'end_at' => now()->addDay()->addHours(2)->format('Y-m-d\TH:i'),
            'duration_minutes' => 90,
            'pass_mark' => 25,
            'status' => Exam::STATUS_SCHEDULED,
            'subjects' => [
                [
                    'subject_id' => $subjectId,
                    'number_of_questions' => 25,
                    'marks_per_question' => 2,
                    'duration_minutes' => null,
                ],
            ],
            'settings' => [
                'shuffle_questions' => true,
                'shuffle_options' => true,
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
