<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdaptiveNextLevelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['idempotency_key' => ['required', 'string', 'min:1', 'max:64'],
            'practice' => ['sometimes', 'boolean'], 'device_fingerprint' => ['nullable', 'string', 'max:255']];
    }
}
