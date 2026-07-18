<?php

namespace App\Services;

use App\Models\AdminRegistrationRequest;
use App\Models\Center;
use App\Models\CbtCenter;
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

            $user = User::create([
                'name' => $registration->admin_name,
                'email' => $registration->admin_email,
                'password' => $registration->password,
                'role' => $role,
                'organization_id' => $registration->entity_type === AdminRegistrationRequest::TYPE_ORGANIZATION ? $entity->id : null,
                'center_id' => $registration->entity_type === AdminRegistrationRequest::TYPE_CENTER ? $entity->id : null,
                'school_id' => $registration->entity_type === AdminRegistrationRequest::TYPE_SCHOOL ? $entity->id : null,
                'secondary_school_id' => $registration->entity_type === AdminRegistrationRequest::TYPE_SECONDARY_SCHOOL ? $entity->id : null,
                'professional_school_id' => $registration->entity_type === AdminRegistrationRequest::TYPE_PROFESSIONAL_SCHOOL ? $entity->id : null,
                'cbt_center_id' => $registration->entity_type === AdminRegistrationRequest::TYPE_CBT_CENTER ? $entity->id : null,
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
