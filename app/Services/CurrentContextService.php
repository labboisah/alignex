<?php

namespace App\Services;

use App\Models\CbtCenter;
use App\Models\Organization;
use App\Models\ProfessionalSchool;
use App\Models\SecondarySchool;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CurrentContextService
{
    public const TYPES = ['organization', 'secondary_school', 'professional_school', 'cbt_center'];

    /**
     * @return array{type: string, id: int, name: string, source?: string}|null
     */
    public function current(User $user): ?array
    {
        $contexts = $this->available($user);

        if ($user->active_context_type && $user->active_context_id) {
            $active = $contexts->first(fn (array $context) => $context['type'] === $user->active_context_type && (string) $context['id'] === (string) $user->active_context_id);

            if ($active) {
                return $active;
            }
        }

        if ($user->isSuperAdmin()) {
            return null;
        }

        $first = $contexts->first();

        if ($first && ($user->active_context_type !== $first['type'] || (string) $user->active_context_id !== (string) $first['id'])) {
            $user->forceFill([
                'active_context_type' => $first['type'],
                'active_context_id' => $first['id'],
            ])->save();
        }

        return $first;
    }

    /**
     * @return Collection<int, array{type: string, id: int, name: string, source?: string}>
     */
    public function available(User $user): Collection
    {
        $contexts = collect();

        if ($user->isSuperAdmin()) {
            return $this->superAdminContexts();
        }

        if ($user->secondary_school_id && $user->secondarySchool) {
            $contexts->push($this->row('secondary_school', $user->secondary_school_id, $user->secondarySchool->name));
        }

        if ($user->school_id && $user->school) {
            $contexts->push($this->row('secondary_school', $user->school_id, $user->school->name, 'legacy_school'));
        }

        if ($user->professional_school_id && $user->professionalSchool) {
            $contexts->push($this->row('professional_school', $user->professional_school_id, $user->professionalSchool->name));
        }

        if ($user->cbt_center_id && $user->cbtCenter) {
            $contexts->push($this->row('cbt_center', $user->cbt_center_id, $user->cbtCenter->name));
        }

        if ($user->center_id && $user->center) {
            $contexts->push($this->row('cbt_center', $user->center_id, $user->center->name, 'legacy_center'));
        }

        if ($user->organization_id && $user->organization) {
            $organizationContexts = collect([$this->row('organization', $user->organization_id, $user->organization->name)])
                ->merge($user->organization->secondarySchools()->orderBy('name')->get(['id', 'name'])->map(fn ($row) => $this->row('secondary_school', $row->id, $row->name)))
                ->merge($user->organization->professionalSchools()->orderBy('name')->get(['id', 'name'])->map(fn ($row) => $this->row('professional_school', $row->id, $row->name)))
                ->merge($user->organization->cbtCenters()->orderBy('name')->get(['id', 'name'])->map(fn ($row) => $this->row('cbt_center', $row->id, $row->name)));

            $contexts = $user->isOrganizationAdmin()
                ? $organizationContexts->merge($contexts)
                : $contexts->merge($organizationContexts);
        }

        return $contexts
            ->unique(fn (array $context) => $context['type'].':'.$context['id'])
            ->values();
    }

    public function switch(User $user, string $type, int|string $id): array
    {
        if (! in_array($type, self::TYPES, true)) {
            throw ValidationException::withMessages(['context_type' => 'Choose a valid context.']);
        }

        $context = $this->available($user)
            ->first(fn (array $row) => $row['type'] === $type && (string) $row['id'] === (string) $id);

        if (! $context) {
            throw ValidationException::withMessages(['context_id' => 'You do not have access to this context.']);
        }

        $user->forceFill([
            'active_context_type' => $type,
            'active_context_id' => (int) $id,
        ])->save();

        return $context;
    }

    private function superAdminContexts(): Collection
    {
        return collect()
            ->merge(Organization::query()->orderBy('name')->limit(30)->get(['id', 'name'])->map(fn ($row) => $this->row('organization', $row->id, $row->name)))
            ->merge(SecondarySchool::query()->orderBy('name')->limit(30)->get(['id', 'name'])->map(fn ($row) => $this->row('secondary_school', $row->id, $row->name)))
            ->merge(ProfessionalSchool::query()->orderBy('name')->limit(30)->get(['id', 'name'])->map(fn ($row) => $this->row('professional_school', $row->id, $row->name)))
            ->merge(CbtCenter::query()->orderBy('name')->limit(30)->get(['id', 'name'])->map(fn ($row) => $this->row('cbt_center', $row->id, $row->name)))
            ->values();
    }

    private function row(string $type, int|string $id, string $name, ?string $source = null): array
    {
        return array_filter([
            'type' => $type,
            'id' => (int) $id,
            'name' => $name,
            'source' => $source,
        ], fn ($value) => $value !== null);
    }
}
