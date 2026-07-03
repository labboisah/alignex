<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\CandidateExamAttempt;
use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamType;
use App\Models\Organization;
use App\Models\ProfessionalModule;
use App\Models\ProfessionalSchool;
use App\Models\Programme;
use App\Models\QuestionBank;
use App\Models\Subject;
use App\Models\TrainingBatch;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProfessionalExamFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
    }

    public function test_professional_school_admin_can_manage_training_structure_candidates_and_question_banks(): void
    {
        $school = $this->professionalSchool();
        $admin = User::factory()->create([
            'role' => User::ROLE_PROFESSIONAL_SCHOOL_ADMIN,
            'professional_school_id' => $school->id,
        ]);

        $this->actingAs($admin)
            ->get('/professional-schools')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ProfessionalSchools/Index')
                ->where('professionalSchools.0.name', $school->name)
            );

        $this->actingAs($admin)
            ->post("/professional-schools/{$school->id}/programmes", [
                'name' => 'Software Engineering Diploma',
                'code' => 'SED',
                'duration' => '6 months',
                'description' => 'Applied software engineering pathway.',
                'status' => 'active',
            ])
            ->assertRedirect();

        $programme = Programme::query()->where('professional_school_id', $school->id)->firstOrFail();

        $this->actingAs($admin)
            ->post("/professional-schools/{$school->id}/courses", [
                'programme_id' => $programme->id,
                'name' => 'Backend Engineering',
                'code' => 'BE',
                'description' => 'Backend services and databases.',
                'status' => 'active',
            ])
            ->assertRedirect();

        $course = Course::query()->where('professional_school_id', $school->id)->firstOrFail();

        $this->actingAs($admin)
            ->post("/professional-schools/{$school->id}/modules", [
                'programme_id' => $programme->id,
                'course_id' => $course->id,
                'name' => 'Laravel Foundations',
                'code' => 'LAR-101',
                'description' => 'Laravel fundamentals.',
                'status' => 'active',
            ])
            ->assertRedirect();

        $module = ProfessionalModule::query()->where('professional_school_id', $school->id)->firstOrFail();

        $this->actingAs($admin)
            ->post("/professional-schools/{$school->id}/training-batches", [
                'programme_id' => $programme->id,
                'name' => 'January Cohort',
                'start_date' => now()->addWeek()->toDateString(),
                'end_date' => now()->addMonths(6)->toDateString(),
                'status' => 'active',
            ])
            ->assertRedirect();

        $batch = TrainingBatch::query()->where('professional_school_id', $school->id)->firstOrFail();

        $this->actingAs($admin)
            ->post("/professional-schools/{$school->id}/candidates", [
                'programme_id' => $programme->id,
                'course_id' => $course->id,
                'training_batch_id' => $batch->id,
                'registration_number' => 'PRO-TRA-001',
                'full_name' => 'Ada Professional',
                'email' => 'ada@example.test',
                'phone' => '08030000000',
                'status' => Candidate::STATUS_ACTIVE,
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post("/professional-schools/{$school->id}/question-banks", [
                'programme_id' => $programme->id,
                'course_id' => $course->id,
                'module_id' => $module->id,
                'name' => 'Laravel Module Bank',
                'code' => 'LAR-BANK',
                'description' => 'Module questions.',
                'status' => QuestionBank::STATUS_ACTIVE,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('candidates', [
            'professional_school_id' => $school->id,
            'programme_id' => $programme->id,
            'course_id' => $course->id,
            'training_batch_id' => $batch->id,
            'candidate_number' => 'PRO-TRA-001',
        ]);
        $this->assertDatabaseHas('question_banks', [
            'owner_type' => Exam::OWNER_PROFESSIONAL_SCHOOL,
            'owner_id' => $school->id,
            'professional_school_id' => $school->id,
            'programme_id' => $programme->id,
            'course_id' => $course->id,
            'module_id' => $module->id,
        ]);
    }

    public function test_professional_school_exam_requires_professional_hierarchy_and_rejects_academic_fields(): void
    {
        [$school, $programme, $course, $module, $subject] = $this->professionalHierarchy();
        $admin = User::factory()->create([
            'role' => User::ROLE_PROFESSIONAL_SCHOOL_ADMIN,
            'professional_school_id' => $school->id,
        ]);

        $payload = $this->examPayload($subject, [
            'professional_school_id' => $school->id,
            'programme_id' => $programme->id,
            'course_id' => $course->id,
            'module_id' => $module->id,
        ]);

        $this->actingAs($admin)
            ->post('/exams', $payload)
            ->assertRedirect();

        $this->assertDatabaseHas('exams', [
            'professional_school_id' => $school->id,
            'programme_id' => $programme->id,
            'course_id' => $course->id,
            'module_id' => $module->id,
            'exam_owner_type' => Exam::OWNER_PROFESSIONAL_SCHOOL,
            'exam_category' => Exam::CATEGORY_PROFESSIONAL,
            'exam_mode' => Exam::MODE_ADAPTIVE,
            'subject_id' => null,
        ]);

        $this->actingAs($admin)
            ->from('/exams/create')
            ->post('/exams', [
                ...$payload,
                'exam_code' => 'PRO-BAD-001',
                'academic_session_id' => 1,
                'school_class_id' => 1,
                'subject_id' => $subject->id,
            ])
            ->assertRedirect('/exams/create')
            ->assertSessionHasErrors(['academic_session_id', 'school_class_id', 'subject_id']);
    }

    public function test_professional_school_can_create_traditional_adaptive_and_certification_exams(): void
    {
        [$school, $programme, $course, $module, $subject] = $this->professionalHierarchy();
        $admin = User::factory()->create([
            'role' => User::ROLE_PROFESSIONAL_SCHOOL_ADMIN,
            'professional_school_id' => $school->id,
        ]);

        foreach ([
            ['PRO-TRAD-001', Exam::CATEGORY_PROFESSIONAL, Exam::MODE_TRADITIONAL],
            ['PRO-ADAPT-001', Exam::CATEGORY_PROFESSIONAL, Exam::MODE_ADAPTIVE],
            ['PRO-CERT-001', Exam::CATEGORY_CERTIFICATION, Exam::MODE_TRADITIONAL],
        ] as [$code, $category, $mode]) {
            $this->actingAs($admin)
                ->post('/exams', $this->examPayload($subject, [
                    'professional_school_id' => $school->id,
                    'programme_id' => $programme->id,
                    'course_id' => $course->id,
                    'module_id' => $module->id,
                    'exam_code' => $code,
                    'exam_category' => $category,
                    'mode' => $mode,
                    'exam_mode' => $mode,
                ]))
                ->assertRedirect();
        }

        $this->assertDatabaseHas('exams', ['professional_school_id' => $school->id, 'code' => 'PRO-TRAD-001', 'exam_mode' => Exam::MODE_TRADITIONAL]);
        $this->assertDatabaseHas('exams', ['professional_school_id' => $school->id, 'code' => 'PRO-ADAPT-001', 'exam_mode' => Exam::MODE_ADAPTIVE]);
        $this->assertDatabaseHas('exams', ['professional_school_id' => $school->id, 'code' => 'PRO-CERT-001', 'exam_category' => Exam::CATEGORY_CERTIFICATION]);
    }

    public function test_organization_admin_can_manage_professional_certificates_and_verification(): void
    {
        [$exam, $attempt] = $this->professionalExam();
        $admin = User::factory()->create([
            'role' => User::ROLE_ORGANIZATION_ADMIN,
            'organization_id' => $exam->organization_id,
        ]);

        $this->actingAs($admin)
            ->get("/exams/{$exam->id}/professional")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Professional/Show')
                ->where('exam.exam_code', $exam->code)
                ->where('attempts.0.registration_number', $attempt->candidate->candidate_number)
            );

        $this->actingAs($admin)
            ->patch("/exams/{$exam->id}/professional/settings", [
                'pass_mark' => 60,
                'attempt_limit' => 2,
                'retake_policy' => 'failed_only',
                'payment_required' => true,
                'certificate_auto_generate' => true,
                'certificate_valid_months' => 24,
            ])
            ->assertRedirect();

        $exam->refresh();
        $this->assertEquals(60, (float) $exam->pass_mark);
        $this->assertSame(2, $exam->settings['professional_attempt_limit']);
        $this->assertTrue($exam->settings['professional_payment_required']);

        $this->actingAs($admin)
            ->post("/exams/{$exam->id}/professional/templates", [
                'name' => 'Professional Certificate',
                'title' => 'Certificate of Professional Competence',
                'body' => 'This certifies {{candidate_name}} passed {{exam_title}}.',
                'signatory_name' => 'Registrar',
                'signatory_title' => 'Certification Office',
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->patch("/exams/{$exam->id}/professional/attempts/{$attempt->id}/payment", [
                'payment_status' => CandidateExamAttempt::PAYMENT_PAID,
                'payment_reference' => 'PAY-001',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('candidate_exam_attempts', [
            'id' => $attempt->id,
            'payment_status' => CandidateExamAttempt::PAYMENT_PAID,
            'payment_reference' => 'PAY-001',
        ]);

        $this->actingAs($admin)
            ->post("/exams/{$exam->id}/professional/attempts/{$attempt->id}/certificate")
            ->assertRedirect();

        $certificate = $attempt->certificate()->firstOrFail();
        $this->assertStringStartsWith('PRO-', $certificate->serial_number);

        $this->get('/verify-certificate')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Public/VerifyCertificate'));

        $this->postJson('/api/certificates/verify', ['identifier' => $certificate->serial_number])
            ->assertOk()
            ->assertJsonPath('valid', true)
            ->assertJsonPath('certificate.registration_number', $attempt->candidate->candidate_number)
            ->assertJsonPath('certificate.serial_number', $certificate->serial_number);
    }

    private function professionalExam(): array
    {
        $organization = Organization::factory()->create();
        $examType = ExamType::factory()->create([
            'name' => 'Professional',
            'code' => 'professional',
        ]);
        $exam = Exam::factory()->create([
            'organization_id' => $organization->id,
            'exam_type_id' => $examType->id,
            'code' => 'PRO-001',
            'total_marks' => 100,
            'pass_mark' => 50,
            'settings' => [
                'professional_payment_required' => false,
                'professional_certificate_auto_generate' => true,
            ],
        ]);
        $candidate = Candidate::factory()->create([
            'organization_id' => $organization->id,
            'candidate_number' => 'PRO-CAN-001',
        ]);
        $exam->candidates()->attach($candidate->id, ['status' => 'assigned']);
        $attempt = CandidateExamAttempt::factory()->create([
            'candidate_id' => $candidate->id,
            'exam_id' => $exam->id,
            'status' => CandidateExamAttempt::STATUS_SUBMITTED,
            'submitted_at' => now(),
            'score' => 82,
            'total_questions' => 50,
            'total_marks' => 100,
            'payment_status' => CandidateExamAttempt::PAYMENT_PENDING,
        ]);

        return [$exam->refresh(), $attempt->refresh()->load('candidate')];
    }

    private function professionalSchool(): ProfessionalSchool
    {
        $organization = Organization::factory()->create();

        return ProfessionalSchool::query()->create([
            'organization_id' => $organization->id,
            'name' => 'AlignEx Professional Academy',
            'code' => 'APA',
            'contact_person' => 'Training Lead',
            'email' => 'academy@example.test',
            'phone' => '08030000001',
            'address' => 'Lagos',
            'status' => ProfessionalSchool::STATUS_ACTIVE,
        ]);
    }

    private function professionalHierarchy(): array
    {
        $school = $this->professionalSchool();
        $programme = Programme::query()->create([
            'professional_school_id' => $school->id,
            'name' => 'Cloud Certification',
            'code' => 'CLOUD',
            'duration' => '12 weeks',
            'status' => 'active',
        ]);
        $course = Course::query()->create([
            'professional_school_id' => $school->id,
            'programme_id' => $programme->id,
            'name' => 'Cloud Architecture',
            'code' => 'ARCH',
            'status' => 'active',
        ]);
        $module = ProfessionalModule::query()->create([
            'professional_school_id' => $school->id,
            'programme_id' => $programme->id,
            'course_id' => $course->id,
            'name' => 'Infrastructure Design',
            'code' => 'INFRA',
            'status' => 'active',
        ]);
        $subject = Subject::factory()->create([
            'organization_id' => null,
            'professional_school_id' => $school->id,
            'name' => 'Infrastructure Design',
            'code' => 'INFRA-SUB',
        ]);

        return [$school, $programme, $course, $module, $subject];
    }

    private function examPayload(Subject $subject, array $overrides = []): array
    {
        return [
            'exam_owner_type' => Exam::OWNER_PROFESSIONAL_SCHOOL,
            'exam_category' => Exam::CATEGORY_PROFESSIONAL,
            'title' => 'Cloud Architecture Certification',
            'exam_code' => 'PRO-CLOUD-001',
            'exam_type' => 'professional',
            'mode' => Exam::MODE_ADAPTIVE,
            'exam_mode' => Exam::MODE_ADAPTIVE,
            'delivery_mode' => 'online',
            'start_at' => now()->addDay()->toDateTimeString(),
            'end_at' => now()->addDays(2)->toDateTimeString(),
            'duration_minutes' => 90,
            'pass_mark' => 50,
            'status' => Exam::STATUS_SCHEDULED,
            'subjects' => [
                [
                    'subject_id' => (string) $subject->id,
                    'number_of_questions' => 50,
                    'marks_per_question' => 2,
                ],
            ],
            'settings' => [
                'shuffle_questions' => true,
                'shuffle_options' => true,
                'show_result_immediately' => false,
                'allow_back_navigation' => true,
                'require_webcam' => false,
                'require_fullscreen' => false,
                'max_tab_switches' => 3,
                'negative_marking' => false,
                'negative_mark_value' => null,
                'bind_device' => false,
                'allow_retake' => false,
            ],
            ...$overrides,
        ];
    }
}
