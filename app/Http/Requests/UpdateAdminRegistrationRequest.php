<?php

namespace App\Http\Requests;

use App\Models\AdminRegistrationRequest;
use App\Models\Center;
use App\Models\CbtCenter;
use App\Models\Organization;
use App\Models\ProfessionalSchool;
use App\Models\School;
use App\Models\SecondarySchool;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdminRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() === true;
    }

    public function rules(): array
    {
        $registration = $this->route('adminRegistration');

        return [
            'entity_type' => ['required', Rule::in([
                AdminRegistrationRequest::TYPE_ORGANIZATION,
                AdminRegistrationRequest::TYPE_SCHOOL,
                AdminRegistrationRequest::TYPE_SECONDARY_SCHOOL,
                AdminRegistrationRequest::TYPE_PROFESSIONAL_SCHOOL,
                AdminRegistrationRequest::TYPE_CENTER,
                AdminRegistrationRequest::TYPE_CBT_CENTER,
            ])],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => [
                'required',
                'email',
                'max:255',
                Rule::unique(User::class, 'email'),
                Rule::unique(AdminRegistrationRequest::class, 'admin_email')->ignore($registration),
            ],
            'entity_name' => ['required', 'string', 'max:255'],
            'entity_code' => [
                'required',
                'string',
                'max:50',
                'alpha_dash',
                Rule::unique(AdminRegistrationRequest::class, 'entity_code')
                    ->where('entity_type', $this->input('entity_type'))
                    ->ignore($registration),
                $this->entityCodeRule(),
            ],
            'location' => ['nullable', 'required_unless:entity_type,organization', 'string', 'max:1000'],
            'capacity' => ['nullable', 'required_unless:entity_type,organization', 'integer', 'min:1', 'max:100000'],
            'contact_person' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'entity_email' => [
                'required',
                'email',
                'max:255',
                Rule::unique(AdminRegistrationRequest::class, 'entity_email')->ignore($registration),
                $this->entityEmailRule(),
            ],
            'address' => ['nullable', 'required_if:entity_type,organization', 'string', 'max:1000'],
            'legal_registration_number' => ['nullable', 'string', 'max:100'],
            'website' => ['nullable', 'url', 'max:255'],
            'years_in_operation' => ['nullable', 'integer', 'min:0', 'max:200'],
            'operating_scope' => ['nullable', 'string', 'max:255'],
            'accreditation_body' => ['nullable', 'string', 'max:255'],
            'accreditation_number' => ['nullable', 'string', 'max:100'],
            'facility_summary' => ['nullable', 'required_unless:entity_type,organization', 'string', 'max:2000'],
            'exam_experience' => ['nullable', 'string', 'max:2000'],
            'expected_candidates' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ];
    }

    private function entityCodeRule(): mixed
    {
        return match ($this->input('entity_type')) {
            AdminRegistrationRequest::TYPE_ORGANIZATION => Rule::unique(Organization::class, 'code'),
            AdminRegistrationRequest::TYPE_SCHOOL => Rule::unique(School::class, 'code'),
            AdminRegistrationRequest::TYPE_SECONDARY_SCHOOL => Rule::unique(SecondarySchool::class, 'code'),
            AdminRegistrationRequest::TYPE_PROFESSIONAL_SCHOOL => Rule::unique(ProfessionalSchool::class, 'code'),
            AdminRegistrationRequest::TYPE_CENTER => Rule::unique(Center::class, 'code'),
            AdminRegistrationRequest::TYPE_CBT_CENTER => Rule::unique(CbtCenter::class, 'code'),
            default => Rule::unique(AdminRegistrationRequest::class, 'entity_code'),
        };
    }

    private function entityEmailRule(): mixed
    {
        return match ($this->input('entity_type')) {
            AdminRegistrationRequest::TYPE_ORGANIZATION => Rule::unique(Organization::class, 'email'),
            AdminRegistrationRequest::TYPE_SCHOOL => Rule::unique(School::class, 'email'),
            AdminRegistrationRequest::TYPE_SECONDARY_SCHOOL => Rule::unique(SecondarySchool::class, 'email'),
            AdminRegistrationRequest::TYPE_PROFESSIONAL_SCHOOL => Rule::unique(ProfessionalSchool::class, 'email'),
            AdminRegistrationRequest::TYPE_CENTER => Rule::unique(Center::class, 'email'),
            AdminRegistrationRequest::TYPE_CBT_CENTER => Rule::unique(CbtCenter::class, 'email'),
            default => Rule::unique(AdminRegistrationRequest::class, 'entity_email'),
        };
    }
}
