<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReleaseExamResultsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('exam'));
    }

    public function rules(): array
    {
        return ['released' => ['required', 'boolean']];
    }
}
