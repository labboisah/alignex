<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Candidate;
use App\Models\CandidateExamAttempt;
use App\Models\CandidateGroup;
use App\Models\CandidatePaper;
use App\Models\Department;
use App\Models\Exam;
use App\Models\ExamParticipant;
use App\Models\Faculty;
use App\Models\Institution;
use App\Models\Organization;
use App\Models\Programme;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InstitutionAssessmentFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_institution_admin_can_manage_course_question_bank_questions_and_exam(): void
    {
        $organization = Organization::factory()->create();
        $institution = Institution::query()->create([
            'organization_id' => $organization->id,
            'name' => 'AlignEx University',
            'code' => 'AXU',
            'status' => 'active',
        ]);
        $faculty = Faculty::query()->create([
            'institution_id' => $institution->id,
            'name' => 'Science',
            'code' => 'SCI',
            'status' => 'active',
        ]);
        $department = Department::query()->create([
            'institution_id' => $institution->id,
            'faculty_id' => $faculty->id,
            'name' => 'Computer Science',
            'code' => 'CSC',
            'status' => 'active',
        ]);
        $programme = Programme::query()->create([
            'institution_id' => $institution->id,
            'faculty_id' => $faculty->id,
            'department_id' => $department->id,
            'name' => 'BSc Computer Science',
            'code' => 'BSC-CS',
            'duration' => 48,
            'status' => 'active',
        ]);
        $course = Course::query()->create([
            'institution_id' => $institution->id,
            'faculty_id' => $faculty->id,
            'department_id' => $department->id,
            'programme_id' => $programme->id,
            'name' => 'Data Structures',
            'code' => 'CSC201',
            'status' => 'active',
        ]);
        $admin = User::factory()->create([
            'role' => User::ROLE_INSTITUTION_ADMIN,
            'organization_id' => $organization->id,
            'institution_id' => $institution->id,
            'active_context_type' => 'institution',
            'active_context_id' => $institution->id,
        ]);
        $group = CandidateGroup::factory()->create([
            'organization_id' => $organization->id,
            'institution_id' => $institution->id,
            'faculty_id' => $faculty->id,
            'department_id' => $department->id,
            'name' => 'CSC 201 Batch A',
            'code' => 'CSC201-A',
        ]);
        $candidate = Candidate::factory()->create([
            'organization_id' => $organization->id,
            'institution_id' => $institution->id,
            'faculty_id' => $faculty->id,
            'department_id' => $department->id,
            'candidate_number' => 'CSC201-001',
        ]);
        $group->candidates()->attach($candidate->id);

        $this->actingAs($admin)
            ->post('/question-bank', [
                'course_id' => $course->id,
                'name' => 'Data Structures Main Bank',
                'code' => 'DS-MAIN',
                'description' => null,
                'status' => QuestionBank::STATUS_ACTIVE,
            ])
            ->assertRedirect(route('question-bank.index', absolute: false));

        $bank = QuestionBank::query()->where('code', 'DS-MAIN')->firstOrFail();
        $this->assertSame($institution->id, $bank->institution_id);
        $this->assertSame($course->id, $bank->course_id);
        $this->assertNull($bank->subject_id);

        $this->actingAs($admin)
            ->post('/questions', [
                'question_bank_id' => $bank->id,
                'subject_id' => null,
                'topic_id' => null,
                'difficulty' => 'medium',
                'marks' => 1,
                'stem' => 'Which data structure uses FIFO ordering?',
                'explanation' => null,
                'status' => 'approved',
                'options' => [
                    ['label' => 'A', 'option_text' => 'Queue', 'is_correct' => true],
                    ['label' => 'B', 'option_text' => 'Stack', 'is_correct' => false],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('questions', [
            'question_bank_id' => $bank->id,
            'subject_id' => null,
        ]);

        $this->actingAs($admin)
            ->post('/questions/import', [
                'course_id' => $course->id,
                'question_bank_id' => $bank->id,
                'subject_id' => null,
                'topic_id' => null,
                'file' => UploadedFile::fake()->createWithContent(
                    'questions.csv',
                    "difficulty,marks,question_text,explanation,status,option_a,option_b,option_c,option_d,option_e,correct_answer\n".
                    "easy,1,Which structure uses LIFO ordering?,,approved,Queue,Stack,Tree,Graph,,B\n"
                ),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('questions', [
            'question_bank_id' => $bank->id,
            'subject_id' => null,
            'stem' => 'Which structure uses LIFO ordering?',
        ]);

        $this->actingAs($admin)
            ->post('/exams', $this->examPayload($institution->id, $course->id, $bank->id, $group->id))
            ->assertRedirect();

        $exam = Exam::query()->where('code', 'CSC201-CA1')->firstOrFail();
        $this->assertSame(Exam::OWNER_INSTITUTION, $exam->effectiveOwnerType());
        $this->assertSame($institution->id, $exam->institution_id);
        $this->assertSame($course->id, $exam->course_id);
        $this->assertDatabaseHas('exam_subjects', [
            'exam_id' => $exam->id,
            'subject_id' => null,
            'question_bank_id' => $bank->id,
            'question_count' => 10,
        ]);
        $this->assertDatabaseHas('exam_candidates', [
            'exam_id' => $exam->id,
            'candidate_id' => $candidate->id,
        ]);
        $this->assertDatabaseHas('exam_participants', [
            'exam_id' => $exam->id,
            'participant_type' => ExamParticipant::TYPE_CANDIDATE,
            'participant_id' => $candidate->id,
        ]);

        $newCandidate = Candidate::factory()->create([
            'organization_id' => $organization->id,
            'institution_id' => $institution->id,
            'faculty_id' => $faculty->id,
            'department_id' => $department->id,
            'candidate_number' => 'CSC201-002',
        ]);
        $group->candidates()->attach($newCandidate->id);

        $this->actingAs($admin)
            ->post(route('exams.participants.refresh', $exam, absolute: false))
            ->assertRedirect();

        $this->assertDatabaseHas('exam_candidates', [
            'exam_id' => $exam->id,
            'candidate_id' => $newCandidate->id,
        ]);
        $this->assertDatabaseHas('exam_participants', [
            'exam_id' => $exam->id,
            'participant_type' => ExamParticipant::TYPE_CANDIDATE,
            'participant_id' => $newCandidate->id,
        ]);
    }

    public function test_institution_lecturer_can_select_department_candidate_group_when_creating_assessment(): void
    {
        $organization = Organization::factory()->create();
        $institution = Institution::query()->create([
            'organization_id' => $organization->id,
            'name' => 'AlignEx University',
            'code' => 'AXU',
            'status' => 'active',
        ]);
        $faculty = Faculty::query()->create([
            'institution_id' => $institution->id,
            'name' => 'Science',
            'code' => 'SCI',
            'status' => 'active',
        ]);
        $department = Department::query()->create([
            'institution_id' => $institution->id,
            'faculty_id' => $faculty->id,
            'name' => 'Computer Science',
            'code' => 'CSC',
            'status' => 'active',
        ]);
        $otherDepartment = Department::query()->create([
            'institution_id' => $institution->id,
            'faculty_id' => $faculty->id,
            'name' => 'Mathematics',
            'code' => 'MTH',
            'status' => 'active',
        ]);
        $programme = Programme::query()->create([
            'institution_id' => $institution->id,
            'faculty_id' => $faculty->id,
            'department_id' => $department->id,
            'name' => 'BSc Computer Science',
            'code' => 'BSC-CS',
            'duration' => 48,
            'status' => 'active',
        ]);
        $course = Course::query()->create([
            'institution_id' => $institution->id,
            'faculty_id' => $faculty->id,
            'department_id' => $department->id,
            'programme_id' => $programme->id,
            'name' => 'Data Structures',
            'code' => 'CSC201',
            'status' => 'active',
        ]);
        $bank = QuestionBank::query()->create([
            'owner_type' => Exam::OWNER_INSTITUTION,
            'owner_id' => $institution->id,
            'organization_id' => $organization->id,
            'institution_id' => $institution->id,
            'course_id' => $course->id,
            'name' => 'Data Structures Main Bank',
            'code' => 'DS-MAIN',
            'status' => QuestionBank::STATUS_ACTIVE,
        ]);
        $group = CandidateGroup::factory()->create([
            'organization_id' => $organization->id,
            'institution_id' => $institution->id,
            'faculty_id' => $faculty->id,
            'department_id' => $department->id,
            'name' => 'CSC 201 Batch A',
            'code' => 'CSC201-A',
        ]);
        $otherGroup = CandidateGroup::factory()->create([
            'organization_id' => $organization->id,
            'institution_id' => $institution->id,
            'faculty_id' => $faculty->id,
            'department_id' => $otherDepartment->id,
            'name' => 'MTH Batch A',
            'code' => 'MTH-A',
        ]);
        $candidate = Candidate::factory()->create([
            'organization_id' => $organization->id,
            'institution_id' => $institution->id,
            'faculty_id' => $faculty->id,
            'department_id' => $department->id,
            'candidate_number' => 'CSC201-001',
        ]);
        $group->candidates()->attach($candidate->id);

        $lecturer = User::factory()->create([
            'role' => User::ROLE_FACILITATOR,
            'organization_id' => $organization->id,
            'institution_id' => $institution->id,
            'faculty_id' => $faculty->id,
            'department_id' => $department->id,
            'active_context_type' => 'institution',
            'active_context_id' => $institution->id,
        ]);
        $lecturer->assignedCourses()->attach($course->id, [
            'professional_school_id' => null,
            'institution_id' => $institution->id,
            'faculty_id' => $faculty->id,
            'department_id' => $department->id,
            'module_id' => null,
        ]);

        $this->actingAs($lecturer)
            ->get('/exams/create?category=assessment&course_id='.$course->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Exams/Create')
                ->where('candidateGroups.0.id', $group->id)
                ->where('candidateGroups.0.department_id', $department->id)
                ->missing('candidateGroups.1')
            );

        $payload = $this->examPayload($institution->id, $course->id, $bank->id, $group->id);

        $this->actingAs($lecturer)
            ->post('/exams', $payload)
            ->assertRedirect();

        $exam = Exam::query()->where('code', 'CSC201-CA1')->firstOrFail();
        $this->assertSame($department->id, $exam->department_id);
        $this->assertDatabaseHas('exam_participants', [
            'exam_id' => $exam->id,
            'participant_type' => ExamParticipant::TYPE_CANDIDATE,
            'participant_id' => $candidate->id,
        ]);
        $this->assertDatabaseMissing('exam_candidates', [
            'exam_id' => $exam->id,
            'candidate_id' => $otherGroup->id,
        ]);

        $attempt = CandidateExamAttempt::query()->create([
            'candidate_id' => $candidate->id,
            'exam_id' => $exam->id,
            'exam_session_id' => null,
            'center_id' => null,
            'access_code_hash' => bcrypt('CSC201-001'),
            'attempt_number' => 1,
            'status' => CandidateExamAttempt::STATUS_IN_PROGRESS,
        ]);
        $question = Question::query()->create([
            'question_bank_id' => $bank->id,
            'subject_id' => null,
            'topic_id' => null,
            'created_by' => $lecturer->id,
            'question_type' => Question::TYPE_SINGLE_CHOICE,
            'stem' => 'Which structure uses FIFO ordering?',
            'difficulty' => 'medium',
            'marks' => 1,
            'status' => Question::STATUS_APPROVED,
        ]);

        CandidatePaper::query()->create([
            'attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'question_order' => 1,
            'option_order' => [],
        ]);

        $this->actingAs($lecturer)
            ->delete(route('exams.destroy', $exam, absolute: false))
            ->assertRedirect(route('exams.index', absolute: false));

        $this->assertDatabaseMissing('candidate_papers', ['attempt_id' => $attempt->id]);
        $this->assertDatabaseMissing('candidate_exam_attempts', ['id' => $attempt->id]);
        $this->assertDatabaseMissing('exams', ['id' => $exam->id]);
    }

    private function examPayload(int $institutionId, int $courseId, string $bankId, int|string $candidateGroupId): array
    {
        return [
            'institution_id' => $institutionId,
            'exam_owner_type' => Exam::OWNER_INSTITUTION,
            'title' => 'Data Structures CA 1',
            'exam_code' => 'CSC201-CA1',
            'exam_type' => 'assessment',
            'exam_category' => Exam::CATEGORY_ASSESSMENT,
            'mode' => Exam::MODE_TRADITIONAL,
            'delivery_mode' => 'online',
            'start_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'end_at' => now()->addDay()->addHours(2)->format('Y-m-d\TH:i'),
            'duration_minutes' => 60,
            'pass_mark' => 5,
            'status' => Exam::STATUS_SCHEDULED,
            'candidate_group_id' => $candidateGroupId,
            'candidate_group_ids' => [$candidateGroupId],
            'subjects' => [
                [
                    'course_id' => $courseId,
                    'subject_id' => null,
                    'question_bank_id' => $bankId,
                    'question_bank_ids' => [$bankId],
                    'number_of_questions' => 10,
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
        ];
    }
}
