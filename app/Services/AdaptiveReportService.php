<?php

namespace App\Services;

use App\Models\AdaptiveAreaBalance;
use App\Models\AdaptiveAttemptState;
use App\Models\AdaptiveDecision;
use App\Models\AdaptiveLevel;
use App\Models\AdaptiveLevelRun;
use App\Models\AdaptiveProgression;
use App\Models\AdaptiveResponse;
use App\Models\AdaptiveSnapshot;
use App\Models\Candidate;
use App\Models\CandidateExamAttempt;
use App\Models\Exam;
use Illuminate\Support\Facades\DB;

class AdaptiveReportService
{
    public const INTERPRETATION = 'Descriptive online practice/diagnostic result. Recovered marks are not a validated ability estimate. Rule-based mastery describes observed evidence only. Do not use for recruitment, certification or automatic academic grading.';

    public function hasProgressions(Exam $exam): bool
    {
        return AdaptiveProgression::where('exam_id', $exam->id)->exists();
    }

    public function report(AdaptiveProgression $progression): array
    {
        // Lifecycle writers lock this same row first. Read one coherent ledger/history.
        return DB::transaction(function () use ($progression): array {
            $progression = AdaptiveProgression::whereKey($progression->id)->lockForUpdate()->firstOrFail();
            $snapshot = AdaptiveSnapshot::findOrFail($progression->snapshot_id);
            $levels = AdaptiveLevel::where('progression_id', $progression->id)->orderBy('number')->get();
            $runs = AdaptiveLevelRun::whereIn('level_id', $levels->pluck('id'))->get()->keyBy('level_id');
            $attempts = CandidateExamAttempt::whereIn('id', $levels->pluck('attempt_id'))->get()->keyBy('id');
            $states = AdaptiveAttemptState::whereIn('attempt_id', $levels->pluck('attempt_id'))->get()->keyBy('attempt_id');
            $decisions = AdaptiveDecision::where('progression_id', $progression->id)->orderBy('step')->get();
            $responses = AdaptiveResponse::whereIn('level_id', $levels->pluck('id'))->get()->keyBy('decision_id');
            $candidate = Candidate::findOrFail($progression->candidate_id);
            $exam = Exam::findOrFail($progression->exam_id);
            $history = $levels->map(function ($level) use ($runs, $attempts, $states, $decisions, $responses): array {
                $run = $runs->get($level->id);
                $path = $decisions->where('level_id', $level->id)->map(function ($decision) use ($responses): array {
                    $response = $responses->get($decision->id);

                    return [
                        'step' => $decision->step, 'question_id' => $decision->question_id,
                        'area_key' => $decision->decision['area_key'], 'topic_id' => $decision->decision['topic_id'],
                        'difficulty' => $decision->decision['difficulty'],
                        'issued_at' => $decision->created_at->toISOString(),
                        'committed_at' => $response?->committed_at?->toISOString(),
                        'correct' => $response?->committed_at ? $response->is_correct : null,
                        'earned_marks' => $this->marks($response?->earned_units ?? 0),
                    ];
                })->values();
                $committed = $path->whereNotNull('committed_at')->count();
                $correct = $path->where('correct', true)->count();
                $coverage = collect($run?->area_plan ?? [])->map(function ($plan, $key) use ($path): array {
                    $items = $path->where('area_key', $key);
                    $confirmed = $items->whereNotNull('committed_at');

                    return [
                        'area_key' => $key, 'planned' => $plan['question_count'],
                        'issued' => $items->count(), 'committed' => $confirmed->count(),
                        'correct' => $confirmed->where('correct', true)->count(),
                        'topics' => collect($plan['topic_ids'])->merge($items->pluck('topic_id')->filter())->unique()->values()
                            ->map(fn ($topic) => ['topic_id' => $topic, 'issued' => $items->where('topic_id', $topic)->count(),
                                'committed' => $confirmed->where('topic_id', $topic)->count()])->all(),
                        'untagged_committed' => $confirmed->whereNull('topic_id')->count(),
                    ];
                })->values()->all();

                return [
                    'number' => $level->number, 'attempt_id' => $level->attempt_id,
                    'status' => $attempts->get($level->attempt_id)?->status,
                    'is_practice' => (bool) $run?->is_practice,
                    'stop_reason' => $states->get($level->attempt_id)?->stop_reason,
                    'started_at' => $level->started_at?->toISOString(), 'submitted_at' => $level->submitted_at?->toISOString(),
                    'incoming_marks' => $this->marks($level->incoming_units),
                    'penalty_percent' => $level->penalty_basis_points / 100,
                    'penalty_marks' => $this->marks($level->penalty_units),
                    'available_marks' => $this->marks($level->available_units),
                    'earned_marks' => $this->marks($level->earned_units),
                    'planned' => array_sum(array_column($run?->area_plan ?? [], 'question_count')),
                    'issued' => $path->count(), 'committed' => $committed, 'correct' => $correct,
                    'raw_accuracy_percent' => $committed ? round(100 * $correct / $committed, 2) : null,
                    'coverage' => $coverage, 'path' => $path->all(),
                ];
            })->all();

            return [
                'progression_id' => $progression->id, 'exam_id' => $exam->id,
                'exam_title' => $exam->title, 'owner_key' => $progression->owner_key,
                'candidate_name' => trim($candidate->first_name.' '.$candidate->last_name),
                'registration_number' => $candidate->candidate_number,
                'interpretation' => self::INTERPRETATION, 'status' => $progression->status,
                'stop_reason' => $progression->stop_reason,
                'candidate_result_released' => $levels->isNotEmpty() && app(AdaptiveLifecycleService::class)->releasedResult($attempts->get($levels->first()->attempt_id), $progression) !== null,
                'snapshot' => ['id' => $snapshot->id, 'version' => $snapshot->version,
                    'engine_version' => $snapshot->engine_version, 'scoring_version' => $snapshot->scoring_version,
                    'fingerprint' => $snapshot->fingerprint],
                'criteria' => ['mastery_threshold_percent' => $snapshot->settings['mastery_threshold_percent'],
                    'min_evidence_per_area' => $snapshot->settings['min_evidence_per_area']],
                'marks' => $this->balances($progression),
                'areas' => AdaptiveAreaBalance::where('progression_id', $progression->id)->orderBy('area_key')->get()
                    ->map(fn ($area) => ['area_key' => $area->area_key, 'mastery' => $area->mastery,
                        'scored_evidence_count' => $area->evidence_count, 'marks' => $this->balances($area)])->all(),
                'levels' => $history,
            ];
        });
    }

