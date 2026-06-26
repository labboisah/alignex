<?php

namespace App\Http\Requests;

use App\Models\School;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSchoolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('school')) === true;
    }

    public function rules(): array
    {
        $school = $this->route('school');

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('schools', 'code')->ignore($school)],
            'location' => ['required', 'string', 'max:1000'],
            'capacity' => ['required', 'integer', 'min:1', 'max:100000'],
            'contact_person' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255', Rule::unique('schools', 'email')->ignore($school)],
            'status' => ['required', Rule::in([School::STATUS_ACTIVE, School::STATUS_INACTIVE])],
        ];
    }
}
