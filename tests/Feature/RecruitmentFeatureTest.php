<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\CandidateExamAttempt;
use App\Models\Exam;
use App\Models\ExamAuditLog;
use App\Models\ExamType;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RecruitmentFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_organization_admin_can_manage_recruitment_workflow(): void
    {
        [$exam, $topAttempt, $lowAttempt] = $this->recruitmentExam();
        $admin = User::factory()->create([
            'role' => User::ROLE_ORGANIZATION_ADMIN,
            'organization_id' => $exam->organization_id,
        ]);

        $this->actingAs($admin)
            ->get("/exams/{$exam->id}/recruitment")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Recruitment/Show')
                ->where('exam.exam_code', $exam->code)
                ->where('ranking.0.registration_number', $topAttempt->candidate->candidate_number)
                ->where('anomalies.duplicate_logins.0.registration_number', $topAttempt->candidate->candidate_number)
                ->where('anomalies.shared_devices.0.device_fingerprint', 'device-shared')
                ->where('anomalies.shared_ips.0.ip_address', '10.10.10.10')
            );

        $this->actingAs($admin)
            ->patch("/exams/{$exam->id}/recruitment/settings", [
                'cutoff_score' => 60,
                'auto_shortlist' => true,
                'shortlist_limit' => 1,
                'negative_marking' => true,
                'negative_mark_value' => 0.25,
            ])
            ->assertRedirect();

        $exam->refresh();
        $this->assertEquals(60, (float) $exam->pass_mark);
        $this->assertTrue($exam->settings['recruitment_auto_shortlist']);
        $this->assertTrue($exam->settings['negative_marking']);
        $this->assertEquals(0.25, (float) $exam->settings['negative_mark_value']);
        $this->assertDatabaseHas('exam_candidates', [
            'exam_id' => $exam->id,
            'candidate_id' => $topAttempt->candidate_id,
            'status' => 'shortlisted',
        ]);
        $this->assertDatabaseHas('exam_candidates', [
            'exam_id' => $exam->id,
            'candidate_id' => $lowAttempt->candidate_id,
            'status' => 'not_shortlisted',
        ]);

        $this->actingAs($admin)
            ->post("/exams/{$exam->id}/recruitment/access-codes")
            ->assertRedirect();

        $this->assertNotNull($topAttempt->refresh()->access_code);

        $this->actingAs($admin)
            ->get("/exams/{$exam->id}/recruitment/shortlist.csv")
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertSee($topAttempt->candidate->candidate_number)
            ->assertDontSee($lowAttempt->candidate->candidate_number);

        $this->actingAs($admin)
            ->get("/exams/{$exam->id}/recruitment/access-codes.csv")
            ->assertOk()
            ->assertSee($topAttempt->refresh()->access_code);
    }

    private function recruitmentExam(): array
    {
        $organization = Organization::factory()->create();
        $examType = ExamType::factory()->create([
            'name' => 'Recruitment',
            'code' => 'recruitment',
        ]);
        $exam = Exam::factory()->create([
            'organization_id' => $organization->id,
            'exam_type_id' => $examType->id,
            'code' => 'REC-001',
            'total_marks' => 100,
            'pass_mark' => 50,
            'settings' => ['negative_marking' => false, 'negative_mark_value' => 0],
        ]);

        $topCandidate = Candidate::factory()->create([
            'organization_id' => $organization->id,
            'candidate_number' => 'REC-TOP-001',
        ]);
        $lowCandidate = Candidate::factory()->create([
            'organization_id' => $organization->id,
            'candidate_number' => 'REC-LOW-001',
        ]);
        $exam->candidates()->attach($topCandidate->id, ['status' => 'assigned']);
        $exam->candidates()->attach($lowCandidate->id, ['status' => 'assigned']);

        $topAttempt = CandidateExamAttempt::factory()->create([
            'candidate_id' => $topCandidate->id,
            'exam_id' => $exam->id,
            'status' => CandidateExamAttempt::STATUS_SUBMITTED,
            'submitted_at' => now(),
            'score' => 85,
            'total_questions' => 50,
            'total_marks' => 100,
            'ip_address' => '10.10.10.10',
            'device_fingerprint' => 'device-shared',
        ]);
        $lowAttempt = CandidateExamAttempt::factory()->create([
            'candidate_id' => $lowCandidate->id,
            'exam_id' => $exam->id,
            'status' => CandidateExamAttempt::STATUS_SUBMITTED,
            'submitted_at' => now(),
            'score' => 42,
            'total_questions' => 50,
            'total_marks' => 100,
            'ip_address' => '10.10.10.10',
            'device_fingerprint' => 'device-shared',
        ]);

        ExamAuditLog::factory()->count(2)->create([
            'exam_id' => $exam->id,
            'candidate_exam_attempt_id' => $topAttempt->id,
            'event_type' => 'login_success',
        ]);

        return [$exam->refresh(), $topAttempt->refresh()->load('candidate'), $lowAttempt->refresh()->load('candidate')];
    }
}
