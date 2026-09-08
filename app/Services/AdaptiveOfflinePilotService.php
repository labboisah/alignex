<?php

namespace App\Services;

use App\Models\AdaptiveOfflineLease;
use App\Models\AdaptiveOfflinePackage;
use App\Models\AdaptiveSnapshot;
use App\Models\CandidateExamAttempt;
use App\Models\Exam;
use App\Models\OfflineServerActivation;
use App\Models\User;
use App\Support\AdaptiveSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

class AdaptiveOfflinePilotService
{
    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['pilot' => $message]);
    }

    public function create(Exam $exam, User $actor, OfflineServerActivation $activation, array $candidateIds): AdaptiveOfflinePackage
    {
        return DB::transaction(function () use ($exam, $actor, $activation, $candidateIds) {
            $exam = Exam::whereKey($exam->id)->lockForUpdate()->firstOrFail();
            if (config('adaptive.pilot_emergency_stop', false) || ! app(AdaptivePilotService::class)->control($exam)?->offline_enabled) {
                $this->reject('Approve this exam for the offline diagnostic pilot first.');
            }
            if (! in_array($exam->status, ['active', 'scheduled'], true) || $activation->status !== 'activated' || ! $activation->expires_at?->isFuture()) {
                $this->reject('An open exam and active center license are required.');
            }
            // Assignment never follows a center hosting relationship across owners implicitly.
            if ($activation->organization_id && $activation->organization_id !== $exam->organization_id) {
                $this->reject('Center activation belongs to another organization.');
            }
            $snapshot = AdaptiveSnapshot::where('exam_id', $exam->id)->latest('version')->first();
            if (! $snapshot?->ready || $snapshot->owner_key !== app(AdaptiveRolloutService::class)->ownerKey($exam)
                || $snapshot->fingerprint !== app(AdaptivePreparationService::class)->inspect($exam)['fingerprint']) {
                $this->reject('Prepare a current ready snapshot.');
            }
            if ($activation->activationCode?->status !== 'active' || $activation->activationCode?->license_expires_at?->isPast() || $activation->activationCode?->expires_at?->isPast()) {
                $this->reject('The center activation code must have a valid license.');
            }
            $settings = $snapshot->settings;
            $bp = $snapshot->blueprint;
            foreach (['require_webcam', 'require_fullscreen', 'payment_required', 'negative_marking'] as $flag) {
                if ($bp['exam_settings'][$flag] ?? false) {
                    $this->reject('The offline diagnostic pilot does not yet support '.$flag.'. Use a separately configured supervised pilot exam.');
                }
            }
            if ($settings['allow_unscored_remediation'] ?? false) {
                $this->reject('Offline pilot currently supports scored recovery only.');
            }
            $candidates = $exam->candidates()->whereIn('candidates.id', $candidateIds)->where('candidates.status', 'active')->get();
            if ($candidates->count() !== count($candidateIds) || $candidates->isEmpty()) {
                $this->reject('Select assigned candidates.');
            }
            $attempts = CandidateExamAttempt::where('exam_id', $exam->id)->whereIn('candidate_id', $candidateIds)->lockForUpdate()->get();
            if ($attempts->contains(fn ($a) => $a->started_at || $a->status !== 'not_started' || $a->papers()->exists())) {
                $this->reject('Offline assignment requires unstarted candidates without traditional papers.');
            }
            if (AdaptiveOfflineLease::where('exam_id', $exam->id)->whereIn('candidate_id', $candidateIds)->exists()) {
                $this->reject('A candidate is already reserved for offline delivery.');
            }
            $id = (string) Str::uuid();
            $ends = $bp['ends_at'] ? strtotime($bp['ends_at']) * 1000 : null;
            $closes = ($settings['progressive_remediation_enabled'] ?? false) ? strtotime($settings['progression_closes_at']) * 1000 : $ends;
            $closes = $closes ? min($closes, $activation->expires_at->getTimestampMs()) : null;
            if (! $closes || $closes <= now()->getTimestampMs()) {
                $this->reject('Set a future closing window.');
            }
            $config = [
                'version' => 'diagnostic-pilot-v1', 'starts_at' => $bp['starts_at'] ? strtotime($bp['starts_at']) * 1000 : now()->getTimestampMs(),
                'closes_at' => $closes, 'initial_ends_at' => $ends, 'duration_ms' => (int) (($settings['progressive_remediation_enabled'] ?? false) ? $settings['level_duration_minutes'] : $bp['duration_minutes']) * 60000,
                'max_tab_switches' => (int) ($bp['exam_settings']['max_tab_switches'] ?? 0),
                'start_difficulty' => $settings['adaptive_start_difficulty'], 'max_levels' => ($settings['progressive_remediation_enabled'] ?? false) ? (int) $settings['max_scored_levels'] : 1,
                'penalty_bps' => AdaptiveSettings::units((string) ($settings['recovery_penalty_percent'] ?? 0)),
                'min_budget' => AdaptiveSettings::units((string) ($settings['min_level_budget'] ?? '0.01')),
                'mastery_bps' => AdaptiveSettings::units((string) ($settings['mastery_threshold_percent'] ?? 70)),
                'min_evidence' => (int) ($settings['min_evidence_per_area'] ?? 1), 'cooldown_ms' => (int) ($settings['level_cooldown_minutes'] ?? 0) * 60000,
                'areas' => array_map(fn ($a) => ['key' => $a['area_key'], 'count' => $a['question_count'], 'budget' => $a['budget_units'], 'topics' => $a['topic_ids']], $bp['areas']),
                'items' => $snapshot->items()->orderBy('id')->get()->map(fn ($i) => [
                    'id' => (string) $i->id, 'area' => $i->area_key, 'difficulty' => $i->difficulty, 'topic' => $i->content['topic_id'],
                    'type' => $i->content['question_type'], 'stem' => $i->content['stem'],
                    'options' => array_map(fn ($o) => ['id' => $o['id'], 'label' => $o['label'], 'text' => $o['option_text'], 'correct' => $o['is_correct']], $i->content['options']),
                ])->all(),
            ];
            $extra = max(0, (int) $settings['adaptive_min_questions'] - array_sum(array_column($config['areas'], 'count')));
            $areaCount = count($config['areas']);
            foreach ($config['areas'] as $index => &$area) {
                $area['count'] += intdiv($extra, $areaCount) + ($index < $extra % $areaCount ? 1 : 0);
            }
            unset($area);
            $rows = $candidates->map(fn ($c) => ['id' => $c->id, 'lease_id' => (string) Str::uuid(), 'registration' => $c->candidate_number,
                'name' => trim($c->first_name.' '.$c->last_name), 'access_code' => Str::upper(Str::random(12))])->all();
            // Validate the portable contract before creating any delivery reservation.
            $this->replay($config, $rows[0]['id'], []);
            $payload = ['contract' => 'alignex.diagnostic-offline.v1', 'id' => $id, 'exam_id' => $exam->id, 'title' => $exam->title,
                'context' => $exam->effectiveOwnerType(), 'owner_key' => $snapshot->owner_key, 'device_id' => $activation->device_id,
                'snapshot_id' => $snapshot->id, 'snapshot_fingerprint' => $snapshot->fingerprint, 'issued_at' => now()->getTimestampMs(),
                'config' => $config, 'candidates' => $rows];
            $package = AdaptiveOfflinePackage::create(['id' => $id, 'exam_id' => $exam->id, 'snapshot_id' => $snapshot->id, 'activation_id' => $activation->id,
                'owner_key' => $snapshot->owner_key, 'payload' => $payload, 'payload_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)), 'created_by' => $actor->id]);
            foreach ($rows as $row) {
                AdaptiveOfflineLease::create(['id' => $row['lease_id'], 'package_id' => $id, 'exam_id' => $exam->id, 'candidate_id' => $row['id']]);
            }
            $exam->auditLogs()->create(['actor_user_id' => $actor->id, 'actor_type' => 'user', 'event_type' => 'adaptive_offline_reserved',
                'description' => 'Offline diagnostic candidates reserved.', 'metadata' => ['package_id' => $id, 'candidate_count' => count($rows)], 'occurred_at' => now()]);

            return $package;
        }, 3);
    }

    public function envelope(AdaptiveOfflinePackage $package, OfflineServerActivation $activation): array
    {
        abort_unless($package->activation_id === $activation->id, 403);
        $body = base64_encode(json_encode($package->payload, JSON_THROW_ON_ERROR));

        return ['contract' => 'alignex.diagnostic-offline.v1', 'body' => $body, 'mac' => hash_hmac('sha256', $body, $activation->license_key)];
    }

    public function replay(array $config, string $candidate, array $commands): array
    {
        $process = new Process([config('adaptive.pilot_node', 'node'), base_path('services/adaptive-pilot/replay.cjs')]);
        $process->setInput(json_encode(compact('config', 'candidate', 'commands'), JSON_THROW_ON_ERROR))->setTimeout(30)->run();
        if (! $process->isSuccessful()) {
            $this->reject('Diagnostic replay failed. Check the Node runtime, engine contract and transcript.');
        }

        return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    }

    public function sync(AdaptiveOfflineLease $lease, OfflineServerActivation $activation, array $data): array
    {
        $package = AdaptiveOfflinePackage::findOrFail($lease->package_id);
        abort_unless($package->activation_id === $activation->id, 403);
        if (app(AdaptiveRolloutService::class)->ownerKey(Exam::findOrFail($package->exam_id)) !== $package->owner_key) {
            $this->reject('The frozen package owner no longer matches this exam. Preserve the transcript for review.');
        }
        $hash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
        if ($lease->status === 'verified') {
            if ($lease->transcript_hash !== $hash) {
                $this->reject('A different transcript is already accepted. Manual review is required.');
            }

            return ['status' => 'verified', 'result' => $lease->result];
        }
        $payload = $package->payload;
        foreach ($data['commands'] as $command) {
            if (($command['at'] ?? PHP_INT_MAX) > now()->addMinutes(2)->getTimestampMs()
                || ($command['at'] ?? 0) < $payload['issued_at']) {
                $this->reject('Transcript contains an invalid event time.');
            }
        }
        try {
            $result = $this->replay($payload['config'], $lease->candidate_id, $data['commands']);
            $valid = $result['status'] === 'closed' && hash_equals($result['state_hash'], $data['state_hash']);
        } catch (ValidationException $exception) {
            $result = null;
            $valid = false;
        }

        return DB::transaction(function () use ($lease, $hash, $data, $result, $valid) {
            $lease = AdaptiveOfflineLease::whereKey($lease->id)->lockForUpdate()->firstOrFail();
            if ($lease->status === 'verified') {
                if ($lease->transcript_hash !== $hash) {
                    $this->reject('Conflicting concurrent transcript.');
                }

                return ['status' => 'verified', 'result' => $lease->result];
            }
            $lease->update(['status' => $valid ? 'verified' : 'quarantined', 'transcript_hash' => $hash, 'transcript' => $data,
                'result' => $valid ? $result : null, 'error_code' => $valid ? null : 'replay_mismatch']);

            Exam::findOrFail($lease->exam_id)->auditLogs()->create(['actor_type' => 'offline_center', 'event_type' => 'adaptive_offline_reconciled',
                'description' => 'Offline diagnostic transcript reviewed by replay.', 'metadata' => ['lease_id' => $lease->id, 'status' => $lease->status], 'occurred_at' => now()]);

            return ['status' => $lease->status, 'result' => $lease->result];
        });
    }
}
