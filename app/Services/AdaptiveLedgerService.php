<?php

namespace App\Services;

use App\Models\AdaptiveAreaBalance;
use App\Models\AdaptiveLevel;
use App\Models\AdaptiveMarkEntry;
use App\Models\AdaptiveProgression;
use Illuminate\Support\Facades\DB;

// Called only inside the lifecycle's progression lock and transaction.
class AdaptiveLedgerService
{
    public function post(AdaptiveProgression $progression, AdaptiveLevel $level, string $areaKey, string $kind, int $units, string $key): void
    {
        if (! DB::transactionLevel() || $level->progression_id != $progression->id
            || ! in_array($kind, ['earned', 'penalty', 'closed'], true) || $units < 0) {
            throw new \LogicException('Invalid adaptive posting context.');
        }
        $existing = AdaptiveMarkEntry::where('progression_id', $progression->id)->where('idempotency_key', $key)->first();
        if ($existing) {
            if ($existing->level_id != $level->id || $existing->area_key !== $areaKey || $existing->kind !== $kind || (int) $existing->units !== $units) {
                throw new \LogicException('Conflicting ledger retry.');
            }

            return;
        }
        $area = AdaptiveAreaBalance::where('progression_id', $progression->id)->where('area_key', $areaKey)->lockForUpdate()->firstOrFail();
        $progression->refresh();
        if ($units > $area->recoverable_units || $units > $progression->recoverable_units) {
            throw new \LogicException('A posting cannot spend another area or exceed remaining marks.');
        }
        $field = $kind.'_units';
        $area->update([$field => $area->$field + $units, 'recoverable_units' => $area->recoverable_units - $units]);
        $progression->update([$field => $progression->$field + $units, 'recoverable_units' => $progression->recoverable_units - $units]);
        AdaptiveMarkEntry::create([
            'progression_id' => $progression->id, 'level_id' => $level->id, 'area_key' => $areaKey,
            'kind' => $kind, 'units' => $units, 'idempotency_key' => $key, 'metadata' => [],
        ]);
        $this->reconcile($progression);
    }

    public function reconcile(AdaptiveProgression $progression): void
    {
        $progression->refresh();
        $areas = AdaptiveAreaBalance::where('progression_id', $progression->id)->get();
        foreach ($areas->concat([$progression]) as $balance) {
            if ((int) $balance->original_units !== (int) ($balance->earned_units + $balance->penalty_units + $balance->recoverable_units + $balance->closed_units)) {
                throw new \LogicException('Adaptive mark conservation failed.');
            }
        }
        foreach (['original_units', 'earned_units', 'penalty_units', 'recoverable_units', 'closed_units'] as $field) {
            if ((int) $areas->sum($field) !== (int) $progression->$field) {
                throw new \LogicException('Adaptive area totals do not reconcile.');
            }
        }
    }

    public function penalty(int $remaining, int $basisPoints): int
    {
        if ($remaining < 0 || $basisPoints < 0 || $basisPoints > 10000) {
            throw new \InvalidArgumentException('Invalid percentage inputs.');
        }

        return intdiv($remaining * $basisPoints + 5000, 10000);
    }

    public function allocate(int $total, array $weights): array
    {
        $sum = array_sum($weights);
        if ($total < 0 || $sum <= 0 || $total > $sum) {
            throw new \LogicException('Invalid allocation.');
        }
        ksort($weights);
        $shares = $remainders = [];
        foreach ($weights as $key => $weight) {
            $shares[$key] = intdiv($total * $weight, $sum);
            $remainders[$key] = ($total * $weight) % $sum;
        }
        arsort($remainders, SORT_NUMERIC);
        $left = $total - array_sum($shares);
        foreach ($remainders as $key => $_) {
            if ($left-- <= 0) {
                break;
            }
            $shares[$key]++;
        }

        return $shares;
    }
}
