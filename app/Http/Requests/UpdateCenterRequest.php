<?php

namespace App\Http\Requests;

use App\Models\Center;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCenterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('center')) === true;
    }

    public function rules(): array
    {
        $center = $this->route('center');

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', 'alpha_dash', Rule::unique('centers', 'code')->ignore($center)],
            'location' => ['required', 'string', 'max:1000'],
            'capacity' => ['required', 'integer', 'min:1', 'max:100000'],
            'contact_person' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255', Rule::unique('centers', 'email')->ignore($center)],
            'status' => ['required', Rule::in([Center::STATUS_ACTIVE, Center::STATUS_INACTIVE])],
        ];
    }

}
