<?php

namespace Tests\Feature;

use App\Models\AdaptiveAttemptState;
use App\Models\AdaptiveCalibration;
use App\Models\AdaptiveLevel;
use App\Models\AdaptiveProgression;
use App\Models\AdaptiveSnapshot;
use App\Models\Exam;
use App\Models\Organization;
use App\Models\User;
use App\Services\AdaptiveCalibrationService;
use App\Services\AdaptiveShadowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdaptiveEngineTest extends TestCase
{
    use RefreshDatabase, \Tests\Support\AdaptiveFixtures;

    private function setupResearch(): array
    {
        [$attempt, $snapshot] = $this->fixture();
        $author = User::factory()->create(['role' => User::ROLE_ORGANIZATION_ADMIN, 'organization_id' => $attempt->exam->organization_id]);
        $reviewer = User::factory()->create(['role' => User::ROLE_ORGANIZATION_ADMIN, 'organization_id' => $attempt->exam->organization_id]);
        $payload = ['schema_version' => 'calibration-2pl-v1', 'snapshot_id' => $snapshot->id,
            'source_reference' => 'Synthetic test fixture', 'specialist' => 'Test reviewer', 'sample_size' => 100,
            'criteria_reference' => 'Synthetic criteria only', 'validation_notes' => 'Not validated for real candidates.',
            'policy' => ['min_questions' => 2, 'max_questions' => 50, 'min_per_area' => 1, 'target_sd' => 0.5, 'cutpoint' => null],
            'items' => $snapshot->items->map(fn ($item) => ['id' => $item->id, 'content_hash' => $item->content_hash,
                'a' => 1.0, 'b' => 0.0, 'exposure_limit' => 100])->all()];
        $calibration = app(AdaptiveCalibrationService::class)->import($attempt->exam, $author, $payload);
        config(['adaptive.engine.shadow_enabled' => true, 'adaptive.engine.url' => 'http://127.0.0.1:8096', 'adaptive.engine.secret' => str_repeat('x', 40)]);

        return [$attempt, $calibration, $author, $reviewer, $payload];
    }

    public static function researchOwners(): array
    {
        return array_map(fn ($owner) => [$owner], ['organization', 'institution', 'professional_school', 'cbt_center']);
    }

    #[DataProvider('researchOwners')]
    public function test_research_import_and_review_are_available_for_each_supported_owner(string $owner): void
    {
        require_once base_path('tests/Browser/fixtures.php');
        $fixture = browserFixture(['owner' => $owner]);
        $exam = Exam::findOrFail($fixture['exam_id']);
        $actor = User::findOrFail($fixture['actor_id']);
        $snapshot = AdaptiveSnapshot::where('exam_id', $exam->id)->firstOrFail();
        $base = '/exams/'.$exam->id.'/adaptive/research';
        $payload = $this->actingAs($actor)->get($base.'/template/'.$snapshot->id)->assertOk()->json();
        $payload = [...$payload, 'source_reference' => 'Synthetic owner test', 'specialist' => 'Fixture reviewer',
            'sample_size' => 100, 'criteria_reference' => 'Fixture criteria', 'validation_notes' => 'Synthetic only.'];
        $payload['policy']['target_sd'] = 0.5;
        foreach ($payload['items'] as &$item) {
            $item['a'] = 1.0;
            $item['b'] = 0.0;
            $item['exposure_limit'] = 100;
        }
        unset($item);
        $this->post($base.'/calibrations', ['payload' => json_encode($payload)])->assertSessionHasNoErrors()->assertRedirect();
        $calibration = AdaptiveCalibration::where('snapshot_id', $snapshot->id)->firstOrFail();
        $reviewer = User::factory()->create(['role' => User::ROLE_ORGANIZATION_ADMIN, 'organization_id' => $actor->organization_id,
            ...($owner === 'organization' ? [] : [$owner.'_id' => $actor->{$owner.'_id'}])]);
        $this->actingAs($reviewer)->post($base.'/calibrations/'.$calibration->id, ['action' => 'review'])->assertSessionHasNoErrors();
        $this->assertSame('reviewed', $calibration->fresh()->status);
        $this->get($base)->assertOk()->assertInertia(fn (Assert $page) => $page->component('Exams/AdaptiveResearch')->has('calibrations', 1));
    }

    private function validResponse(array $request): array
    {
        return [
            'protocol' => 'alignex-shadow-v1', 'engine_version' => 'shadow-2pl-eap-v1',
            'request_id' => $request['request_id'], 'state_version' => $request['state_version'],
            'calibration_fingerprint' => $request['calibration_fingerprint'],
            'theta' => 0.2, 'posterior_sd' => 0.8, 'interval_95' => [-1, 1],
            'evidence_count' => count($request['responses']), 'coverage_satisfied' => count($request['responses']) >= 1,
            'action' => 'select', 'stop_reason' => null,
            'selected_item_id' => collect($request['items'])->firstWhere('eligible', true)['id'],
            'experimental_classification' => 'undetermined', 'interpretation' => 'experimental_shadow_only',
        ];
    }

    public function test_import_review_revoke_validation_and_snapshot_identity(): void
    {
        [$attempt, $calibration, $author, $reviewer, $payload] = $this->setupResearch();
        $base = '/exams/'.$attempt->exam_id.'/adaptive/research';
        $this->actingAs($author)->post($base.'/calibrations/'.$calibration->id, ['action' => 'review'])->assertSessionHasErrors('action');
        $this->actingAs($reviewer)->post($base.'/calibrations/'.$calibration->id, ['action' => 'review'])->assertSessionHasNoErrors();
        $this->assertSame('reviewed', $calibration->fresh()->status);
        $this->post($base.'/calibrations', ['payload' => '{}'])->assertSessionHasErrors('schema_version');
        $this->post($base.'/calibrations', [])->assertSessionHasErrors('payload');
        $this->post($base.'/calibrations', ['payload' => 'null'])->assertSessionHasErrors('payload');
        $payload['items'][0]['content_hash'] = str_repeat('0', 64);
        $this->post($base.'/calibrations', ['payload' => json_encode($payload)])->assertSessionHasErrors('payload');
        $this->post($base.'/calibrations/'.$calibration->id, ['action' => 'revoke'])->assertSessionHasNoErrors();
        $this->assertSame('revoked', $calibration->fresh()->status);
        $this->post($base.'/calibrations/'.$calibration->id, ['action' => 'review'])->assertSessionHasErrors('action');
        $this->assertDatabaseHas('exam_audit_logs', ['event_type' => 'adaptive_calibration_revoke']);
        $this->expectException(\LogicException::class);
        $calibration->update(['payload' => $payload]);
    }

    public function test_shadow_evaluation_and_replay_are_deterministic_private_and_do_not_change_scores(): void
    {
        [$attempt, $calibration, $author, $reviewer] = $this->setupResearch();
        app(AdaptiveCalibrationService::class)->transition($attempt->exam, $reviewer, $calibration, 'review');
        $this->commit($attempt, $this->start($attempt), true, 'first');
        $before = AdaptiveProgression::firstOrFail()->getAttributes();
        Http::swap(new Factory);
        Http::fake(fn ($request) => Http::response($this->validResponse($request->data())));
        $level = AdaptiveLevel::firstOrFail();
        $service = app(AdaptiveShadowService::class);
        $run = $service->evaluate($attempt->exam, $reviewer, $calibration, $level);
        $this->assertSame('succeeded', $run->status);
        $replay = $service->evaluate($attempt->exam, $reviewer, $calibration, $level, $run);
        $this->assertSame($run->request_hash, $replay->request_hash);
        $this->assertEquals($run->result, $replay->result);
        $this->assertSame($before, AdaptiveProgression::firstOrFail()->getAttributes());
        $this->assertArrayNotHasKey('request_payload', $run->toArray());
        Http::assertSent(function ($request) {
            $encoded = json_encode($request->data());
            $this->assertStringNotContainsString('candidate_name', $encoded);
            $this->assertStringNotContainsString('selected_options', $encoded);
            $this->assertStringNotContainsString('stem', $encoded);

            return $request->hasHeader('X-AlignEx-Engine-Key');
        });
        $this->actingAs($author)->get('/exams/'.$attempt->exam_id.'/adaptive/research')
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('Exams/AdaptiveResearch')->has('runs', 2));
        $this->expectException(\LogicException::class);
        $run->update(['result' => []]);
    }

    public function test_engine_outage_or_invalid_output_never_mutates_delivery_or_ledger(): void
    {
        [$attempt, $calibration, $author, $reviewer] = $this->setupResearch();
        app(AdaptiveCalibrationService::class)->transition($attempt->exam, $reviewer, $calibration, 'review');
        $state = $this->start($attempt);
        $before = AdaptiveProgression::firstOrFail()->getAttributes();
        foreach (['outage', 'stale', 'unknown', 'redirect'] as $scenario) {
            Http::swap(new Factory);
            Http::fake(function ($request) use ($scenario) {
                if ($scenario === 'outage') {
                    return Http::failedConnection();
                }
                if ($scenario === 'redirect') {
                    return Http::response('', 302, ['Location' => 'https://untrusted.invalid']);
                }
                $data = $this->validResponse($request->data());
                if ($scenario === 'stale') {
                    $data['state_version']++;
                }
                if ($scenario === 'unknown') {
                    $data['selected_item_id'] = 'unknown';
                }

                return Http::response($data);
            });
            $run = app(AdaptiveShadowService::class)->evaluate($attempt->exam, $reviewer, $calibration, AdaptiveLevel::firstOrFail());
            $this->assertSame('failed', $run->status);
            $this->assertNull($run->result);
            $this->assertSame($before, AdaptiveProgression::firstOrFail()->getAttributes());
        }
        Http::preventStrayRequests();
        $state = $this->commit($attempt, $state, true, 'local-only');
        $this->assertNotNull($state['current_item']);
        $this->assertEquals(200, AdaptiveProgression::firstOrFail()->earned_units);
    }

    public function test_concurrent_state_change_or_revocation_discards_the_engine_result(): void
    {
        foreach (['state_changed', 'calibration_revoked'] as $reason) {
            [$attempt, $calibration, $author, $reviewer] = $this->setupResearch();
            app(AdaptiveCalibrationService::class)->transition($attempt->exam, $reviewer, $calibration, 'review');
            $this->start($attempt);
            Http::swap(new Factory);
            Http::fake(function ($request) use ($attempt, $calibration, $reviewer, $reason) {
                if ($reason === 'state_changed') {
                    AdaptiveAttemptState::where('attempt_id', $attempt->id)->increment('state_version');
                } else {
                    app(AdaptiveCalibrationService::class)->transition($attempt->exam, $reviewer, $calibration, 'revoke');
                }

                return Http::response($this->validResponse($request->data()));
            });
            $run = app(AdaptiveShadowService::class)->evaluate($attempt->exam, $reviewer, $calibration, AdaptiveLevel::where('attempt_id', $attempt->id)->firstOrFail());
            $this->assertSame($reason, $run->error_code);
            $this->assertNull($run->result);
        }
    }

    public function test_owner_candidate_and_reviewer_write_boundaries(): void
    {
        [$attempt, $calibration, $author] = $this->setupResearch();
        $base = '/exams/'.$attempt->exam_id.'/adaptive/research';
        $outsider = User::factory()->create(['role' => User::ROLE_ORGANIZATION_ADMIN, 'organization_id' => Organization::factory()]);
        $this->actingAs($outsider)->get($base)->assertForbidden();
        $this->post($base.'/calibrations/'.$calibration->id, ['action' => 'review'])->assertForbidden();
        $this->get($base.'/template/'.$calibration->snapshot_id)->assertForbidden();
        $candidate = User::factory()->create(['role' => User::ROLE_CANDIDATE, 'organization_id' => $attempt->exam->organization_id]);
        $this->actingAs($candidate)->get($base)->assertForbidden();
        $this->actingAs($author)->get($base.'/template/'.$calibration->snapshot_id)->assertOk()->assertJsonPath('items.0.a', null)->assertJsonMissingPath('items.0.options');
    }

    public function test_draft_and_revoked_calibrations_cannot_run_and_replay_mismatch_is_retained(): void
    {
        [$attempt, $calibration, $author, $reviewer] = $this->setupResearch();
        $this->start($attempt);
        $service = app(AdaptiveShadowService::class);
        try {
            $service->evaluate($attempt->exam, $reviewer, $calibration, AdaptiveLevel::firstOrFail());
            $this->fail('Draft accepted');
        } catch (ValidationException) {
            $this->assertDatabaseCount('adaptive_engine_runs', 0);
        }
        app(AdaptiveCalibrationService::class)->transition($attempt->exam, $reviewer, $calibration, 'review');
        Http::swap(new Factory);
        Http::fake(fn ($request) => Http::response($this->validResponse($request->data())));
        $run = $service->evaluate($attempt->exam, $reviewer, $calibration, AdaptiveLevel::firstOrFail());
        Http::swap(new Factory);
        Http::fake(fn ($request) => Http::response([...$this->validResponse($request->data()), 'theta' => 0.4]));
        $replay = $service->evaluate($attempt->exam, $reviewer, $calibration, AdaptiveLevel::firstOrFail(), $run);
        $this->assertSame('replay_mismatch', $replay->error_code);
        app(AdaptiveCalibrationService::class)->transition($attempt->exam, $reviewer, $calibration, 'revoke');
        $this->expectException(ValidationException::class);
        $service->evaluate($attempt->exam, $reviewer, $calibration, AdaptiveLevel::firstOrFail(), $run);
    }
}
