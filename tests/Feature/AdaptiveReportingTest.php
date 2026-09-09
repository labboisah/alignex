<?php

namespace Tests\Feature;

use App\Models\AdaptivePilotControl;
use App\Models\AdaptiveProgression;
use App\Models\CandidateExamAttempt;
use App\Models\Exam;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Question;
use App\Models\Role;
use App\Models\User;
use App\Services\AdaptiveLifecycleService;
use App\Services\AdaptiveReportService;
use App\Services\AdaptiveRolloutService;
use App\Services\ProfessionalExamService;
use App\Services\RecruitmentService;
use App\Services\ResultManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdaptiveReportingTest extends TestCase
{
    use RefreshDatabase, \Tests\Support\AdaptiveFixtures;

    public function test_report_preserves_level_path_penalties_evidence_and_frozen_versions(): void
    {
        [$attempt, $snapshot] = $this->fixture();
        $state = $this->commit($attempt, $this->start($attempt), true, 'earned');
        app(AdaptiveLifecycleService::class)->execute($attempt, 'submit');
        app(AdaptiveLifecycleService::class)->execute($attempt, 'next-level', ['idempotency_key' => 'recovery']);
        Question::query()->update(['difficulty' => 'hard', 'stem' => 'Edited after issue']);
        $report = app(AdaptiveReportService::class)->report(AdaptiveProgression::firstOrFail());
        $this->assertCount(2, $report['levels']);
        $this->assertSame('2.00', $report['marks']['earned']);
        $this->assertSame('0.40', $report['marks']['penalty']);
        $this->assertSame('3.60', $report['marks']['recoverable']);
        $this->assertSame($snapshot->fingerprint, $report['snapshot']['fingerprint']);
        $this->assertSame('medium', $report['levels'][0]['path'][0]['difficulty']);
        $this->assertTrue($report['levels'][0]['path'][0]['correct']);
        $this->assertNull($report['levels'][0]['path'][1]['correct']);
        $this->assertEquals(1, $report['levels'][0]['coverage'][0]['committed']);
        $this->assertEquals(1, $report['areas'][0]['scored_evidence_count']);
        $this->assertFalse($report['candidate_result_released']);
        foreach (['selected_options', 'options', 'content', 'is_correct', 'stem'] as $secret) {
            $this->assertStringNotContainsString('"'.$secret.'":', json_encode($report));
        }
    }

    public function test_practice_reports_raw_accuracy_without_awarding_marks_or_updating_scored_mastery(): void
    {
        [$attempt] = $this->fixture(settings: ['max_scored_levels' => 1, 'allow_unscored_remediation' => true]);
        $this->start($attempt);
        app(AdaptiveLifecycleService::class)->execute($attempt, 'submit');
        $next = app(AdaptiveLifecycleService::class)->execute($attempt, 'next-level', ['idempotency_key' => 'practice', 'practice' => true]);
        $practice = CandidateExamAttempt::findOrFail($next['attempt']['id']);
        $this->commit($practice, $next, true, 'practice-answer');
        app(AdaptiveLifecycleService::class)->execute($practice, 'submit');
        $report = app(AdaptiveReportService::class)->report(AdaptiveProgression::firstOrFail());
        $this->assertTrue($report['levels'][1]['is_practice']);
        $this->assertEquals(100, $report['levels'][1]['raw_accuracy_percent']);
        $this->assertSame('0.00', $report['levels'][1]['earned_marks']);
        $this->assertSame('0.00', $report['marks']['earned']);
        $this->assertEquals(0, $report['areas'][0]['scored_evidence_count']);
        $this->assertNotSame('mastered', $report['areas'][0]['mastery']);
    }

    public function test_report_and_export_are_scoped_audited_and_do_not_create_traditional_grades_or_hashes(): void
    {
        [$attempt] = $this->fixture();
        $this->start($attempt);
        app(AdaptiveLifecycleService::class)->execute($attempt, 'submit');
        $progression = AdaptiveProgression::firstOrFail();
        $admin = User::factory()->create(['role' => User::ROLE_ORGANIZATION_ADMIN, 'organization_id' => $attempt->exam->organization_id]);
        $this->grantPlanFeatures($attempt->exam->organization, ['csv_export', 'pdf_export']);
        $url = '/results/adaptive/progressions/'.$progression->id;
        $this->actingAs($admin)->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page->component('Results/Adaptive')->where('report.candidate_result_released', false));
        $this->get($url.'/export.csv')->assertOk()->assertSee('Descriptive online practice/diagnostic', false);
        $this->assertDatabaseHas('exam_audit_logs', ['event_type' => 'adaptive_report_exported', 'actor_user_id' => $admin->id]);
        $this->get('/results/exams/'.$attempt->exam_id)->assertOk()->assertInertia(fn (Assert $page) => $page->component('Results/AdaptiveIndex')->has('progressions.data', 1));
        $this->get('/results/attempts/'.$attempt->id)->assertOk()->assertInertia(fn (Assert $page) => $page->component('Results/Adaptive'));
        $this->get('/results/attempts/'.$attempt->id.'/marked-paper.pdf')->assertNotFound();
        $this->get('/results')->assertOk()->assertInertia(fn (Assert $page) => $page->where('dashboard.summary.total', 0));
        $this->assertNull($attempt->fresh()->result_hash);
        $this->assertCount(0, app(ResultManagementService::class)->queryForExam($attempt->exam)->get());
        $outsider = User::factory()->create(['role' => User::ROLE_ORGANIZATION_ADMIN, 'organization_id' => Organization::factory()]);
        $this->grantPlanFeatures($outsider->organization, ['csv_export']);
        $this->actingAs($outsider)->get($url)->assertForbidden();
        $this->get($url.'/export.csv')->assertForbidden();
        $this->get('/results/adaptive/exams/'.$attempt->exam_id)->assertForbidden();
        $candidateUser = User::factory()->create(['role' => User::ROLE_CANDIDATE, 'organization_id' => $attempt->exam->organization_id]);
        $this->actingAs($candidateUser)->get($url)->assertForbidden();
        // Moving an exam cannot transfer its frozen diagnostic history to another owner.
        $attempt->exam->update(['organization_id' => $outsider->organization_id, 'exam_owner_id' => $outsider->organization_id]);
        $this->actingAs($outsider)->get($url)->assertForbidden();
        $this->get('/results/adaptive/exams/'.$attempt->exam_id)->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('progressions.data', 0));
    }

    public function test_release_and_legacy_hash_cannot_leak_withheld_adaptive_results(): void
    {
        [$attempt] = $this->fixture(settings: ['max_scored_levels' => 1]);
        $this->commit($attempt, $this->start($attempt), true, 'answer');
        app(AdaptiveLifecycleService::class)->execute($attempt, 'submit');
        $attempt->update(['result_hash' => 'LEGACY-ADAPTIVE-HASH']);
        $this->postJson('/api/results/verify', ['hash' => 'LEGACY-ADAPTIVE-HASH'])->assertOk()->assertJsonPath('valid', false)->assertJsonPath('result', null);
        $data = ['exam_code' => $attempt->exam->code, 'identifier' => $attempt->candidate->candidate_number];
        $this->postJson('/api/candidate/result', $data)->assertUnprocessable();
        $attempt->exam->update(['result_release_settings' => ['release_mode' => 'released']]);
        $this->postJson('/api/candidate/result', $data)->assertOk()->assertJsonPath('result.score', '2.00')->assertJsonMissingPath('result.levels')->assertJsonMissingPath('result.grade');
        $attempt->update(['status' => 'disqualified']);
        $this->postJson('/api/candidate/result', $data)->assertUnprocessable();
        $this->assertNull(app(ProfessionalExamService::class)->generateForAttempt($attempt));
    }

    public function test_csv_escapes_spreadsheet_formulas_and_recruitment_rejects_pilot_results(): void
    {
        [$attempt] = $this->fixture();
        $attempt->candidate->update(['first_name' => '=HYPERLINK("bad")']);
        $report = app(AdaptiveReportService::class)->report(AdaptiveProgression::firstOrFail());
        $csv = app(AdaptiveReportService::class)->csv($report);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->expectException(ValidationException::class);
        app(RecruitmentService::class)->applyShortlist($attempt->exam);
    }

    public function test_report_only_reviewer_is_scoped_and_has_no_exam_write_access(): void
    {
        [$attempt] = $this->fixture();
        $role = Role::firstOrCreate(['name' => User::ROLE_EXAMINER], ['label' => 'Reviewer']);
        $permission = Permission::firstOrCreate(['name' => 'viewReports'], ['label' => 'View reports', 'group' => 'Reports']);
        $role->permissions()->sync([$permission->id]);
        $reviewer = User::factory()->create(['role' => User::ROLE_EXAMINER, 'organization_id' => $attempt->exam->organization_id]);
        $progression = AdaptiveProgression::firstOrFail();
        $this->actingAs($reviewer)->get('/results/adaptive/progressions/'.$progression->id)->assertOk();
        $this->assertFalse($reviewer->can('update', $attempt->exam));
        $reviewer->update(['organization_id' => Organization::factory()->create()->id]);
        $this->get('/results/adaptive/progressions/'.$progression->id)->assertForbidden();
        $teacher = User::factory()->create(['role' => User::ROLE_TEACHER, 'organization_id' => $attempt->exam->organization_id]);
        $this->assertFalse($teacher->can('view', $progression));
    }

    public function test_legacy_unbound_started_attempt_keeps_its_traditional_result_and_ranking(): void
    {
        $exam = Exam::factory()->create(['mode' => 'adaptive', 'exam_mode' => 'adaptive', 'exam_category' => 'recruitment']);
        $attempt = CandidateExamAttempt::factory()->create(['exam_id' => $exam->id, 'status' => 'submitted',
            'started_at' => now()->subMinutes(2), 'submitted_at' => now(), 'score' => 4, 'total_marks' => 6]);
        $this->assertCount(1, app(RecruitmentService::class)->ranking($exam));
        $this->assertSame($attempt->id, app(ResultManagementService::class)->queryForExam($exam)->first()->id);
        $this->assertNotEmpty(app(ResultManagementService::class)->row($attempt)['result_hash']);
    }

    public static function owners(): array
    {
        return array_map(fn ($owner) => [$owner], ['organization', 'institution', 'professional_school', 'cbt_center']);
    }

    #[DataProvider('owners')]
    public function test_real_pilot_gate_preparation_candidate_flow_and_result_for_each_owner(string $owner): void
    {
        require_once base_path('tests/Browser/fixtures.php');
        $fixture = browserFixture(['owner' => $owner, 'settings' => ['max_scored_levels' => 1]]);
        $exam = Exam::findOrFail($fixture['exam_id']);
        AdaptivePilotControl::create(['exam_id' => $exam->id, 'owner_key' => app(AdaptiveRolloutService::class)->ownerKey($exam), 'online_enabled' => true, 'offline_enabled' => false, 'purpose' => 'Reporting test diagnostic cohort.', 'updated_by' => $exam->created_by]);
        $rollout = app(AdaptiveRolloutService::class);
        $this->assertTrue($rollout->status($exam)['can_publish']);
        $proposed = new Exam($exam->getAttributes());
        $proposed->setAttribute('id', null);
        $rollout->ensureSaveAllowed($proposed, $exam);
        $login = $this->postJson('/api/candidate/login', ['exam_code' => $fixture['code'], 'identifier' => $fixture['identifier'], 'device_fingerprint' => 'pilot-device'])->assertOk();
        $token = $login->json('exam_token');
        $state = $this->withToken($token)->postJson('/api/candidate/start', ['device_fingerprint' => 'pilot-device'])->assertOk()->json();
        for ($i = 0; $i < 3; $i++) {
            $state = $this->withToken($token)->postJson('/api/candidate/answer', ['commit' => true, ...$this->answerData($state, true, 'pilot-'.$i)])->assertOk()->json();
        }
        $progression = AdaptiveProgression::where('exam_id', $exam->id)->firstOrFail();
        $this->assertSame('closed', $progression->status);
        $this->assertNull($state['result']);
        $admin = User::findOrFail($fixture['actor_id']);
        $this->actingAs($admin)->get('/results/adaptive/progressions/'.$progression->id)->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('report.marks.earned', '6.00')->where('report.snapshot.engine_version', 'simple-v1'));
        $exam->update(['result_release_settings' => ['release_mode' => 'released']]);
        $this->postJson('/api/candidate/result', ['exam_code' => $fixture['code'], 'identifier' => $fixture['identifier']])->assertOk()->assertJsonPath('result.score', '6.00');
        AdaptivePilotControl::where('exam_id', $exam->id)->update(['online_enabled' => false]);
        $this->withToken($token)->getJson('/api/candidate/exam')->assertOk();
    }

    public function test_pilot_requires_exact_exam_and_owner_online_delivery_and_nonconsequential_category(): void
    {
        $exam = Exam::factory()->create(['exam_owner_type' => 'organization', 'mode' => 'adaptive', 'exam_mode' => 'adaptive', 'exam_category' => 'assessment', 'delivery_mode' => 'online']);
        $service = app(AdaptiveRolloutService::class);
        $control = AdaptivePilotControl::create(['exam_id' => $exam->id, 'owner_key' => $service->ownerKey($exam), 'online_enabled' => true, 'offline_enabled' => false, 'purpose' => 'Reporting test diagnostic cohort.', 'updated_by' => $exam->created_by]);
        $this->assertTrue($service->status($exam)['can_publish']);
        foreach (['recruitment', 'certification', 'professional', 'terminal'] as $category) {
            $exam->exam_category = $category;
            $this->assertFalse($service->status($exam)['can_publish']);
        }
        $exam->exam_category = 'assessment';
        $exam->delivery_mode = 'offline';
        $this->assertFalse($service->status($exam)['can_publish']);
        $exam->delivery_mode = 'online';
        $control->update(['owner_key' => 'organization:wrong-owner']);
        $this->assertFalse($service->status($exam)['can_publish']);
        $exam->exam_owner_type = 'secondary_school';
        $control->update(['owner_key' => $service->ownerKey($exam)]);
        $this->assertTrue($service->status($exam)['can_publish']);
        $exam->exam_category = 'terminal';
        $this->assertFalse($service->status($exam)['can_publish']);
    }
}
