<?php

namespace App\Services;

use App\Models\AdaptivePoolItem;
use App\Models\AdaptiveSnapshot;
use App\Models\Exam;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Support\AdaptiveSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdaptivePreparationService
{
    public function inspect(Exam $exam): array
    {
        if ($exam->effectiveMode() !== Exam::MODE_ADAPTIVE || $exam->mode !== $exam->effectiveMode()) {
            throw ValidationException::withMessages(['exam' => 'Choose consistent adaptive mode fields before preparing a snapshot.']);
        }
        $exam->load('examSubjects');
        $quota = (int) $exam->examSubjects->sum('question_count');
        $settings = AdaptiveSettings::validate($exam->settings ?? [], $quota, $exam->starts_at?->toDateTimeString());
        $progressive = (bool) $settings['progressive_remediation_enabled'];
        $levels = $progressive ? (int) $settings['max_scored_levels'] : 1;
        $warnings = [];
        $areas = [];
        $items = [];
        $seen = [];
        if ($quota < 1 || $exam->examSubjects->isEmpty()) {
            $warnings[] = 'Add at least one paper row with a positive question count.';
        }
        foreach ($exam->examSubjects->sortBy('display_order')->values() as $index => $row) {
            $area = 'row:'.($index + 1);
            $bankIds = collect(data_get($row->selection_rules, 'question_bank_ids', []))
                ->whenEmpty(fn ($ids) => collect([$row->question_bank_id ?? $exam->question_bank_id]))
                ->filter()->map(fn ($id) => (string) $id)->unique()->sort()->values();
            $topics = collect(data_get($row->selection_rules, 'topic_ids', []))->map(fn ($id) => (string) $id)->unique()->sort()->values();
            $banks = QuestionBank::query()->whereIn('id', $bankIds)->get();
            $authorized = $banks->count() === $bankIds->count() && $bankIds->isNotEmpty();
            foreach ($banks as $bank) {
                $authorized = $authorized && $this->bankEligible($exam, $row, $bank);
            }
            $questions = collect();
            if (! $authorized) {
                $warnings[] = $area.': one or more banks are missing, inactive, or outside this owner/subject/course.';
            } else {
                $questions = Question::query()->whereIn('question_bank_id', $bankIds)
                    ->where('status', Question::STATUS_APPROVED)
                    ->whereIn('difficulty', ['easy', 'medium', 'hard'])
                    ->whereIn('question_type', [Question::TYPE_SINGLE_CHOICE, Question::TYPE_MULTIPLE_CHOICE, Question::TYPE_TRUE_FALSE])
                    ->where('marks', '>', 0)
                    ->when($exam->effectiveOwnerType() !== Exam::OWNER_INSTITUTION, fn ($query) => $query->where('subject_id', $row->subject_id))
                    ->when($topics->isNotEmpty(), fn ($query) => $query->whereIn('topic_id', $topics))
                    ->with('options')->orderBy('id')->get()->filter(function (Question $question): bool {
                        $correct = $question->options->where('is_correct', true)->count();

                        return $question->options->count() >= 2 && ($question->question_type === Question::TYPE_MULTIPLE_CHOICE ? $correct >= 1 : $correct === 1);
                    })->values();
            }
            $required = (int) $row->question_count * $levels;
            if ($questions->count() < $required) {
                $warnings[] = $area.": needs {$required} fresh approved questions for {$levels} level(s); found ".$questions->count().'.';
            }
            $difficulty = [];
            foreach (['easy', 'medium', 'hard'] as $band) {
                $difficulty[$band] = $questions->where('difficulty', $band)->count();
                if ($difficulty[$band] < $levels) {
                    $warnings[] = $area.": needs at least {$levels} fresh {$band} question(s).";
                }
            }
            if ($progressive && (int) $row->question_count < (int) $settings['min_evidence_per_area']) {
                $warnings[] = $area.': question quota is smaller than the mastery evidence minimum.';
            }
            foreach ($topics as $topic) {
                if ($questions->where('topic_id', $topic)->count() < $levels) {
                    $warnings[] = $area.': insufficient fresh questions for selected topic '.$topic.'.';
                }
            }
            if ($topics->count() > (int) $row->question_count) {
                $warnings[] = $area.': question quota cannot cover every selected topic.';
            }
            $areas[] = [
                'area_key' => $area, 'subject_id' => $row->subject_id,
                'course_id' => data_get($row->selection_rules, 'course_id', $exam->course_id),
                'module_id' => data_get($row->selection_rules, 'module_id', $exam->module_id),
                'topic_ids' => $topics->all(), 'bank_ids' => $bankIds->all(),
                'question_count' => (int) $row->question_count, 'marks_per_question' => (string) $row->marks_per_question,
                'budget_units' => AdaptiveSettings::units((string) $row->total_marks),
                'available' => $questions->count(), 'required' => $required, 'difficulty' => $difficulty,
            ];
            foreach ($questions as $question) {
                if (isset($seen[$question->id])) {
                    $warnings[] = $area.': questions overlap another paper row; use disjoint pools.';

                    continue;
                }
                $seen[$question->id] = true;
                $content = [
                    'question_id' => $question->id, 'subject_id' => $question->subject_id, 'topic_id' => $question->topic_id,
                    'question_type' => $question->question_type, 'stem' => $question->stem, 'image_path' => $question->image_path,
                    'difficulty' => $question->difficulty,
                    'options' => $question->options->sortBy('id')->map(fn ($option) => [
                        'id' => $option->id, 'label' => $option->label, 'option_text' => $option->option_text,
                        'is_correct' => (bool) $option->is_correct, 'display_order' => $option->display_order,
                    ])->values()->all(),
                ];
                $items[] = ['question_id' => $question->id, 'area_key' => $area, 'difficulty' => $question->difficulty,
                    'content_hash' => hash('sha256', json_encode($content, JSON_THROW_ON_ERROR)), 'content' => $content];
            }
        }
        if (count($items) < (int) $settings['adaptive_max_questions'] * $levels) {
            $warnings[] = 'The fresh pool cannot cover the configured maximum question count across all levels.';
        }
        $total = AdaptiveSettings::units((string) $exam->total_marks);
        if ($total < 1 || array_sum(array_column($areas, 'budget_units')) !== $total) {
            $warnings[] = 'The positive original mark total must equal the sum of the paper-row budgets.';
        }
        if ($progressive && AdaptiveSettings::units((string) $settings['min_level_budget']) > $total) {
            $warnings[] = 'The minimum level budget exceeds the original total.';
        }
        $blueprint = [
            'owner_key' => app(AdaptiveRolloutService::class)->ownerKey($exam), 'category' => $exam->exam_category,
            'original_units' => $total, 'pass_mark' => (string) $exam->pass_mark, 'duration_minutes' => $exam->duration_minutes,
            'starts_at' => $exam->starts_at?->toISOString(), 'ends_at' => $exam->ends_at?->toISOString(),
            'delivery_mode' => $exam->delivery_mode, 'exam_settings' => $exam->settings ?? [], 'areas' => $areas,
        ];
        $readiness = ['ready' => $warnings === [], 'warnings' => array_values(array_unique($warnings)), 'areas' => $areas, 'item_count' => count($items)];

        return [
            'settings' => $settings, 'blueprint' => $blueprint, 'readiness' => $readiness, 'items' => $items,
            'fingerprint' => hash('sha256', json_encode([$settings, $blueprint, array_column($items, 'content_hash')], JSON_THROW_ON_ERROR)),
        ];
    }

    public function prepare(Exam $exam, int $actorId): AdaptiveSnapshot
    {
        return DB::transaction(function () use ($exam, $actorId): AdaptiveSnapshot {
            $exam = Exam::whereKey($exam->id)->lockForUpdate()->firstOrFail();
            app(AdaptiveRolloutService::class)->ensureSaveAllowed($exam, $exam);
            $data = $this->inspect($exam);
            $snapshot = AdaptiveSnapshot::create([
                'exam_id' => $exam->id, 'version' => ((int) AdaptiveSnapshot::where('exam_id', $exam->id)->max('version')) + 1,
                'owner_key' => $data['blueprint']['owner_key'], 'engine_version' => 'simple-v1', 'scoring_version' => 'recovery-v1',
                'settings' => $data['settings'], 'blueprint' => $data['blueprint'], 'readiness' => $data['readiness'],
                'ready' => $data['readiness']['ready'], 'fingerprint' => $data['fingerprint'], 'created_by' => $actorId,
            ]);
            foreach ($data['items'] as $item) {
                AdaptivePoolItem::create(['snapshot_id' => $snapshot->id, ...$item]);
            }
            $exam->auditLogs()->create([
                'actor_user_id' => $actorId, 'actor_type' => 'user', 'event_type' => 'adaptive_snapshot_prepared',
                'description' => 'Adaptive configuration and pool snapshot prepared.',
                'metadata' => ['snapshot_id' => $snapshot->id, 'version' => $snapshot->version, 'ready' => $snapshot->ready],
                'occurred_at' => now(),
            ]);

            return $snapshot;
        });
    }

    private function bankEligible(Exam $exam, $row, QuestionBank $bank): bool
    {
        $owner = $exam->effectiveOwnerType();
        $column = match ($owner) {
            Exam::OWNER_ORGANIZATION => 'organization_id',
            Exam::OWNER_INSTITUTION => 'institution_id',
            Exam::OWNER_PROFESSIONAL_SCHOOL => 'professional_school_id',
            Exam::OWNER_CBT_CENTER => 'cbt_center_id',
            default => null,
        };
        if (! $column || ! $exam->$column || (string) $bank->$column !== (string) $exam->$column || $bank->status !== QuestionBank::STATUS_ACTIVE) {
            return false;
        }
        if (app(AdaptiveRolloutService::class)->ownerKey($exam) !== $owner.':'.$exam->$column) {
            return false;
        }
        if ($bank->owner_type && ($bank->owner_type !== $owner || (string) $bank->owner_id !== (string) $exam->$column)) {
            return false;
        }
        if ($owner === Exam::OWNER_ORGANIZATION && ($bank->institution_id || $bank->secondary_school_id || $bank->professional_school_id || $bank->cbt_center_id || $bank->school_id || $bank->center_id)) {
            return false;
        }
        if ($owner === Exam::OWNER_INSTITUTION) {
            $courseId = data_get($row->selection_rules, 'course_id', $exam->course_id);

            return $courseId && (string) $bank->course_id === (string) $courseId;
        }
        if ((string) $bank->subject_id !== (string) $row->subject_id) {
            return false;
        }
        foreach (['course_id', 'module_id'] as $field) {
            $required = data_get($row->selection_rules, $field);
            if ($required && (string) $bank->$field !== (string) $required) {
                return false;
            }
        }

        return true;
    }
}
