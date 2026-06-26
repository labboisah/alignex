<?php

namespace Tests\Feature;

use App\Models\Center;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CenterModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_view_and_create_centers(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        Center::factory()->create([
            'name' => 'Main CBT Center',
        ]);

        $this->actingAs($superAdmin)
            ->get('/centers')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Centers/Index')
                ->where('centers.data.0.name', 'Main CBT Center')
            );

        $response = $this->actingAs($superAdmin)->post('/centers', [
            'name' => 'North CBT Center',
            'code' => 'NORTH-CBT',
            'location' => 'North campus',
            'capacity' => 250,
            'contact_person' => 'Mary Johnson',
            'phone' => '08030000000',
            'email' => 'north@example.test',
            'status' => Center::STATUS_ACTIVE,
        ]);

        $center = Center::query()->where('code', 'NORTH-CBT')->firstOrFail();

        $response->assertRedirect(route('centers.show', $center, absolute: false));
        $this->assertDatabaseHas('centers', [
            'name' => 'North CBT Center',
            'capacity' => 250,
        ]);
    }

    public function test_organization_admin_cannot_manage_centers_without_center_scope(): void
    {
        $organizationAdmin = User::factory()->create([
            'role' => User::ROLE_ORGANIZATION_ADMIN,
        ]);

        $this->actingAs($organizationAdmin)->get('/centers')->assertForbidden();
    }

    public function test_center_admin_can_manage_only_own_center(): void
    {
        $center = Center::factory()->create();
        $otherCenter = Center::factory()->create();
        $centerAdmin = User::factory()->create([
            'role' => User::ROLE_CENTER_ADMIN,
            'center_id' => $center->id,
        ]);

        $this->actingAs($centerAdmin)
            ->get('/centers')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('centers.data.0.id', $center->id)
                ->has('centers.data', 1)
            );

        $this->actingAs($centerAdmin)
            ->patch("/centers/{$center->id}", [
                'name' => 'Updated Center',
                'code' => $center->code,
                'location' => 'Updated location',
                'capacity' => 300,
                'contact_person' => 'Center Manager',
                'phone' => '08031111111',
                'email' => $center->email,
                'status' => Center::STATUS_ACTIVE,
            ])
            ->assertRedirect(route('centers.show', $center, absolute: false));

        $this->assertDatabaseHas('centers', [
            'id' => $center->id,
            'name' => 'Updated Center',
            'capacity' => 300,
        ]);

        $this->actingAs($centerAdmin)
            ->get("/centers/{$otherCenter->id}")
            ->assertForbidden();

        $this->actingAs($centerAdmin)
            ->get('/centers/create')
            ->assertForbidden();
    }

    public function test_center_validation_requires_unique_code_and_email(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        Center::factory()->create([
            'code' => 'DUP-CENTER',
            'email' => 'duplicate-center@example.test',
        ]);

        $this->actingAs($superAdmin)
            ->post('/centers', [
                'name' => '',
                'code' => 'DUP-CENTER',
                'location' => '',
                'capacity' => 0,
                'contact_person' => '',
                'email' => 'duplicate-center@example.test',
                'status' => 'unknown',
            ])
            ->assertSessionHasErrors(['name', 'code', 'location', 'capacity', 'contact_person', 'email', 'status']);
    }
}
