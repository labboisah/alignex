<?php

namespace Tests\Feature;

use App\Models\Center;
use App\Models\Candidate;
use App\Models\Exam;
use App\Models\Organization;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RbacTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_candidate_cannot_access_dashboard(): void
    {
        $candidate = User::factory()->create(['role' => User::ROLE_CANDIDATE]);

        $this->actingAs($candidate)->get('/dashboard')->assertForbidden();
    }

    public function test_super_admin_can_access_all_primary_portal_sections(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        foreach ([
            '/dashboard',
            '/organizations',
            '/access-controls',
            '/admin-registrations',
            '/centers',
            '/schools',
            '/users',
            '/subjects',
            '/topics',
            '/question-bank',
            '/questions',
            '/exams',
            '/candidates',
            '/results',
            '/reports',
            '/settings',
            '/assigned-exams',
            '/supervisor-monitor',
            '/candidate-activity',
        ] as $path) {
            $this->actingAs($superAdmin)->get($path)->assertOk();
        }
    }

    public function test_organization_admin_sees_only_own_organization_data(): void
    {
        $ownOrganization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();
        $organizationAdmin = User::factory()->create([
            'role' => User::ROLE_ORGANIZATION_ADMIN,
            'organization_id' => $ownOrganization->id,
        ]);

        $this->actingAs($organizationAdmin)
            ->get("/organizations/{$ownOrganization->id}")
            ->assertOk();

        $this->actingAs($organizationAdmin)
            ->get("/organizations/{$otherOrganization->id}")
            ->assertForbidden();
    }

    public function test_examiner_cannot_manage_organizations(): void
    {
        $examiner = User::factory()->create(['role' => User::ROLE_EXAMINER]);

        $this->actingAs($examiner)->get('/organizations')->assertForbidden();
    }

    public function test_supervisor_cannot_manage_question_bank(): void
    {
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);

        $this->actingAs($supervisor)->get('/question-bank')->assertForbidden();
    }

    public function test_sidebar_renders_correctly_by_role(): void
    {
        $expected = [
            User::ROLE_SUPER_ADMIN => [
                'Dashboard',
                'Platform',
                'Organizations',
                'Applications',
                'CBT Centers',
                'Admin',
                'Users',
                'Access Controls',
                'Reports',
            ],
            User::ROLE_ORGANIZATION_ADMIN => [
                'Dashboard',
                'Candidates',
                'Candidate Groups',
                'Questions',
                'Subjects',
                'Question Bank',
                'Exams',
                'Recruitment Exams',
                'Assessment Exams',
                'Certification Exams',
                'Adaptive Exams',
                'Reports',
                'Results',
                'Admin',
                'Users',
                'Settings',
            ],
            User::ROLE_EXAMINER => [
                'Dashboard',
                'Candidates',
                'Candidate Groups',
                'Questions',
                'Subjects',
                'Question Bank',
                'Exams',
                'Recruitment Exams',
                'Assessment Exams',
                'Certification Exams',
                'Adaptive Exams',
                'Reports',
                'Results',
            ],
            User::ROLE_SUPERVISOR => [
                'Dashboard',
                'Reports',
                'Results',
            ],
            User::ROLE_CENTER_ADMIN => [
                'Dashboard',
                'Candidates',
                'Candidate Groups',
                'Questions',
                'Subjects',
                'Question Bank',
                'Exams',
                'Recruitment Exams',
                'Assessment Exams',
                'Certification Exams',
                'Adaptive Exams',
                'Reports',
                'Results',
            ],
            User::ROLE_SCHOOL_ADMIN => [
                'Dashboard',
                'Candidates',
                'Candidate Groups',
                'Questions',
                'Subjects',
                'Question Bank',
                'Exams',
                'Recruitment Exams',
                'Assessment Exams',
                'Certification Exams',
                'Adaptive Exams',
                'Reports',
                'Results',
            ],
        ];

        foreach ($expected as $role => $labels) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)
                ->get('/dashboard')
                ->assertInertia(fn (Assert $page) => $page
                    ->component('Dashboard/Index')
                    ->where('auth.navigation', function ($navigation) use ($labels, $role) {
                        $actual = $navigation
                            ->flatMap(fn ($item) => collect([data_get($item, 'label')])
                                ->merge(collect(data_get($item, 'children', []))->pluck('label')))
                            ->unique()
                            ->values()
                            ->all();

                        $this->assertSame($labels, $actual, "Navigation mismatch for {$role}");

                        return true;
                    })
                );
        }
    }

    public function test_teacher_sidebar_uses_default_permissions_when_role_permissions_are_empty(): void
    {
        Role::query()->updateOrCreate(
            ['name' => User::ROLE_TEACHER],
            [
                'label' => 'Teacher',
                'description' => 'Uploads questions and creates assessments for assigned secondary school subjects.',
                'is_system' => true,
            ]
        )->permissions()->detach();

        $teacher = User::factory()->create(['role' => User::ROLE_TEACHER]);

        $this->actingAs($teacher)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Index')
                ->where('auth.permissions.manageQuestionBank', true)
                ->where('auth.permissions.manageExams', true)
                ->where('auth.permissions.viewReports', true)
                ->where('auth.navigation', function ($navigation) {
                    $labels = collect($navigation)
                        ->pluck('label')
                        ->values()
                        ->all();

                    $this->assertSame([
                        'Dashboard',
                        'Subjects',
                        'Question Bank',
                        'Questions',
                        'Assessments',
                        'Results',
                    ], $labels);
                    $this->assertFalse(collect($navigation)->contains(fn ($item) => isset($item['children'])));

                    return true;
                })
            );
    }

    public function test_dashboard_metrics_are_scoped_to_user_role(): void
    {
        $ownOrganization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();
        $ownExam = Exam::factory()->create(['organization_id' => $ownOrganization->id]);
        Exam::factory()->create(['organization_id' => $otherOrganization->id]);
        Candidate::factory()->create(['organization_id' => $ownOrganization->id]);
        Candidate::factory()->create(['organization_id' => $otherOrganization->id]);
        $organizationAdmin = User::factory()->create([
            'role' => User::ROLE_ORGANIZATION_ADMIN,
            'organization_id' => $ownOrganization->id,
        ]);

        $this->actingAs($organizationAdmin)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Index')
                ->where('role.scope', 'Organization scope')
                ->where('metrics', function ($metrics) {
                    $values = $metrics->pluck('value', 'label');

                    $this->assertSame(1, $values['Total Exams']);
                    $this->assertSame(1, $values['Total Candidates']);

                    return true;
                })
                ->where('recent_exams.0.code', $ownExam->code)
            );
    }

    public function test_only_super_admin_can_manage_access_controls(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $organizationAdmin = User::factory()->create(['role' => User::ROLE_ORGANIZATION_ADMIN]);

        $this->actingAs($superAdmin)
            ->get('/access-controls')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('AccessControls/Index')
                ->has('roles')
                ->has('permissions')
            );

        $this->actingAs($organizationAdmin)
            ->get('/access-controls')
            ->assertForbidden();
    }

    public function test_organization_admin_cannot_manage_centers(): void
    {
        $organizationAdmin = User::factory()->create([
            'role' => User::ROLE_ORGANIZATION_ADMIN,
        ]);

        $this->actingAs($organizationAdmin)->get('/centers')->assertForbidden();
    }

    public function test_center_users_are_scoped_to_their_center(): void
    {
        $center = Center::factory()->create();
        $otherCenter = Center::factory()->create();
        $supervisor = User::factory()->create([
            'role' => User::ROLE_CENTER_ADMIN,
            'center_id' => $center->id,
        ]);

        $this->assertTrue($supervisor->belongsToCenter($center->id));
        $this->assertFalse($supervisor->belongsToCenter($otherCenter->id));
    }

    public function test_school_users_are_scoped_to_their_school(): void
    {
        $school = School::factory()->create();
        $otherSchool = School::factory()->create();
        $schoolAdmin = User::factory()->create([
            'role' => User::ROLE_SCHOOL_ADMIN,
            'school_id' => $school->id,
        ]);

        $this->assertTrue($schoolAdmin->belongsToSchool($school->id));
        $this->assertFalse($schoolAdmin->belongsToSchool($otherSchool->id));
    }

    public function test_access_control_updates_affect_sidebar_and_protected_routes(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $examiner = User::factory()->create(['role' => User::ROLE_EXAMINER]);

        $this->actingAs($superAdmin)
            ->patch('/access-controls', [
                'roles' => [
                    User::ROLE_EXAMINER => ['manageExams', 'viewReports'],
                ],
            ])
            ->assertRedirect();

        $this->actingAs($examiner)
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.permissions.manageQuestionBank', false)
                ->where('auth.navigation', fn ($navigation) => ! in_array('Question Bank', $navigation->pluck('label')->all(), true))
            );

        $this->actingAs($examiner)
            ->get('/question-bank')
            ->assertForbidden();
    }
}
