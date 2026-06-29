<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\CandidateExamAttempt;
use App\Models\Exam;
use App\Models\ExamType;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProfessionalExamFeatureTest extends TestCase
{
    use RefreshDatabase;

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
}
