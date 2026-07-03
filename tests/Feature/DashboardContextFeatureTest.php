<?php

namespace Tests\Feature;

use App\Models\CbtCenter;
use App\Models\Organization;
use App\Models\ProfessionalSchool;
use App\Models\SecondarySchool;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardContextFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
    }

    public function test_user_with_one_context_auto_selects_context(): void
    {
        $organization = Organization::factory()->create(['name' => 'Hope Future NGO']);
        $user = User::factory()->create(['role' => User::ROLE_ORGANIZATION_ADMIN, 'organization_id' => $organization->id]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Index')
                ->where('current_context.type', 'organization')
                ->where('current_context.name', 'Hope Future NGO')
                ->where('available_contexts.0.type', 'organization')
            );
    }

    public function test_super_admin_without_selected_context_sees_platform_dashboard(): void
    {
        Organization::factory()->create();
        SecondarySchool::query()->create($this->schoolPayload(Organization::factory()->create()->id, 'Minarat Science Academy', 'MIN-SCI', 'minarat@example.test'));
        $user = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Index')
                ->where('current_context', null)
                ->where('role.scope', 'Platform-wide')
                ->where('metrics.0.label', 'Total Organizations')
                ->where('auth.navigation.1.label', 'Organizations')
            );
    }

    public function test_user_with_multiple_contexts_can_switch_context(): void
    {
        $organization = Organization::factory()->create();
        $secondary = SecondarySchool::query()->create($this->schoolPayload($organization->id, 'Secondary Academy', 'SEC-CTX', 'secondary@example.test'));
        $professional = ProfessionalSchool::query()->create($this->schoolPayload($organization->id, 'Professional Academy', 'PRO-CTX', 'professional@example.test'));
        $user = User::factory()->create(['role' => User::ROLE_ORGANIZATION_ADMIN, 'organization_id' => $organization->id]);

        $this->actingAs($user)
            ->patch('/current-context', ['context_type' => 'professional_school', 'context_id' => $professional->id])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'active_context_type' => 'professional_school',
            'active_context_id' => $professional->id,
        ]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('current_context.type', 'professional_school')
                ->where('current_context.name', 'Professional Academy')
                ->has('available_contexts', 3)
            );

        $this->assertTrue($user->fresh()->canAccessSecondarySchool($secondary->id));
    }

    public function test_dashboard_metrics_and_sidebar_follow_selected_context(): void
    {
        $organization = Organization::factory()->create();
        $secondary = SecondarySchool::query()->create($this->schoolPayload($organization->id, 'Minarat Science Academy', 'MIN-SCI', 'minarat@example.test'));
        $professional = ProfessionalSchool::query()->create($this->schoolPayload($organization->id, 'Kernelbridge Computer Academy', 'KCA', 'kca@example.test'));
        $center = CbtCenter::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Malam Yahaya Digital Technology Center',
            'code' => 'MYDTC',
            'location' => 'Kano',
            'capacity' => 100,
            'contact_person' => 'Manager',
            'email' => 'mydtc@example.test',
            'status' => CbtCenter::STATUS_ACTIVE,
        ]);
        $user = User::factory()->create(['role' => User::ROLE_ORGANIZATION_ADMIN, 'organization_id' => $organization->id]);

        foreach ([
            ['secondary_school', $secondary->id, 'Total Students', 'Administration', 'Academic Sessions'],
            ['professional_school', $professional->id, 'Total Candidates / Trainees', 'Programmes', 'Programmes'],
            ['cbt_center', $center->id, 'Total Candidates', 'Traditional CBT Exams', 'Traditional CBT Exams'],
        ] as [$type, $id, $metric, $visibleNav, $nestedNav]) {
            $this->actingAs($user)->patch('/current-context', ['context_type' => $type, 'context_id' => $id]);

            $this->actingAs($user)
                ->get('/dashboard')
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('Dashboard/Index')
                    ->where('current_context.type', $type)
                    ->where('metrics.0.label', $metric)
                    ->where('auth.navigation', function ($navigation) use ($visibleNav, $nestedNav) {
                        $labels = $this->navigationLabels($navigation);

                        $this->assertSame('Dashboard', $labels[0]);
                        $this->assertContains($visibleNav, $labels);
                        $this->assertContains($nestedNav, $labels);

                        return true;
                    })
                );
        }
    }

    public function test_sidebar_hides_modules_from_other_contexts(): void
    {
        $organization = Organization::factory()->create();
        $secondary = SecondarySchool::query()->create($this->schoolPayload($organization->id, 'Minarat Science Academy', 'MIN-SCI', 'minarat@example.test'));
        $professional = ProfessionalSchool::query()->create($this->schoolPayload($organization->id, 'Kernelbridge Computer Academy', 'KCA', 'kca@example.test'));
        $center = CbtCenter::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Malam Yahaya Digital Technology Center',
            'code' => 'MYDTC',
            'location' => 'Kano',
            'capacity' => 100,
            'contact_person' => 'Manager',
            'email' => 'mydtc-sidebar@example.test',
            'status' => CbtCenter::STATUS_ACTIVE,
        ]);
        $user = User::factory()->create(['role' => User::ROLE_ORGANIZATION_ADMIN, 'organization_id' => $organization->id]);

        $checks = [
            ['secondary_school', $secondary->id, ['Administration', 'Exam', 'Academic Sessions', 'Exams'], ['Programmes', 'Training Batches', 'Traditional CBT Exams']],
            ['professional_school', $professional->id, ['Programmes', 'Training Batches'], ['Academic Sessions', 'Terminal Exams', 'Traditional CBT Exams']],
            ['cbt_center', $center->id, ['Traditional CBT Exams', 'Adaptive CBT Exams'], ['Academic Sessions', 'Programmes', 'Training Batches']],
        ];

        foreach ($checks as [$type, $id, $expected, $unexpected]) {
            $this->actingAs($user)->patch('/current-context', ['context_type' => $type, 'context_id' => $id]);

            $this->actingAs($user)
                ->get('/dashboard')
                ->assertInertia(fn (Assert $page) => $page
                    ->where('auth.navigation', function ($navigation) use ($expected, $unexpected) {
                        $labels = $this->navigationLabels($navigation);

                        foreach ($expected as $label) {
                            $this->assertContains($label, $labels);
                        }

                        foreach ($unexpected as $label) {
                            $this->assertNotContains($label, $labels);
                        }

                        return true;
                    })
                );
        }
    }

    public function test_frontend_terminology_helper_defines_context_labels(): void
    {
        $helper = file_get_contents(resource_path('js/lib/terminology.ts'));

        $this->assertStringContainsString("organization", $helper);
        $this->assertStringContainsString("secondary_school", $helper);
        $this->assertStringContainsString("professional_school", $helper);
        $this->assertStringContainsString("cbt_center", $helper);
        $this->assertStringContainsString("Terminal Exam", $helper);
        $this->assertStringContainsString("Professional Exam", $helper);
        $this->assertStringContainsString("CBT Exam", $helper);
        $this->assertStringContainsString("Report Card", $helper);
    }

    public function test_candidate_users_cannot_access_admin_dashboard(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_CANDIDATE]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertForbidden();
    }

    private function schoolPayload(int $organizationId, string $name, string $code, string $email): array
    {
        return [
            'organization_id' => $organizationId,
            'name' => $name,
            'code' => $code,
            'contact_person' => 'Registrar',
            'email' => $email,
            'phone' => '08000000000',
            'address' => 'Address',
            'status' => SecondarySchool::STATUS_ACTIVE,
        ];
    }

    private function navigationLabels($navigation): array
    {
        return collect($navigation)
            ->flatMap(fn ($item) => collect([$item['label']])->merge(isset($item['children']) ? $this->navigationLabels($item['children']) : []))
            ->values()
            ->all();
    }
}
