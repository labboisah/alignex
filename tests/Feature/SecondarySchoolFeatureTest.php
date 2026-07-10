<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\AcademicTerm;
use App\Models\Candidate;
use App\Models\CandidateAnswer;
use App\Models\CandidateExamAttempt;
use App\Models\CandidatePerformanceProfile;
use App\Models\ClassArm;
use App\Models\Exam;
use App\Models\ExamParticipant;
use App\Models\ExamType;
use App\Models\Organization;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\SecondarySchool;
use App\Models\Student;
use App\Models\StudentGroup;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SecondarySchoolFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
    }

    public function test_secondary_school_can_be_created_and_belong_to_organization(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $organization = Organization::factory()->create();

        $this->actingAs($admin)
            ->post('/secondary-schools', [
                'organization_id' => $organization->id,
                'name' => 'Unity Secondary School',
                'code' => 'USS',
                'contact_person' => 'Principal',
                'email' => 'principal@uss.test',
                'phone' => '08030000000',
                'address' => '12 School Road',
                'status' => SecondarySchool::STATUS_ACTIVE,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('secondary_schools', [
            'organization_id' => $organization->id,
            'code' => 'USS',
        ]);
    }

    public function test_academic_session_and_term_active_flags_are_unique_per_school(): void
    {
        [$school, $admin] = $this->secondarySchoolAdmin();

        $this->actingAs($admin)->post("/secondary-schools/{$school->id}/academic-sessions", [
            'name' => '2026/2027',
            'code' => '2026',
            'start_date' => '2026-09-01',
            'end_date' => '2027-07-31',
            'status' => 'active',
            'is_active' => true,
        ])->assertRedirect();

        $this->actingAs($admin)->post("/secondary-schools/{$school->id}/academic-sessions", [
            'name' => '2027/2028',
            'code' => '2027',
            'status' => 'active',
            'is_active' => true,
        ])->assertRedirect();

        $this->assertSame(1, $school->academicSessions()->where('is_active', true)->count());
        $activeSession = $school->academicSessions()->where('is_active', true)->firstOrFail();

        foreach (['First Term', 'Second Term'] as $term) {
            $this->actingAs($admin)->post("/secondary-schools/{$school->id}/terms", [
                'academic_session_id' => $activeSession->id,
                'name' => $term,
                'status' => 'active',
                'is_active' => true,
            ])->assertRedirect();
        }

        $this->assertSame(1, $school->terms()->where('is_active', true)->count());
    }

    public function test_student_admission_number_is_unique_per_school(): void
    {
        [$school, $admin] = $this->secondarySchoolAdmin();
        $class = SchoolClass::query()->create(['secondary_school_id' => $school->id, 'name' => 'JSS 1', 'code' => 'JSS1', 'level' => 'JSS 1', 'level_order' => 1, 'status' => 'active']);

        $payload = [
            'school_class_id' => $class->id,
            'admission_number' => 'ADM-001',
            'full_name' => 'Ada Student',
            'guardian_phone' => '08030000000',
            'status' => 'active',
        ];

        $this->actingAs($admin)->post("/secondary-schools/{$school->id}/students", $payload)->assertRedirect();
        $this->actingAs($admin)->post("/secondary-schools/{$school->id}/students", $payload)->assertSessionHasErrors('admission_number');
    }

    public function test_subject_topic_and_question_bank_can_be_created_for_secondary_school(): void
    {
        [$school, $admin] = $this->secondarySchoolAdmin();

        $this->actingAs($admin)->post("/secondary-schools/{$school->id}/subjects", [
            'name' => 'Mathematics',
            'code' => 'MATH',
            'status' => Subject::STATUS_ACTIVE,
        ])->assertRedirect();

        $subject = Subject::query()->where('secondary_school_id', $school->id)->firstOrFail();

        $this->actingAs($admin)->post("/secondary-schools/{$school->id}/topics", [
            'subject_id' => $subject->id,
            'name' => 'Algebra',
            'code' => 'ALG',
            'status' => Topic::STATUS_ACTIVE,
        ])->assertRedirect();

        $this->actingAs($admin)->post('/question-bank', [
            'subject_id' => $subject->id,
            'name' => 'Mathematics Terminal Bank',
            'code' => 'MATH-TERM',
            'status' => QuestionBank::STATUS_ACTIVE,
        ])->assertRedirect();

        $this->assertDatabaseHas('topics', ['subject_id' => $subject->id, 'code' => 'ALG']);
        $this->assertDatabaseHas('question_banks', ['secondary_school_id' => $school->id, 'code' => 'MATH-TERM']);
    }

    public function test_secondary_school_admin_can_create_teacher_with_assigned_subjects(): void
    {
        [$school, $admin] = $this->secondarySchoolAdmin();
        $class = SchoolClass::query()->create(['secondary_school_id' => $school->id, 'name' => 'JSS 1', 'code' => 'JSS1', 'level' => 'JSS 1', 'level_order' => 1, 'status' => 'active']);
        $subject = Subject::factory()->create(['secondary_school_id' => $school->id, 'school_class_id' => $class->id, 'organization_id' => null, 'school_id' => null]);

        $this->actingAs($admin)
            ->post("/secondary-schools/{$school->id}/teachers", [
                'name' => 'Ada Teacher',
                'email' => 'ada.teacher@example.test',
                'password' => 'password123',
                'school_class_id' => $class->id,
                'subject_ids' => [$subject->id],
            ])
            ->assertRedirect();

        $teacher = User::query()->where('email', 'ada.teacher@example.test')->firstOrFail();
        $this->assertSame(User::ROLE_TEACHER, $teacher->role);
        $this->assertSame($school->id, $teacher->secondary_school_id);
        $this->assertDatabaseHas('subject_teacher', [
            'user_id' => $teacher->id,
            'subject_id' => $subject->id,
            'school_class_id' => $class->id,
            'secondary_school_id' => $school->id,
        ]);
    }

    public function test_teacher_can_only_use_assigned_secondary_subject_question_banks(): void
    {
        [$school] = $this->secondarySchoolAdmin();
        $class = SchoolClass::query()->create(['secondary_school_id' => $school->id, 'name' => 'JSS 1', 'code' => 'JSS1', 'level' => 'JSS 1', 'level_order' => 1, 'status' => 'active']);
        $assigned = Subject::factory()->create(['secondary_school_id' => $school->id, 'school_class_id' => $class->id, 'organization_id' => null, 'school_id' => null]);
        $unassigned = Subject::factory()->create(['secondary_school_id' => $school->id, 'school_class_id' => $class->id, 'organization_id' => null, 'school_id' => null]);
        $assignedBank = QuestionBank::factory()->create(['secondary_school_id' => $school->id, 'organization_id' => null, 'school_id' => null, 'subject_id' => $assigned->id, 'status' => QuestionBank::STATUS_ACTIVE]);
        $unassignedBank = QuestionBank::factory()->create(['secondary_school_id' => $school->id, 'organization_id' => null, 'school_id' => null, 'subject_id' => $unassigned->id, 'status' => QuestionBank::STATUS_ACTIVE]);
        $teacher = User::factory()->create([
            'role' => User::ROLE_TEACHER,
            'secondary_school_id' => $school->id,
            'active_context_type' => 'secondary_school',
            'active_context_id' => $school->id,
        ]);
        $teacher->assignedSubjects()->attach($assigned->id, ['school_class_id' => $class->id, 'secondary_school_id' => $school->id]);

        $questionPayload = [
            'question_bank_id' => $assignedBank->id,
            'subject_id' => $assigned->id,
            'difficulty' => 'medium',
            'marks' => 1,
            'stem' => 'What is 2 + 2?',
            'status' => Question::STATUS_DRAFT,
            'options' => [
                ['label' => 'A', 'option_text' => '3', 'is_correct' => false],
                ['label' => 'B', 'option_text' => '4', 'is_correct' => true],
            ],
        ];

        $this->actingAs($teacher)->post('/questions', $questionPayload)->assertRedirect();

        $this->actingAs($teacher)
            ->post('/questions', [
                ...$questionPayload,
                'question_bank_id' => $unassignedBank->id,
                'subject_id' => $unassigned->id,
            ])
            ->assertSessionHasErrors('question_bank_id');
    }

    public function test_teacher_can_create_assessment_but_not_terminal_exam(): void
    {
        [$school, $admin, $subject, $session, $term, $class] = $this->terminalExamSetup();
        $teacher = User::factory()->create([
            'role' => User::ROLE_TEACHER,
            'secondary_school_id' => $school->id,
            'active_context_type' => 'secondary_school',
            'active_context_id' => $school->id,
        ]);
        $teacher->assignedSubjects()->attach($subject->id, ['school_class_id' => $class->id, 'secondary_school_id' => $school->id]);

        $this->actingAs($teacher)
            ->post('/exams', $this->terminalExamPayload($school, $subject, $session, $term, $class, ['exam_code' => 'TCHR-TERM-001']))
            ->assertSessionHasErrors('exam_category');

        $this->actingAs($teacher)
            ->post('/exams', $this->terminalExamPayload($school, $subject, $session, $term, $class, [
                'exam_code' => 'TCHR-ASSMT-001',
                'exam_type' => 'assessment',
                'exam_category' => Exam::CATEGORY_ASSESSMENT,
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('exams', [
            'code' => 'TCHR-ASSMT-001',
            'exam_category' => Exam::CATEGORY_ASSESSMENT,
            'created_by' => $teacher->id,
        ]);
    }

    public function test_secondary_school_can_create_terminal_traditional_exam(): void
    {
        [$school, $admin, $subject, $session, $term, $class] = $this->terminalExamSetup();

        $this->actingAs($admin)
            ->post('/exams', $this->terminalExamPayload($school, $subject, $session, $term, $class, ['candidate_ids' => []]))
            ->assertRedirect();

        $this->assertDatabaseHas('exams', [
            'secondary_school_id' => $school->id,
            'exam_category' => Exam::CATEGORY_TERMINAL,
            'exam_mode' => Exam::MODE_TRADITIONAL,
            'academic_session_id' => $session->id,
            'academic_term_id' => $term->id,
            'school_class_id' => null,
            'subject_id' => $subject->id,
        ]);
        $exam = Exam::query()->where('secondary_school_id', $school->id)->latest('id')->firstOrFail();
        $this->assertDatabaseHas('exam_subjects', [
            'exam_id' => $exam->id,
            'subject_id' => $subject->id,
            'question_bank_id' => QuestionBank::query()->where('subject_id', $subject->id)->value('id'),
        ]);
    }

    public function test_secondary_school_cannot_create_adaptive_or_recruitment_exam(): void
    {
        [$school, $admin, $subject, $session, $term, $class] = $this->terminalExamSetup();

        $this->actingAs($admin)
            ->post('/exams', $this->terminalExamPayload($school, $subject, $session, $term, $class, ['exam_mode' => Exam::MODE_ADAPTIVE, 'mode' => Exam::MODE_ADAPTIVE]))
            ->assertSessionHasErrors('exam_mode');

        $this->actingAs($admin)
            ->post('/exams', $this->terminalExamPayload($school, $subject, $session, $term, $class, ['exam_category' => Exam::CATEGORY_RECRUITMENT]))
            ->assertSessionHasErrors('exam_category');
    }

    public function test_secondary_school_exam_requires_academic_session_term_student_group_and_subject(): void
    {
        [$school, $admin, $subject, $session, $term, $class] = $this->terminalExamSetup();

        $payload = $this->terminalExamPayload($school, $subject, $session, $term, $class);
        unset($payload['academic_session_id'], $payload['term_id'], $payload['student_group_id']);
        $payload['subjects'][0]['subject_id'] = '';
        $payload['subjects'][0]['question_bank_id'] = '';

        $this->actingAs($admin)
            ->post('/exams', $payload)
            ->assertSessionHasErrors(['academic_session_id', 'term_id', 'student_group_id', 'subjects.0.subject_id', 'subjects.0.question_bank_id']);
    }

    public function test_secondary_sidebar_and_dashboard_show_secondary_metrics(): void
    {
        [$school, $admin] = $this->secondarySchoolAdmin();
        Student::query()->create(['secondary_school_id' => $school->id, 'admission_number' => 'ADM-1', 'first_name' => 'Ada', 'status' => 'active']);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Index')
                ->where('role.scope', 'Secondary school scope')
                ->where('quick_actions.0.label', 'Manage Academic Sessions')
                ->where('auth.navigation', function ($navigation) {
                    $labels = collect($navigation)
                        ->flatMap(fn ($item) => collect([data_get($item, 'label')])->merge(collect(data_get($item, 'children', []))->pluck('label')))
                        ->all();

                    return in_array('Administration', $labels, true)
                        && in_array('Academic Sessions', $labels, true)
                        && in_array('Arms / Sections', $labels, true)
                        && in_array('Exam', $labels, true)
                        && in_array('Questions', $labels, true)
                        && in_array('Exams', $labels, true)
                        && in_array('Reports', $labels, true)
                        && ! in_array('Programmes', $labels, true);
                })
            );

        $this->actingAs($admin)->get('/reports')->assertOk();
    }

    public function test_secondary_exam_create_page_displays_terms_attached_through_session(): void
    {
        $school = School::factory()->create();
        $admin = User::factory()->create([
            'role' => User::ROLE_SCHOOL_ADMIN,
            'school_id' => $school->id,
        ]);
        $session = AcademicSession::query()->create([
            'school_id' => $school->id,
            'name' => '2026/2027',
            'code' => '2026',
            'status' => 'active',
            'is_active' => true,
        ]);
        $term = AcademicTerm::query()->create([
            'academic_session_id' => $session->id,
            'name' => 'First Term',
            'code' => 'T1',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get('/exams/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Exams/Create')
                ->where('academicTerms.0.id', $term->id)
                ->where('academicTerms.0.academic_session_id', $session->id)
            );
    }

    public function test_corrected_secondary_administration_dropdown_pages_open(): void
    {
        [$school, $admin] = $this->secondarySchoolAdmin();

        foreach ([
            "/secondary-schools/{$school->id}/academic-sessions",
            "/secondary-schools/{$school->id}/terms",
            "/secondary-schools/{$school->id}/classes",
            "/secondary-schools/{$school->id}/arms",
            "/secondary-schools/{$school->id}/student-groups",
            "/secondary-schools/{$school->id}/students",
        ] as $path) {
            $this->actingAs($admin)->get($path)->assertOk();
        }
    }

    public function test_legacy_school_admin_secondary_dropdown_uses_distinct_administration_pages(): void
    {
        $school = School::factory()->create();
        $admin = User::factory()->create([
            'role' => User::ROLE_SCHOOL_ADMIN,
            'school_id' => $school->id,
        ]);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('current_context.source', 'legacy_school')
                ->where('auth.navigation', function ($navigation) {
                    $administration = collect($navigation)->firstWhere('label', 'Administration');

                    $this->assertNotNull($administration);
                    $this->assertSame([
                        '/secondary-school/academic-sessions',
                        '/secondary-school/terms',
                        '/secondary-school/classes',
                        '/secondary-school/arms',
                        '/secondary-school/students',
                        '/secondary-school/student-groups',
                    ], collect($administration['children'])->pluck('href')->values()->all());

                    return true;
                })
            );

        foreach ([
            '/secondary-school/academic-sessions' => 'SecondarySchools/AcademicSessions',
            '/secondary-school/terms' => 'SecondarySchools/Terms',
            '/secondary-school/classes' => 'SecondarySchools/Classes',
            '/secondary-school/arms' => 'SecondarySchools/Arms',
            '/secondary-school/students' => 'SecondarySchools/Students',
            '/secondary-school/student-groups' => 'SecondarySchools/StudentGroups',
        ] as $path => $component) {
            $this->actingAs($admin)
                ->get($path)
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component($component)
                    ->where('basePath', $path)
                );
        }
    }

    public function test_legacy_school_admin_can_create_arm_without_teacher_id(): void
    {
        $school = School::factory()->create();
        $admin = User::factory()->create([
            'role' => User::ROLE_SCHOOL_ADMIN,
            'school_id' => $school->id,
        ]);
        $class = SchoolClass::query()->create([
            'school_id' => $school->id,
            'name' => 'JSS 1',
            'code' => 'JSS1',
            'level' => 'JSS 1',
            'level_order' => 1,
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->post('/secondary-school/arms', [
                'school_class_id' => $class->id,
                'name' => 'Gold',
                'status' => 'active',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('class_arms', [
            'school_id' => $school->id,
            'school_class_id' => $class->id,
            'name' => 'Gold',
            'class_teacher_id' => null,
        ]);
    }

    public function test_students_can_be_imported_from_secondary_school_students_page(): void
    {
        [$school, $admin] = $this->secondarySchoolAdmin();
        $class = SchoolClass::query()->create([
            'secondary_school_id' => $school->id,
            'name' => 'JSS 1',
            'code' => 'JSS1',
            'level' => 'JSS 1',
            'level_order' => 1,
            'status' => 'active',
        ]);
        $arm = ClassArm::query()->create([
            'secondary_school_id' => $school->id,
            'school_class_id' => $class->id,
            'name' => 'Gold',
            'code' => 'GOLD',
            'status' => 'active',
        ]);
        $file = UploadedFile::fake()->createWithContent(
            'students.csv',
            "admission_number,full_name,gender,email,phone,guardian_name,guardian_phone,status\nADM-CSV-1,Ada Student,female,ada@example.test,08030000000,Parent Name,08030000001,active\n"
        );

        $this->actingAs($admin)
            ->post("/secondary-schools/{$school->id}/students/import", [
                'file' => $file,
                'school_class_id' => $class->id,
                'class_arm_id' => $arm->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('students', [
            'secondary_school_id' => $school->id,
            'school_class_id' => $class->id,
            'class_arm_id' => $arm->id,
            'admission_number' => 'ADM-CSV-1',
            'first_name' => 'Ada',
            'last_name' => 'Student',
        ]);
    }

    public function test_secondary_administration_items_can_be_updated_and_deleted(): void
    {
        [$school, $admin] = $this->secondarySchoolAdmin();

        $session = AcademicSession::query()->create(['secondary_school_id' => $school->id, 'name' => '2026/2027', 'code' => '2026', 'status' => 'active']);
        $this->actingAs($admin)->patch("/secondary-schools/{$school->id}/academic-sessions/{$session->id}", [
            'name' => '2027/2028',
            'code' => '2027',
            'status' => 'active',
            'is_active' => true,
        ])->assertRedirect();
        $this->assertDatabaseHas('academic_sessions', ['id' => $session->id, 'name' => '2027/2028', 'is_active' => true]);

        $term = AcademicTerm::query()->create(['secondary_school_id' => $school->id, 'academic_session_id' => $session->id, 'name' => 'First Term', 'code' => 'T1', 'status' => 'active']);
        $this->actingAs($admin)->patch("/secondary-schools/{$school->id}/terms/{$term->id}", [
            'academic_session_id' => $session->id,
            'name' => 'Second Term',
            'code' => 'T2',
            'status' => 'active',
            'is_active' => true,
        ])->assertRedirect();
        $this->assertDatabaseHas('academic_terms', ['id' => $term->id, 'name' => 'Second Term', 'is_active' => true]);

        $class = SchoolClass::query()->create(['secondary_school_id' => $school->id, 'name' => 'JSS 1', 'code' => 'JSS1', 'level' => 'JSS 1', 'level_order' => 1, 'status' => 'active']);
        $this->actingAs($admin)->patch("/secondary-schools/{$school->id}/classes/{$class->id}", [
            'name' => 'JSS One',
            'level' => 'JSS 1',
            'level_order' => 2,
            'status' => 'inactive',
        ])->assertRedirect();
        $this->assertDatabaseHas('school_classes', ['id' => $class->id, 'name' => 'JSS One', 'status' => 'inactive']);

        $arm = ClassArm::query()->create(['secondary_school_id' => $school->id, 'school_class_id' => $class->id, 'name' => 'Gold', 'code' => 'GOLD', 'status' => 'active']);
        $this->actingAs($admin)->patch("/secondary-schools/{$school->id}/arms/{$arm->id}", [
            'school_class_id' => $class->id,
            'name' => 'Blue',
            'status' => 'inactive',
        ])->assertRedirect();
        $this->assertDatabaseHas('class_arms', ['id' => $arm->id, 'name' => 'Blue', 'status' => 'inactive']);

        $group = StudentGroup::query()->create(['school_class_id' => $class->id, 'name' => 'Science', 'code' => 'SCI', 'status' => 'active']);
        $this->actingAs($admin)->patch("/secondary-schools/{$school->id}/student-groups/{$group->id}", [
            'school_class_id' => $class->id,
            'name' => 'Commercial',
            'code' => 'COM',
            'status' => 'inactive',
        ])->assertRedirect();
        $this->assertDatabaseHas('student_groups', ['id' => $group->id, 'name' => 'Commercial', 'status' => 'inactive']);

        $student = Student::query()->create(['secondary_school_id' => $school->id, 'school_class_id' => $class->id, 'admission_number' => 'ADM-1', 'first_name' => 'Ada', 'status' => 'active']);
        $this->actingAs($admin)->patch("/secondary-schools/{$school->id}/students/{$student->id}", [
            'school_class_id' => $class->id,
            'admission_number' => 'ADM-2',
            'full_name' => 'Ada Updated',
            'status' => 'inactive',
        ])->assertRedirect();
        $this->assertDatabaseHas('students', ['id' => $student->id, 'admission_number' => 'ADM-2', 'first_name' => 'Ada', 'last_name' => 'Updated', 'status' => 'inactive']);

        $this->actingAs($admin)->delete("/secondary-schools/{$school->id}/students/{$student->id}")->assertRedirect();
        $this->actingAs($admin)->delete("/secondary-schools/{$school->id}/student-groups/{$group->id}")->assertRedirect();
        $this->actingAs($admin)->delete("/secondary-schools/{$school->id}/arms/{$arm->id}")->assertRedirect();
        $this->actingAs($admin)->delete("/secondary-schools/{$school->id}/classes/{$class->id}")->assertRedirect();
        $this->actingAs($admin)->delete("/secondary-schools/{$school->id}/terms/{$term->id}")->assertRedirect();
        $this->actingAs($admin)->delete("/secondary-schools/{$school->id}/academic-sessions/{$session->id}")->assertRedirect();

        $this->assertSoftDeleted('students', ['id' => $student->id]);
        $this->assertSoftDeleted('student_groups', ['id' => $group->id]);
        $this->assertDatabaseMissing('class_arms', ['id' => $arm->id]);
        $this->assertSoftDeleted('school_classes', ['id' => $class->id]);
        $this->assertSoftDeleted('academic_terms', ['id' => $term->id]);
        $this->assertSoftDeleted('academic_sessions', ['id' => $session->id]);
    }

    public function test_school_admin_can_manage_student_groups_and_batch_secondary_exam(): void
    {
        [$school, $admin, $subject, $session, $term, $class] = $this->terminalExamSetup();
        $studentA = Student::query()->create([
            'secondary_school_id' => $school->id,
            'school_class_id' => $class->id,
            'admission_number' => 'ADM-GRP-1',
            'first_name' => 'Ada',
            'last_name' => 'Student',
            'status' => 'active',
        ]);
        $studentB = Student::query()->create([
            'secondary_school_id' => $school->id,
            'school_class_id' => $class->id,
            'admission_number' => 'ADM-GRP-2',
            'first_name' => 'Bola',
            'last_name' => 'Student',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->post("/secondary-schools/{$school->id}/student-groups", [
                'school_class_id' => $class->id,
                'name' => 'Science',
                'code' => 'SCI',
                'status' => 'active',
                'student_ids' => [$studentA->id, $studentB->id],
            ])
            ->assertRedirect();

        $group = $class->groups()->where('code', 'SCI')->firstOrFail();
        $this->assertDatabaseHas('student_group_student', ['student_group_id' => $group->id, 'student_id' => $studentA->id]);
        $this->assertDatabaseHas('student_group_student', ['student_group_id' => $group->id, 'student_id' => $studentB->id]);
        $candidate = Candidate::factory()->create([
            'organization_id' => null,
            'secondary_school_id' => $school->id,
            'candidate_number' => 'STU-GRP-001',
        ]);
        $examType = ExamType::factory()->create(['name' => 'Secondary', 'code' => 'secondary']);
        $exam = Exam::factory()->create([
            'organization_id' => null,
            'secondary_school_id' => $school->id,
            'exam_type_id' => $examType->id,
            'code' => 'SEC-CERT-001',
            'total_marks' => 70,
            'pass_mark' => 40,
        ]);
        $exam->candidates()->attach($candidate->id, ['status' => 'assigned']);
        CandidateExamAttempt::factory()->create([
            'candidate_id' => $candidate->id,
            'exam_id' => $exam->id,
            'status' => CandidateExamAttempt::STATUS_SUBMITTED,
            'submitted_at' => now(),
            'score' => 50,
            'total_questions' => 10,
            'total_marks' => 70,
        ]);
        $topic = Topic::factory()->create(['subject_id' => $subject->id, 'name' => 'Algebra']);

        CandidatePerformanceProfile::query()->create([
            'candidate_id' => $candidate->id,
            'exam_id' => $exam->id,
            'subject_id' => $subject->id,
            'topic_id' => $topic->id,
            'difficulty' => 'medium',
            'total_questions' => 10,
            'correct_answers' => 3,
            'score_percentage' => 30,
            'mastery_level' => CandidatePerformanceProfile::MASTERY_WEAK,
        ]);

        $this->actingAs($admin)
            ->get("/secondary-school?exam_id={$exam->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Secondary/Index')
                ->where('weaknesses.0.topic', $topic->name)
            );

        $this->actingAs($admin)
            ->post('/exams', $this->terminalExamPayload(
                $school,
                $subject,
                $session,
                $term,
                $class,
                ['exam_code' => 'SEC-GROUP-001', 'student_group_id' => $group->id]
            ))
            ->assertRedirect();

        $this->assertDatabaseHas('exams', ['code' => 'SEC-GROUP-001']);
        $groupExam = Exam::query()->where('code', 'SEC-GROUP-001')->firstOrFail();
        $this->assertSame($group->id, $groupExam->settings['secondary_student_group_id']);
        $this->assertEqualsCanonicalizing([(string) $studentA->id, (string) $studentB->id], $groupExam->settings['secondary_student_ids']);
        $this->assertDatabaseHas('exam_participants', ['exam_id' => $groupExam->id, 'participant_type' => ExamParticipant::TYPE_STUDENT, 'participant_id' => $studentA->id]);
        $this->assertDatabaseHas('exam_participants', ['exam_id' => $groupExam->id, 'participant_type' => ExamParticipant::TYPE_STUDENT, 'participant_id' => $studentB->id]);

        $this->actingAs($admin)
            ->get("/exams/{$exam->id}/certification")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Professional/Show')
                ->where('exam.exam_type', 'secondary')
            );

        $attempt = CandidateExamAttempt::query()->where('exam_id', $exam->id)->where('candidate_id', $candidate->id)->firstOrFail();

        $this->actingAs($admin)
            ->post("/exams/{$exam->id}/certification/templates", [
                'name' => 'Secondary Certificate',
                'title' => 'Certificate of Achievement',
                'body' => 'This certifies {{candidate_name}} passed {{exam_title}}.',
                'signatory_name' => 'Principal',
                'signatory_title' => 'School Head',
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post("/exams/{$exam->id}/certification/attempts/{$attempt->id}/certificate")
            ->assertRedirect();

        $certificate = $attempt->certificate()->firstOrFail();
        $this->assertStringStartsWith('SEC-', $certificate->serial_number);

        $this->postJson('/api/certificates/verify', ['identifier' => $certificate->serial_number])
            ->assertOk()
            ->assertJsonPath('valid', true)
            ->assertJsonPath('certificate.registration_number', $candidate->candidate_number);
    }

    private function secondaryExam(): array
    {
        $school = School::factory()->create();
        $examType = ExamType::factory()->create([
            'name' => 'Secondary',
            'code' => 'secondary',
        ]);
        $subject = Subject::factory()->create(['organization_id' => null, 'school_id' => $school->id]);
        $topic = Topic::factory()->create(['subject_id' => $subject->id, 'name' => 'Algebra']);
        $bank = QuestionBank::factory()->create(['organization_id' => null, 'school_id' => $school->id, 'subject_id' => $subject->id]);
        $question = Question::factory()->create([
            'question_bank_id' => $bank->id,
            'subject_id' => $subject->id,
            'topic_id' => $topic->id,
        ]);
        $exam = Exam::factory()->create([
            'organization_id' => null,
            'school_id' => $school->id,
            'exam_type_id' => $examType->id,
            'code' => 'SEC-001',
            'total_marks' => 70,
            'pass_mark' => 40,
        ]);
        $candidate = Candidate::factory()->create([
            'organization_id' => null,
            'school_id' => $school->id,
            'candidate_number' => 'STU-001',
        ]);
        $exam->candidates()->attach($candidate->id, ['status' => 'assigned']);
        $attempt = CandidateExamAttempt::factory()->create([
            'candidate_id' => $candidate->id,
            'exam_id' => $exam->id,
            'status' => CandidateExamAttempt::STATUS_SUBMITTED,
            'submitted_at' => now(),
            'score' => 50,
            'total_questions' => 10,
            'total_marks' => 70,
        ]);
        CandidateAnswer::factory()->create([
            'candidate_exam_attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'subject_id' => $subject->id,
            'score_awarded' => 50,
            'scored_at' => now(),
            'submitted_at' => now(),
        ]);

        return [$exam->refresh(), $candidate->refresh(), $subject->refresh(), $topic->refresh()];
    }

    private function secondarySchoolAdmin(): array
    {
        $school = SecondarySchool::query()->create([
            'name' => 'Demo Secondary',
            'code' => fake()->unique()->bothify('SEC-###'),
            'contact_person' => 'Principal',
            'email' => fake()->unique()->safeEmail(),
            'status' => SecondarySchool::STATUS_ACTIVE,
        ]);
        $admin = User::factory()->create([
            'role' => User::ROLE_SECONDARY_SCHOOL_ADMIN,
            'secondary_school_id' => $school->id,
        ]);

        return [$school, $admin];
    }

    private function terminalExamSetup(): array
    {
        [$school, $admin] = $this->secondarySchoolAdmin();
        $subject = Subject::factory()->create(['organization_id' => null, 'school_id' => null, 'secondary_school_id' => $school->id]);
        $session = AcademicSession::query()->create(['secondary_school_id' => $school->id, 'name' => '2026/2027', 'code' => '2026', 'status' => 'active', 'is_active' => true]);
        $term = AcademicTerm::query()->create(['secondary_school_id' => $school->id, 'academic_session_id' => $session->id, 'name' => 'First Term', 'code' => 'T1', 'status' => 'active', 'is_active' => true]);
        $class = SchoolClass::query()->create(['secondary_school_id' => $school->id, 'name' => 'JSS 1', 'code' => 'JSS1', 'level' => 'JSS 1', 'level_order' => 1, 'status' => 'active']);
        StudentGroup::query()->create(['school_class_id' => $class->id, 'name' => 'General Group', 'code' => 'GEN', 'status' => 'active']);
        QuestionBank::factory()->create(['organization_id' => null, 'school_id' => null, 'secondary_school_id' => $school->id, 'subject_id' => $subject->id]);

        return [$school, $admin, $subject, $session, $term, $class];
    }

    private function terminalExamPayload(SecondarySchool $school, Subject $subject, AcademicSession $session, AcademicTerm $term, SchoolClass $class, array $overrides = []): array
    {
        return array_replace_recursive([
            'secondary_school_id' => $school->id,
            'exam_owner_type' => Exam::OWNER_SECONDARY_SCHOOL,
            'title' => 'First Term Mathematics',
            'exam_code' => fake()->unique()->bothify('TERM-###'),
            'exam_type' => 'secondary',
            'exam_category' => Exam::CATEGORY_TERMINAL,
            'mode' => Exam::MODE_TRADITIONAL,
            'exam_mode' => Exam::MODE_TRADITIONAL,
            'academic_session_id' => $session->id,
            'term_id' => $term->id,
            'student_group_id' => $class->groups()->firstOrFail()->id,
            'subject_id' => $subject->id,
            'delivery_mode' => 'online',
            'start_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'end_at' => now()->addDay()->addHours(2)->format('Y-m-d\TH:i'),
            'duration_minutes' => 60,
            'pass_mark' => 20,
            'status' => Exam::STATUS_SCHEDULED,
            'subjects' => [
                [
                    'subject_id' => $subject->id,
                    'question_bank_id' => QuestionBank::query()->where('subject_id', $subject->id)->value('id'),
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
