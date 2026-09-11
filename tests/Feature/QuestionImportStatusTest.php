<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\ProfessionalSchool;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class QuestionImportStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_selected_status_overrides_every_csv_row_in_both_import_workflows(): void
    {
        foreach ([false, true] as $professional) {
            [$admin, $bank, $url] = $this->importContext($professional);
            foreach (['draft', 'review', 'approved', 'rejected', 'archived'] as $status) {
                $this->actingAs($admin)->post($url, [
                    'question_bank_id' => $bank->id,
                    'subject_id' => $bank->subject_id,
                    'status' => $status,
                    'file' => $this->csv(),
                ])->assertRedirect()->assertSessionHasNoErrors();
                $this->assertSame([$status, $status], $bank->questions()->pluck('status')->all());
                $bank->questions()->get()->each->forceDelete();
            }
        }
    }

    public function test_invalid_status_rejects_entire_upload_before_creating_questions(): void
    {
        foreach ([false, true] as $professional) {
            [$admin, $bank, $url] = $this->importContext($professional);
            $this->actingAs($admin)->post($url, [
                'question_bank_id' => $bank->id,
                    'subject_id' => $bank->subject_id,
                'status' => 'published',
                'file' => $this->csv(),
            ])->assertSessionHasErrors('status');
            $this->assertSame(0, $bank->questions()->count());
        }
    }

    private function csv(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('questions.csv',
            "difficulty,marks,question_text,status,option_a,option_b,correct_answer\neasy,1,First question,draft,Yes,No,A\nmedium,1,Second question,invalid-csv-status,Yes,No,B\n"
        );
    }

    private function importContext(bool $professional): array
    {
        $organization = Organization::factory()->create();
        $school = $professional ? ProfessionalSchool::query()->create([
            'organization_id' => $organization->id, 'name' => 'Import Academy',
            'code' => 'IMPORT', 'status' => ProfessionalSchool::STATUS_ACTIVE,
            'contact_person' => 'Import Lead', 'email' => 'import@example.test',
            'phone' => '08030000001', 'address' => 'Lagos',
        ]) : null;
        $admin = User::factory()->create([
            'role' => $professional ? User::ROLE_PROFESSIONAL_SCHOOL_ADMIN : User::ROLE_ORGANIZATION_ADMIN,
            'organization_id' => $professional ? null : $organization->id,
            'professional_school_id' => $school?->id,
        ]);
        $subject = Subject::factory()->create(['organization_id' => $organization->id, 'school_id' => null]);
        $bank = QuestionBank::factory()->create([
            'organization_id' => $organization->id, 'professional_school_id' => $school?->id,
            'subject_id' => $subject->id, 'school_id' => null,
        ]);

        return [$admin, $bank, $professional ? "/professional-schools/{$school->id}/questions/import" : '/questions/import'];
    }
}
