<?php

namespace Tests\Feature;

use App\Models\AdminRegistrationRequest;
use App\Models\Center;
use App\Models\Organization;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_submit_organization_admin_registration_request(): void
    {
        $this->get('/register-admin')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('AdminRegistrations/Create')
                ->has('entityTypes', 4)
                ->where('entityTypes.1.value', AdminRegistrationRequest::TYPE_SECONDARY_SCHOOL)
                ->where('entityTypes.2.value', AdminRegistrationRequest::TYPE_PROFESSIONAL_SCHOOL)
            );

        $this->post('/register-admin', [
            'entity_type' => AdminRegistrationRequest::TYPE_ORGANIZATION,
            'admin_name' => 'Org Admin',
            'admin_email' => 'org-admin@example.test',
            'password' => 'password',
            'password_confirmation' => 'password',
            'entity_name' => 'Exam NGO',
            'entity_code' => 'EXAM-NGO',
            'contact_person' => 'Contact One',
            'phone' => '08030000000',
            'entity_email' => 'contact@examngo.test',
            'address' => '10 Registration Street',
            'legal_registration_number' => 'RC-12345',
            'website' => 'https://examngo.test',
            'years_in_operation' => 5,
            'operating_scope' => 'National',
            'accreditation_body' => 'Corporate Affairs Commission',
            'accreditation_number' => 'CAC-9988',
            'exam_experience' => 'Conducted scholarship exams for five years.',
            'expected_candidates' => 1500,
        ])->assertRedirect(route('admin-registrations.thank-you', absolute: false));

        $this->assertDatabaseHas('admin_registration_requests', [
            'entity_type' => AdminRegistrationRequest::TYPE_ORGANIZATION,
            'admin_email' => 'org-admin@example.test',
            'entity_code' => 'EXAM-NGO',
            'legal_registration_number' => 'RC-12345',
            'website' => 'https://examngo.test',
            'expected_candidates' => 1500,
            'status' => AdminRegistrationRequest::STATUS_PENDING,
        ]);
        $this->assertDatabaseMissing('users', ['email' => 'org-admin@example.test']);
        $this->assertDatabaseMissing('organizations', ['code' => 'EXAM-NGO']);
    }

    public function test_super_admin_can_approve_school_registration_and_create_login(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $registration = AdminRegistrationRequest::factory()->create([
            'entity_type' => AdminRegistrationRequest::TYPE_SCHOOL,
            'admin_email' => 'school-admin@example.test',
            'password' => Hash::make('password'),
            'entity_name' => 'Code Academy',
            'entity_code' => 'CODE-ACADEMY',
            'entity_email' => 'info@codeacademy.test',
            'facility_summary' => 'Two computer labs, backup power, and biometric check-in.',
            'exam_experience' => 'Delivered professional certification tests.',
        ]);

        $this->actingAs($superAdmin)
            ->get('/admin-registrations')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('AdminRegistrations/Index')
                ->where('registrations.data.0.entity_name', 'Code Academy')
            );

        $this->actingAs($superAdmin)
            ->get("/admin-registrations/{$registration->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('AdminRegistrations/Show')
                ->where('registration.data.facility_summary', 'Two computer labs, backup power, and biometric check-in.')
                ->where('registration.data.exam_experience', 'Delivered professional certification tests.')
            );

        $this->actingAs($superAdmin)
            ->patch("/admin-registrations/{$registration->id}/approve", [
                'review_notes' => 'Verified.',
            ])
            ->assertRedirect(route('admin-registrations.show', $registration, absolute: false));

        $school = School::query()->where('code', 'CODE-ACADEMY')->firstOrFail();
        $user = User::query()->where('email', 'school-admin@example.test')->firstOrFail();

        $this->assertSame(User::ROLE_SCHOOL_ADMIN, $user->role);
        $this->assertSame($school->id, $user->school_id);
        $this->assertTrue(Hash::check('password', $user->password));
        $this->assertDatabaseHas('admin_registration_requests', [
            'id' => $registration->id,
            'entity_id' => $school->id,
            'status' => AdminRegistrationRequest::STATUS_APPROVED,
        ]);
    }

    public function test_super_admin_can_approve_center_registration_and_create_center_admin(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $registration = AdminRegistrationRequest::factory()->create([
            'entity_type' => AdminRegistrationRequest::TYPE_CENTER,
            'admin_email' => 'center-admin@example.test',
            'password' => Hash::make('password'),
            'entity_name' => 'Prime CBT',
            'entity_code' => 'PRIME-CBT',
            'entity_email' => 'info@primecbt.test',
        ]);

        $this->actingAs($superAdmin)
            ->patch("/admin-registrations/{$registration->id}/approve")
            ->assertRedirect(route('admin-registrations.show', $registration, absolute: false));

        $center = Center::query()->where('code', 'PRIME-CBT')->firstOrFail();
        $user = User::query()->where('email', 'center-admin@example.test')->firstOrFail();

        $this->assertSame(User::ROLE_CENTER_ADMIN, $user->role);
        $this->assertSame($center->id, $user->center_id);
    }

    public function test_super_admin_can_edit_pending_application_before_approval(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $registration = AdminRegistrationRequest::factory()->create([
            'entity_type' => AdminRegistrationRequest::TYPE_CENTER,
            'admin_email' => 'old-center-admin@example.test',
            'entity_name' => 'Old CBT',
            'entity_code' => 'OLD-CBT',
            'entity_email' => 'old@cbt.test',
        ]);

        $this->actingAs($superAdmin)
            ->get("/admin-registrations/{$registration->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('AdminRegistrations/Edit')
                ->where('registration.data.entity_name', 'Old CBT')
            );

        $this->actingAs($superAdmin)
            ->patch("/admin-registrations/{$registration->id}", [
                'entity_type' => AdminRegistrationRequest::TYPE_CENTER,
                'admin_name' => $registration->admin_name,
                'admin_email' => 'new-center-admin@example.test',
                'entity_name' => 'Updated CBT',
                'entity_code' => 'UPDATED-CBT',
                'location' => 'Updated location',
                'capacity' => 250,
                'contact_person' => 'Updated Contact',
                'phone' => '08030000001',
                'entity_email' => 'updated@cbt.test',
                'address' => null,
                'legal_registration_number' => 'RC-UPDATED',
                'website' => 'https://updated-cbt.test',
                'years_in_operation' => 4,
                'operating_scope' => 'State',
                'accreditation_body' => 'Exam Board',
                'accreditation_number' => 'ACC-123',
                'facility_summary' => 'Updated labs and backup power.',
                'exam_experience' => 'Handled mock examinations.',
                'expected_candidates' => 300,
            ])
            ->assertRedirect(route('admin-registrations.show', $registration, absolute: false));

        $this->assertDatabaseHas('admin_registration_requests', [
            'id' => $registration->id,
            'admin_email' => 'new-center-admin@example.test',
            'entity_name' => 'Updated CBT',
            'entity_code' => 'UPDATED-CBT',
            'facility_summary' => 'Updated labs and backup power.',
        ]);
    }

    public function test_super_admin_can_deactivate_approved_application_and_linked_record(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $school = School::factory()->create(['status' => School::STATUS_ACTIVE]);
        $registration = AdminRegistrationRequest::factory()->create([
            'entity_type' => AdminRegistrationRequest::TYPE_SCHOOL,
            'entity_id' => $school->id,
            'status' => AdminRegistrationRequest::STATUS_APPROVED,
        ]);

        $this->actingAs($superAdmin)
            ->patch("/admin-registrations/{$registration->id}/deactivate", [
                'review_notes' => 'Accreditation withdrawn.',
            ])
            ->assertRedirect(route('admin-registrations.show', $registration, absolute: false));

        $this->assertDatabaseHas('admin_registration_requests', [
            'id' => $registration->id,
            'status' => AdminRegistrationRequest::STATUS_DEACTIVATED,
            'review_notes' => 'Accreditation withdrawn.',
        ]);
        $this->assertDatabaseHas('schools', [
            'id' => $school->id,
            'status' => School::STATUS_INACTIVE,
        ]);
    }

    public function test_rejected_registration_does_not_create_entity_or_user(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $registration = AdminRegistrationRequest::factory()->create([
            'entity_type' => AdminRegistrationRequest::TYPE_ORGANIZATION,
            'admin_email' => 'rejected@example.test',
            'entity_code' => 'REJECTED-ORG',
        ]);

        $this->actingAs($superAdmin)
            ->patch("/admin-registrations/{$registration->id}/reject", [
                'review_notes' => 'Incomplete details.',
            ])
            ->assertRedirect(route('admin-registrations.show', $registration, absolute: false));

        $this->assertDatabaseHas('admin_registration_requests', [
            'id' => $registration->id,
            'status' => AdminRegistrationRequest::STATUS_REJECTED,
        ]);
        $this->assertDatabaseMissing('users', ['email' => 'rejected@example.test']);
        $this->assertDatabaseMissing('organizations', ['code' => 'REJECTED-ORG']);
    }

    public function test_non_super_admin_cannot_review_registrations(): void
    {
        $schoolAdmin = User::factory()->create(['role' => User::ROLE_SCHOOL_ADMIN]);
        $registration = AdminRegistrationRequest::factory()->create();

        $this->actingAs($schoolAdmin)->get('/admin-registrations')->assertForbidden();
        $this->actingAs($schoolAdmin)->patch("/admin-registrations/{$registration->id}/approve")->assertForbidden();
    }
}
