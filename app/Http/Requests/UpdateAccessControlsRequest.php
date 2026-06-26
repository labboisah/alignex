<?php

namespace App\Http\Requests;

use App\Support\AccessControl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccessControlsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'roles' => ['required', 'array'],
            'roles.*' => ['array'],
            'roles.*.*' => ['string', Rule::in(array_keys(AccessControl::permissions()))],
        ];
    }
}
