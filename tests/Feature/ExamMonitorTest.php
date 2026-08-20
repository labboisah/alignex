<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\CandidateAnswer;
use App\Models\CandidateExamAttempt;
use App\Models\Center;
use App\Models\Course;
use App\Models\Department;
use App\Models\Exam;
use App\Models\ExamAuditLog;
use App\Models\ExamSupervisor;
use App\Models\ExamSubject;
use App\Models\Faculty;
use App\Models\Institution;
use App\Models\Organization;
use App\Models\Programme;
use App\Models\ProctoringEvent;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExamMonitorTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_can_monitor_assigned_center_exam(): void
    {
        [$exam, $attempt] = $this->examWithAttempt();
        $supervisor = User::factory()->create([
            'role' => User::ROLE_SUPERVISOR,
            'center_id' => $exam->center_id,
        ]);

        $this->actingAs($supervisor)
            ->get("/exams/{$exam->id}/monitor")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ExamMonitor/Show')
                ->where('exam.status', $exam->status)
                ->where('exam.starts_at', $exam->starts_at?->toISOString())
                ->where('exam.ends_at', $exam->ends_at?->toISOString())
                ->has('exam.server_time')
                ->where('summary.total_candidates', 1)
                ->where('summary.logged_in', 1)
                ->where('summary.suspicious', 1)
                ->where('rows.0.registration_number', $attempt->candidate->candidate_number)
            );

        $this->actingAs($supervisor)
            ->getJson("/exams/{$exam->id}/monitor/summary")
            ->assertOk()
            ->assertJsonPath('total_candidates', 1)
            ->assertJsonPath('active', 1);

        $this->actingAs($supervisor)
            ->getJson("/exams/{$exam->id}/monitor/rows")
            ->assertOk()
            ->assertJsonPath('rows.0.answered_questions', 1);

        $this->actingAs($supervisor)
            ->getJson("/exams/{$exam->id}/monitor/feed")
            ->assertOk()
            ->assertJsonPath('feed.0.type', 'suspicious');
    }

    public function test_supervisor_can_reset_candidate_attempt_without_deleting_saved_answers(): void
    {
        [$exam, $attempt] = $this->examWithAttempt();
        $attempt->update([
            'status' => CandidateExamAttempt::STATUS_SUBMITTED,
            'submitted_at' => now(),
            'score' => 1,
            'result_hash' => 'ABC123',
        ]);
        $attempt->answers()->update([
            'submitted_at' => now(),
            'score_awarded' => 1,
            'scored_at' => now(),
        ]);
        $supervisor = User::factory()->create([
            'role' => User::ROLE_SUPERVISOR,
            'center_id' => $exam->center_id,
        ]);

        $this->actingAs($supervisor)
            ->postJson("/exams/{$exam->id}/monitor/attempts/{$attempt->id}/reset", [
                'reason' => 'Device changed during exam.',
            ])
            ->assertOk()
            ->assertJsonPath('reset', true)
            ->assertJsonPath('row.status', 'active')
            ->assertJsonPath('row.answered_questions', 1);

        $attempt->refresh();
        $answer = $attempt->answers()->firstOrFail();
        $this->assertSame(CandidateExamAttempt::STATUS_IN_PROGRESS, $attempt->status);
        $this->assertNull($attempt->submitted_at);
        $this->assertNull($attempt->score);
        $this->assertNull($attempt->result_hash);
        $this->assertSame(['option-1'], $answer->selected_option_ids);
        $this->assertNull($answer->submitted_at);
        $this->assertNull($answer->score_awarded);
        $this->assertDatabaseHas('exam_audit_logs', [
            'candidate_exam_attempt_id' => $attempt->id,
            'event_type' => 'candidate_reset',
            'actor_user_id' => $supervisor->id,
        ]);
        $this->assertSame('device_change', ExamAuditLog::query()
            ->where('candidate_exam_attempt_id', $attempt->id)
            ->where('event_type', 'candidate_reset')
            ->firstOrFail()
            ->metadata['reset_reason_category']);
    }

    public function test_supervisor_cannot_reset_candidate_after_exam_end_time(): void
    {
        [$exam, $attempt] = $this->examWithAttempt();
        $exam->update(['ends_at' => now()->subMinute()]);
        $supervisor = User::factory()->create([
            'role' => User::ROLE_SUPERVISOR,
            'center_id' => $exam->center_id,
        ]);

        $this->actingAs($supervisor)
            ->postJson("/exams/{$exam->id}/monitor/attempts/{$attempt->id}/reset", [
                'reason' => 'Device changed during exam.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('exam');

        $this->assertSame(CandidateExamAttempt::STATUS_IN_PROGRESS, $attempt->refresh()->status);
    }

    public function test_supervisor_cannot_monitor_unassigned_exam(): void
    {
        [$exam] = $this->examWithAttempt();
        $otherCenter = Center::factory()->create();
        $supervisor = User::factory()->create([
            'role' => User::ROLE_SUPERVISOR,
            'center_id' => $otherCenter->id,
        ]);

        $this->actingAs($supervisor)
            ->get("/exams/{$exam->id}/monitor")
            ->assertForbidden();
    }

    public function test_shared_supervisor_can_monitor_and_generate_incident_report(): void
    {
        [$exam] = $this->examWithAttempt();
        $course = Course::query()->create([
            'name' => 'Data Structures',
            'code' => 'CSC201',
            'status' => Course::STATUS_ACTIVE,
        ]);
        $exam->update([
            'title' => 'Mid Semester Assessment',
            'course_id' => $course->id,
        ]);
        $exam->refresh();
        $owner = User::factory()->create([
            'role' => User::ROLE_EXAMINER,
            'organization_id' => $exam->organization_id,
        ]);
        $sharedSupervisor = User::factory()->create([
            'role' => User::ROLE_FACILITATOR,
            'organization_id' => $exam->organization_id,
        ]);

        $this->actingAs($owner)
            ->post("/exams/{$exam->id}/supervisors", [
                'user_id' => $sharedSupervisor->id,
                'role' => ExamSupervisor::ROLE_SUPERVISOR,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('exam_supervisors', [
            'exam_id' => $exam->id,
            'user_id' => $sharedSupervisor->id,
            'revoked_at' => null,
        ]);

        $this->actingAs($sharedSupervisor)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('auth.navigation', 3)
                ->where('auth.navigation.1.label', 'Supervision')
                ->where('auth.navigation.1.children.0.label', 'Mid Semester Assessment - CSC201')
                ->where('auth.navigation.1.children.0.href', "/exams/{$exam->id}/monitor")
            );

        $this->actingAs($sharedSupervisor)
            ->get('/assigned-exams')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ExamMonitor/AssignedExams')
                ->where('exams.0.id', $exam->id)
                ->where('exams.0.exam_code', $exam->code)
                ->where('exams.0.course_label', 'CSC201')
                ->where('exams.0.supervision_role', ExamSupervisor::ROLE_SUPERVISOR)
            );

        $this->actingAs($sharedSupervisor)
            ->get("/exams/{$exam->id}/monitor")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ExamMonitor/Show')
                ->where('summary.total_candidates', 1)
            );

        $this->actingAs($sharedSupervisor)
            ->get("/exams/{$exam->id}/monitor/incident-report")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ExamMonitor/IncidentReport')
                ->where('summary.suspicious', 1)
                ->where('events.0.event_type', 'focus_loss')
            );

        $assignment = ExamSupervisor::query()
            ->where('exam_id', $exam->id)
            ->where('user_id', $sharedSupervisor->id)
            ->firstOrFail();

        $this->actingAs($owner)
            ->delete("/exams/{$exam->id}/supervisors/{$assignment->id}")
            ->assertRedirect();

        $this->actingAs($sharedSupervisor)
            ->get("/exams/{$exam->id}/monitor")
            ->assertForbidden();
    }

    public function test_institution_lecturer_can_monitor_assessment_for_assigned_course(): void
    {
        [$exam, $lecturer] = $this->institutionAssessmentWithLecturer();

        $this->actingAs($lecturer)
            ->get("/exams/{$exam->id}/monitor")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ExamMonitor/Show')
                ->where('summary.total_candidates', 1)
                ->where('rows.0.registration_number', 'CSC201-001')
            );

        $this->actingAs($lecturer)
            ->getJson("/exams/{$exam->id}/monitor/rows")
            ->assertOk()
            ->assertJsonPath('rows.0.registration_number', 'CSC201-001');
    }

    private function examWithAttempt(): array
    {
        $organization = Organization::factory()->create();
        $center = Center::factory()->create();
        $subject = Subject::factory()->create(['organization_id' => $organization->id]);
        $exam = Exam::factory()->create([
            'organization_id' => $organization->id,
            'center_id' => $center->id,
            'status' => Exam::STATUS_ACTIVE,
        ]);
        $candidate = Candidate::factory()->create([
            'organization_id' => $organization->id,
            'center_id' => $center->id,
            'candidate_number' => 'MON-001',
        ]);
        $exam->candidates()->attach($candidate->id, ['status' => 'assigned']);
        $bank = QuestionBank::factory()->create(['organization_id' => $organization->id, 'subject_id' => $subject->id]);
        $question = Question::factory()->create([
            'question_bank_id' => $bank->id,
            'subject_id' => $subject->id,
        ]);
        $attempt = CandidateExamAttempt::factory()->create([
            'candidate_id' => $candidate->id,
            'exam_id' => $exam->id,
            'center_id' => $center->id,
            'status' => CandidateExamAttempt::STATUS_IN_PROGRESS,
            'started_at' => now()->subMinutes(10),
            'server_due_at' => now()->addMinutes(50),
            'total_questions' => 2,
            'total_marks' => 2,
            'ip_address' => '127.0.0.1',
        ]);
        CandidateAnswer::factory()->create([
            'candidate_exam_attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'subject_id' => $subject->id,
            'selected_option_ids' => ['option-1'],
            'saved_at' => now(),
        ]);
        ExamAuditLog::factory()->create([
            'exam_id' => $exam->id,
            'candidate_exam_attempt_id' => $attempt->id,
            'event_type' => 'login_success',
            'occurred_at' => now()->subMinutes(9),
        ]);
        ProctoringEvent::factory()->create([
            'exam_id' => $exam->id,
            'candidate_exam_attempt_id' => $attempt->id,
            'candidate_id' => $candidate->id,
            'center_id' => $center->id,
            'severity' => 'warning',
            'event_type' => 'focus_loss',
            'occurred_at' => now(),
        ]);

        return [$exam->refresh(), $attempt->refresh()->load('candidate')];
    }

    private function institutionAssessmentWithLecturer(): array
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
        $exam = Exam::factory()->create([
            'organization_id' => $organization->id,
            'institution_id' => $institution->id,
            'faculty_id' => $faculty->id,
            'department_id' => $department->id,
            'course_id' => $course->id,
            'exam_category' => Exam::CATEGORY_ASSESSMENT,
            'status' => Exam::STATUS_ACTIVE,
        ]);
        ExamSubject::factory()->create([
            'exam_id' => $exam->id,
            'subject_id' => null,
            'question_bank_id' => $bank->id,
            'question_count' => 1,
            'marks_per_question' => 1,
            'total_marks' => 1,
        ]);
        $candidate = Candidate::factory()->create([
            'organization_id' => $organization->id,
            'institution_id' => $institution->id,
            'faculty_id' => $faculty->id,
            'department_id' => $department->id,
            'candidate_number' => 'CSC201-001',
        ]);
        $exam->candidates()->attach($candidate->id, ['status' => 'assigned']);
        CandidateExamAttempt::factory()->create([
            'candidate_id' => $candidate->id,
            'exam_id' => $exam->id,
            'status' => CandidateExamAttempt::STATUS_IN_PROGRESS,
            'started_at' => now()->subMinutes(5),
            'server_due_at' => now()->addMinutes(55),
            'total_questions' => 1,
            'total_marks' => 1,
        ]);
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
            'institution_id' => $institution->id,
            'faculty_id' => $faculty->id,
            'department_id' => $department->id,
            'module_id' => null,
        ]);

        return [$exam->refresh(), $lecturer->refresh()];
    }
}
