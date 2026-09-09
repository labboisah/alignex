<?php

namespace App\Http\Requests;

use App\Models\CbtCenter;
use App\Models\Institution;
use App\Models\ProfessionalSchool;
use App\Models\SecondarySchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOwnerStatusRequest extends FormRequest
{
    public function owner(): Model
    {
        foreach (['institution' => Institution::class, 'professionalSchool' => ProfessionalSchool::class,
            'secondarySchool' => SecondarySchool::class, 'cbtCenter' => CbtCenter::class] as $key => $class) {
            if ($value = $this->route($key)) {
                return $value instanceof Model ? $value : $class::findOrFail($value);
            }
        }
        abort(404);
    }

    public function authorize(): bool
    {
        $owner = $this->owner();
        $user = $this->user();
        if ($user?->isSuperAdmin()) {
            return true;
        }
        if ($owner instanceof Institution) {
            return $user?->isInstitutionAdmin() && (string) $user->institution_id === (string) $owner->id;
        }

        return $user?->can('update', $owner) ?? false;
    }

    public function rules(): array
    {
        return ['status' => ['required', Rule::in(['active', 'inactive'])]];
    }
}
