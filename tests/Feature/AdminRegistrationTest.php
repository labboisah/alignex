<?php

namespace Tests\Feature;

use App\Models\AdminRegistrationRequest;
use App\Models\Center;
use App\Models\Institution;
use App\Models\Organization;
use App\Models\PricingPlan;
use App\Models\ProfessionalSchool;
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
                ->has('entityTypes', 5)
                ->where('entityTypes.1.value', AdminRegistrationRequest::TYPE_INSTITUTION)
                ->where('entityTypes.2.value', AdminRegistrationRequest::TYPE_SECONDARY_SCHOOL)
                ->where('entityTypes.3.value', AdminRegistrationRequest::TYPE_PROFESSIONAL_SCHOOL)
            );

        $this->post('/register-admin', [
            'entity_type' => AdminRegistrationRequest::TYPE_ORGANIZATION,
            'pricing_plan_id' => $this->pricingPlan()->id,
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

    public function test_guest_can_submit_institution_admin_registration_request(): void
    {
        $this->post('/register-admin', [
            'entity_type' => AdminRegistrationRequest::TYPE_INSTITUTION,
            'pricing_plan_id' => $this->pricingPlan()->id,
            'admin_name' => 'Institution Admin',
            'admin_email' => 'institution-admin@example.test',
            'password' => 'password',
            'password_confirmation' => 'password',
            'entity_name' => 'Niger Delta University',
            'entity_code' => 'NDU',
            'location' => 'Wilberforce Island, Bayelsa',
            'capacity' => 12000,
            'contact_person' => 'Registrar Office',
            'phone' => '08030000022',
            'entity_email' => 'registrar@ndu.test',
            'address' => 'Wilberforce Island, Bayelsa State',
            'legal_registration_number' => 'NDU-2026',
            'website' => 'https://ndu.test',
            'years_in_operation' => 25,
            'operating_scope' => 'National',
            'accreditation_body' => 'National University Commission',
            'accreditation_number' => 'NUC-0001',
            'facility_summary' => 'Academic blocks, library, labs, and ICT centres.',
            'exam_experience' => 'Runs semester and professional certificate exams.',
            'expected_candidates' => 6000,
        ])->assertRedirect(route('admin-registrations.thank-you', absolute: false));

        $this->assertDatabaseHas('admin_registration_requests', [
            'entity_type' => AdminRegistrationRequest::TYPE_INSTITUTION,
            'admin_email' => 'institution-admin@example.test',
            'entity_code' => 'NDU',
            'status' => AdminRegistrationRequest::STATUS_PENDING,
        ]);
    }

    public function test_super_admin_can_approve_institution_registration_and_create_login(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $registration = AdminRegistrationRequest::factory()->create([
            'entity_type' => AdminRegistrationRequest::TYPE_INSTITUTION,
            'admin_email' => 'institution-admin@example.test',
            'password' => Hash::make('password'),
            'entity_name' => 'Niger Delta University',
            'entity_code' => 'NDU',
            'entity_email' => 'registrar@ndu.test',
            'location' => 'Wilberforce Island, Bayelsa',
            'capacity' => 12000,
            'contact_person' => 'Registrar Office',
            'phone' => '08030000022',
            'address' => 'Wilberforce Island, Bayelsa State',
            'facility_summary' => 'Academic blocks, library, labs, and ICT centres.',
            'exam_experience' => 'Runs semester and professional certificate exams.',
        ]);

        $this->actingAs($superAdmin)
            ->patch("/admin-registrations/{$registration->id}/approve", [
                'review_notes' => 'Approved.',
            ])
            ->assertRedirect(route('admin-registrations.show', $registration, absolute: false));

        $institution = \App\Models\Institution::query()->where('code', 'NDU')->firstOrFail();
        $user = User::query()->where('email', 'institution-admin@example.test')->firstOrFail();

        $this->assertSame(User::ROLE_INSTITUTION_ADMIN, $user->role);
        $this->assertSame($institution->id, $user->institution_id);
        $this->assertTrue(Hash::check('password', $user->password));
        $this->assertDatabaseHas('admin_registration_requests', [
            'id' => $registration->id,
            'entity_id' => $institution->id,
            'status' => AdminRegistrationRequest::STATUS_APPROVED,
        ]);
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
                'pricing_plan_id' => $this->pricingPlan()->id,
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

    public function test_super_admin_can_change_approved_account_type(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $plan = $this->pricingPlan();
        $professionalSchool = ProfessionalSchool::create([
            'pricing_plan_id' => $plan->id,
            'name' => 'Wrong Professional School',
            'code' => 'WRONG-PRO',
            'contact_person' => 'Registrar',
            'email' => 'wrong-pro@example.test',
            'phone' => '08030000009',
            'address' => 'Old address',
            'status' => ProfessionalSchool::STATUS_ACTIVE,
        ]);
        $registration = AdminRegistrationRequest::factory()->create([
            'entity_type' => AdminRegistrationRequest::TYPE_PROFESSIONAL_SCHOOL,
            'entity_id' => $professionalSchool->id,
            'pricing_plan_id' => $plan->id,
            'status' => AdminRegistrationRequest::STATUS_APPROVED,
            'admin_name' => 'Account Admin',
            'admin_email' => 'account-admin@example.test',
            'entity_name' => 'Wrong Professional School',
            'entity_code' => 'WRONG-PRO',
            'entity_email' => 'wrong-pro@example.test',
        ]);
        $admin = User::factory()->create([
            'name' => 'Account Admin',
            'email' => 'account-admin@example.test',
            'role' => User::ROLE_PROFESSIONAL_SCHOOL_ADMIN,
            'professional_school_id' => $professionalSchool->id,
        ]);

        $this->actingAs($superAdmin)
            ->patch("/admin-registrations/{$registration->id}", [
                'entity_type' => AdminRegistrationRequest::TYPE_INSTITUTION,
                'pricing_plan_id' => $plan->id,
                'admin_name' => 'Institution Account Admin',
                'admin_email' => 'institution-account-admin@example.test',
                'entity_name' => 'Correct Institution',
                'entity_code' => 'CORRECT-INST',
                'location' => 'Institution location',
                'capacity' => 1000,
                'contact_person' => 'Registrar',
                'phone' => '08030000010',
                'entity_email' => 'correct-inst@example.test',
                'address' => 'Institution address',
                'legal_registration_number' => 'INST-001',
                'website' => 'https://correct-inst.test',
                'years_in_operation' => 10,
                'operating_scope' => 'National',
                'accreditation_body' => 'NUC',
                'accreditation_number' => 'NUC-001',
                'facility_summary' => 'Institution facilities.',
                'exam_experience' => 'Semester examinations.',
                'expected_candidates' => 1000,
            ])
            ->assertRedirect(route('admin-registrations.show', $registration, absolute: false));

        $institution = Institution::query()->where('code', 'CORRECT-INST')->firstOrFail();
        $admin->refresh();

        $this->assertSame(User::ROLE_INSTITUTION_ADMIN, $admin->role);
        $this->assertSame($institution->id, $admin->institution_id);
        $this->assertNull($admin->professional_school_id);
        $this->assertDatabaseHas('professional_schools', [
            'id' => $professionalSchool->id,
            'status' => ProfessionalSchool::STATUS_INACTIVE,
        ]);
        $this->assertDatabaseHas('admin_registration_requests', [
            'id' => $registration->id,
            'entity_type' => AdminRegistrationRequest::TYPE_INSTITUTION,
            'entity_id' => $institution->id,
            'admin_email' => 'institution-account-admin@example.test',
        ]);
    }

    public function test_changing_institution_account_to_organization_deactivates_old_lecturers(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $plan = $this->pricingPlan();
        $institution = Institution::create([
            'name' => 'Old Institution',
            'code' => 'OLD-INST',
            'institution_type' => 'university',
            'email' => 'old-institution@example.test',
            'phone' => '08030000018',
            'address' => 'Old institution address',
            'status' => Institution::STATUS_ACTIVE,
        ]);
        $registration = AdminRegistrationRequest::factory()->create([
            'entity_type' => AdminRegistrationRequest::TYPE_INSTITUTION,
            'entity_id' => $institution->id,
            'pricing_plan_id' => $plan->id,
            'status' => AdminRegistrationRequest::STATUS_APPROVED,
            'admin_name' => 'Institution Admin',
            'admin_email' => 'institution-owner@example.test',
            'entity_name' => 'Old Institution',
            'entity_code' => 'OLD-INST',
            'entity_email' => 'old-institution@example.test',
        ]);
        $admin = User::factory()->create([
            'name' => 'Institution Admin',
            'email' => 'institution-owner@example.test',
            'role' => User::ROLE_INSTITUTION_ADMIN,
            'status' => User::STATUS_ACTIVE,
            'institution_id' => $institution->id,
            'active_context_type' => 'institution',
            'active_context_id' => $institution->id,
        ]);
        $lecturer = User::factory()->create([
            'role' => User::ROLE_FACILITATOR,
            'status' => User::STATUS_ACTIVE,
            'institution_id' => $institution->id,
            'active_context_type' => 'institution',
            'active_context_id' => $institution->id,
        ]);

        $this->actingAs($superAdmin)
            ->patch("/admin-registrations/{$registration->id}", [
                'entity_type' => AdminRegistrationRequest::TYPE_ORGANIZATION,
                'pricing_plan_id' => $plan->id,
                'admin_name' => 'Organization Account Admin',
                'admin_email' => 'organization-owner@example.test',
                'entity_name' => 'Correct Organization',
                'entity_code' => 'CORRECT-ORG',
                'location' => 'Organization location',
                'capacity' => 1000,
                'contact_person' => 'Director',
                'phone' => '08030000019',
                'entity_email' => 'correct-org@example.test',
                'address' => 'Organization address',
                'legal_registration_number' => 'ORG-001',
                'website' => 'https://correct-org.test',
                'years_in_operation' => 10,
                'operating_scope' => 'National',
                'accreditation_body' => 'CAC',
                'accreditation_number' => 'CAC-001',
                'facility_summary' => 'Organization facilities.',
                'exam_experience' => 'Organization examinations.',
                'expected_candidates' => 1000,
            ])
            ->assertRedirect(route('admin-registrations.show', $registration, absolute: false));

        $organization = Organization::query()->where('code', 'CORRECT-ORG')->firstOrFail();
        $admin->refresh();
        $lecturer->refresh();

        $this->assertSame(User::ROLE_ORGANIZATION_ADMIN, $admin->role);
        $this->assertSame($organization->id, $admin->organization_id);
        $this->assertNull($admin->institution_id);
        $this->assertSame(User::STATUS_ACTIVE, $admin->status);
        $this->assertSame(User::STATUS_INACTIVE, $lecturer->status);
        $this->assertNull($lecturer->active_context_type);
        $this->assertNull($lecturer->active_context_id);
        $this->assertDatabaseHas('institutions', [
            'id' => $institution->id,
            'status' => Institution::STATUS_INACTIVE,
        ]);

        $this->actingAs($lecturer)
            ->get('/dashboard')
            ->assertRedirect(route('login', absolute: false))
            ->assertSessionHasErrors('email');
    }

    public function test_changing_organization_account_to_institution_deactivates_old_scoped_users(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $plan = $this->pricingPlan();
        $organization = Organization::create([
            'pricing_plan_id' => $plan->id,
            'name' => 'Old Organization',
            'code' => 'OLD-ORG',
            'contact_person' => 'Director',
            'email' => 'old-org@example.test',
            'phone' => '08030000020',
            'address' => 'Old organization address',
            'status' => Organization::STATUS_ACTIVE,
        ]);
        $childInstitution = Institution::create([
            'organization_id' => $organization->id,
            'name' => 'Child Institution',
            'code' => 'CHILD-INST',
            'institution_type' => 'university',
            'email' => 'child-institution@example.test',
            'phone' => '08030000021',
            'address' => 'Child institution address',
            'status' => Institution::STATUS_ACTIVE,
        ]);
        $registration = AdminRegistrationRequest::factory()->create([
            'entity_type' => AdminRegistrationRequest::TYPE_ORGANIZATION,
            'entity_id' => $organization->id,
            'pricing_plan_id' => $plan->id,
            'status' => AdminRegistrationRequest::STATUS_APPROVED,
            'admin_name' => 'Organization Admin',
            'admin_email' => 'old-organization-owner@example.test',
            'entity_name' => 'Old Organization',
            'entity_code' => 'OLD-ORG',
            'entity_email' => 'old-org@example.test',
        ]);
        $admin = User::factory()->create([
            'name' => 'Organization Admin',
            'email' => 'old-organization-owner@example.test',
            'role' => User::ROLE_ORGANIZATION_ADMIN,
            'status' => User::STATUS_ACTIVE,
            'organization_id' => $organization->id,
            'active_context_type' => 'organization',
            'active_context_id' => $organization->id,
        ]);
        $examiner = User::factory()->create([
            'role' => User::ROLE_EXAMINER,
            'status' => User::STATUS_ACTIVE,
            'organization_id' => $organization->id,
            'active_context_type' => 'organization',
            'active_context_id' => $organization->id,
        ]);
        $lecturer = User::factory()->create([
            'role' => User::ROLE_FACILITATOR,
            'status' => User::STATUS_ACTIVE,
            'organization_id' => $organization->id,
            'institution_id' => $childInstitution->id,
            'active_context_type' => 'institution',
            'active_context_id' => $childInstitution->id,
        ]);

        $this->actingAs($superAdmin)
            ->patch("/admin-registrations/{$registration->id}", [
                'entity_type' => AdminRegistrationRequest::TYPE_INSTITUTION,
                'pricing_plan_id' => $plan->id,
                'admin_name' => 'Institution Account Admin',
                'admin_email' => 'new-institution-owner@example.test',
                'entity_name' => 'New Institution',
                'entity_code' => 'NEW-INST',
                'location' => 'Institution location',
                'capacity' => 1000,
                'contact_person' => 'Registrar',
                'phone' => '08030000022',
                'entity_email' => 'new-inst@example.test',
                'address' => 'Institution address',
                'legal_registration_number' => 'INST-002',
                'website' => 'https://new-inst.test',
                'years_in_operation' => 10,
                'operating_scope' => 'National',
                'accreditation_body' => 'NUC',
                'accreditation_number' => 'NUC-002',
                'facility_summary' => 'Institution facilities.',
                'exam_experience' => 'Semester examinations.',
                'expected_candidates' => 1000,
            ])
            ->assertRedirect(route('admin-registrations.show', $registration, absolute: false));

        $newInstitution = Institution::query()->where('code', 'NEW-INST')->firstOrFail();
        $admin->refresh();
        $examiner->refresh();
        $lecturer->refresh();

        $this->assertSame(User::ROLE_INSTITUTION_ADMIN, $admin->role);
        $this->assertSame($newInstitution->id, $admin->institution_id);
        $this->assertNull($admin->organization_id);
        $this->assertSame(User::STATUS_ACTIVE, $admin->status);
        $this->assertSame(User::STATUS_INACTIVE, $examiner->status);
        $this->assertSame(User::STATUS_INACTIVE, $lecturer->status);
        $this->assertDatabaseHas('organizations', [
            'id' => $organization->id,
            'status' => Organization::STATUS_INACTIVE,
        ]);
        $this->assertDatabaseHas('institutions', [
            'id' => $childInstitution->id,
            'status' => Institution::STATUS_INACTIVE,
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

    private function pricingPlan(): PricingPlan
    {
        return PricingPlan::query()->firstOrFail();
    }
}
