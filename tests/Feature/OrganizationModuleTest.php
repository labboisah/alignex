<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OrganizationModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_view_organizations_list(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $organization = Organization::factory()->create(['name' => 'Acme Exams']);

        $this->actingAs($superAdmin)
            ->get('/organizations')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Organizations/Index')
                ->where('organizations.data.0.name', 'Acme Exams')
            );
    }

    public function test_super_admin_can_create_organization(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $response = $this->actingAs($superAdmin)->post('/organizations', [
            'name' => 'Bright Future Schools',
            'code' => 'BFS',
            'contact_person' => 'Ada Lovelace',
            'email' => 'admin@brightfuture.test',
            'phone' => '08030000000',
            'address' => '12 Exam Road',
            'status' => Organization::STATUS_ACTIVE,
        ]);

        $organization = Organization::query()->where('code', 'BFS')->firstOrFail();

        $response->assertRedirect(route('organizations.show', $organization, absolute: false));
        $this->assertDatabaseHas('organizations', [
            'name' => 'Bright Future Schools',
            'email' => 'admin@brightfuture.test',
            'status' => Organization::STATUS_ACTIVE,
        ]);
    }

    public function test_super_admin_can_update_and_deactivate_organization(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $organization = Organization::factory()->create();

        $this->actingAs($superAdmin)
            ->patch("/organizations/{$organization->id}", [
                'name' => 'Updated Organization',
                'code' => $organization->code,
                'contact_person' => 'Grace Hopper',
                'email' => $organization->email,
                'phone' => '08031111111',
                'address' => 'Updated address',
                'status' => Organization::STATUS_ACTIVE,
            ])
            ->assertRedirect(route('organizations.show', $organization, absolute: false));

        $this->assertDatabaseHas('organizations', [
            'id' => $organization->id,
            'name' => 'Updated Organization',
            'contact_person' => 'Grace Hopper',
        ]);

        $this->actingAs($superAdmin)
            ->patch("/organizations/{$organization->id}/deactivate")
            ->assertRedirect();

        $this->assertDatabaseHas('organizations', [
            'id' => $organization->id,
            'status' => Organization::STATUS_INACTIVE,
        ]);
    }

    public function test_organization_admin_can_view_only_own_organization_details(): void
    {
        $organization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();
        $organizationAdmin = User::factory()->create([
            'role' => User::ROLE_ORGANIZATION_ADMIN,
            'organization_id' => $organization->id,
        ]);

        $this->actingAs($organizationAdmin)
            ->get("/organizations/{$organization->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Organizations/Show')
                ->where('can.update', false)
                ->where('can.deactivate', false)
            );

        $this->actingAs($organizationAdmin)
            ->get("/organizations/{$organization->id}/edit")
            ->assertForbidden();

        $this->actingAs($organizationAdmin)
            ->patch("/organizations/{$organization->id}/deactivate")
            ->assertForbidden();

        $this->actingAs($organizationAdmin)
            ->get("/organizations/{$otherOrganization->id}")
            ->assertForbidden();
    }

    public function test_organization_validation_requires_unique_code_and_email(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        Organization::factory()->create([
            'code' => 'DUPLICATE',
            'email' => 'duplicate@example.test',
        ]);

        $this->actingAs($superAdmin)
            ->post('/organizations', [
                'name' => '',
                'code' => 'DUPLICATE',
                'contact_person' => '',
                'email' => 'duplicate@example.test',
                'status' => 'unknown',
            ])
            ->assertSessionHasErrors(['name', 'code', 'contact_person', 'email', 'status']);
    }
}
