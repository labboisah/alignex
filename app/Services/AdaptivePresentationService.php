<?php

namespace App\Services;

use App\Models\AdaptiveAreaBalance;
use App\Models\AdaptiveLevel;
use App\Models\AdaptiveLevelRun;
use App\Models\AdaptiveProgression;
use App\Models\AdaptiveSnapshot;
use App\Models\CandidateExamAttempt;
use App\Models\Course;
use App\Models\ProfessionalModule;
use App\Models\Subject;
use App\Support\AdaptiveSettings;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class AdaptivePresentationService
{
    public function areaLabel(array $area): string
    {
        return $area['label'] ?? ProfessionalModule::find($area['module_id'] ?? null)?->name
            ?? Course::find($area['course_id'] ?? null)?->name
            ?? Subject::find($area['subject_id'] ?? null)?->name
            ?? 'Area '.substr($area['area_key'], 4);
    }

    private function feedback(CandidateExamAttempt $attempt, AdaptiveLevel $level, AdaptiveProgression $progression, AdaptiveSnapshot $snapshot): ?array
    {
        if (! ($snapshot->blueprint['exam_settings']['adaptive_show_level_feedback'] ?? false)
            || ! in_array($level->status, ['submitted', 'closed'], true) || $attempt->status === 'disqualified'
            || AdaptiveLevel::where('progression_id', $progression->id)->where('number', '>', $level->number)->exists()) {
            return null;
        }
        $labels = collect($snapshot->blueprint['areas'])->mapWithKeys(fn ($area) => [$area['area_key'] => $this->areaLabel($area)]);

        return [
            'levels' => AdaptiveLevel::where('progression_id', $progression->id)->whereIn('status', ['submitted', 'closed'])
                ->where('number', '<=', $level->number)->orderBy('number')->get()->map(fn ($row) => [
                    'number' => (int) $row->number, 'score' => number_format($row->earned_units / 100, 2, '.', ''),
                    'available_marks' => number_format($row->available_units / 100, 2, '.', ''),
                    'penalty_marks' => number_format($row->penalty_units / 100, 2, '.', ''),
                ])->all(),
            'areas' => AdaptiveAreaBalance::where('progression_id', $progression->id)->orderBy('area_key')->get()->map(fn ($area) => [
                'name' => $labels[$area->area_key] ?? 'Area',
                'status' => $area->mastery === 'mastered' ? 'Strength' : ($area->evidence_count > 0 ? 'Needs more practice' : 'Not yet assessed'),
            ])->all(),
            'earned_marks' => number_format($progression->earned_units / 100, 2, '.', ''),
            'original_marks' => number_format($progression->original_units / 100, 2, '.', ''),
            'remaining_marks' => number_format($progression->recoverable_units / 100, 2, '.', ''),
        ];
    }

    public function candidate(CandidateExamAttempt $attempt, AdaptiveLevel $level, AdaptiveProgression $progression, AdaptiveSnapshot $snapshot): array
    {
        $settings = $snapshot->settings;
        $controls = $snapshot->blueprint['exam_settings'];
        $starts = $snapshot->blueprint['starts_at'] ? Carbon::parse($snapshot->blueprint['starts_at']) : null;
        $ends = $snapshot->blueprint['ends_at'] ? Carbon::parse($snapshot->blueprint['ends_at']) : null;
        $run = AdaptiveLevelRun::where('level_id', $level->id)->first();
        $recovery = ['can_start' => false, 'can_practice' => false, 'available_at' => null,
            'penalty_percent' => $settings['recovery_penalty_percent'] ?? 0, 'message' => 'Complete this level before requesting another.'];
        $open = ! $progression->closes_at || $progression->closes_at->isFuture();
        $latest = ! AdaptiveLevel::where('progression_id', $progression->id)->where('number', '>', $level->number)->exists();
        $weak = AdaptiveAreaBalance::where('progression_id', $progression->id)->whereIn('mastery', ['weak', 'untested', 'insufficient_evidence'])->exists();
        if ($level->status === 'submitted' && $latest && ! $run?->is_practice && $settings['progressive_remediation_enabled']) {
            $availableAt = $level->submitted_at->copy()->addMinutes((int) $settings['level_cooldown_minutes']);
            $recovery['available_at'] = $availableAt->toISOString();
            $recovery['message'] = 'Scored recovery is closed.';
            if (! $open) {
                $recovery['message'] = 'The progression access window has closed.';
            } elseif (! $weak) {
                $recovery['message'] = 'No unresolved areas remain.';
            } elseif ($availableAt->isFuture()) {
                $recovery['message'] = 'Wait until the recovery cooldown ends.';
            } elseif ($progression->status === 'active' && $level->number < $settings['max_scored_levels']) {
                $penalty = app(AdaptiveLedgerService::class)->penalty((int) $progression->recoverable_units, AdaptiveSettings::units((string) $settings['recovery_penalty_percent']));
                $recovery['can_start'] = $progression->recoverable_units - $penalty >= AdaptiveSettings::units((string) $settings['min_level_budget']);
                $recovery['message'] = $recovery['can_start'] ? 'You may request a recovery level on unresolved areas. Fresh-question availability is checked when you continue.' : 'There are insufficient recoverable marks for another scored level.';
            } elseif ($progression->status === 'closed' && $settings['allow_unscored_remediation']) {
                $recovery['can_practice'] = true;
                $recovery['message'] = 'You may request one unscored practice level. It does not change your result.';
            }
        }
        if (in_array($level->status, ['submitted', 'closed'], true) && $run?->is_practice) {
            $recovery['message'] = 'Unscored practice is complete. It does not change your result.';
        } elseif ($level->status === 'submitted' && ! $settings['progressive_remediation_enabled']) {
            $recovery['message'] = 'This exam has one level. Recovery levels are not configured.';
        }
        if ($attempt->status === 'disqualified' || $progression->stop_reason === 'disqualified') {
            $recovery = [...$recovery, 'can_start' => false, 'can_practice' => false, 'message' => 'This progression is disqualified. Contact your supervisor.'];
        }
        if ($progression->stop_reason === 'supervisor_end') {
            $recovery = [...$recovery, 'can_start' => false, 'can_practice' => false, 'message' => 'Your supervisor has ended this progression.'];
        }
        $allowed = true;
        try {
            app(AdaptiveRolloutService::class)->ensureDeliveryAllowed($attempt->exam);
        } catch (ValidationException) {
            $allowed = false;
            $recovery = [...$recovery, 'can_start' => false, 'can_practice' => false, 'message' => 'New adaptive levels are not enabled.'];
        }

        return [
            'candidate' => ['full_name' => trim($attempt->candidate->first_name.' '.$attempt->candidate->last_name),
                'registration_number' => $attempt->candidate->candidate_number],
            'exam' => ['title' => $attempt->exam->title, 'exam_code' => $attempt->exam->code,
                'settings' => ['require_fullscreen' => (bool) ($controls['require_fullscreen'] ?? false),
                    'require_webcam' => (bool) ($controls['require_webcam'] ?? false),
                    'monitor_screenshots' => (bool) ($controls['monitor_screenshots'] ?? false)],
                'instructions' => 'This exam helps you identify strengths and improve weaker areas. Confirm each answer to continue. Confirmed answers cannot be changed. Only your current question is available.'],
            'can_start' => $allowed && $level->status === 'prepared' && $attempt->exam->status === 'active'
                && (! $starts || ! $starts->isFuture()) && (! $ends || $ends->isFuture()) && $open,
            'starts_in_seconds' => $starts ? max(0, (int) now()->diffInSeconds($starts, false)) : 0,
            'recovery' => $recovery,
            'learning_progress' => $this->feedback($attempt, $level, $progression, $snapshot),
        ];
    }
}
