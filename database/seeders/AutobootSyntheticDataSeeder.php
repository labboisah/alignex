<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\QuestionOption;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AutobootSyntheticDataSeeder extends Seeder
{
    /** @var array<string, string> */
    private const SUBJECTS = [
        'ENG' => 'English',
        'MATH' => 'Mathematics',
        'CHEM' => 'Chemistry',
        'PHY' => 'Physics',
        'BIO' => 'Biology',
    ];

    public function run(int $questionsPerSubject = 50, bool $fresh = false): array
    {
        $questionsPerSubject = max(1, $questionsPerSubject);
        $organization = Organization::query()->firstOrCreate(
            ['code' => 'AUTOBOOT-SYNTHETIC'],
            [
                'name' => 'AlignEx Autoboot Synthetic Data',
                'contact_person' => 'Autoboot Generator',
                'email' => 'autoboot-synthetic@example.test',
                'phone' => '0000000000',
                'address' => 'Synthetic data workspace',
                'status' => 'active',
            ],
        );
        $creator = User::query()->where('role', User::ROLE_SUPER_ADMIN)->first() ?? User::query()->first();

        if (! $creator) {
            $creator = User::factory()->create([
                'name' => 'Autoboot Data Generator',
                'email' => 'autoboot-generator@example.test',
                'role' => User::ROLE_SUPER_ADMIN,
                'organization_id' => $organization->id,
            ]);
        }

        $summary = DB::transaction(function () use ($organization, $creator, $questionsPerSubject, $fresh): array {
            $createdQuestions = 0;
            $createdOptions = 0;
            $subjects = 0;
            $banks = 0;

            foreach (self::SUBJECTS as $code => $name) {
                $subject = Subject::query()->withTrashed()->updateOrCreate(
                    ['organization_id' => $organization->id, 'code' => "AUTO-{$code}"],
                    [
                        'name' => $name,
                        'description' => "Synthetic {$name} content for AlignEx Autoboot readiness drills.",
                        'status' => Subject::STATUS_ACTIVE,
                        'school_id' => null,
                        'deleted_at' => null,
                    ],
                );
                $subjects++;

                $bank = QuestionBank::query()->withTrashed()->updateOrCreate(
                    ['organization_id' => $organization->id, 'code' => "AUTO-{$code}-BANK"],
                    [
                        'subject_id' => $subject->id,
                        'created_by' => $creator->id,
                        'name' => "Autoboot {$name} Bank",
                        'description' => "Synthetic question bank for {$name} Autoboot readiness tests.",
                        'status' => QuestionBank::STATUS_ACTIVE,
                        'school_id' => null,
                        'deleted_at' => null,
                    ],
                );
                $banks++;

                if ($fresh) {
                    $questionIds = Question::query()->withTrashed()->where('question_bank_id', $bank->id)->pluck('id');
                    QuestionOption::query()->whereIn('question_id', $questionIds)->delete();
                    Question::query()->whereIn('id', $questionIds)->forceDelete();
                }

                $existing = Question::query()->where('question_bank_id', $bank->id)->count();
                for ($number = $existing + 1; $number <= $questionsPerSubject; $number++) {
                    $question = Question::query()->create([
                        'question_bank_id' => $bank->id,
                        'subject_id' => $subject->id,
                        'topic_id' => null,
                        'created_by' => $creator->id,
                        'question_type' => Question::TYPE_SINGLE_CHOICE,
                        'stem' => "Autoboot {$name} question {$number}: select the synthetic answer for readiness validation.",
                        'explanation' => 'Synthetic content used only to exercise paper loading and answer persistence.',
                        'difficulty' => ['easy', 'medium', 'hard'][($number - 1) % 3],
                        'marks' => 1,
                        'negative_marks' => null,
                        'status' => Question::STATUS_APPROVED,
                        'reviewed_by' => $creator->id,
                        'reviewed_at' => now(),
                    ]);
                    $correctLabel = ['A', 'B', 'C', 'D'][($number - 1) % 4];
                    foreach (['A', 'B', 'C', 'D'] as $index => $label) {
                        QuestionOption::query()->create([
                            'question_id' => $question->id,
                            'label' => $label,
                            'option_text' => "{$name} synthetic option {$label} for question {$number}.",
                            'display_order' => $index + 1,
                            'is_correct' => $label === $correctLabel,
                        ]);
                        $createdOptions++;
                    }
                    $createdQuestions++;
                }
            }

            return compact('subjects', 'banks', 'createdQuestions', 'createdOptions', 'questionsPerSubject');
        });

        return $summary;
    }
}
