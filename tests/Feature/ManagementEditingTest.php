<?php

namespace Tests\Feature;

use App\Models\CandidateExamAttempt;
use App\Models\Course;
use App\Models\Department;
use App\Models\Exam;
use App\Models\Faculty;
use App\Models\Institution;
use App\Models\ProfessionalModule;
use App\Models\ProfessionalSchool;
use App\Models\Programme;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\User;
use App\Services\RecordDeletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ManagementEditingTest extends TestCase
{
    use RefreshDatabase;

    private function institution(): Institution
    {
        return Institution::create(['name' => 'Test institution', 'code' => 'INST', 'email' => 'institution@example.test', 'institution_type' => 'college', 'status' => 'active']);
    }

    public function test_institution_pages_and_owner_status_are_available_and_scoped(): void
    {
        $institution = $this->institution();
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $this->actingAs($admin)->get('/institutions/create')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Institutions/Create'));
        $this->get("/institutions/{$institution->id}/edit")->assertOk()->assertInertia(fn (Assert $page) => $page->component('Institutions/Edit'));
        $this->patch("/institutions/{$institution->id}/status", ['status' => 'inactive'])->assertSessionHasNoErrors();
        $this->assertSame('inactive', $institution->fresh()->status);
        $this->patch("/institutions/{$institution->id}/status", ['status' => 'active'])->assertSessionHasNoErrors();
        $this->assertSame('active', $institution->fresh()->status);
        $outsider = User::factory()->create(['role' => User::ROLE_INSTITUTION_ADMIN, 'institution_id' => null]);
        $this->actingAs($outsider)->patch("/institutions/{$institution->id}/status", ['status' => 'inactive'])->assertForbidden();
        $this->assertSame('active', $institution->fresh()->status);
    }

    public function test_referenced_faculty_cannot_be_deleted_or_detach_its_department(): void
    {
        $institution = $this->institution();
        $faculty = Faculty::create(['institution_id' => $institution->id, 'name' => 'Science', 'code' => 'SCI', 'status' => 'active']);
        $department = Department::create(['institution_id' => $institution->id, 'faculty_id' => $faculty->id, 'name' => 'Physics', 'code' => 'PHY', 'status' => 'active']);
        $admin = User::factory()->create(['role' => User::ROLE_INSTITUTION_ADMIN, 'institution_id' => $institution->id]);
        $this->actingAs($admin)->delete("/institutions/{$institution->id}/faculties/{$faculty->id}")->assertSessionHasErrors('record');
        $this->assertNotNull($faculty->fresh());
        $this->assertEquals($faculty->id, $department->fresh()->faculty_id);
    }

    public function test_professional_structure_can_be_edited_but_referenced_parents_cannot_be_deleted(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $school = ProfessionalSchool::create(['name' => 'Professional', 'code' => 'PRO', 'email' => 'pro@example.test', 'contact_person' => 'Admin', 'status' => 'active']);
        $programme = Programme::create(['professional_school_id' => $school->id, 'name' => 'Programme', 'code' => 'P', 'status' => 'active']);
        $course = Course::create(['professional_school_id' => $school->id, 'programme_id' => $programme->id, 'name' => 'Course', 'code' => 'C', 'status' => 'active']);
        $module = ProfessionalModule::create(['professional_school_id' => $school->id, 'programme_id' => $programme->id, 'course_id' => $course->id, 'name' => 'Module', 'code' => 'M', 'status' => 'active']);
        $base = "/professional-schools/{$school->id}";
        $this->actingAs($admin)->patch("$base/programmes/{$programme->id}", ['name' => 'Updated programme', 'code' => 'P', 'status' => 'inactive'])->assertSessionHasNoErrors();
        $this->patch("$base/courses/{$course->id}", ['name' => 'Updated course', 'code' => 'C', 'programme_id' => $programme->id, 'status' => 'active'])->assertSessionHasNoErrors();
        $this->patch("$base/modules/{$module->id}", ['name' => 'Updated module', 'code' => 'M', 'course_id' => $course->id, 'status' => 'active'])->assertSessionHasNoErrors();
        $this->assertSame('Updated module', $module->fresh()->name);
        $this->delete("$base/programmes/{$programme->id}")->assertSessionHasErrors('record');
        $this->delete("$base/courses/{$course->id}")->assertSessionHasErrors('record');
        $this->assertEquals($programme->id, $course->fresh()->programme_id);
        $this->assertEquals($course->id, $module->fresh()->course_id);
        $other = ProfessionalSchool::create(['name' => 'Other', 'code' => 'OTHER', 'email' => 'other@example.test', 'contact_person' => 'Other', 'status' => 'active']);
        $this->patch("/professional-schools/{$other->id}/modules/{$module->id}", ['name' => 'Wrong owner', 'status' => 'active', 'course_id' => $course->id])->assertNotFound();
        $this->delete("$base/modules/{$module->id}")->assertSessionHasNoErrors();
        $this->assertNull($module->fresh());
    }

    public function test_deleting_attempted_assessment_preserves_attempts(): void
    {
        $exam = Exam::factory()->create(['exam_category' => 'assessment']);
        $attempt = CandidateExamAttempt::factory()->create(['exam_id' => $exam->id, 'status' => 'submitted']);
        try {
            app(RecordDeletionService::class)->delete($exam);
            $this->fail('Historical exam deletion must be blocked.');
        } catch (ValidationException $e) {
            $this->assertNotNull($exam->fresh());
            $this->assertNotNull($attempt->fresh());
        }
    }

    public function test_unused_question_can_be_soft_deleted_with_its_options_retained(): void
    {
        $question = Question::factory()->create();
        $option = QuestionOption::factory()->create(['question_id' => $question->id]);
        app(RecordDeletionService::class)->delete($question);
        $this->assertSoftDeleted('questions', ['id' => $question->id]);
        $this->assertDatabaseHas('question_options', ['id' => $option->id, 'question_id' => $question->id]);
    }

    public function test_soft_deleted_dependants_still_protect_the_parent(): void
    {
        $exam = Exam::factory()->create();
        $attempt = CandidateExamAttempt::factory()->create(['exam_id' => $exam->id]);
        $attempt->delete();
        $this->expectException(ValidationException::class);
        app(RecordDeletionService::class)->delete($exam);
    }
}