    private function balances($row): array
    {
        $result = [];
        foreach (['original', 'earned', 'penalty', 'recoverable', 'closed'] as $kind) {
            $result[$kind] = $this->marks($row->{$kind.'_units'});
        }

        return $result;
    }

    private function marks(int $units): string
    {
        return sprintf('%d.%02d', intdiv($units, 100), $units % 100);
    }

    public function csv(array $report): string
    {
        $stream = fopen('php://temp', 'r+');
        $write = function (array $cells) use ($stream): void {
            // Spreadsheet formula injection also applies to tenant-controlled identifiers.
            fputcsv($stream, array_map(fn ($value) => is_string($value) && preg_match('/^[\s]*[=+@-]/u', $value) ? "'".$value : $value, $cells), ',', '"', '');
        };
        $write(['Interpretation', $report['interpretation']]);
        $write(['Candidate', $report['candidate_name'], 'Registration', $report['registration_number'], 'Progression', $report['progression_id']]);
        $write(['Engine', $report['snapshot']['engine_version'], 'Scoring', $report['snapshot']['scoring_version'], 'Snapshot version', $report['snapshot']['version'], 'Fingerprint', $report['snapshot']['fingerprint']]);
        $write(['Status', $report['status'], 'Stop reason', $report['stop_reason'], 'Candidate result released', $report['candidate_result_released'] ? 'yes' : 'no']);
        $write(['Mastery threshold %', $report['criteria']['mastery_threshold_percent'], 'Minimum scored evidence per area', $report['criteria']['min_evidence_per_area']]);
        $write(['Original', 'Earned', 'Penalty', 'Recoverable', 'Closed']);
        $write(array_values($report['marks']));
        $write(['Area', 'Rule-based mastery', 'Scored evidence', 'Original', 'Earned', 'Penalty', 'Recoverable', 'Closed']);
        foreach ($report['areas'] as $area) {
            $write([$area['area_key'], $area['mastery'], $area['scored_evidence_count'], ...array_values($area['marks'])]);
        }
        $write(['Level', 'Attempt', 'Unscored practice', 'Status', 'Stop reason', 'Planned', 'Issued', 'Committed', 'Correct', 'Raw accuracy %', 'Incoming', 'Penalty %', 'Penalty', 'Available', 'Earned']);
        foreach ($report['levels'] as $level) {
            $write([$level['number'], $level['attempt_id'], $level['is_practice'] ? 'yes' : 'no', $level['status'], $level['stop_reason'], $level['planned'], $level['issued'], $level['committed'], $level['correct'], $level['raw_accuracy_percent'], $level['incoming_marks'], $level['penalty_percent'], $level['penalty_marks'], $level['available_marks'], $level['earned_marks']]);
        }
        $write(['Level', 'Area', 'Planned', 'Issued', 'Committed', 'Correct', 'Topic', 'Topic issued', 'Topic committed', 'Untagged committed']);
        foreach ($report['levels'] as $level) {
            foreach ($level['coverage'] as $area) {
                foreach ($area['topics'] ?: [['topic_id' => null, 'issued' => null, 'committed' => null]] as $topic) {
                    $write([$level['number'], $area['area_key'], $area['planned'], $area['issued'], $area['committed'], $area['correct'], $topic['topic_id'], $topic['issued'], $topic['committed'], $area['untagged_committed']]);
                }
            }
        }
        $write(['Level', 'Step', 'Question', 'Area', 'Topic', 'Difficulty', 'Issued at', 'Committed at', 'Correct', 'Earned marks']);
        foreach ($report['levels'] as $level) {
            foreach ($level['path'] as $item) {
                $write([$level['number'], $item['step'], $item['question_id'], $item['area_key'], $item['topic_id'], $item['difficulty'], $item['issued_at'], $item['committed_at'], $item['correct'] === null ? '' : ($item['correct'] ? 'yes' : 'no'), $item['earned_marks']]);
            }
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv;
    }
}
