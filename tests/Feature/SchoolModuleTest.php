<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SchoolModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_view_and_create_schools(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        School::factory()->create(['name' => 'Prime Academy']);

        $this->actingAs($superAdmin)
            ->get('/schools')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Schools/Index')
                ->where('schools.data.0.name', 'Prime Academy')
            );

        $response = $this->actingAs($superAdmin)->post('/schools', [
            'name' => 'North Secondary School',
            'code' => 'NSS',
            'location' => 'North district',
            'capacity' => 1200,
            'contact_person' => 'Jane Doe',
            'phone' => '08030000000',
            'email' => 'northschool@example.test',
            'status' => School::STATUS_ACTIVE,
        ]);

        $school = School::query()->where('code', 'NSS')->firstOrFail();

        $response->assertRedirect(route('schools.show', $school, absolute: false));
        $this->assertDatabaseHas('schools', [
            'name' => 'North Secondary School',
            'capacity' => 1200,
            'status' => School::STATUS_ACTIVE,
        ]);
    }

    public function test_school_admin_can_manage_only_own_school(): void
    {
        $school = School::factory()->create();
        $otherSchool = School::factory()->create();
        $schoolAdmin = User::factory()->create([
            'role' => User::ROLE_SCHOOL_ADMIN,
            'school_id' => $school->id,
        ]);

        $this->actingAs($schoolAdmin)
            ->get('/schools')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('schools.data.0.id', $school->id)
                ->has('schools.data', 1)
            );

        $this->actingAs($schoolAdmin)
            ->patch("/schools/{$school->id}", [
                'name' => 'Updated School',
                'code' => $school->code,
                'location' => 'Updated location',
                'capacity' => 1500,
                'contact_person' => 'School Manager',
                'phone' => '08031111111',
                'email' => $school->email,
                'status' => School::STATUS_ACTIVE,
            ])
            ->assertRedirect(route('schools.show', $school, absolute: false));

        $this->assertDatabaseHas('schools', [
            'id' => $school->id,
            'name' => 'Updated School',
            'capacity' => 1500,
        ]);

        $this->actingAs($schoolAdmin)
            ->get("/schools/{$otherSchool->id}")
            ->assertForbidden();

        $this->actingAs($schoolAdmin)
            ->get('/schools/create')
            ->assertForbidden();
    }

    public function test_school_validation_requires_unique_code_and_email(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        School::factory()->create([
            'code' => 'DUP-SCHOOL',
            'email' => 'duplicate-school@example.test',
        ]);

        $this->actingAs($superAdmin)
            ->post('/schools', [
                'name' => '',
                'code' => 'DUP-SCHOOL',
                'location' => '',
                'capacity' => 0,
                'contact_person' => '',
                'email' => 'duplicate-school@example.test',
                'status' => 'unknown',
            ])
            ->assertSessionHasErrors(['name', 'code', 'location', 'capacity', 'contact_person', 'email', 'status']);
    }
}
