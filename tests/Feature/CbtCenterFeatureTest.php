<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\CbtCenter;
use App\Models\Exam;
use App\Models\Organization;
use App\Models\QuestionBank;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CbtCenterFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
    }

    public function test_super_admin_can_create_and_view_cbt_center(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $organization = Organization::factory()->create();

        $this->actingAs($admin)
            ->post('/cbt-centers', [
                'organization_id' => $organization->id,
                'name' => 'Mainland CBT Center',
                'code' => 'MCBT',
                'location' => 'Lagos',
                'capacity' => 250,
                'contact_person' => 'Ada Admin',
                'email' => 'mainland@example.test',
                'phone' => '08030000000',
                'status' => CbtCenter::STATUS_ACTIVE,
            ])
            ->assertRedirect();

        $center = CbtCenter::query()->where('code', 'MCBT')->firstOrFail();

        $this->actingAs($admin)
            ->get('/cbt-centers')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('CbtCenters/Index')
                ->where('centers.0.name', 'Mainland CBT Center')
            );

        $this->actingAs($admin)
            ->get("/cbt-centers/{$center->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('CbtCenters/Show')
                ->where('center.code', 'MCBT')
            );
    }

    public function test_cbt_center_candidates_are_unique_per_center_and_can_be_imported(): void
    {
        $center = $this->center();
        $admin = $this->cbtAdmin($center);

        $this->actingAs($admin)
            ->post("/cbt-centers/{$center->id}/candidates", [
                'registration_number' => 'CBT-001',
                'full_name' => 'Ada Candidate',
                'email' => 'ada@example.test',
                'phone' => '08030000001',
                'nin' => '12345678901',
                'status' => Candidate::STATUS_ACTIVE,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('candidates', [
            'cbt_center_id' => $center->id,
            'candidate_number' => 'CBT-001',
            'nin' => '12345678901',
        ]);

        $this->actingAs($admin)
            ->post("/cbt-centers/{$center->id}/candidates", [
                'registration_number' => 'CBT-001',
                'full_name' => 'Duplicate Candidate',
                'status' => Candidate::STATUS_ACTIVE,
            ])
            ->assertSessionHasErrors('registration_number');

        $file = UploadedFile::fake()->createWithContent('candidates.csv', "registration_number,full_name,email,phone,nin,status\nCBT-002,Grace Hopper,grace@example.test,08030000002,22222222222,active\nCBT-002,Grace Duplicate,dup@example.test,08030000003,33333333333,active\n");

        $this->actingAs($admin)
            ->post("/cbt-centers/{$center->id}/candidates/import", ['file' => $file])
            ->assertRedirect()
            ->assertSessionHas('import_summary');

        $this->assertDatabaseHas('candidates', [
            'cbt_center_id' => $center->id,
            'candidate_number' => 'CBT-002',
        ]);
    }

    public function test_cbt_center_can_create_question_bank_without_academic_or_professional_structure(): void
    {
        $center = $this->center();
        $admin = $this->cbtAdmin($center);

        $this->actingAs($admin)
            ->post("/cbt-centers/{$center->id}/question-banks", [
                'name' => 'General CBT Bank',
                'code' => 'GCBT',
                'description' => 'General center questions.',
                'status' => QuestionBank::STATUS_ACTIVE,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('question_banks', [
            'owner_type' => Exam::OWNER_CBT_CENTER,
            'owner_id' => $center->id,
            'cbt_center_id' => $center->id,
            'subject_id' => null,
            'code' => 'GCBT',
        ]);
    }

    public function test_cbt_center_exam_requires_question_bank_candidates_and_rejects_academic_fields(): void
    {
        $center = $this->center();
        $admin = $this->cbtAdmin($center);
        [$subject, $bank, $candidate] = $this->examSetup($center, $admin);

        $this->actingAs($admin)
            ->post('/exams', $this->examPayload($subject->id, $bank->id, [$candidate->id], [
                'exam_code' => 'CBT-GEN-001',
                'exam_category' => Exam::CATEGORY_GENERAL,
                'mode' => Exam::MODE_TRADITIONAL,
            ]))
            ->assertRedirect();

        $exam = Exam::query()->where('code', 'CBT-GEN-001')->firstOrFail();
        $this->assertSame($center->id, $exam->cbt_center_id);
        $this->assertSame($bank->id, $exam->question_bank_id);
        $this->assertDatabaseHas('exam_candidates', ['exam_id' => $exam->id, 'candidate_id' => $candidate->id]);

        $this->actingAs($admin)
            ->post('/exams', $this->examPayload($subject->id, $bank->id, [], ['exam_code' => 'CBT-NO-CAN']))
            ->assertSessionHasErrors('candidate_ids');

        $this->actingAs($admin)
            ->post('/exams', $this->examPayload($subject->id, $bank->id, [$candidate->id], [
                'exam_code' => 'CBT-BAD-001',
                'academic_session_id' => 1,
            ]))
            ->assertSessionHasErrors('academic_session_id');
    }

    public function test_cbt_center_supports_adaptive_and_recruitment_exam_categories(): void
    {
        $center = $this->center();
        $admin = $this->cbtAdmin($center);
        [$subject, $bank, $candidate] = $this->examSetup($center, $admin);

        $this->actingAs($admin)
            ->post('/exams', $this->examPayload($subject->id, $bank->id, [$candidate->id], [
                'exam_code' => 'CBT-REC-001',
                'exam_type' => 'recruitment',
                'exam_category' => Exam::CATEGORY_RECRUITMENT,
                'mode' => Exam::MODE_ADAPTIVE,
                'exam_mode' => Exam::MODE_ADAPTIVE,
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('exams', [
            'cbt_center_id' => $center->id,
            'code' => 'CBT-REC-001',
            'exam_category' => Exam::CATEGORY_RECRUITMENT,
            'exam_mode' => Exam::MODE_ADAPTIVE,
        ]);
    }

    public function test_cbt_sidebar_dashboard_and_external_assignment_are_available(): void
    {
        $organization = Organization::factory()->create();
        $center = $this->center(['organization_id' => $organization->id]);
        $admin = $this->cbtAdmin($center);
        $subject = Subject::factory()->create(['organization_id' => $organization->id, 'cbt_center_id' => $center->id, 'school_id' => null, 'center_id' => null]);
        $exam = Exam::factory()->create([
            'organization_id' => $organization->id,
            'created_by' => $admin->id,
            'subject_id' => $subject->id,
            'exam_category' => Exam::CATEGORY_ASSESSMENT,
            'mode' => Exam::MODE_TRADITIONAL,
            'exam_mode' => Exam::MODE_TRADITIONAL,
        ]);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Index')
                ->where('auth.navigation.1.label', 'Candidates')
                ->where('auth.navigation.3.label', 'Exams')
                ->where('auth.navigation.4.label', 'Traditional CBT Exams')
            );

        $orgAdmin = User::factory()->create(['role' => User::ROLE_ORGANIZATION_ADMIN, 'organization_id' => $organization->id]);

        $this->actingAs($orgAdmin)
            ->post("/cbt-centers/{$center->id}/external-exams", ['exam_id' => $exam->id])
            ->assertRedirect();

        $this->assertDatabaseHas('exam_center_assignments', [
            'exam_id' => $exam->id,
            'cbt_center_id' => $center->id,
            'status' => 'assigned',
        ]);
    }

    private function center(array $overrides = []): CbtCenter
    {
        return CbtCenter::query()->create([
            'organization_id' => null,
            'name' => 'Standalone CBT Center',
            'code' => fake()->unique()->bothify('CBT-###'),
            'location' => 'Lagos',
            'capacity' => 100,
            'contact_person' => 'Center Lead',
            'email' => fake()->unique()->safeEmail(),
            'phone' => '08030000000',
            'status' => CbtCenter::STATUS_ACTIVE,
            ...$overrides,
        ]);
    }

    private function cbtAdmin(CbtCenter $center): User
    {
        return User::factory()->create([
            'role' => User::ROLE_CBT_CENTER_ADMIN,
            'organization_id' => $center->organization_id,
            'cbt_center_id' => $center->id,
        ]);
    }

    private function examSetup(CbtCenter $center, User $admin): array
    {
        $subject = Subject::factory()->create([
            'organization_id' => $center->organization_id,
            'owner_type' => Exam::OWNER_CBT_CENTER,
            'owner_id' => $center->id,
            'cbt_center_id' => $center->id,
            'school_id' => null,
            'center_id' => null,
        ]);
        $bank = QuestionBank::query()->create([
            'owner_type' => Exam::OWNER_CBT_CENTER,
            'owner_id' => $center->id,
            'organization_id' => $center->organization_id,
            'cbt_center_id' => $center->id,
            'created_by' => $admin->id,
            'name' => 'CBT Bank',
            'code' => fake()->unique()->bothify('CBT-BANK-###'),
            'status' => QuestionBank::STATUS_ACTIVE,
        ]);
        $candidate = Candidate::factory()->create([
            'owner_type' => Exam::OWNER_CBT_CENTER,
            'owner_id' => $center->id,
            'organization_id' => $center->organization_id,
            'cbt_center_id' => $center->id,
            'candidate_number' => fake()->unique()->bothify('CBT-CAN-###'),
        ]);

        return [$subject, $bank, $candidate];
    }

    private function examPayload(string $subjectId, string $questionBankId, array $candidateIds, array $overrides = []): array
    {
        return array_replace_recursive([
            'title' => 'CBT General Exam',
            'exam_code' => 'CBT-GEN',
            'exam_type' => 'general',
            'exam_category' => Exam::CATEGORY_GENERAL,
            'mode' => Exam::MODE_TRADITIONAL,
            'exam_mode' => Exam::MODE_TRADITIONAL,
            'delivery_mode' => 'online',
            'start_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'end_at' => now()->addDay()->addHours(2)->format('Y-m-d\TH:i'),
            'duration_minutes' => 60,
            'pass_mark' => 10,
            'status' => Exam::STATUS_SCHEDULED,
            'question_bank_id' => $questionBankId,
            'candidate_ids' => $candidateIds,
            'subjects' => [
                [
                    'subject_id' => $subjectId,
                    'number_of_questions' => 20,
                    'marks_per_question' => 1,
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
