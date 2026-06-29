<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Center;
use App\Models\QuestionBank;
use App\Models\School;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class QuestionBankModulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_organization_admin_can_manage_scoped_subjects_topics_and_question_banks(): void
    {
        $organization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();
        $admin = User::factory()->create([
            'role' => User::ROLE_ORGANIZATION_ADMIN,
            'organization_id' => $organization->id,
        ]);

        Subject::factory()->create(['organization_id' => $otherOrganization->id, 'school_id' => null, 'name' => 'Hidden Subject']);

        $this->actingAs($admin)
            ->get('/subjects')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Subjects/Index')
                ->has('subjects.data', 0)
            );

        $this->actingAs($admin)
            ->post('/subjects', [
                'name' => 'Mathematics',
                'code' => 'MATH',
                'description' => 'Core mathematics',
                'status' => Subject::STATUS_ACTIVE,
            ])
            ->assertRedirect('/subjects');

        $subject = Subject::query()->where('code', 'MATH')->firstOrFail();
        $this->assertSame($organization->id, $subject->organization_id);

        $this->actingAs($admin)
            ->post('/topics', [
                'subject_id' => $subject->id,
                'parent_id' => null,
                'name' => 'Algebra',
                'code' => 'ALG',
                'description' => 'Foundations',
                'status' => Topic::STATUS_ACTIVE,
            ])
            ->assertRedirect('/topics');

        $topic = Topic::query()->where('code', 'ALG')->firstOrFail();

        $this->actingAs($admin)
            ->post('/question-bank', [
                'subject_id' => $subject->id,
                'name' => 'Mathematics Bank',
                'code' => 'MATH-BANK',
                'description' => 'Main bank',
                'status' => QuestionBank::STATUS_DRAFT,
            ])
            ->assertRedirect('/question-bank');

        $bank = QuestionBank::query()->where('code', 'MATH-BANK')->firstOrFail();
        $this->assertSame($organization->id, $bank->organization_id);

        $this->actingAs($admin)
            ->patch("/subjects/{$subject->id}", [
                'name' => 'Updated Mathematics',
                'code' => 'MATH',
                'description' => 'Updated',
                'status' => Subject::STATUS_INACTIVE,
            ])
            ->assertRedirect('/subjects');

        $this->actingAs($admin)->delete("/topics/{$topic->id}")->assertRedirect();
        $this->assertSoftDeleted('topics', ['id' => $topic->id]);
    }

    public function test_school_admin_can_access_sidebar_links_and_manage_school_scoped_records(): void
    {
        $school = School::factory()->create();
        $admin = User::factory()->create([
            'role' => User::ROLE_SCHOOL_ADMIN,
            'school_id' => $school->id,
        ]);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.permissions.manageQuestionBank', true)
                ->where('auth.navigation', fn ($navigation) => in_array('Subjects', $navigation->pluck('label')->all(), true)
                    && in_array('Topics', $navigation->pluck('label')->all(), true)
                    && in_array('Question Bank', $navigation->pluck('label')->all(), true))
            );

        $this->actingAs($admin)
            ->post('/subjects', [
                'name' => 'English Language',
                'code' => 'ENG',
                'description' => null,
                'status' => Subject::STATUS_ACTIVE,
            ])
            ->assertRedirect('/subjects');

        $subject = Subject::query()->where('code', 'ENG')->firstOrFail();
        $this->assertSame($school->id, $subject->school_id);

        $this->actingAs($admin)
            ->post('/question-bank', [
                'subject_id' => $subject->id,
                'name' => 'English Bank',
                'code' => 'ENG-BANK',
                'description' => null,
                'status' => QuestionBank::STATUS_ACTIVE,
            ])
            ->assertRedirect('/question-bank');

        $this->assertDatabaseHas('question_banks', [
            'school_id' => $school->id,
            'code' => 'ENG-BANK',
        ]);
    }

    public function test_center_admin_can_manage_center_scoped_question_bank_records(): void
    {
        $center = Center::factory()->create();
        $admin = User::factory()->create([
            'role' => User::ROLE_CENTER_ADMIN,
            'center_id' => $center->id,
        ]);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.permissions.manageQuestionBank', true)
                ->where('auth.navigation', fn ($navigation) => in_array('Subjects', $navigation->pluck('label')->all(), true)
                    && in_array('Topics', $navigation->pluck('label')->all(), true)
                    && in_array('Question Bank', $navigation->pluck('label')->all(), true))
            );

        $this->actingAs($admin)
            ->post('/subjects', [
                'name' => 'Computer Basics',
                'code' => 'CBT-BASIC',
                'description' => 'Center-administered basics',
                'status' => Subject::STATUS_ACTIVE,
            ])
            ->assertRedirect('/subjects');

        $subject = Subject::query()->where('code', 'CBT-BASIC')->firstOrFail();
        $this->assertSame($center->id, $subject->center_id);

        $this->actingAs($admin)
            ->post('/topics', [
                'subject_id' => $subject->id,
                'parent_id' => null,
                'name' => 'Keyboard Skills',
                'code' => 'KEYBOARD',
                'description' => null,
                'status' => Topic::STATUS_ACTIVE,
            ])
            ->assertRedirect('/topics');

        $this->actingAs($admin)
            ->post('/question-bank', [
                'subject_id' => $subject->id,
                'name' => 'Computer Basics Bank',
                'code' => 'CBT-BASIC-BANK',
                'description' => null,
                'status' => QuestionBank::STATUS_DRAFT,
            ])
            ->assertRedirect('/question-bank');

        $this->assertDatabaseHas('question_banks', [
            'center_id' => $center->id,
            'code' => 'CBT-BASIC-BANK',
        ]);
    }

    public function test_bulk_imports_create_records_inside_actor_scope(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->create([
            'role' => User::ROLE_ORGANIZATION_ADMIN,
            'organization_id' => $organization->id,
        ]);

        $subjectCsv = UploadedFile::fake()->createWithContent('subjects.csv', "name,code,description,status,scope_type,scope_code\nPhysics,PHY,Physics subject,active,,\n");

        $this->actingAs($admin)
            ->post('/subjects/import', ['file' => $subjectCsv])
            ->assertRedirect();

        $subject = Subject::query()->where('code', 'PHY')->firstOrFail();
        $this->assertSame($organization->id, $subject->organization_id);

        $topicCsv = UploadedFile::fake()->createWithContent('topics.csv', "subject_code,parent_code,name,code,description,status\nPHY,,Mechanics,MECH,Mechanics topic,active\n");

        $this->actingAs($admin)
            ->post('/topics/import', ['file' => $topicCsv])
            ->assertRedirect();

        $bankCsv = UploadedFile::fake()->createWithContent('question-banks.csv', "subject_code,name,code,description,status\nPHY,Physics Main Bank,PHY-MAIN,Main bank,draft\n");

        $this->actingAs($admin)
            ->post('/question-bank/import', ['file' => $bankCsv])
            ->assertRedirect();

        $this->assertDatabaseHas('topics', ['subject_id' => $subject->id, 'code' => 'MECH']);
        $this->assertDatabaseHas('question_banks', ['organization_id' => $organization->id, 'code' => 'PHY-MAIN']);
    }

    public function test_admins_cannot_use_subjects_outside_their_scope(): void
    {
        $ownOrganization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();
        $outsideSubject = Subject::factory()->create(['organization_id' => $otherOrganization->id, 'school_id' => null]);
        $admin = User::factory()->create([
            'role' => User::ROLE_ORGANIZATION_ADMIN,
            'organization_id' => $ownOrganization->id,
        ]);

        $this->actingAs($admin)
            ->post('/topics', [
                'subject_id' => $outsideSubject->id,
                'parent_id' => null,
                'name' => 'Forbidden Topic',
                'code' => 'NOPE',
                'description' => null,
                'status' => Topic::STATUS_ACTIVE,
            ])
            ->assertSessionHasErrors('subject_id');
    }
}
