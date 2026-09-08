<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdaptivePilotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['online_enabled' => ['required', 'boolean'], 'offline_enabled' => ['required', 'boolean'], 'purpose' => ['required', 'string', 'min:10', 'max:2000'], 'diagnostic_only' => ['required', 'accepted']];
    }
}
