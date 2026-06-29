<?php

namespace Tests\Feature;

use App\Models\CbtCenter;
use App\Models\Exam;
use App\Models\ExamType;
use App\Models\Organization;
use App\Models\ProfessionalSchool;
use App\Models\SecondarySchool;
use App\Models\User;
use App\Support\ExamOwnershipRules;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CorrectedEntityFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_organization_does_not_require_school_type(): void
    {
        $this->assertFalse(Schema::hasColumn('organizations', 'school_type'));

        $organization = Organization::query()->create([
            'name' => 'Demo NGO',
            'code' => 'DEMO-NGO',
            'organization_type' => 'ngo',
            'contact_person' => 'Demo Contact',
            'email' => 'demo-ngo@example.test',
            'status' => Organization::STATUS_ACTIVE,
        ]);

        $this->assertSame('ngo', $organization->organization_type);
    }

    public function test_corrected_entities_can_be_created_independently(): void
    {
        $secondarySchool = SecondarySchool::query()->create($this->schoolPayload('secondary@example.test', 'SEC-001'));
        $professionalSchool = ProfessionalSchool::query()->create($this->schoolPayload('professional@example.test', 'PRO-001'));
        $cbtCenter = CbtCenter::query()->create([
            'name' => 'Standalone CBT Center',
            'code' => 'CBT-001',
            'location' => 'Lagos',
            'capacity' => 250,
            'contact_person' => 'Center Manager',
            'email' => 'center@example.test',
            'status' => CbtCenter::STATUS_ACTIVE,
        ]);

        $this->assertDatabaseHas('secondary_schools', ['id' => $secondarySchool->id, 'organization_id' => null]);
        $this->assertDatabaseHas('professional_schools', ['id' => $professionalSchool->id, 'organization_id' => null]);
        $this->assertDatabaseHas('cbt_centers', ['id' => $cbtCenter->id, 'organization_id' => null]);
    }

    public function test_organization_can_own_corrected_entities(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Certification Body',
            'code' => 'CERT-BODY',
            'organization_type' => 'certification_body',
            'contact_person' => 'Registrar',
            'email' => 'cert-body@example.test',
            'status' => Organization::STATUS_ACTIVE,
        ]);

        $secondarySchool = $organization->secondarySchools()->create($this->schoolPayload('owned-secondary@example.test', 'OWN-SEC'));
        $professionalSchool = $organization->professionalSchools()->create($this->schoolPayload('owned-professional@example.test', 'OWN-PRO'));
        $cbtCenter = $organization->cbtCenters()->create([
            'name' => 'Owned CBT Center',
            'code' => 'OWN-CBT',
            'location' => 'Abuja',
            'capacity' => 120,
            'contact_person' => 'Manager',
            'email' => 'owned-cbt@example.test',
            'status' => CbtCenter::STATUS_ACTIVE,
        ]);

        $this->assertTrue($organization->secondarySchools()->whereKey($secondarySchool->id)->exists());
        $this->assertTrue($organization->professionalSchools()->whereKey($professionalSchool->id)->exists());
        $this->assertTrue($organization->cbtCenters()->whereKey($cbtCenter->id)->exists());
    }

    public function test_exam_owner_categories_and_modes_are_enforced_by_foundation_rules(): void
    {
        $this->assertTrue(ExamOwnershipRules::isValid(Exam::OWNER_ORGANIZATION, Exam::CATEGORY_RECRUITMENT, Exam::MODE_ADAPTIVE));
        $this->assertTrue(ExamOwnershipRules::isValid(Exam::OWNER_SECONDARY_SCHOOL, Exam::CATEGORY_TERMINAL, Exam::MODE_TRADITIONAL));
        $this->assertTrue(ExamOwnershipRules::isValid(Exam::OWNER_PROFESSIONAL_SCHOOL, Exam::CATEGORY_CERTIFICATION, Exam::MODE_ADAPTIVE));
        $this->assertTrue(ExamOwnershipRules::isValid(Exam::OWNER_CBT_CENTER, Exam::CATEGORY_GENERAL, Exam::MODE_TRADITIONAL));

        $this->assertFalse(ExamOwnershipRules::isValid(Exam::OWNER_SECONDARY_SCHOOL, Exam::CATEGORY_CERTIFICATION, Exam::MODE_TRADITIONAL));
        $this->assertFalse(ExamOwnershipRules::isValid(Exam::OWNER_SECONDARY_SCHOOL, Exam::CATEGORY_TERMINAL, Exam::MODE_ADAPTIVE));
        $this->assertFalse(ExamOwnershipRules::isValid(Exam::OWNER_CBT_CENTER, Exam::CATEGORY_TERMINAL, Exam::MODE_TRADITIONAL));
    }

    public function test_organization_level_exam_can_be_created_with_corrected_owner_fields(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Recruitment Agency',
            'code' => 'REC-AGENCY',
            'organization_type' => 'recruitment_agency',
            'contact_person' => 'Recruiter',
            'email' => 'recruiter@example.test',
            'status' => Organization::STATUS_ACTIVE,
        ]);
        $examType = ExamType::query()->create(['name' => 'Recruitment', 'code' => 'recruitment', 'status' => ExamType::STATUS_ACTIVE]);
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $exam = Exam::query()->create([
            'organization_id' => $organization->id,
            'owner_type' => Exam::OWNER_ORGANIZATION,
            'owner_id' => $organization->id,
            'exam_owner_type' => Exam::OWNER_ORGANIZATION,
            'exam_owner_id' => $organization->id,
            'exam_type_id' => $examType->id,
            'created_by' => $admin->id,
            'title' => 'Recruitment Aptitude Test',
            'code' => 'REC-APT-001',
            'mode' => Exam::MODE_ADAPTIVE,
            'exam_mode' => Exam::MODE_ADAPTIVE,
            'exam_category' => Exam::CATEGORY_RECRUITMENT,
            'delivery_mode' => 'online',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
            'duration_minutes' => 60,
            'total_marks' => 100,
            'pass_mark' => 50,
            'status' => Exam::STATUS_DRAFT,
            'settings' => [],
        ]);

        $this->assertSame(Exam::OWNER_ORGANIZATION, $exam->effectiveOwnerType());
        $this->assertSame(Exam::MODE_ADAPTIVE, $exam->effectiveMode());
    }

    public function test_unauthorized_user_cannot_access_another_entity_record(): void
    {
        $this->seed(RolesSeeder::class);

        $ownSchool = SecondarySchool::query()->create($this->schoolPayload('own-school@example.test', 'OWN-SCH'));
        $otherSchool = SecondarySchool::query()->create($this->schoolPayload('other-school@example.test', 'OTHER-SCH'));
        $user = User::factory()->create([
            'role' => User::ROLE_SECONDARY_SCHOOL_ADMIN,
            'secondary_school_id' => $ownSchool->id,
        ]);

        $this->assertTrue(Gate::forUser($user)->allows('view', $ownSchool));
        $this->assertFalse(Gate::forUser($user)->allows('view', $otherSchool));
    }

    private function schoolPayload(string $email, string $code): array
    {
        return [
            'name' => 'Demo School '.$code,
            'code' => $code,
            'contact_person' => 'Demo Contact',
            'email' => $email,
            'phone' => '08000000000',
            'address' => 'Demo Address',
            'status' => SecondarySchool::STATUS_ACTIVE,
        ];
    }
}
