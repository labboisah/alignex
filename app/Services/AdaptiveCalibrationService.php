<?php

namespace App\Services;

use App\Models\AdaptiveCalibration;
use App\Models\AdaptiveSnapshot;
use App\Models\Exam;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AdaptiveCalibrationService
{
    public function import(Exam $exam, User $actor, array $payload): AdaptiveCalibration
    {
        $data = Validator::make($payload, [
            'schema_version' => ['required', 'in:calibration-2pl-v1'],
            'snapshot_id' => ['required', 'integer'],
            'source_reference' => ['required', 'string', 'max:1000'],
            'specialist' => ['required', 'string', 'max:255'],
            'sample_size' => ['required', 'integer', 'min:1'],
            'criteria_reference' => ['required', 'string', 'max:1000'],
            'validation_notes' => ['required', 'string', 'max:10000'],
            'policy' => ['required', 'array:min_questions,max_questions,min_per_area,target_sd,cutpoint'],
            'policy.min_questions' => ['required', 'integer', 'between:1,1000'],
            'policy.max_questions' => ['required', 'integer', 'between:1,1000', 'gte:policy.min_questions'],
            'policy.min_per_area' => ['required', 'integer', 'between:1,1000'],
            'policy.target_sd' => ['required', 'numeric', 'gt:0', 'max:2'],
            'policy.cutpoint' => ['present', 'nullable', 'numeric', 'between:-6,6'],
            'items' => ['required', 'array', 'min:1', 'max:10000'],
            'items.*' => ['array:id,content_hash,a,b,exposure_limit'],
            'items.*.id' => ['required', 'integer', 'distinct'],
            'items.*.content_hash' => ['required', 'string', 'size:64'],
            'items.*.a' => ['required', 'numeric', 'gt:0', 'max:4'],
            'items.*.b' => ['required', 'numeric', 'between:-6,6'],
            'items.*.exposure_limit' => ['required', 'integer', 'between:1,1000000'],
            'consequential_approved' => ['prohibited'],
        ])->validate();

        return DB::transaction(function () use ($exam, $actor, $data) {
            $snapshot = AdaptiveSnapshot::where('exam_id', $exam->id)->whereKey($data['snapshot_id'])->lockForUpdate()->firstOrFail();
            if (! $snapshot->ready || $snapshot->owner_key !== app(AdaptiveRolloutService::class)->ownerKey($exam)) {
                throw ValidationException::withMessages(['payload' => 'Use a ready snapshot from this owner.']);
            }
            $pool = $snapshot->items()->get()->keyBy('id');
            if ($pool->count() !== count($data['items'])) {
                throw ValidationException::withMessages(['payload' => 'Provide one calibration for every frozen pool item.']);
            }
            foreach ($data['items'] as $item) {
                if (! $pool->has($item['id']) || ! hash_equals($pool[$item['id']]->content_hash, $item['content_hash'])) {
                    throw ValidationException::withMessages(['payload' => 'Unknown item or stale content hash.']);
                }
            }
            if (count($snapshot->blueprint['areas']) * $data['policy']['min_per_area'] > $data['policy']['max_questions']) {
                throw ValidationException::withMessages(['payload' => 'Coverage cannot fit the maximum length.']);
            }
            $calibration = AdaptiveCalibration::create([
                'snapshot_id' => $snapshot->id, 'owner_key' => $snapshot->owner_key,
                'version' => (int) AdaptiveCalibration::where('snapshot_id', $snapshot->id)->max('version') + 1,
                'payload' => $data, 'fingerprint' => hash('sha256', json_encode($data, JSON_THROW_ON_ERROR)),
                'status' => 'draft', 'created_by' => $actor->id,
            ]);
            $this->audit($exam, $actor, 'imported', $calibration);

            return $calibration;
        });
    }

    public function transition(Exam $exam, User $actor, AdaptiveCalibration $calibration, string $action): void
    {
        DB::transaction(function () use ($exam, $actor, $calibration, $action): void {
            $calibration = AdaptiveCalibration::whereKey($calibration->id)->lockForUpdate()->firstOrFail();
            abort_unless(AdaptiveSnapshot::whereKey($calibration->snapshot_id)->where('exam_id', $exam->id)->exists()
                && $calibration->owner_key === app(AdaptiveRolloutService::class)->ownerKey($exam), 404);
            if ($action === 'review' && $calibration->status === 'draft' && (string) $calibration->created_by !== (string) $actor->id) {
                $calibration->update(['status' => 'reviewed', 'reviewed_by' => $actor->id, 'reviewed_at' => now()]);
            } elseif ($action === 'revoke' && $calibration->status !== 'revoked') {
                $calibration->update(['status' => 'revoked', 'revoked_at' => now()]);
            } else {
                throw ValidationException::withMessages(['action' => 'Review requires a different reviewer and a draft. Revoked versions cannot be reused.']);
            }
            $this->audit($exam, $actor, $action, $calibration);
        });
    }

    private function audit(Exam $exam, User $actor, string $action, AdaptiveCalibration $calibration): void
    {
        $exam->auditLogs()->create(['actor_user_id' => $actor->id, 'actor_type' => 'user',
            'event_type' => 'adaptive_calibration_'.$action, 'description' => 'Experimental calibration '.$action.'.',
            'metadata' => ['calibration_id' => $calibration->id, 'fingerprint' => $calibration->fingerprint],
            'occurred_at' => now()]);
    }
}
