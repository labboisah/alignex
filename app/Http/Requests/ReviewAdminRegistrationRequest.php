<?php

namespace App\Http\Requests;

use App\Models\AdminRegistrationRequest;
use Illuminate\Foundation\Http\FormRequest;

class ReviewAdminRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'review_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
