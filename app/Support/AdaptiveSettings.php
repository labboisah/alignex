<?php

namespace App\Support;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AdaptiveSettings
{
    public static function defaults(int $questions): array
    {
        return [
            'adaptive_start_difficulty' => 'medium',
            'adaptive_step_policy' => 'simple',
            'adaptive_min_questions' => max(1, $questions),
            'adaptive_max_questions' => max(1, $questions),
            'progressive_remediation_enabled' => false,
        ];
    }

    public static function rules(bool $progressive): array
    {
        $required = $progressive ? 'required' : 'nullable';

        return [
            'adaptive_start_difficulty' => ['required', Rule::in(['easy', 'medium', 'hard'])],
            'adaptive_step_policy' => ['required', Rule::in(['simple'])],
            'adaptive_min_questions' => ['required', 'integer', 'min:1', 'max:1000'],
            'adaptive_max_questions' => ['required', 'integer', 'min:1', 'max:1000', 'gte:adaptive_min_questions'],
            'progressive_remediation_enabled' => ['required', 'boolean'],
            'recovery_penalty_percent' => [$required, 'numeric', 'between:0,100', 'decimal:0,2'],
            'max_scored_levels' => [$required, 'integer', 'between:1,20'],
            'min_level_budget' => [$required, 'numeric', 'min:0.01', 'max:1000000', 'decimal:0,2'],
            'mastery_threshold_percent' => [$required, 'numeric', 'between:1,100', 'decimal:0,2'],
            'min_evidence_per_area' => [$required, 'integer', 'between:1,1000'],
            'level_duration_minutes' => [$required, 'integer', 'between:1,10080'],
            'progression_closes_at' => [$required, 'date'],
            'level_cooldown_minutes' => [$required, 'integer', 'between:0,10080'],
            'allow_unscored_remediation' => [$required, 'boolean'],
        ];
    }

    public static function validate(array $settings, int $questions, mixed $startsAt = null): array
    {
        $settings = array_replace(self::defaults($questions), $settings);
        $progressive = filter_var($settings['progressive_remediation_enabled'], FILTER_VALIDATE_BOOLEAN);
        $validator = Validator::make($settings, self::rules($progressive));
        $validator->after(function ($validator) use ($settings, $questions, $progressive, $startsAt): void {
            if ($validator->errors()->any()) {
                return;
            }
            if ((int) $settings['adaptive_max_questions'] < $questions) {
                $validator->errors()->add('adaptive_max_questions', 'The question limit must cover all paper-row quotas.');
            }
            if ($progressive && ((int) $settings['adaptive_min_questions'] !== $questions || (int) $settings['adaptive_max_questions'] !== $questions)) {
                $validator->errors()->add('adaptive_max_questions', 'Progressive levels use the fixed sum of paper-row question quotas.');
            }
            if ($progressive && ! empty($settings['negative_marking'])) {
                $validator->errors()->add('negative_marking', 'Progressive recovery cannot be combined with negative marking.');
            }
            if ($progressive && $startsAt && ! $validator->errors()->has('progression_closes_at')
                && strtotime((string) $settings['progression_closes_at']) <= strtotime((string) $startsAt)) {
                $validator->errors()->add('progression_closes_at', 'The progression must close after the exam starts.');
            }
        });

        return $validator->validate();
    }

    public static function units(string|int $value): int
    {
        if (! preg_match('/^([0-9]+)(?:\.([0-9]{1,2}))?$/', (string) $value, $parts)) {
            throw new \InvalidArgumentException('Marks must be a non-negative decimal with at most two decimal places.');
        }

        return (int) $parts[1] * 100 + (int) str_pad($parts[2] ?? '', 2, '0');
    }
}
