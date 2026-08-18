<?php

namespace App\Services;

use App\Models\AdminRegistrationRequest;
use App\Models\Center;
use App\Models\CbtCenter;
use App\Models\Institution;
use App\Models\Organization;
use App\Models\ProfessionalSchool;
use App\Models\School;
use App\Models\SecondarySchool;
use App\Models\User;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Support\Facades\DB;

class AdminRegistrationApprovalService
{
    public function approve(AdminRegistrationRequest $registration, User $reviewer, ?string $notes = null): void
    {
        $approved = false;

        DB::transaction(function () use ($registration, $reviewer, $notes, &$approved): void {
            if ($registration->status !== AdminRegistrationRequest::STATUS_PENDING) {
                return;
            }

            [$entity, $role] = match ($registration->entity_type) {
                AdminRegistrationRequest::TYPE_ORGANIZATION => [
                    Organization::create([
                        'name' => $registration->entity_name,
                        'code' => $registration->entity_code,
                        'contact_person' => $registration->contact_person,
                        'pricing_plan_id' => $registration->pricing_plan_id,
                        'email' => $registration->entity_email,
                        'phone' => $registration->phone,
                        'address' => $registration->address,
                        'status' => Organization::STATUS_ACTIVE,
                    ]),
                    User::ROLE_ORGANIZATION_ADMIN,
                ],
                AdminRegistrationRequest::TYPE_INSTITUTION => [
                    Institution::create($this->institutionPayload($registration)),
                    User::ROLE_INSTITUTION_ADMIN,
                ],
                AdminRegistrationRequest::TYPE_SCHOOL => [
                    School::create($this->schoolOrCenterPayload($registration)),
                    User::ROLE_SCHOOL_ADMIN,
                ],
                AdminRegistrationRequest::TYPE_SECONDARY_SCHOOL => [
                    SecondarySchool::create($this->secondaryOrProfessionalSchoolPayload($registration)),
                    User::ROLE_SECONDARY_SCHOOL_ADMIN,
                ],
                AdminRegistrationRequest::TYPE_PROFESSIONAL_SCHOOL => [
                    ProfessionalSchool::create($this->secondaryOrProfessionalSchoolPayload($registration)),
                    User::ROLE_PROFESSIONAL_SCHOOL_ADMIN,
                ],
                AdminRegistrationRequest::TYPE_CENTER => [
                    Center::create($this->schoolOrCenterPayload($registration)),
                    User::ROLE_CENTER_ADMIN,
                ],
                AdminRegistrationRequest::TYPE_CBT_CENTER => [
                    CbtCenter::create($this->cbtCenterPayload($registration)),
                    User::ROLE_CBT_CENTER_ADMIN,
                ],
            };

            User::create([
                'name' => $registration->admin_name,
                'email' => $registration->admin_email,
                'password' => $registration->password,
                'role' => $role,
                ...$this->userScopeFor($registration->entity_type, $entity->id),
            ]);

            $registration->update([
                'entity_id' => $entity->id,
                'status' => AdminRegistrationRequest::STATUS_APPROVED,
                'review_notes' => $notes,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            $approved = true;
        });

        if ($approved) {
            $registration->refresh();
            $this->notifyRegistration($registration, 'admin_application_approved', [
                'approved_by' => $reviewer->name,
            ]);
        }
    }

    public function reject(AdminRegistrationRequest $registration, User $reviewer, ?string $notes = null): void
    {
        if ($registration->status !== AdminRegistrationRequest::STATUS_PENDING) {
            return;
        }

        $registration->update([
            'status' => AdminRegistrationRequest::STATUS_REJECTED,
            'review_notes' => $notes,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);

        $this->notifyRegistration($registration->refresh(), 'admin_application_rejected', [
            'rejection_reason' => $notes ?: 'Not specified',
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateApprovedAccount(AdminRegistrationRequest $registration, User $reviewer, array $data): void
    {
        DB::transaction(function () use ($registration, $reviewer, $data): void {
            if ($registration->status !== AdminRegistrationRequest::STATUS_APPROVED || ! $registration->entity_id) {
                return;
            }

            $oldType = $registration->entity_type;
            $oldEntityId = $registration->entity_id;
            $admin = $this->adminUserFor($registration);

            $registration->fill($data);

            if ($registration->entity_type === $oldType) {
                $entity = $this->entityQuery($registration->entity_type)->whereKey($oldEntityId)->firstOrFail();
                $entity->update($this->payloadFor($registration));
            } else {
                $this->deactivateEntity($oldType, $oldEntityId, $admin);
                $entity = $this->createEntityFor($registration);
                $registration->entity_id = $entity->id;
            }

            $registration->forceFill([
                'review_notes' => trim(($registration->review_notes ? $registration->review_notes."\n\n" : '').'Account updated by '.$reviewer->name.' on '.now()->toDateTimeString().'.'),
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ])->save();

            if ($admin) {
                $admin->update([
                    'name' => $registration->admin_name,
                    'email' => $registration->admin_email,
                    'role' => $this->roleFor($registration->entity_type),
                    ...$this->userScopeFor($registration->entity_type, $registration->entity_id),
                    'active_context_type' => $this->contextTypeFor($registration->entity_type),
                    'active_context_id' => $registration->entity_id,
                ]);
            }
        });
    }

    public function deactivate(AdminRegistrationRequest $registration, User $reviewer, ?string $notes = null): void
    {
        DB::transaction(function () use ($registration, $reviewer, $notes): void {
            if ($registration->status !== AdminRegistrationRequest::STATUS_APPROVED || ! $registration->entity_id) {
                return;
            }

            match ($registration->entity_type) {
                AdminRegistrationRequest::TYPE_ORGANIZATION => Organization::query()
                    ->whereKey($registration->entity_id)
                    ->update(['status' => Organization::STATUS_INACTIVE]),
                AdminRegistrationRequest::TYPE_INSTITUTION => Institution::query()
                    ->whereKey($registration->entity_id)
                    ->update(['status' => Institution::STATUS_INACTIVE]),
                AdminRegistrationRequest::TYPE_SCHOOL => School::query()
                    ->whereKey($registration->entity_id)
                    ->update(['status' => School::STATUS_INACTIVE]),
                AdminRegistrationRequest::TYPE_SECONDARY_SCHOOL => SecondarySchool::query()
                    ->whereKey($registration->entity_id)
                    ->update(['status' => SecondarySchool::STATUS_INACTIVE]),
                AdminRegistrationRequest::TYPE_PROFESSIONAL_SCHOOL => ProfessionalSchool::query()
                    ->whereKey($registration->entity_id)
                    ->update(['status' => ProfessionalSchool::STATUS_INACTIVE]),
                AdminRegistrationRequest::TYPE_CENTER => Center::query()
                    ->whereKey($registration->entity_id)
                    ->update(['status' => Center::STATUS_INACTIVE]),
                AdminRegistrationRequest::TYPE_CBT_CENTER => CbtCenter::query()
                    ->whereKey($registration->entity_id)
                    ->update(['status' => CbtCenter::STATUS_INACTIVE]),
            };

            $this->deactivateUsersFor($registration->entity_type, $registration->entity_id);

            $registration->update([
                'status' => AdminRegistrationRequest::STATUS_DEACTIVATED,
                'review_notes' => $notes ?: $registration->review_notes,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ]);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function schoolOrCenterPayload(AdminRegistrationRequest $registration): array
    {
        return [
            'name' => $registration->entity_name,
            'code' => $registration->entity_code,
            'location' => $registration->location,
            'capacity' => $registration->capacity,
            'contact_person' => $registration->contact_person,
            'phone' => $registration->phone,
            'email' => $registration->entity_email,
            'status' => School::STATUS_ACTIVE,
            'pricing_plan_id' => $registration->pricing_plan_id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function institutionPayload(AdminRegistrationRequest $registration): array
    {
        return [
            'organization_id' => null,
            'name' => $registration->entity_name,
            'code' => $registration->entity_code,
            'institution_type' => 'university',
            'email' => $registration->entity_email,
            'phone' => $registration->phone,
            'address' => $registration->address ?: $registration->location,
            'description' => $registration->facility_summary ?: $registration->exam_experience,
            'status' => Institution::STATUS_ACTIVE,
        ];
    }

    private function secondaryOrProfessionalSchoolPayload(AdminRegistrationRequest $registration): array
    {
        return [
            'name' => $registration->entity_name,
            'code' => $registration->entity_code,
            'contact_person' => $registration->contact_person,
            'email' => $registration->entity_email,
            'phone' => $registration->phone,
            'address' => $registration->address ?: $registration->location,
            'status' => SecondarySchool::STATUS_ACTIVE,
            'pricing_plan_id' => $registration->pricing_plan_id,
        ];
    }

    private function cbtCenterPayload(AdminRegistrationRequest $registration): array
    {
        return [
            'name' => $registration->entity_name,
            'code' => $registration->entity_code,
            'organization_id' => null,
            'pricing_plan_id' => $registration->pricing_plan_id,
            'location' => $registration->location ?: $registration->address ?: 'Not specified',
            'capacity' => $registration->capacity ?? 0,
            'contact_person' => $registration->contact_person,
            'email' => $registration->entity_email,
            'phone' => $registration->phone,
            'status' => CbtCenter::STATUS_ACTIVE,
        ];
    }

    private function createEntityFor(AdminRegistrationRequest $registration): Organization|Institution|School|SecondarySchool|ProfessionalSchool|Center|CbtCenter
    {
        return match ($registration->entity_type) {
            AdminRegistrationRequest::TYPE_ORGANIZATION => Organization::create($this->payloadFor($registration)),
            AdminRegistrationRequest::TYPE_INSTITUTION => Institution::create($this->payloadFor($registration)),
            AdminRegistrationRequest::TYPE_SCHOOL => School::create($this->payloadFor($registration)),
            AdminRegistrationRequest::TYPE_SECONDARY_SCHOOL => SecondarySchool::create($this->payloadFor($registration)),
            AdminRegistrationRequest::TYPE_PROFESSIONAL_SCHOOL => ProfessionalSchool::create($this->payloadFor($registration)),
            AdminRegistrationRequest::TYPE_CENTER => Center::create($this->payloadFor($registration)),
            AdminRegistrationRequest::TYPE_CBT_CENTER => CbtCenter::create($this->payloadFor($registration)),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadFor(AdminRegistrationRequest $registration): array
    {
        return match ($registration->entity_type) {
            AdminRegistrationRequest::TYPE_ORGANIZATION => [
                'name' => $registration->entity_name,
                'code' => $registration->entity_code,
                'contact_person' => $registration->contact_person,
                'pricing_plan_id' => $registration->pricing_plan_id,
                'email' => $registration->entity_email,
                'phone' => $registration->phone,
                'address' => $registration->address,
                'status' => Organization::STATUS_ACTIVE,
            ],
            AdminRegistrationRequest::TYPE_INSTITUTION => $this->institutionPayload($registration),
            AdminRegistrationRequest::TYPE_SCHOOL,
            AdminRegistrationRequest::TYPE_CENTER => $this->schoolOrCenterPayload($registration),
            AdminRegistrationRequest::TYPE_SECONDARY_SCHOOL,
            AdminRegistrationRequest::TYPE_PROFESSIONAL_SCHOOL => $this->secondaryOrProfessionalSchoolPayload($registration),
            AdminRegistrationRequest::TYPE_CBT_CENTER => $this->cbtCenterPayload($registration),
        };
    }

    private function deactivateEntity(string $type, int|string|null $id, ?User $exceptUser = null): void
    {
        if (! $id) {
            return;
        }

        $this->entityQuery($type)->whereKey($id)->update(['status' => $this->inactiveStatusFor($type)]);
        $this->deactivateChildEntitiesFor($type, $id);
        $this->deactivateUsersFor($type, $id, $exceptUser);
    }

    private function deactivateUsersFor(string $type, int|string|null $id, ?User $exceptUser = null): void
    {
        if (! $id) {
            return;
        }

        User::query()
            ->where(function ($query) use ($type, $id): void {
                $query->where($this->userScopeColumnFor($type), $id);

                if ($type === AdminRegistrationRequest::TYPE_ORGANIZATION) {
                    $institutionIds = Institution::query()->where('organization_id', $id)->pluck('id');
                    $secondarySchoolIds = SecondarySchool::query()->where('organization_id', $id)->pluck('id');
                    $professionalSchoolIds = ProfessionalSchool::query()->where('organization_id', $id)->pluck('id');
                    $cbtCenterIds = CbtCenter::query()->where('organization_id', $id)->pluck('id');

                    $query
                        ->when($institutionIds->isNotEmpty(), fn ($scope) => $scope->orWhereIn('institution_id', $institutionIds))
                        ->when($secondarySchoolIds->isNotEmpty(), fn ($scope) => $scope->orWhereIn('secondary_school_id', $secondarySchoolIds))
                        ->when($professionalSchoolIds->isNotEmpty(), fn ($scope) => $scope->orWhereIn('professional_school_id', $professionalSchoolIds))
                        ->when($cbtCenterIds->isNotEmpty(), fn ($scope) => $scope->orWhereIn('cbt_center_id', $cbtCenterIds));
                }
            })
            ->when($exceptUser, fn ($query) => $query->where('id', '!=', $exceptUser->id))
            ->update([
                'status' => User::STATUS_INACTIVE,
                'active_context_type' => null,
                'active_context_id' => null,
            ]);
    }

    private function deactivateChildEntitiesFor(string $type, int|string|null $id): void
    {
        if ($type !== AdminRegistrationRequest::TYPE_ORGANIZATION || ! $id) {
            return;
        }

        Institution::query()->where('organization_id', $id)->update(['status' => Institution::STATUS_INACTIVE]);
        SecondarySchool::query()->where('organization_id', $id)->update(['status' => SecondarySchool::STATUS_INACTIVE]);
        ProfessionalSchool::query()->where('organization_id', $id)->update(['status' => ProfessionalSchool::STATUS_INACTIVE]);
        CbtCenter::query()->where('organization_id', $id)->update(['status' => CbtCenter::STATUS_INACTIVE]);
    }

    private function entityQuery(string $type)
    {
        return match ($type) {
            AdminRegistrationRequest::TYPE_ORGANIZATION => Organization::query(),
            AdminRegistrationRequest::TYPE_INSTITUTION => Institution::query(),
            AdminRegistrationRequest::TYPE_SCHOOL => School::query(),
            AdminRegistrationRequest::TYPE_SECONDARY_SCHOOL => SecondarySchool::query(),
            AdminRegistrationRequest::TYPE_PROFESSIONAL_SCHOOL => ProfessionalSchool::query(),
            AdminRegistrationRequest::TYPE_CENTER => Center::query(),
            AdminRegistrationRequest::TYPE_CBT_CENTER => CbtCenter::query(),
        };
    }

    private function inactiveStatusFor(string $type): string
    {
        return match ($type) {
            AdminRegistrationRequest::TYPE_ORGANIZATION => Organization::STATUS_INACTIVE,
            AdminRegistrationRequest::TYPE_INSTITUTION => Institution::STATUS_INACTIVE,
            AdminRegistrationRequest::TYPE_SCHOOL => School::STATUS_INACTIVE,
            AdminRegistrationRequest::TYPE_SECONDARY_SCHOOL => SecondarySchool::STATUS_INACTIVE,
            AdminRegistrationRequest::TYPE_PROFESSIONAL_SCHOOL => ProfessionalSchool::STATUS_INACTIVE,
            AdminRegistrationRequest::TYPE_CENTER => Center::STATUS_INACTIVE,
            AdminRegistrationRequest::TYPE_CBT_CENTER => CbtCenter::STATUS_INACTIVE,
        };
    }

    private function userScopeColumnFor(string $type): string
    {
        return match ($type) {
            AdminRegistrationRequest::TYPE_ORGANIZATION => 'organization_id',
            AdminRegistrationRequest::TYPE_INSTITUTION => 'institution_id',
            AdminRegistrationRequest::TYPE_SCHOOL => 'school_id',
            AdminRegistrationRequest::TYPE_SECONDARY_SCHOOL => 'secondary_school_id',
            AdminRegistrationRequest::TYPE_PROFESSIONAL_SCHOOL => 'professional_school_id',
            AdminRegistrationRequest::TYPE_CENTER => 'center_id',
            AdminRegistrationRequest::TYPE_CBT_CENTER => 'cbt_center_id',
        };
    }

    private function roleFor(string $type): string
    {
        return match ($type) {
            AdminRegistrationRequest::TYPE_ORGANIZATION => User::ROLE_ORGANIZATION_ADMIN,
            AdminRegistrationRequest::TYPE_INSTITUTION => User::ROLE_INSTITUTION_ADMIN,
            AdminRegistrationRequest::TYPE_SCHOOL => User::ROLE_SCHOOL_ADMIN,
            AdminRegistrationRequest::TYPE_SECONDARY_SCHOOL => User::ROLE_SECONDARY_SCHOOL_ADMIN,
            AdminRegistrationRequest::TYPE_PROFESSIONAL_SCHOOL => User::ROLE_PROFESSIONAL_SCHOOL_ADMIN,
            AdminRegistrationRequest::TYPE_CENTER => User::ROLE_CENTER_ADMIN,
            AdminRegistrationRequest::TYPE_CBT_CENTER => User::ROLE_CBT_CENTER_ADMIN,
        };
    }

    /**
     * @return array<string, int|string|null>
     */
    private function userScopeFor(string $type, int|string|null $entityId): array
    {
        return [
            'organization_id' => $type === AdminRegistrationRequest::TYPE_ORGANIZATION ? $entityId : null,
            'institution_id' => $type === AdminRegistrationRequest::TYPE_INSTITUTION ? $entityId : null,
            'center_id' => $type === AdminRegistrationRequest::TYPE_CENTER ? $entityId : null,
            'school_id' => $type === AdminRegistrationRequest::TYPE_SCHOOL ? $entityId : null,
            'secondary_school_id' => $type === AdminRegistrationRequest::TYPE_SECONDARY_SCHOOL ? $entityId : null,
            'professional_school_id' => $type === AdminRegistrationRequest::TYPE_PROFESSIONAL_SCHOOL ? $entityId : null,
            'cbt_center_id' => $type === AdminRegistrationRequest::TYPE_CBT_CENTER ? $entityId : null,
        ];
    }

    private function contextTypeFor(string $type): string
    {
        return match ($type) {
            AdminRegistrationRequest::TYPE_ORGANIZATION => 'organization',
            AdminRegistrationRequest::TYPE_INSTITUTION => 'institution',
            AdminRegistrationRequest::TYPE_SCHOOL,
            AdminRegistrationRequest::TYPE_SECONDARY_SCHOOL => 'secondary_school',
            AdminRegistrationRequest::TYPE_CENTER,
            AdminRegistrationRequest::TYPE_CBT_CENTER => 'cbt_center',
            AdminRegistrationRequest::TYPE_PROFESSIONAL_SCHOOL => 'professional_school',
        };
    }

    private function adminUserFor(AdminRegistrationRequest $registration): ?User
    {
        return User::query()
            ->where('role', $this->roleFor($registration->entity_type))
            ->where(function ($query) use ($registration): void {
                match ($registration->entity_type) {
                    AdminRegistrationRequest::TYPE_ORGANIZATION => $query->where('organization_id', $registration->entity_id),
                    AdminRegistrationRequest::TYPE_INSTITUTION => $query->where('institution_id', $registration->entity_id),
                    AdminRegistrationRequest::TYPE_SCHOOL => $query->where('school_id', $registration->entity_id),
                    AdminRegistrationRequest::TYPE_SECONDARY_SCHOOL => $query->where('secondary_school_id', $registration->entity_id),
                    AdminRegistrationRequest::TYPE_PROFESSIONAL_SCHOOL => $query->where('professional_school_id', $registration->entity_id),
                    AdminRegistrationRequest::TYPE_CENTER => $query->where('center_id', $registration->entity_id),
                    AdminRegistrationRequest::TYPE_CBT_CENTER => $query->where('cbt_center_id', $registration->entity_id),
                };
            })
            ->first()
            ?? User::query()->where('email', $registration->admin_email)->first();
    }

    private function notifyRegistration(AdminRegistrationRequest $registration, string $type, array $extraContext = []): void
    {
        try {
            app(NotificationDispatcher::class)->dispatch(
                $type,
                [
                    'name' => $registration->admin_name,
                    'email' => $registration->admin_email,
                    'phone' => $registration->phone,
                ],
                [
                    'admin_name' => $registration->admin_name,
                    'admin_email' => $registration->admin_email,
                    'application_name' => $registration->entity_name,
                    'reference' => 'AX-APP-'.$registration->id,
                    'portal_login_url' => route('login', absolute: true),
                    'password_reset_url' => route('password.request', absolute: true),
                    ...$extraContext,
                ],
            );
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
