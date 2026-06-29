<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Candidate;
use App\Models\CandidateAnswer;
use App\Models\CandidateExamAttempt;
use App\Models\CandidatePerformanceProfile;
use App\Models\ContinuousAssessment;
use App\Models\Exam;
use App\Models\ExamType;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SecondarySchoolFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_school_admin_can_manage_secondary_school_results_and_report_cards(): void
    {
        [$exam, $candidate, $subject, $topic] = $this->secondaryExam();
        $admin = User::factory()->create([
            'role' => User::ROLE_SCHOOL_ADMIN,
            'school_id' => $exam->school_id,
        ]);

        $this->actingAs($admin)
            ->post('/secondary-school/sessions', [
                'name' => '2026/2027',
                'code' => '2026',
                'starts_on' => '2026-09-01',
                'ends_on' => '2027-07-30',
                'status' => 'active',
            ])
            ->assertRedirect();

        $session = AcademicSession::query()->firstOrFail();

        $this->actingAs($admin)
            ->post('/secondary-school/terms', [
                'academic_session_id' => $session->id,
                'name' => 'First Term',
                'code' => 'T1',
                'starts_on' => '2026-09-01',
                'ends_on' => '2026-12-10',
                'status' => 'active',
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post('/secondary-school/classes', [
                'name' => 'SS 2',
                'code' => 'SS2',
                'level_order' => 5,
                'status' => 'active',
            ])
            ->assertRedirect();

        $class = SchoolClass::query()->firstOrFail();

        $this->actingAs($admin)
            ->post('/secondary-school/groups', [
                'school_class_id' => $class->id,
                'name' => 'Science',
                'code' => 'SCI',
                'status' => 'active',
                'candidate_ids' => [$candidate->id],
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->patch("/secondary-school/exams/{$exam->id}/ca-setup", [
                'academic_session_id' => $session->id,
                'academic_term_id' => $session->terms()->firstOrFail()->id,
                'school_class_id' => $class->id,
                'student_group_id' => $class->groups()->firstOrFail()->id,
                'ca_max_score' => 30,
                'exam_max_score' => 70,
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post("/secondary-school/exams/{$exam->id}/assessments", [
                'candidate_id' => $candidate->id,
                'subject_id' => $subject->id,
                'ca_score' => 25,
                'teacher_comment' => 'Good improvement.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('continuous_assessments', [
            'exam_id' => $exam->id,
            'candidate_id' => $candidate->id,
            'subject_id' => $subject->id,
            'ca_score' => 25,
            'exam_score' => 50,
            'total_score' => 75,
            'grade' => 'A',
        ]);

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
                ->where('dashboard.assessments_recorded', 1)
                ->where('result_sheet.0.registration_number', $candidate->candidate_number)
                ->where('result_sheet.0.total_score', 75)
                ->where('weaknesses.0.topic', $topic->name)
            );

        $this->actingAs($admin)
            ->get("/secondary-school/exams/{$exam->id}/candidates/{$candidate->id}/report-card.pdf")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertSee('AlignEx Secondary School Report Card')
            ->assertSee($candidate->candidate_number);

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
}
