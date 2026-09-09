<?php

namespace App\Http\Requests;

use App\Models\Course;
use App\Models\ProfessionalModule;
use App\Models\Programme;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfessionalStructureRequest extends FormRequest
{
    public function authorize(): bool
    {
        $school = $this->route('professionalSchool');
        $record = $this->route('programme') ?? $this->route('course') ?? $this->route('module');
        abort_unless($record && (string) $record->professional_school_id === (string) $school->id, 404);

        return $this->user()?->isSuperAdmin() || ($school && $this->user()?->can('update', $school));
    }

    public function rules(): array
    {
        $school = $this->route('professionalSchool');
        $record = $this->route('programme') ?? $this->route('course') ?? $this->route('module');
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', 'alpha_dash', Rule::unique($record->getTable(), 'code')->where('professional_school_id', $school->id)->ignore($record->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
        if ($record instanceof Programme) {
            $rules['duration'] = ['nullable', 'string', 'max:100'];
        } else {
            $rules['programme_id'] = [$record instanceof Course ? 'required' : 'nullable', Rule::exists('programmes', 'id')->where('professional_school_id', $school->id)];
        }
        if ($record instanceof ProfessionalModule) {
            $rules['course_id'] = ['required', Rule::exists('courses', 'id')->where('professional_school_id', $school->id)];
        }

        return $rules;
    }
}
