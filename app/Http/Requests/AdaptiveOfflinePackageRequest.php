<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdaptiveOfflinePackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['activation_id' => ['required', 'integer', 'exists:offline_server_activations,id'],
            'candidate_ids' => ['required', 'array', 'min:1', 'max:200'], 'candidate_ids.*' => ['required', 'string', 'distinct', 'exists:candidates,id']];
    }
}
