<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdaptiveResearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        $exam = $this->route('exam');

        return $this->user()?->can('viewAdaptiveReport', $exam) && $this->user()?->can('update', $exam);
    }

    public function rules(): array
    {
        $operation = $this->route()->getActionMethod();

        return [
            'payload' => [$operation === 'import' ? 'required' : 'sometimes', 'json', 'max:2000000', function ($attribute, $value, $fail) {
                if (! is_array(json_decode($value, true))) {
                    $fail('Provide a JSON object.');
                }
            }],
            'action' => [$operation === 'transition' ? 'required' : 'sometimes', 'in:review,revoke'],
            'calibration_id' => [$operation === 'evaluate' ? 'required' : 'sometimes', 'integer'],
            'level_id' => [$operation === 'evaluate' ? 'required' : 'sometimes', 'integer'],
        ];
    }
}
