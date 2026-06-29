<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\Exam;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CandidateModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_organization_admin_can_create_view_update_and_assign_candidate(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->create([
            'role' => User::ROLE_ORGANIZATION_ADMIN,
            'organization_id' => $organization->id,
        ]);
        $exam = Exam::factory()->create([
            'organization_id' => $organization->id,
            'school_id' => null,
            'center_id' => null,
        ]);

        $this->actingAs($admin)
            ->post('/candidates', $this->payload())
            ->assertRedirect();

        $candidate = Candidate::query()->where('candidate_number', 'REG-001')->firstOrFail();

        $this->assertSame($organization->id, $candidate->organization_id);

        $this->actingAs($admin)
            ->get("/candidates/{$candidate->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Candidates/Show')
                ->where('candidate.data.registration_number', 'REG-001')
            );

        $this->actingAs($admin)
            ->patch("/candidates/{$candidate->id}", $this->payload(['full_name' => 'Grace Hopper', 'registration_number' => 'REG-002']))
            ->assertRedirect(route('candidates.show', $candidate, absolute: false));

        $this->assertSame('Grace', $candidate->refresh()->first_name);
        $this->assertSame('REG-002', $candidate->candidate_number);

        $this->actingAs($admin)
            ->post('/candidates/assign', [
                'exam_id' => $exam->id,
                'candidate_ids' => [$candidate->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('exam_candidates', [
            'exam_id' => $exam->id,
            'candidate_id' => $candidate->id,
            'status' => 'assigned',
        ]);

        $this->actingAs($admin)
            ->delete("/exams/{$exam->id}/candidates/{$candidate->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('exam_candidates', [
            'exam_id' => $exam->id,
            'candidate_id' => $candidate->id,
        ]);
    }

    public function test_registration_number_is_unique_inside_scope(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->create([
            'role' => User::ROLE_ORGANIZATION_ADMIN,
            'organization_id' => $organization->id,
        ]);

        Candidate::factory()->create([
            'organization_id' => $organization->id,
            'school_id' => null,
            'center_id' => null,
            'candidate_number' => 'REG-001',
        ]);

        $this->actingAs($admin)
            ->post('/candidates', $this->payload())
            ->assertSessionHasErrors('registration_number');
    }

    public function test_candidate_csv_import_reports_successes_duplicates_and_failures(): void
    {
        $organization = Organization::factory()->create(['code' => 'ORG001']);
        $admin = User::factory()->create([
            'role' => User::ROLE_ORGANIZATION_ADMIN,
            'organization_id' => $organization->id,
        ]);
        $exam = Exam::factory()->create([
            'organization_id' => $organization->id,
            'school_id' => null,
            'center_id' => null,
        ]);

        Candidate::factory()->create([
            'organization_id' => $organization->id,
            'school_id' => null,
            'center_id' => null,
            'candidate_number' => 'REG-EXISTING',
        ]);

        $file = UploadedFile::fake()->createWithContent('candidates.csv', implode("\n", [
            'full_name,registration_number,email,phone,date_of_birth,status',
            'Ada Okafor,REG-NEW,ada@example.com,08030000000,2008-04-12,active',
            'Ada Okafor,REG-NEW,ada@example.com,08030000000,2008-04-12,active',
            ',REG-BAD,not-an-email,08030000000,2008-04-12,active',
            'Existing Person,REG-EXISTING,existing@example.com,08030000000,2008-04-12,active',
        ]));

        $this->actingAs($admin)
            ->post('/candidates/import', ['exam_id' => $exam->id, 'file' => $file])
            ->assertRedirect()
            ->assertSessionHas('candidate_import_report');

        $report = session('candidate_import_report');

        $this->assertCount(1, $report['successful']);
        $this->assertCount(1, $report['failed']);
        $this->assertCount(2, $report['duplicates']);
        $this->assertDatabaseHas('candidates', [
            'organization_id' => $organization->id,
            'candidate_number' => 'REG-NEW',
        ]);
        $this->assertDatabaseHas('exam_candidates', [
            'exam_id' => $exam->id,
            'status' => 'assigned',
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'full_name' => 'Ada Okafor',
            'registration_number' => 'REG-001',
            'email' => 'ada@example.com',
            'phone' => '08030000000',
            'date_of_birth' => '2008-04-12',
            'status' => Candidate::STATUS_ACTIVE,
        ], $overrides);
    }
}
