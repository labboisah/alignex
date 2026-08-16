<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Institution;
use App\Models\Programme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstitutionStructureFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_institution_demo_seed_creates_an_institution_admin_user(): void
    {
        $this->seed(\Database\Seeders\InstitutionDemoSeeder::class);

        $user = User::query()->where('email', 'institution.admin@alignex.test')->first();

        $this->assertNotNull($user);
        $this->assertSame(User::ROLE_INSTITUTION_ADMIN, $user->role);
        $this->assertTrue($user->isInstitutionAdmin());
        $this->assertNotNull($user->institution_id);
    }

    public function test_institution_admin_can_manage_faculties_departments_programmes_and_courses(): void
    {
        $institution = Institution::create([
            'name' => 'Federal Polytechnic',
            'code' => 'FEDPOLY',
            'institution_type' => 'polytechnic',
            'email' => 'admin@fedpoly.test',
            'phone' => '08030000000',
            'address' => 'Main campus',
            'status' => Institution::STATUS_ACTIVE,
        ]);

        $admin = User::factory()->create([
            'role' => User::ROLE_INSTITUTION_ADMIN,
            'institution_id' => $institution->id,
        ]);

        $this->actingAs($admin)
            ->post("/institutions/{$institution->id}/faculties", [
                'name' => 'Faculty of Engineering',
                'code' => 'ENG',
                'dean_name' => 'Prof. Adeyemi',
                'email' => 'engineering@fedpoly.test',
                'phone' => '08031111111',
                'status' => Faculty::STATUS_ACTIVE,
            ])
            ->assertRedirect();

        $faculty = Faculty::query()->where('institution_id', $institution->id)->firstOrFail();

        $this->actingAs($admin)
            ->post("/institutions/{$institution->id}/departments", [
                'faculty_id' => $faculty->id,
                'name' => 'Computer Engineering',
                'code' => 'CENG',
                'head_name' => 'Dr. Okafor',
                'email' => 'ceng@fedpoly.test',
                'phone' => '08032222222',
                'status' => Department::STATUS_ACTIVE,
            ])
            ->assertRedirect();

        $department = Department::query()->where('institution_id', $institution->id)->firstOrFail();

        $this->actingAs($admin)
            ->post("/institutions/{$institution->id}/programmes", [
                'faculty_id' => $faculty->id,
                'department_id' => $department->id,
                'name' => 'B.Eng Computer Engineering',
                'code' => 'BENG-CENG',
                'duration' => '4 years',
                'description' => 'Undergraduate engineering programme',
                'status' => Programme::STATUS_ACTIVE,
            ])
            ->assertRedirect();

        $programme = Programme::query()->where('institution_id', $institution->id)->firstOrFail();

        $this->actingAs($admin)
            ->post("/institutions/{$institution->id}/courses", [
                'faculty_id' => $faculty->id,
                'department_id' => $department->id,
                'programme_id' => $programme->id,
                'name' => 'Computer Networking',
                'code' => 'NET101',
                'description' => 'Networking fundamentals',
                'status' => Course::STATUS_ACTIVE,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('faculties', ['institution_id' => $institution->id, 'code' => 'ENG']);
        $this->assertDatabaseHas('departments', ['institution_id' => $institution->id, 'code' => 'CENG']);
        $this->assertDatabaseHas('programmes', ['institution_id' => $institution->id, 'code' => 'BENG-CENG']);
        $this->assertDatabaseHas('courses', ['institution_id' => $institution->id, 'code' => 'NET101']);
    }
}
