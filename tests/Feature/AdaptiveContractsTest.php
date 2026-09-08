<?php

namespace Tests\Feature;

use App\Http\Resources\AdaptiveCandidateItemResource;
use App\Models\AdaptiveAreaBalance;
use App\Models\AdaptiveDecision;
use App\Models\AdaptiveLevel;
use App\Models\AdaptiveMarkEntry;
use App\Models\AdaptiveProgression;
use App\Models\Candidate;
use App\Models\CandidateExamAttempt;
use App\Models\CbtCenter;
use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\Institution;
use App\Models\Organization;
use App\Models\ProfessionalSchool;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\QuestionOption;
use App\Models\SecondarySchool;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;
use App\Services\AdaptiveAttemptPreparationService;
use App\Services\AdaptivePreparationService;
use App\Support\AdaptiveSettings;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdaptiveContractsTest extends TestCase
{
    use RefreshDatabase;

    public function test_configuration_round_trips_and_rejects_invalid_progressive_settings(): void
    {
        [$exam, $user, $candidate, $row] = $this->fixture();
        $payload = $this->payload($exam, $candidate, $row);
        $settings = $payload['settings'] + $this->progressive();
        $this->actingAs($user)->patch('/exams/'.$exam->id, [...$payload, 'settings' => $settings])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('12.50', $exam->fresh()->settings['recovery_penalty_percent']);
        $this->assertSame(true, $exam->fresh()->settings['progressive_remediation_enabled']);
        foreach ([
            ['recovery_penalty_percent' => '100.01'],
            ['recovery_penalty_percent' => '-1'],
            ['recovery_penalty_percent' => '1.234'],
            ['max_scored_levels' => 0],
            ['min_level_budget' => 0],
            ['adaptive_step_policy' => 'unknown'],
            ['adaptive_max_questions' => 2],
            ['negative_marking' => true],
            ['progression_closes_at' => now()->subDay()->toDateTimeString()],
        ] as $bad) {
            $response = $this->patch('/exams/'.$exam->id, [...$payload, 'settings' => [...$settings, ...$bad]]);
            $response->assertSessionHasErrors('settings.'.array_key_first($bad));
        }
        foreach (['0', '100'] as $rate) {
            $this->patch('/exams/'.$exam->id, [...$payload, 'settings' => [...$settings, 'recovery_penalty_percent' => $rate]])
                ->assertSessionHasNoErrors();
        }
    }

    public function test_conflicting_modes_and_traditional_progression_are_rejected_but_ordinary_cbt_saves(): void
    {
        [$exam, $user, $candidate, $row] = $this->fixture();
        $payload = $this->payload($exam, $candidate, $row);
        $this->actingAs($user)->patch('/exams/'.$exam->id, [...$payload, 'exam_mode' => 'traditional'])->assertSessionHasErrors('exam_mode');
        $traditional = [...$payload, 'mode' => 'traditional', 'exam_mode' => 'traditional'];
        $this->patch('/exams/'.$exam->id, [...$traditional, 'settings' => [...$payload['settings'], 'progressive_remediation_enabled' => true]])
            ->assertSessionHasErrors('settings.progressive_remediation_enabled');
        $this->patch('/exams/'.$exam->id, $traditional)->assertSessionHasNoErrors();
        $this->assertSame('traditional', $exam->fresh()->effectiveMode());
        $this->assertDatabaseCount('adaptive_snapshots', 0);
    }

    public function test_preparation_is_authorized_and_does_not_serialize_answer_keys(): void
    {
        [$exam, $user] = $this->fixture();
        $outsider = User::factory()->create(['role' => User::ROLE_ORGANIZATION_ADMIN, 'organization_id' => Organization::factory()]);
        $this->actingAs($outsider)->get('/exams/'.$exam->id.'/adaptive')->assertForbidden();
        $this->post('/exams/'.$exam->id.'/adaptive/prepare')->assertForbidden();
        $this->actingAs($user)->get('/exams/'.$exam->id.'/adaptive')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Exams/AdaptivePreparation')->where('readiness.ready', true)->where('readiness.item_count', 9)
            ->missing('readiness.items')->missing('readiness.content'));
        $this->post('/exams/'.$exam->id.'/adaptive/prepare')->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseCount('adaptive_snapshots', 1);
        $this->assertDatabaseHas('exam_audit_logs', ['event_type' => 'adaptive_snapshot_prepared', 'exam_id' => $exam->id]);
    }

    public function test_snapshot_content_is_encrypted_and_safe_candidate_contract_omits_internal_fields(): void
    {
        [$exam, $user] = $this->fixture();
        $snapshot = app(AdaptivePreparationService::class)->prepare($exam, $user->id);
        $item = $snapshot->items()->firstOrFail();
        $raw = DB::table('adaptive_pool_items')->where('id', $item->id)->value('content');
        $this->assertStringNotContainsString('is_correct', $raw);
        $this->assertArrayNotHasKey('content', $item->toArray());
        $safe = (new AdaptiveCandidateItemResource($item))->resolve();
        $this->assertArrayNotHasKey('is_correct', $safe['options'][0]);
        $this->assertArrayNotHasKey('difficulty', $safe);
        $this->assertArrayNotHasKey('scoring_metadata', $safe);
        $this->assertArrayNotHasKey('content_hash', $safe);
        $this->assertArrayHasKey('question_text', $safe);
    }

    public function test_versions_preserve_original_question_content_and_reject_history_mutation(): void
    {
        [$exam, $user] = $this->fixture();
        $service = app(AdaptivePreparationService::class);
        $first = $service->prepare($exam, $user->id);
        $item = $first->items()->firstOrFail();
        Question::findOrFail($item->question_id)->update(['stem' => 'Revised question']);
        $second = $service->prepare($exam->fresh(), $user->id);
        $this->assertSame(2, $second->version);
        $this->assertNotSame($first->fingerprint, $second->fingerprint);
        $this->assertNotSame('Revised question', $item->fresh()->content['stem']);
        $this->expectException(\LogicException::class);
        $first->update(['settings' => ['replacement' => true]]);
    }

    public function test_owner_subject_and_approval_boundaries_exclude_unusable_questions(): void
    {
        [$exam, $user, $candidate, $row] = $this->fixture();
        $firstQuestion = Question::where('question_bank_id', $row->question_bank_id)->firstOrFail();
        $firstQuestion->update(['status' => 'draft']);
        $inspection = app(AdaptivePreparationService::class)->inspect($exam);
        $this->assertCount(8, $inspection['items']);
        $this->assertNotContains($firstQuestion->id, array_column($inspection['items'], 'question_id'));
        $foreign = QuestionBank::factory()->create(['organization_id' => Organization::factory(), 'subject_id' => $row->subject_id]);
        $row->update(['selection_rules' => ['question_bank_ids' => [$foreign->id]]]);
        $inspection = app(AdaptivePreparationService::class)->inspect($exam->fresh());
        $this->assertFalse($inspection['readiness']['ready']);
        $this->assertCount(0, $inspection['items']);
        $this->assertStringContainsString('outside this owner', implode(' ', $inspection['readiness']['warnings']));
    }

    public function test_progressive_readiness_requires_fresh_items_for_all_levels_and_difficulty_bands(): void
    {
        [$exam] = $this->fixture();
        $exam->update(['settings' => [...$exam->settings, ...$this->progressive(), 'max_scored_levels' => 4]]);
        $inspection = app(AdaptivePreparationService::class)->inspect($exam);
        $this->assertFalse($inspection['readiness']['ready']);
        $this->assertSame(12, $inspection['readiness']['areas'][0]['required']);
        $this->assertStringContainsString('needs 12', implode(' ', $inspection['readiness']['warnings']));
    }

    public function test_binding_is_idempotent_freezes_budget_and_does_not_start_or_charge_a_penalty(): void
    {
        [$exam, $user, $candidate] = $this->fixture();
        $snapshot = app(AdaptivePreparationService::class)->prepare($exam, $user->id);
        $attempt = $this->emptyAttempt($exam, $candidate);
        $service = app(AdaptiveAttemptPreparationService::class);
        $state = $service->bind($attempt, $snapshot);
        $this->assertSame($state->id, $service->bind($attempt, $snapshot)->id);
        $this->assertNull($attempt->fresh()->started_at);
        $this->assertSame('not_started', $attempt->fresh()->status);
        $this->assertDatabaseCount('candidate_papers', 0);
        $this->assertDatabaseCount('adaptive_progressions', 1);
        $this->assertDatabaseCount('adaptive_levels', 1);
        $this->assertDatabaseCount('adaptive_mark_entries', 1);
        $progression = AdaptiveProgression::firstOrFail();
        $this->assertSame(600, $progression->original_units);
        $this->assertSame(600, $progression->recoverable_units);
        $this->assertSame(600, (int) AdaptiveAreaBalance::sum('recoverable_units'));
        $this->assertSame(0, (int) AdaptiveLevel::firstOrFail()->penalty_units);
        $exam->update(['settings' => [...$exam->settings, 'adaptive_start_difficulty' => 'easy']]);
        $this->assertSame('medium', $snapshot->fresh()->settings['adaptive_start_difficulty']);
        $this->expectException(\LogicException::class);
        $state->update(['delivery_mode' => 'traditional']);
    }

    public function test_stale_or_foreign_snapshot_cannot_bind_an_attempt(): void
    {
        [$exam, $user, $candidate] = $this->fixture();
        $snapshot = app(AdaptivePreparationService::class)->prepare($exam, $user->id);
        $exam->update(['pass_mark' => 5]);
        $this->expectException(ValidationException::class);
        app(AdaptiveAttemptPreparationService::class)->bind($this->emptyAttempt($exam, $candidate), $snapshot);
    }

    public function test_unassigned_candidate_cannot_bind_even_a_ready_snapshot(): void
    {
        [$exam, $user, $candidate] = $this->fixture();
        $snapshot = app(AdaptivePreparationService::class)->prepare($exam, $user->id);
        $exam->candidates()->detach($candidate->id);
        $this->expectException(ValidationException::class);
        app(AdaptiveAttemptPreparationService::class)->bind($this->emptyAttempt($exam, $candidate), $snapshot);
    }

    public function test_second_attempt_cannot_reset_the_recovery_budget(): void
    {
        [$exam, $user, $candidate] = $this->fixture();
        $snapshot = app(AdaptivePreparationService::class)->prepare($exam, $user->id);
        app(AdaptiveAttemptPreparationService::class)->bind($this->emptyAttempt($exam, $candidate), $snapshot);
        $another = $this->emptyAttempt($exam, $candidate, 2);
        $this->expectException(ValidationException::class);
        app(AdaptiveAttemptPreparationService::class)->bind($another, $snapshot);
    }

    public function test_ledger_is_append_only_and_duplicate_keys_are_constrained(): void
    {
        [$exam, $user, $candidate] = $this->fixture();
        $snapshot = app(AdaptivePreparationService::class)->prepare($exam, $user->id);
        app(AdaptiveAttemptPreparationService::class)->bind($this->emptyAttempt($exam, $candidate), $snapshot);
        $entry = AdaptiveMarkEntry::firstOrFail();
        try {
            $entry->update(['units' => 0]);
            $this->fail('Ledger update must fail.');
        } catch (\LogicException) {
            $this->assertSame(600, (int) $entry->fresh()->units);
        }
        $this->expectException(QueryException::class);
        AdaptiveMarkEntry::create([
            'progression_id' => $entry->progression_id, 'level_id' => $entry->level_id,
            'kind' => 'opening', 'units' => 600, 'idempotency_key' => 'opening', 'metadata' => [],
        ]);
    }

    public function test_mark_units_are_exact(): void
    {
        $this->assertSame(3060, AdaptiveSettings::units('30.60'));
        $this->assertSame(1, AdaptiveSettings::units('0.01'));
        $this->assertSame(1250, AdaptiveSettings::units('12.5'));
    }

    public function test_each_supported_entity_scopes_its_formative_pool(): void
    {
        foreach ([
            'institution' => Institution::class,
            'professional_school' => ProfessionalSchool::class,
            'cbt_center' => CbtCenter::class,
            'secondary_school' => SecondarySchool::class,
        ] as $type => $model) {
            [$exam, $user, $candidate, $row] = $this->fixture();
            $entity = $model::create(['organization_id' => $exam->organization_id, 'name' => $type, 'code' => $type, 'status' => 'active', 'contact_person' => 'Admin', 'email' => $type.'@example.test', 'phone' => '08030000000', 'address' => 'Lagos', 'location' => 'Lagos', 'capacity' => 30]);
            $column = $type.'_id';
            $exam->update([$column => $entity->id, 'exam_owner_type' => $type, 'exam_owner_id' => $entity->id]);
            $bank = QuestionBank::findOrFail($row->question_bank_id);
            $bank->update([$column => $entity->id, 'owner_type' => $type, 'owner_id' => $entity->id]);
            if ($type === 'institution') {
                $course = Course::create(['institution_id' => $entity->id, 'name' => 'Course', 'code' => 'ADAPT', 'status' => 'active']);
                $bank->update(['course_id' => $course->id]);
                $row->update(['selection_rules' => ['question_bank_ids' => [$bank->id], 'course_id' => $course->id]]);
            }
            $inspection = app(AdaptivePreparationService::class)->inspect($exam->fresh());
            $this->assertTrue($inspection['readiness']['ready'], $type);
            $this->assertCount(9, $inspection['items']);
            $bank->update(['owner_id' => $entity->id + 1000]);
            $this->assertCount(0, app(AdaptivePreparationService::class)->inspect($exam->fresh())['items']);
        }
    }

    public function test_selected_topics_exclude_other_items_and_show_missing_coverage(): void
    {
        [$exam, $user, $candidate, $row] = $this->fixture();
        $topic = Topic::factory()->create(['subject_id' => $row->subject_id]);
        $question = Question::where('question_bank_id', $row->question_bank_id)->firstOrFail();
        $question->update(['topic_id' => $topic->id]);
        $row->update(['selection_rules' => ['question_bank_ids' => [$row->question_bank_id], 'topic_ids' => [$topic->id]]]);
        $inspection = app(AdaptivePreparationService::class)->inspect($exam->fresh());
        $this->assertSame([$question->id], array_column($inspection['items'], 'question_id'));
        $this->assertFalse($inspection['readiness']['ready']);
    }

    public function test_started_attempt_cannot_be_bound_to_adaptive_state(): void
    {
        [$exam, $user, $candidate] = $this->fixture();
        $snapshot = app(AdaptivePreparationService::class)->prepare($exam, $user->id);
        $attempt = $this->emptyAttempt($exam, $candidate);
        $attempt->update(['status' => 'in_progress', 'started_at' => now()]);
        $this->expectException(ValidationException::class);
        app(AdaptiveAttemptPreparationService::class)->bind($attempt, $snapshot);
    }

    public function test_database_rejects_reissuing_a_question_in_the_same_progression(): void
    {
        [$exam, $user, $candidate] = $this->fixture();
        $snapshot = app(AdaptivePreparationService::class)->prepare($exam, $user->id);
        app(AdaptiveAttemptPreparationService::class)->bind($this->emptyAttempt($exam, $candidate), $snapshot);
        $level = AdaptiveLevel::firstOrFail();
        $item = $snapshot->items()->firstOrFail();
        $attributes = ['progression_id' => $level->progression_id, 'level_id' => $level->id,
            'pool_item_id' => $item->id, 'question_id' => $item->question_id, 'decision' => []];
        AdaptiveDecision::create([...$attributes, 'step' => 1, 'idempotency_key' => 'first']);
        $this->expectException(QueryException::class);
        AdaptiveDecision::create([...$attributes, 'step' => 2, 'idempotency_key' => 'second']);
    }

    private function fixture(): array
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create(['role' => User::ROLE_ORGANIZATION_ADMIN, 'organization_id' => $organization->id]);
        $subject = Subject::factory()->create(['organization_id' => $organization->id]);
        $bank = QuestionBank::factory()->create([
            'organization_id' => $organization->id, 'subject_id' => $subject->id,
            'owner_type' => 'organization', 'owner_id' => $organization->id, 'status' => 'active',
        ]);
        $exam = Exam::factory()->create([
            'organization_id' => $organization->id, 'exam_owner_type' => 'organization', 'exam_owner_id' => $organization->id,
            'mode' => 'adaptive', 'exam_mode' => 'adaptive', 'exam_category' => 'assessment', 'status' => 'draft',
            'starts_at' => now()->addDay(), 'ends_at' => now()->addDays(2), 'total_marks' => 6, 'pass_mark' => 3,
            'question_bank_id' => $bank->id, 'settings' => [],
        ]);
        $row = ExamSubject::factory()->create([
            'exam_id' => $exam->id, 'subject_id' => $subject->id, 'question_bank_id' => $bank->id,
            'question_count' => 3, 'marks_per_question' => 2, 'total_marks' => 6, 'display_order' => 1,
            'selection_rules' => ['question_bank_ids' => [$bank->id]], 'difficulty_distribution' => null,
        ]);
        foreach (['easy', 'medium', 'hard'] as $band) {
            for ($i = 0; $i < 3; $i++) {
                $question = Question::factory()->create([
                    'question_bank_id' => $bank->id, 'subject_id' => $subject->id, 'topic_id' => null,
                    'difficulty' => $band, 'status' => 'approved', 'question_type' => 'single_choice', 'marks' => 2,
                ]);
                QuestionOption::factory()->create(['question_id' => $question->id, 'label' => 'A', 'display_order' => 1, 'is_correct' => true]);
                QuestionOption::factory()->create(['question_id' => $question->id, 'label' => 'B', 'display_order' => 2, 'is_correct' => false]);
            }
        }
        $candidate = Candidate::factory()->create(['organization_id' => $organization->id]);
        $exam->candidates()->attach($candidate->id);

        return [$exam, $user, $candidate, $row];
    }

    private function emptyAttempt(Exam $exam, Candidate $candidate, int $number = 1): CandidateExamAttempt
    {
        return CandidateExamAttempt::factory()->create([
            'exam_id' => $exam->id, 'candidate_id' => $candidate->id,
            'attempt_number' => $number, 'status' => 'not_started', 'started_at' => null,
        ]);
    }

    private function progressive(): array
    {
        return [
            'progressive_remediation_enabled' => true, 'recovery_penalty_percent' => '12.50',
            'max_scored_levels' => 3, 'min_level_budget' => '0.01', 'mastery_threshold_percent' => 70,
            'min_evidence_per_area' => 1, 'level_duration_minutes' => 30,
            'progression_closes_at' => now()->addDays(5)->toDateTimeString(), 'level_cooldown_minutes' => 0,
            'allow_unscored_remediation' => false,
        ];
    }

    private function payload(Exam $exam, Candidate $candidate, ExamSubject $row): array
    {
        return [
            'title' => $exam->title, 'exam_code' => $exam->code, 'exam_type' => 'assessment', 'exam_category' => 'assessment',
            'mode' => 'adaptive', 'exam_mode' => 'adaptive', 'delivery_mode' => 'online',
            'start_at' => $exam->starts_at->toDateTimeString(), 'end_at' => $exam->ends_at->toDateTimeString(),
            'duration_minutes' => 30, 'total_marks' => 6, 'pass_mark' => 3, 'status' => 'draft',
            'question_bank_id' => $row->question_bank_id, 'candidate_ids' => [$candidate->id],
            'subjects' => [['subject_id' => $row->subject_id, 'number_of_questions' => 3, 'marks_per_question' => 2]],
            'settings' => array_fill_keys(['shuffle_questions', 'shuffle_options', 'show_result_immediately', 'allow_back_navigation', 'require_webcam', 'require_fullscreen', 'negative_marking', 'bind_device', 'allow_retake'], false) + ['max_tab_switches' => 0],
        ];
    }
}
