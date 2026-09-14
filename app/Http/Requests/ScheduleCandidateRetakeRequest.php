<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ScheduleCandidateRetakeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('scheduleRetake', $this->route('attempt'));
    }

    public function rules(): array
    {
        return [
            'starts_at' => ['required', 'date', 'after_or_equal:now'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
