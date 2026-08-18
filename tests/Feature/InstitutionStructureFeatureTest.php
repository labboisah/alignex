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
                'status' => Faculty::STATUS_ACTIVE,
            ])
            ->assertRedirect();

        $faculty = Faculty::query()->where('institution_id', $institution->id)->firstOrFail();

        $this->actingAs($admin)
            ->post("/institutions/{$institution->id}/departments", [
                'faculty_id' => $faculty->id,
                'name' => 'Computer Engineering',
                'code' => 'CENG',
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
                'duration' => '48',
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
                'status' => Course::STATUS_ACTIVE,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('faculties', ['institution_id' => $institution->id, 'code' => 'ENG']);
        $this->assertDatabaseHas('departments', ['institution_id' => $institution->id, 'code' => 'CENG']);
        $this->assertDatabaseHas('programmes', ['institution_id' => $institution->id, 'code' => 'BENG-CENG']);
        $this->assertDatabaseHas('courses', ['institution_id' => $institution->id, 'code' => 'NET101']);

        $this->actingAs($admin)
            ->patch("/institutions/{$institution->id}/faculties/{$faculty->id}", [
                'name' => 'Faculty of Applied Engineering',
                'code' => 'ENG',
                'status' => Faculty::STATUS_ACTIVE,
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->patch("/institutions/{$institution->id}/departments/{$department->id}", [
                'faculty_id' => $faculty->id,
                'name' => 'Computer Systems Engineering',
                'code' => 'CENG',
                'status' => Department::STATUS_ACTIVE,
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->patch("/institutions/{$institution->id}/programmes/{$programme->id}", [
                'faculty_id' => $faculty->id,
                'department_id' => $department->id,
                'name' => 'B.Eng Computer Systems Engineering',
                'code' => 'BENG-CENG',
                'duration' => '60',
                'status' => Programme::STATUS_ACTIVE,
            ])
            ->assertRedirect();

        $course = Course::query()->where('institution_id', $institution->id)->firstOrFail();

        $this->actingAs($admin)
            ->patch("/institutions/{$institution->id}/courses/{$course->id}", [
                'faculty_id' => $faculty->id,
                'department_id' => $department->id,
                'programme_id' => $programme->id,
                'name' => 'Advanced Computer Networking',
                'code' => 'NET101',
                'status' => Course::STATUS_ACTIVE,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('faculties', ['id' => $faculty->id, 'name' => 'Faculty of Applied Engineering']);
        $this->assertDatabaseHas('departments', ['id' => $department->id, 'name' => 'Computer Systems Engineering']);
        $this->assertDatabaseHas('programmes', ['id' => $programme->id, 'duration' => '60']);
        $this->assertDatabaseHas('courses', ['id' => $course->id, 'name' => 'Advanced Computer Networking']);

        $this->actingAs($admin)
            ->post("/institutions/{$institution->id}/faculties", [
                'name' => 'Faculty of Applied Engineering',
                'code' => 'ENG2',
                'status' => Faculty::STATUS_ACTIVE,
            ])
            ->assertSessionHasErrors('name');

        $this->actingAs($admin)
            ->post("/institutions/{$institution->id}/departments", [
                'faculty_id' => $faculty->id,
                'name' => 'Electrical Engineering',
                'code' => 'CENG',
                'status' => Department::STATUS_ACTIVE,
            ])
            ->assertSessionHasErrors('code');

        $this->actingAs($admin)
            ->post("/institutions/{$institution->id}/programmes", [
                'faculty_id' => $faculty->id,
                'department_id' => $department->id,
                'name' => 'B.Eng Computer Systems Engineering',
                'code' => 'BENG-CENG-2',
                'duration' => '48',
                'status' => Programme::STATUS_ACTIVE,
            ])
            ->assertSessionHasErrors('name');

        $this->actingAs($admin)
            ->post("/institutions/{$institution->id}/courses", [
                'faculty_id' => $faculty->id,
                'department_id' => $department->id,
                'programme_id' => $programme->id,
                'name' => 'Database Systems',
                'code' => 'NET101',
                'status' => Course::STATUS_ACTIVE,
            ])
            ->assertSessionHasErrors('code');

        $deleteCourse = $institution->courses()->create([
            'faculty_id' => $faculty->id,
            'department_id' => $department->id,
            'programme_id' => $programme->id,
            'name' => 'Systems Lab',
            'code' => 'LAB101',
            'status' => Course::STATUS_ACTIVE,
        ]);

        $this->actingAs($admin)
            ->delete("/institutions/{$institution->id}/courses/{$deleteCourse->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('courses', ['id' => $deleteCourse->id]);

        $deleteProgramme = $institution->programmes()->create([
            'faculty_id' => $faculty->id,
            'department_id' => $department->id,
            'name' => 'Delete Me Programme',
            'code' => 'DEL-PROG',
            'duration' => '12',
            'status' => Programme::STATUS_ACTIVE,
        ]);

        $this->actingAs($admin)
            ->delete("/institutions/{$institution->id}/programmes/{$deleteProgramme->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('programmes', ['id' => $deleteProgramme->id]);

        $deleteDepartment = $institution->departments()->create([
            'faculty_id' => $faculty->id,
            'name' => 'Delete Me Department',
            'code' => 'DEL-DEPT',
            'status' => Department::STATUS_ACTIVE,
        ]);

        $this->actingAs($admin)
            ->delete("/institutions/{$institution->id}/departments/{$deleteDepartment->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('departments', ['id' => $deleteDepartment->id]);

        $deleteFaculty = $institution->faculties()->create([
            'name' => 'Delete Me Faculty',
            'code' => 'DEL-FAC',
            'status' => Faculty::STATUS_ACTIVE,
        ]);

        $this->actingAs($admin)
            ->delete("/institutions/{$institution->id}/faculties/{$deleteFaculty->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('faculties', ['id' => $deleteFaculty->id]);
    }
}
