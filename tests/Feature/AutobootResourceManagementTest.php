<?php

namespace Tests\Feature;

use App\Models\OfflineReadinessPackage;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\AutobootSyntheticDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AutobootResourceManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_sees_only_synthetic_resources_in_autoboot_pages(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        app(AutobootSyntheticDataSeeder::class)->run(50);
        Subject::factory()->create(['name' => 'Normal Subject']);

        foreach ([
            ['/autoboot/subjects', '/subjects?scope=autoboot', 'Subjects/Index', 'subjects.data'],
            ['/autoboot/question-banks', '/question-bank?scope=autoboot', 'QuestionBanks/Index', 'questionBanks.data'],
            ['/autoboot/questions', '/questions?scope=autoboot', 'Questions/Index', 'questions.data'],
            ['/autoboot/candidates', '/candidates?scope=autoboot', 'Candidates/Index', 'candidates.data'],
        ] as [$url, $scopedUrl, $component, $prop]) {
            $this->actingAs($admin)
                ->get($url)
                ->assertRedirect();

            $this->actingAs($admin)
                ->get($scopedUrl)
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component($component)
                    ->where($prop, function ($records): bool {
                        $items = $records['data'] ?? $records;

                        return collect($items)->every(fn ($item) => str_contains(json_encode($item), 'Autoboot') || str_contains(json_encode($item), 'AUTO-'));
                    })
                );
        }
    }

    public function test_package_creation_generates_a_readiness_paper_from_each_row(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        app(AutobootSyntheticDataSeeder::class)->run(50);
        $subjects = Subject::query()->where('code', 'like', 'AUTO-%')->orderBy('code')->get();
        $banks = QuestionBank::query()->where('code', 'like', 'AUTO-%-BANK')->orderBy('code')->get();

        $response = $this->actingAs($admin)->post('/offline-readiness-packages', [
            'code' => 'autoboot.test.v1-15',
            'version' => '1',
            'capacity_profile' => 15,
            'candidate_count' => 15,
            'status' => OfflineReadinessPackage::STATUS_DRAFT,
            'paper_rows' => [
                ['subject_id' => $subjects[0]->id, 'question_bank_id' => $banks[0]->id, 'question_count' => 3],
                ['subject_id' => $subjects[1]->id, 'question_bank_id' => $banks[1]->id, 'question_count' => 2],
            ],
        ]);

        $response->assertRedirect('/offline-readiness-packages');
        $package = OfflineReadinessPackage::query()->where('code', 'autoboot.test.v1-15')->firstOrFail();

        $this->assertSame(5, $package->question_count);
        $this->assertCount(2, $package->paper_rows);
        $this->assertCount(5, collect($package->payload['subjects'])->flatMap(fn (array $subject) => $subject['questions']));
        $this->assertSame(18, $package->autoboot_target_clients);
        $this->assertCount(4, Question::query()->whereIn('question_bank_id', $banks->take(2)->pluck('id'))->first()->options);
    }

    public function test_package_list_action_regenerates_paper_and_active_packages_are_immutable(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        app(AutobootSyntheticDataSeeder::class)->run(50);
        $subject = Subject::query()->where('code', 'AUTO-ENG')->firstOrFail();
        $bank = QuestionBank::query()->where('code', 'AUTO-ENG-BANK')->firstOrFail();

        $this->actingAs($admin)->post('/offline-readiness-packages', [
            'code' => 'autoboot.action.v1-15',
            'version' => '1',
            'capacity_profile' => 15,
            'candidate_count' => 15,
            'status' => OfflineReadinessPackage::STATUS_DRAFT,
            'paper_rows' => [['subject_id' => $subject->id, 'question_bank_id' => $bank->id, 'question_count' => 4]],
        ])->assertRedirect();

        $package = OfflineReadinessPackage::query()->where('code', 'autoboot.action.v1-15')->firstOrFail();
        $originalChecksum = $package->checksum_sha256;

        $this->actingAs($admin)
            ->post("/offline-readiness-packages/{$package->id}/generate-paper")
            ->assertRedirect();

        $this->assertNotSame($originalChecksum, $package->fresh()->checksum_sha256);

        $package->update(['status' => OfflineReadinessPackage::STATUS_ACTIVE]);
        $this->actingAs($admin)
            ->post("/offline-readiness-packages/{$package->id}/generate-paper")
            ->assertStatus(409);
    }
}
