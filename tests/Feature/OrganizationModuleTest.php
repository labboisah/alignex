<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Candidate;
use App\Models\Exam;
use App\Models\QuestionBank;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OrganizationModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
    }

    public function test_super_admin_can_view_organizations_list(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $organization = Organization::factory()->create(['name' => 'Acme Exams']);

        $this->actingAs($superAdmin)
            ->get('/organizations')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Organizations/Index')
                ->where('organizations.data.0.name', 'Acme Exams')
            );
    }

    public function test_super_admin_can_create_organization(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $response = $this->actingAs($superAdmin)->post('/organizations', [
            'name' => 'Bright Future Schools',
            'code' => 'BFS',
            'organization_type' => Organization::TYPE_COMPANY,
            'description' => 'Runs assessment and recruitment exams.',
            'website' => 'https://brightfuture.test',
            'contact_person' => 'Ada Lovelace',
            'email' => 'admin@brightfuture.test',
            'phone' => '08030000000',
            'address' => '12 Exam Road',
            'status' => Organization::STATUS_ACTIVE,
        ]);

        $organization = Organization::query()->where('code', 'BFS')->firstOrFail();

        $response->assertRedirect(route('organizations.show', $organization, absolute: false));
        $this->assertDatabaseHas('organizations', [
            'name' => 'Bright Future Schools',
            'email' => 'admin@brightfuture.test',
            'organization_type' => Organization::TYPE_COMPANY,
            'status' => Organization::STATUS_ACTIVE,
        ]);
    }

    public function test_super_admin_can_update_and_deactivate_organization(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $organization = Organization::factory()->create();

        $this->actingAs($superAdmin)
            ->patch("/organizations/{$organization->id}", [
                'name' => 'Updated Organization',
                'code' => $organization->code,
                'organization_type' => Organization::TYPE_NGO,
                'contact_person' => 'Grace Hopper',
                'email' => $organization->email,
                'phone' => '08031111111',
                'address' => 'Updated address',
                'status' => Organization::STATUS_ACTIVE,
            ])
            ->assertRedirect(route('organizations.show', $organization, absolute: false));

        $this->assertDatabaseHas('organizations', [
            'id' => $organization->id,
            'name' => 'Updated Organization',
            'contact_person' => 'Grace Hopper',
        ]);

        $this->actingAs($superAdmin)
            ->patch("/organizations/{$organization->id}/deactivate")
            ->assertRedirect();

        $this->assertDatabaseHas('organizations', [
            'id' => $organization->id,
            'status' => Organization::STATUS_INACTIVE,
        ]);
    }

    public function test_organization_admin_can_view_only_own_organization_details(): void
    {
        $organization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();
        $organizationAdmin = User::factory()->create([
            'role' => User::ROLE_ORGANIZATION_ADMIN,
            'organization_id' => $organization->id,
        ]);

        $this->actingAs($organizationAdmin)
            ->get("/organizations/{$organization->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Organizations/Show')
                ->where('can.update', false)
                ->where('can.deactivate', false)
            );

        $this->actingAs($organizationAdmin)
            ->get("/organizations/{$organization->id}/edit")
            ->assertForbidden();

        $this->actingAs($organizationAdmin)
            ->patch("/organizations/{$organization->id}/deactivate")
            ->assertForbidden();

        $this->actingAs($organizationAdmin)
            ->get("/organizations/{$otherOrganization->id}")
            ->assertForbidden();
    }

    public function test_organization_validation_requires_unique_code_and_email(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        Organization::factory()->create([
            'code' => 'DUPLICATE',
            'email' => 'duplicate@example.test',
        ]);

        $this->actingAs($superAdmin)
            ->post('/organizations', [
                'name' => '',
                'code' => 'DUPLICATE',
                'contact_person' => '',
                'email' => 'duplicate@example.test',
                'status' => 'unknown',
            ])
            ->assertSessionHasErrors(['name', 'code', 'contact_person', 'email', 'status']);
    }

    public function test_organization_can_be_created_without_school_type(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->actingAs($superAdmin)
            ->post('/organizations', [
                'name' => 'National Certification Body',
                'code' => 'NCB',
                'organization_type' => Organization::TYPE_CERTIFICATION_BODY,
                'contact_person' => 'Registrar',
                'email' => 'registrar@ncb.test',
                'status' => Organization::STATUS_ACTIVE,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('organizations', [
            'code' => 'NCB',
            'organization_type' => Organization::TYPE_CERTIFICATION_BODY,
        ]);
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('organizations', 'school_type'));
    }

    public function test_organization_admin_can_create_direct_candidate(): void
    {
        $organization = Organization::factory()->create();
        $admin = $this->organizationAdmin($organization);

        $this->actingAs($admin)
            ->post('/candidates', [
                'full_name' => 'Ada Candidate',
                'registration_number' => 'ORG-CAN-001',
                'email' => 'ada.candidate@example.test',
                'phone' => '08030000000',
                'status' => Candidate::STATUS_ACTIVE,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('candidates', [
            'organization_id' => $organization->id,
            'candidate_number' => 'ORG-CAN-001',
            'first_name' => 'Ada',
        ]);
    }

    public function test_organization_can_create_question_bank(): void
    {
        $organization = Organization::factory()->create();
        $admin = $this->organizationAdmin($organization);
        $subject = Subject::factory()->create(['organization_id' => $organization->id, 'school_id' => null, 'center_id' => null]);

        $this->actingAs($admin)
            ->post('/question-bank', [
                'subject_id' => $subject->id,
                'name' => 'General Aptitude Bank',
                'code' => 'GAB',
                'description' => 'Organization aptitude questions.',
                'status' => QuestionBank::STATUS_ACTIVE,
            ])
            ->assertRedirect(route('question-bank.index', absolute: false));

        $this->assertDatabaseHas('question_banks', [
            'organization_id' => $organization->id,
            'code' => 'GAB',
        ]);
    }

    public function test_organization_can_create_requested_exam_categories_and_modes(): void
    {
        $organization = Organization::factory()->create();
        $admin = $this->organizationAdmin($organization);
        $subject = Subject::factory()->create(['organization_id' => $organization->id, 'school_id' => null, 'center_id' => null]);
        $bank = QuestionBank::factory()->create(['organization_id' => $organization->id, 'subject_id' => $subject->id, 'school_id' => null, 'center_id' => null]);
        $candidate = Candidate::factory()->create(['organization_id' => $organization->id, 'school_id' => null, 'center_id' => null]);

        foreach ([
            ['code' => 'REC-001', 'category' => Exam::CATEGORY_RECRUITMENT, 'mode' => Exam::MODE_TRADITIONAL],
            ['code' => 'ASM-001', 'category' => Exam::CATEGORY_ASSESSMENT, 'mode' => Exam::MODE_TRADITIONAL],
            ['code' => 'CRT-001', 'category' => Exam::CATEGORY_CERTIFICATION, 'mode' => Exam::MODE_TRADITIONAL],
            ['code' => 'ADP-001', 'category' => Exam::CATEGORY_ASSESSMENT, 'mode' => Exam::MODE_ADAPTIVE],
        ] as $row) {
            $this->actingAs($admin)
                ->post('/exams', $this->examPayload($subject->id, $row['code'], $row['category'], $row['mode'], [
                    'question_bank_id' => $bank->id,
                    'candidate_ids' => [$candidate->id],
                ]))
                ->assertRedirect();
        }

        $this->assertDatabaseHas('exams', ['organization_id' => $organization->id, 'code' => 'REC-001', 'exam_category' => Exam::CATEGORY_RECRUITMENT]);
        $this->assertDatabaseHas('exams', ['organization_id' => $organization->id, 'code' => 'ASM-001', 'exam_category' => Exam::CATEGORY_ASSESSMENT]);
        $this->assertDatabaseHas('exams', ['organization_id' => $organization->id, 'code' => 'CRT-001', 'exam_category' => Exam::CATEGORY_CERTIFICATION]);
        $this->assertDatabaseHas('exams', ['organization_id' => $organization->id, 'code' => 'ADP-001', 'exam_mode' => Exam::MODE_ADAPTIVE]);
    }

    public function test_organization_exam_rejects_school_and_programme_fields(): void
    {
        $organization = Organization::factory()->create();
        $admin = $this->organizationAdmin($organization);
        $subject = Subject::factory()->create(['organization_id' => $organization->id, 'school_id' => null, 'center_id' => null]);
        $bank = QuestionBank::factory()->create(['organization_id' => $organization->id, 'subject_id' => $subject->id, 'school_id' => null, 'center_id' => null]);
        $candidate = Candidate::factory()->create(['organization_id' => $organization->id, 'school_id' => null, 'center_id' => null]);

        $this->actingAs($admin)
            ->post('/exams', [
                ...$this->examPayload($subject->id, 'BAD-001', Exam::CATEGORY_RECRUITMENT, Exam::MODE_TRADITIONAL, [
                    'question_bank_id' => $bank->id,
                    'candidate_ids' => [$candidate->id],
                ]),
                'academic_session_id' => '01J00000000000000000000000',
                'term_id' => 'term-1',
                'school_class_id' => '01J00000000000000000000001',
                'programme_id' => 1,
                'course_id' => 1,
                'module_id' => 1,
                'training_batch_id' => 1,
            ])
            ->assertSessionHasErrors(['academic_session_id', 'term_id', 'school_class_id', 'programme_id', 'course_id', 'module_id', 'training_batch_id']);
    }

    public function test_organization_exam_requires_question_bank_and_candidates(): void
    {
        $organization = Organization::factory()->create();
        $admin = $this->organizationAdmin($organization);
        $subject = Subject::factory()->create(['organization_id' => $organization->id, 'school_id' => null, 'center_id' => null]);

        $this->actingAs($admin)
            ->post('/exams', $this->examPayload($subject->id, 'ORG-NO-PARTS', Exam::CATEGORY_RECRUITMENT))
            ->assertSessionHasErrors(['question_bank_id', 'candidate_ids']);
    }

    public function test_organization_dashboard_returns_organization_metrics(): void
    {
        $organization = Organization::factory()->create();
        $admin = $this->organizationAdmin($organization);
        Exam::factory()->create([
            'organization_id' => $organization->id,
            'exam_category' => Exam::CATEGORY_RECRUITMENT,
            'exam_mode' => Exam::MODE_ADAPTIVE,
            'mode' => Exam::MODE_ADAPTIVE,
        ]);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Index')
                ->where('role.scope', 'Organization scope')
                ->has('organization_charts.exams_by_category')
                ->where('quick_actions.0.label', 'Create Recruitment Exam')
            );
    }

    public function test_sidebar_shows_organization_menus_only(): void
    {
        $organization = Organization::factory()->create();
        $admin = $this->organizationAdmin($organization);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('auth.navigation', fn (Assert $navigation) => $navigation
                    ->where('0.label', 'Dashboard')
                    ->where('1.label', 'Candidates')
                    ->where('2.label', 'Question Bank')
                    ->where('3.label', 'Exams')
                    ->where('4.label', 'Recruitment Exams')
                    ->etc()
                )
            );
    }

    private function organizationAdmin(Organization $organization): User
    {
        return User::factory()->create([
            'role' => User::ROLE_ORGANIZATION_ADMIN,
            'organization_id' => $organization->id,
        ]);
    }

    private function examPayload(string $subjectId, string $code, string $category, string $mode = Exam::MODE_TRADITIONAL, array $overrides = []): array
    {
        return array_replace_recursive([
            'title' => 'Organization Exam '.$code,
            'exam_code' => $code,
            'exam_type' => $category === Exam::CATEGORY_PROFESSIONAL ? 'professional' : 'recruitment',
            'exam_category' => $category,
            'mode' => $mode,
            'exam_mode' => $mode,
            'delivery_mode' => 'online',
            'start_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'end_at' => now()->addDay()->addHours(2)->format('Y-m-d\TH:i'),
            'duration_minutes' => 60,
            'pass_mark' => 20,
            'status' => Exam::STATUS_SCHEDULED,
            'subjects' => [
                [
                    'subject_id' => $subjectId,
                    'number_of_questions' => 20,
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
