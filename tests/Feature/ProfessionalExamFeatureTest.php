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
use App\Services\AdaptivePreparationService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
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
            'course_id' => null,
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
            'exam_category' => Exam::CATEGORY_ASSESSMENT,
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
            ['PRO-ADAPT-001', Exam::CATEGORY_ASSESSMENT, Exam::MODE_ADAPTIVE],
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
        $this->grantPlanFeatures($exam->organization, ['certificate_generation']);
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

    public function test_adaptive_module_rows_can_share_a_subject_without_losing_their_banks_or_budgets(): void
    {
        [$school, $programme, $course, $module, $subject] = $this->professionalHierarchy();
        $admin = User::factory()->create(['role' => User::ROLE_PROFESSIONAL_SCHOOL_ADMIN, 'professional_school_id' => $school->id]);
        $otherModule = $module->replicate();
        $otherModule->fill(['name' => 'Second module', 'code' => 'SECOND'])->save();
        $payload = $this->examPayload($subject, [
            'professional_school_id' => $school->id, 'programme_id' => $programme->id,
            'course_id' => $course->id, 'module_id' => null, 'exam_code' => 'SHARED-ADAPT',
        ]);
        $bank = QuestionBank::findOrFail($payload['question_bank_id']);
        $bank->update(['module_id' => $module->id]);
        $otherBank = $bank->replicate();
        $otherBank->fill(['name' => 'Second bank', 'code' => 'SECOND-BANK', 'module_id' => $otherModule->id])->save();
        $payload['subjects'][0] += ['question_bank_ids' => [$bank->id], 'course_id' => $course->id, 'module_id' => $module->id];
        $payload['subjects'][] = [
            'subject_id' => $subject->id, 'question_bank_ids' => [$otherBank->id],
            'course_id' => $course->id, 'module_id' => $otherModule->id,
            'number_of_questions' => 7, 'marks_per_question' => 1,
        ];
        $this->actingAs($admin)->post('/exams', $payload)->assertRedirect()->assertSessionHasNoErrors();
        $exam = Exam::where('code', 'SHARED-ADAPT')->firstOrFail();
        $rows = $exam->examSubjects()->orderBy('display_order')->get();
        $this->assertCount(2, $rows);
        $this->assertSame([$subject->id, $subject->id], $rows->pluck('subject_id')->all());
        $this->assertSame([$bank->id, $otherBank->id], $rows->pluck('question_bank_id')->all());
        $this->assertEquals([50, 7], $rows->pluck('question_count')->all());
        $this->assertEquals([100, 7], $rows->pluck('total_marks')->all());
        $areas = app(AdaptivePreparationService::class)->inspect($exam)['blueprint']['areas'];
        $this->assertCount(2, $areas);
        $this->assertNotSame($areas[0]['area_key'], $areas[1]['area_key']);
        $this->assertEquals([$module->id, $otherModule->id], array_column($areas, 'module_id'));
        $payload['subjects'][1]['number_of_questions'] = 8;
        $this->patch("/exams/{$exam->id}", $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertEquals([50, 8], $exam->examSubjects()->orderBy('display_order')->pluck('question_count')->all());

        // Switching the same rows to traditional mode must fail before persistence.
        $payload['mode'] = $payload['exam_mode'] = Exam::MODE_TRADITIONAL;
        $this->patch("/exams/{$exam->id}", $payload)->assertSessionHasErrors('subjects.1.subject_id');
        $this->assertSame(Exam::MODE_ADAPTIVE, $exam->fresh()->exam_mode);
    }

    public function test_traditional_duplicate_subject_rows_return_validation_instead_of_database_errors(): void
    {
        [$school, $programme, $course, $module, $subject] = $this->professionalHierarchy();
        $admin = User::factory()->create(['role' => User::ROLE_PROFESSIONAL_SCHOOL_ADMIN, 'professional_school_id' => $school->id]);
        $payload = $this->examPayload($subject, [
            'professional_school_id' => $school->id, 'programme_id' => $programme->id,
            'course_id' => $course->id, 'module_id' => $module->id,
            'mode' => Exam::MODE_TRADITIONAL, 'exam_mode' => Exam::MODE_TRADITIONAL,
            'exam_code' => 'DUPLICATE-TRAD',
        ]);
        $payload['subjects'][] = $payload['subjects'][0];
        $this->actingAs($admin)->post('/exams', $payload)->assertSessionHasErrors('subjects.1.subject_id');
        $this->assertDatabaseMissing('exams', ['code' => 'DUPLICATE-TRAD']);
    }

    public function test_question_upload_shows_active_hierarchy_without_banks_and_includes_draft_banks(): void
    {
        [$school, $programme, $course, $module, $subject] = $this->professionalHierarchy();
        $admin = User::factory()->create(['role' => User::ROLE_PROFESSIONAL_SCHOOL_ADMIN, 'professional_school_id' => $school->id]);
        foreach (['/questions', "/professional-schools/{$school->id}/questions"] as $url) {
            $this->actingAs($admin)->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
                ->has('importCourses', 1)->where('importCourses.0.id', $course->id)
                ->has('importModules', 1)->where('importModules.0.id', $module->id));
        }
        $bank = QuestionBank::factory()->create(['organization_id' => null, 'professional_school_id' => $school->id,
            'subject_id' => $subject->id, 'course_id' => $course->id, 'module_id' => $module->id, 'status' => 'draft']);
        $this->get("/professional-schools/{$school->id}/questions")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('questionBanks', 1)->where('questionBanks.0.id', $bank->id));
    }

    public function test_management_options_include_inactive_empty_hierarchy_and_archived_banks(): void
    {
        [$school, $programme, $course, $module, $subject] = $this->professionalHierarchy();
        $course->update(['status' => 'inactive']);
        $module->update(['status' => 'inactive']);
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $bank = QuestionBank::factory()->create(['organization_id' => null, 'professional_school_id' => $school->id,
            'subject_id' => $subject->id, 'course_id' => $course->id, 'module_id' => $module->id, 'status' => 'archived']);
        foreach (['/questions', '/question-bank', "/professional-schools/{$school->id}/questions"] as $url) {
            $this->actingAs($admin)->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
                ->has('importCourses', 1)->where('importCourses.0.id', $course->id)
                ->has('importModules', 1)->where('importModules.0.id', $module->id));
        }
        $this->get("/professional-schools/{$school->id}/questions")->assertInertia(fn (Assert $page) => $page
            ->has('questionBanks', 1)->where('questionBanks.0.id', $bank->id)->has('questions', 0));
    }

    public function test_import_hierarchy_is_limited_to_the_facilitators_assignments_and_owner(): void
    {
        [$school, $programme, $course, $module] = $this->professionalHierarchy();
        $unassigned = Course::create(['professional_school_id' => $school->id, 'programme_id' => $programme->id, 'name' => 'Unassigned', 'code' => 'UNASSIGNED', 'status' => 'active']);
        $otherSchool = $school->replicate();
        $otherSchool->fill(['code' => 'OTHER-SCHOOL', 'email' => 'other-school@example.test'])->save();
        Course::create(['professional_school_id' => $otherSchool->id, 'name' => 'Other owner', 'code' => 'OTHER', 'status' => 'active']);
        $facilitator = User::factory()->create(['role' => User::ROLE_FACILITATOR, 'professional_school_id' => $school->id]);
        $facilitator->assignedModules()->attach($module->id, ['course_id' => $course->id]);
        foreach (['/questions', "/professional-schools/{$school->id}/questions"] as $url) {
            $this->actingAs($facilitator)->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
                ->has('importCourses', 1)->where('importCourses.0.id', $course->id)
                ->has('importModules', 1)->where('importModules.0.id', $module->id));
        }
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
        $batch = TrainingBatch::query()->create([
            'professional_school_id' => $subject->professional_school_id,
            'programme_id' => $overrides['programme_id'] ?? null,
            'name' => 'Exam cohort', 'code' => 'COHORT-'.Str::random(8), 'status' => 'active',
        ]);
        $bank = QuestionBank::factory()->create([
            'organization_id' => null, 'professional_school_id' => $subject->professional_school_id,
            'owner_type' => Exam::OWNER_PROFESSIONAL_SCHOOL, 'owner_id' => $subject->professional_school_id,
            'subject_id' => $subject->id, 'programme_id' => $overrides['programme_id'] ?? null,
            'course_id' => $overrides['course_id'] ?? null, 'module_id' => $overrides['module_id'] ?? null,
        ]);

        return [
            'training_batch_id' => $batch->id,
            'question_bank_id' => $bank->id,
            'exam_owner_type' => Exam::OWNER_PROFESSIONAL_SCHOOL,
            'exam_category' => Exam::CATEGORY_ASSESSMENT,
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
            'status' => Exam::STATUS_DRAFT,
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
