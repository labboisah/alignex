<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\CandidateAnswer;
use App\Models\CandidateExamAttempt;
use App\Models\CandidatePaper;
use App\Models\Course;
use App\Models\Department;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\Faculty;
use App\Models\Institution;
use App\Models\Organization;
use App\Models\Programme;
use App\Models\ProctoringEvent;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\QuestionOption;
use App\Models\Subject;
use App\Models\User;
use App\Services\CandidatePerformanceProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ResultManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_organization_admin_can_view_exam_results_and_exports(): void
    {
        [$exam, $attempt] = $this->submittedAttempt();
        $admin = User::factory()->create([
            'role' => User::ROLE_ORGANIZATION_ADMIN,
            'organization_id' => $exam->organization_id,
        ]);

        $this->actingAs($admin)
            ->get('/results')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Results/Index')
                ->where('dashboard.summary.total', 1)
                ->where('exams.0.exam_code', $exam->code)
            );

        $this->actingAs($admin)
            ->get("/results/exams/{$exam->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Results/Exam')
                ->where('rows.0.registration_number', $attempt->candidate->candidate_number)
                ->where('rows.0.status', 'Pass')
                ->where('rows.0.suspicious_event_count', 1)
                ->where('adaptive_analysis.topic_mastery.0.mastery_level', 'strong')
            );

        $this->actingAs($admin)
            ->get("/results/attempts/{$attempt->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Results/Candidate')
                ->where('adaptive.topic_mastery.0.mastery_level', 'strong')
                ->has('adaptive.difficulty_performance')
            );

        $this->actingAs($admin)
            ->get("/results/exams/{$exam->id}/export.csv")
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $this->actingAs($admin)
            ->get("/results/exams/{$exam->id}/summary.pdf")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertSee('Candidate Performance Table')
            ->assertSee($admin->email);

        $this->actingAs($admin)
            ->get("/results/attempts/{$attempt->id}/marked-paper.pdf")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertSee('Detailed Marked Question Paper')
            ->assertSee('Correct Answer: A');
    }

    public function test_candidate_self_result_and_verification_respect_release_settings(): void
    {
        [$exam, $attempt] = $this->submittedAttempt(['settings' => ['show_result_immediately' => true]]);

        $response = $this->postJson('/api/candidate/result', [
            'exam_code' => $exam->code,
            'registration_number' => $attempt->candidate->candidate_number,
        ])
            ->assertOk()
            ->assertJsonPath('result.registration_number', $attempt->candidate->candidate_number);

        $hash = $response->json('result.result_hash');

        $this->postJson('/api/results/verify', ['hash' => $hash])
            ->assertOk()
            ->assertJsonPath('valid', true)
            ->assertJsonPath('result.result_hash', $hash);
    }

    public function test_online_result_checking_page_is_available_to_candidates(): void
    {
        $this->get('/candidate-result')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Results/Self')
            );
    }

    public function test_candidate_self_result_is_hidden_until_released(): void
    {
        [$exam, $attempt] = $this->submittedAttempt(['settings' => ['show_result_immediately' => false]]);

        $this->postJson('/api/candidate/result', [
            'exam_code' => $exam->code,
            'identifier' => $attempt->candidate->candidate_number,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('exam_code');
    }

    public function test_institution_lecturer_can_view_results_for_assigned_course_assessment(): void
    {
        [$exam, $attempt, $lecturer] = $this->institutionSubmittedAttempt();

        $this->actingAs($lecturer)
            ->get('/results')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Results/Index')
                ->where('dashboard.summary.total', 1)
                ->where('exams.0.exam_code', $exam->code)
            );

        $this->actingAs($lecturer)
            ->get("/results/exams/{$exam->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Results/Exam')
                ->where('rows.0.registration_number', $attempt->candidate->candidate_number)
                ->where('rows.0.status', 'Pass')
            );

        $this->actingAs($lecturer)
            ->get("/results/attempts/{$attempt->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Results/Candidate')
                ->where('result.registration_number', $attempt->candidate->candidate_number)
            );

        [$unassignedExam] = $this->institutionSubmittedAttempt(['lecturer' => $lecturer, 'course_code' => 'MTH101']);

        $this->actingAs($lecturer)
            ->get("/results/exams/{$unassignedExam->id}")
            ->assertForbidden();
    }

    private function submittedAttempt(array $examOverrides = []): array
    {
        $organization = Organization::factory()->create();
        $subject = Subject::factory()->create(['organization_id' => $organization->id]);
        $exam = Exam::factory()->create(array_replace_recursive([
            'organization_id' => $organization->id,
            'total_marks' => 10,
            'pass_mark' => 5,
            'settings' => ['show_result_immediately' => false],
        ], $examOverrides));
        $candidate = Candidate::factory()->create([
            'organization_id' => $organization->id,
            'candidate_number' => 'REG-RESULT-1',
        ]);
        $exam->candidates()->attach($candidate->id, ['status' => 'assigned']);
        $bank = QuestionBank::factory()->create([
            'organization_id' => $organization->id,
            'subject_id' => $subject->id,
        ]);
        $question = Question::factory()->create([
            'question_bank_id' => $bank->id,
            'subject_id' => $subject->id,
            'marks' => 10,
            'stem' => 'What is the correct option?',
            'explanation' => 'A is the expected answer.',
        ]);
        $optionA = QuestionOption::factory()->create([
            'question_id' => $question->id,
            'label' => 'A',
            'option_text' => 'Correct option',
            'display_order' => 1,
            'is_correct' => true,
        ]);
        $optionB = QuestionOption::factory()->create([
            'question_id' => $question->id,
            'label' => 'B',
            'option_text' => 'Wrong option',
            'display_order' => 2,
            'is_correct' => false,
        ]);
        $attempt = CandidateExamAttempt::factory()->create([
            'candidate_id' => $candidate->id,
            'exam_id' => $exam->id,
            'access_code_hash' => Hash::make('access'),
            'status' => CandidateExamAttempt::STATUS_SUBMITTED,
            'started_at' => now()->subMinutes(50),
            'submitted_at' => now()->subMinutes(5),
            'score' => 8,
            'total_questions' => 1,
            'total_marks' => 10,
        ]);
        CandidatePaper::query()->create([
            'attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'question_order' => 1,
            'option_order' => [$optionA->id, $optionB->id],
        ]);
        CandidateAnswer::factory()->create([
            'candidate_exam_attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'subject_id' => $subject->id,
            'selected_option_ids' => [$optionA->id],
            'score_awarded' => 8,
            'submitted_at' => now()->subMinutes(5),
        ]);
        ProctoringEvent::factory()->create([
            'exam_id' => $exam->id,
            'candidate_exam_attempt_id' => $attempt->id,
            'candidate_id' => $candidate->id,
            'severity' => 'warning',
        ]);
        app(CandidatePerformanceProfileService::class)->generate($attempt->refresh()->load(['papers.question', 'answers.question']));

        return [$exam->refresh(), $attempt->refresh()->load('candidate')];
    }

    private function institutionSubmittedAttempt(array $options = []): array
    {
        $organization = Organization::factory()->create();
        $institution = Institution::query()->create([
            'organization_id' => $organization->id,
            'name' => 'AlignEx University',
            'code' => 'AXU'.str()->random(4),
            'status' => 'active',
        ]);
        $faculty = Faculty::query()->create([
            'institution_id' => $institution->id,
            'name' => 'Science',
            'code' => 'SCI'.str()->random(4),
            'status' => 'active',
        ]);
        $department = Department::query()->create([
            'institution_id' => $institution->id,
            'faculty_id' => $faculty->id,
            'name' => 'Computer Science',
            'code' => 'CSC'.str()->random(4),
            'status' => 'active',
        ]);
        $programme = Programme::query()->create([
            'institution_id' => $institution->id,
            'faculty_id' => $faculty->id,
            'department_id' => $department->id,
            'name' => 'BSc Computer Science',
            'code' => 'BSC'.str()->random(4),
            'duration' => 48,
            'status' => 'active',
        ]);
        $course = Course::query()->create([
            'institution_id' => $institution->id,
            'faculty_id' => $faculty->id,
            'department_id' => $department->id,
            'programme_id' => $programme->id,
            'name' => $options['course_name'] ?? 'Data Structures',
            'code' => $options['course_code'] ?? 'CSC201',
            'status' => Course::STATUS_ACTIVE,
        ]);
        $subject = Subject::factory()->create(['organization_id' => $organization->id]);
        $bank = QuestionBank::query()->create([
            'owner_type' => Exam::OWNER_INSTITUTION,
            'owner_id' => $institution->id,
            'organization_id' => $organization->id,
            'institution_id' => $institution->id,
            'course_id' => $course->id,
            'subject_id' => $subject->id,
            'name' => 'Assessment Bank '.$course->code,
            'code' => 'BANK'.str()->random(4),
            'status' => QuestionBank::STATUS_ACTIVE,
        ]);
        $exam = Exam::factory()->create([
            'organization_id' => $organization->id,
            'institution_id' => $institution->id,
            'faculty_id' => $faculty->id,
            'department_id' => $department->id,
            'programme_id' => $programme->id,
            'course_id' => $course->id,
            'exam_category' => Exam::CATEGORY_ASSESSMENT,
            'total_marks' => 10,
            'pass_mark' => 5,
        ]);
        ExamSubject::factory()->create([
            'exam_id' => $exam->id,
            'subject_id' => null,
            'question_bank_id' => $bank->id,
            'question_count' => 1,
            'marks_per_question' => 10,
            'total_marks' => 10,
        ]);
        $question = Question::factory()->create([
            'question_bank_id' => $bank->id,
            'subject_id' => $subject->id,
            'marks' => 10,
            'stem' => 'Institution result question?',
        ]);
        $option = QuestionOption::factory()->create([
            'question_id' => $question->id,
            'label' => 'A',
            'display_order' => 1,
            'is_correct' => true,
        ]);
        $candidate = Candidate::factory()->create([
            'organization_id' => $organization->id,
            'institution_id' => $institution->id,
            'faculty_id' => $faculty->id,
            'department_id' => $department->id,
            'candidate_number' => 'INST-RESULT-'.$course->code,
        ]);
        $exam->candidates()->attach($candidate->id, ['status' => 'assigned']);
        $attempt = CandidateExamAttempt::factory()->create([
            'candidate_id' => $candidate->id,
            'exam_id' => $exam->id,
            'status' => CandidateExamAttempt::STATUS_SUBMITTED,
            'submitted_at' => now()->subMinutes(5),
            'score' => 8,
            'total_questions' => 1,
            'total_marks' => 10,
        ]);
        CandidatePaper::query()->create([
            'attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'question_order' => 1,
            'option_order' => [$option->id],
        ]);
        CandidateAnswer::factory()->create([
            'candidate_exam_attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'subject_id' => $subject->id,
            'selected_option_ids' => [$option->id],
            'score_awarded' => 8,
            'submitted_at' => now()->subMinutes(5),
        ]);

        $lecturer = $options['lecturer'] ?? User::factory()->create([
            'role' => User::ROLE_FACILITATOR,
            'organization_id' => $organization->id,
            'institution_id' => $institution->id,
            'faculty_id' => $faculty->id,
            'department_id' => $department->id,
            'active_context_type' => 'institution',
            'active_context_id' => $institution->id,
        ]);

        if (! isset($options['lecturer'])) {
            $lecturer->assignedCourses()->attach($course->id, [
                'institution_id' => $institution->id,
                'faculty_id' => $faculty->id,
                'department_id' => $department->id,
                'module_id' => null,
            ]);
        }

        return [$exam->refresh(), $attempt->refresh()->load('candidate'), $lecturer->refresh()];
    }
}
