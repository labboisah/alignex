<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Organization;
use App\Models\PricingPlan;
use App\Models\ProfessionalSchool;
use App\Models\Programme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProfessionalFacilitatorManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_professional_school_admin_manages_facilitators_with_page_based_course_checkboxes(): void
    {
        [$school, $courseA, $courseB, $admin] = $this->professionalSchoolWithCourses();

        $this->actingAs($admin)
            ->get("/professional-schools/{$school->id}/facilitators")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ProfessionalSchools/Facilitators')
                ->where('professionalSchool.id', $school->id)
            );

        $this->actingAs($admin)
            ->get("/professional-schools/{$school->id}/facilitators/create")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ProfessionalSchools/FacilitatorCreate')
                ->where('courses.0.id', $courseA->id)
                ->where('courses.1.id', $courseB->id)
            );

        $this->actingAs($admin)
            ->post("/professional-schools/{$school->id}/facilitators", [
                'name' => 'Dr Ada Facilitator',
                'email' => 'ada.facilitator@example.test',
                'password' => 'password123',
                'course_ids' => [$courseA->id],
            ])
            ->assertRedirect("/professional-schools/{$school->id}/facilitators");

        $facilitator = User::query()->where('email', 'ada.facilitator@example.test')->firstOrFail();
        $this->assertDatabaseHas('course_facilitator', [
            'user_id' => $facilitator->id,
            'professional_school_id' => $school->id,
            'course_id' => $courseA->id,
        ]);

        $this->actingAs($admin)
            ->get("/professional-schools/{$school->id}/facilitators/{$facilitator->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ProfessionalSchools/FacilitatorEdit')
                ->where('facilitator.id', $facilitator->id)
                ->where('facilitator.course_ids.0', $courseA->id)
            );

        $this->actingAs($admin)
            ->patch("/professional-schools/{$school->id}/facilitators/{$facilitator->id}", [
                'name' => 'Dr Ada Updated',
                'email' => 'ada.updated@example.test',
                'password' => '',
                'course_ids' => [$courseB->id],
            ])
            ->assertRedirect("/professional-schools/{$school->id}/facilitators");

        $this->assertDatabaseMissing('course_facilitator', [
            'user_id' => $facilitator->id,
            'course_id' => $courseA->id,
        ]);
        $this->assertDatabaseHas('course_facilitator', [
            'user_id' => $facilitator->id,
            'professional_school_id' => $school->id,
            'course_id' => $courseB->id,
        ]);
    }

    private function professionalSchoolWithCourses(): array
    {
        $plan = PricingPlan::query()->where('slug', 'professional')->firstOrFail();
        $plan->update([
            'features' => array_merge($plan->features ?? [], ['facilitator_management' => true]),
        ]);
        $organization = Organization::factory()->create(['pricing_plan_id' => $plan->id]);
        $school = ProfessionalSchool::query()->create([
            'organization_id' => $organization->id,
            'pricing_plan_id' => $plan->id,
            'name' => 'AlignEx Professional Institute',
            'code' => 'API',
            'contact_person' => 'Ada Admin',
            'email' => 'professional@example.test',
            'status' => ProfessionalSchool::STATUS_ACTIVE,
        ]);
        $programme = Programme::query()->create([
            'professional_school_id' => $school->id,
            'name' => 'Project Management',
            'code' => 'PM',
            'duration' => '6 months',
            'status' => Programme::STATUS_ACTIVE,
        ]);
        $courseA = Course::query()->create([
            'professional_school_id' => $school->id,
            'programme_id' => $programme->id,
            'name' => 'Alpha Planning',
            'code' => 'PM101',
            'status' => Course::STATUS_ACTIVE,
        ]);
        $courseB = Course::query()->create([
            'professional_school_id' => $school->id,
            'programme_id' => $programme->id,
            'name' => 'Zeta Execution',
            'code' => 'PM102',
            'status' => Course::STATUS_ACTIVE,
        ]);
        $admin = User::factory()->create([
            'role' => User::ROLE_PROFESSIONAL_SCHOOL_ADMIN,
            'organization_id' => $organization->id,
            'professional_school_id' => $school->id,
            'active_context_type' => 'professional_school',
            'active_context_id' => $school->id,
        ]);

        return [$school, $courseA, $courseB, $admin];
    }
}
