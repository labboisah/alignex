<?php

use App\Models\Candidate;
use App\Models\CbtCenter;
use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\Institution;
use App\Models\Organization;
use App\Models\PricingPlan;
use App\Models\ProfessionalSchool;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\QuestionOption;
use App\Models\SecondarySchool;
use App\Models\Subject;
use App\Models\User;
use App\Services\AdaptivePreparationService;
use App\Services\ExamPaperGeneratorService;
use Illuminate\Support\Str;

function browserFixture(array $input): array
{
    $type = $input['owner'] ?? 'organization';
    $traditional = (bool) ($input['traditional'] ?? false);
    $organization = Organization::factory()->create();
    $ownerId = $organization->id;
    $ownerFields = [];
    if ($type !== 'organization') {
        $model = ['institution' => Institution::class, 'professional_school' => ProfessionalSchool::class,
            'cbt_center' => CbtCenter::class, 'secondary_school' => SecondarySchool::class][$type];
        $entity = $model::create(['organization_id' => $organization->id, 'name' => $type,
            'code' => 'B'.Str::random(8), 'status' => 'active', 'contact_person' => 'Browser Admin',
            'email' => 'browser@example.test', 'phone' => '08030000000', 'address' => 'Lagos', 'location' => 'Lagos', 'capacity' => 30]);
        $ownerId = $entity->id;
        $ownerFields[$type.'_id'] = $ownerId;
    }
    $actor = User::factory()->create(['organization_id' => $organization->id, 'role' => User::ROLE_ORGANIZATION_ADMIN,
        ...$ownerFields]);
    if ($input['report_exports'] ?? false) {
        $plan = PricingPlan::create([
            'name' => 'Browser report plan', 'description' => 'Isolated report export test.', 'slug' => 'browser-'.Str::uuid(), 'price' => 0,
            'currency' => 'NGN', 'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['csv_export' => true],
        ]);
        $organization->update(['pricing_plan_id' => $plan->id]);
    }
    $subject = Subject::factory()->create(['organization_id' => $organization->id]);
    $bank = QuestionBank::factory()->create(['organization_id' => $organization->id, 'subject_id' => $subject->id,
        'owner_type' => $type, 'owner_id' => $ownerId, 'status' => 'active', ...$ownerFields]);
    $rules = ['question_bank_ids' => [$bank->id]];
    if ($type === 'institution') {
        $course = Course::create(['institution_id' => $ownerId, 'name' => 'Browser Course', 'code' => 'BRW', 'status' => 'active']);
        $bank->update(['course_id' => $course->id]);
        $rules['course_id'] = $course->id;
    }
    $exam = Exam::factory()->create(['organization_id' => $organization->id, ...$ownerFields,
        'exam_owner_type' => $type, 'exam_owner_id' => $ownerId, 'mode' => $traditional ? 'traditional' : 'adaptive',
        'exam_mode' => $traditional ? 'traditional' : 'adaptive', 'exam_category' => 'assessment', 'delivery_mode' => 'online',
        'status' => 'draft', 'starts_at' => now()->subMinute(), 'ends_at' => now()->addHour(), 'duration_minutes' => 30,
        'title' => 'Browser '.str_replace('_', ' ', $type), 'total_marks' => 6, 'pass_mark' => 3,
        'settings' => ['shuffle_questions' => false, 'shuffle_options' => false, 'bind_device' => false, 'allow_back_navigation' => true,
            'require_webcam' => false, 'require_fullscreen' => false, 'monitor_screenshots' => false, 'max_tab_switches' => 0,
            'progressive_remediation_enabled' => ! $traditional, 'recovery_penalty_percent' => 10, 'max_scored_levels' => 3,
            'min_level_budget' => '0.01', 'mastery_threshold_percent' => 70, 'min_evidence_per_area' => 1,
            'level_duration_minutes' => 30, 'progression_closes_at' => now()->addDays(2)->toDateTimeString(),
            'level_cooldown_minutes' => 0, 'allow_unscored_remediation' => false, ...($input['settings'] ?? [])]]);
    ExamSubject::factory()->create(['exam_id' => $exam->id, 'subject_id' => $subject->id, 'question_bank_id' => $bank->id,
        'question_count' => 3, 'marks_per_question' => 2, 'total_marks' => 6, 'display_order' => 1,
        'selection_rules' => $rules, 'difficulty_distribution' => null]);
    foreach (['easy', 'medium', 'hard'] as $band) {
        for ($i = 1; $i <= 4; $i++) {
            $question = Question::factory()->create(['question_bank_id' => $bank->id, 'subject_id' => $subject->id,
                'topic_id' => null, 'difficulty' => $band, 'status' => 'approved', 'question_type' => 'single_choice',
                'stem' => 'Browser question '.$band.' '.$i, 'marks' => 2, 'image_path' => null]);
            foreach (['A', 'B'] as $index => $label) {
                QuestionOption::factory()->create(['question_id' => $question->id, 'label' => $label,
                    'option_text' => $label === 'A' ? 'First option' : 'Second option', 'is_correct' => $label === 'A', 'display_order' => $index + 1]);
            }
        }
    }
    $candidate = Candidate::factory()->create(['organization_id' => $organization->id, ...$ownerFields]);
    $exam->candidates()->attach($candidate->id, ['status' => 'assigned']);
    if ($traditional) {
        $exam->update(['starts_at' => now()->addMinute()]);
        app(ExamPaperGeneratorService::class)->generate($exam);
        $exam->update(['starts_at' => now()->subMinute()]);
    } else {
        $snapshot = app(AdaptivePreparationService::class)->prepare($exam, $actor->id);
        if (! $snapshot->ready) {
            throw new RuntimeException(json_encode($snapshot->readiness));
        }
    }
    $exam->update(['status' => 'active']);

    return ['exam_id' => $exam->id, 'code' => $exam->code, 'identifier' => $candidate->candidate_number, 'actor_id' => $actor->id, 'actor_email' => $actor->email];
}
