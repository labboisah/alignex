<?php

namespace App\Http\Requests;

use App\Services\AdaptiveLifecycleService;
use App\Services\CandidateExamSessionService;
use Illuminate\Foundation\Http\FormRequest;

class CandidateAnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $attempt = app(CandidateExamSessionService::class)->attemptFromRequest($this);
        $this->attributes->set('candidate_attempt', $attempt);
        $adaptive = app(AdaptiveLifecycleService::class)->handles($attempt);

        return [
            'question_id' => $adaptive ? ['required', 'string'] : ['required', 'string', 'exists:questions,id'],
            'selected_option_ids' => ['array', 'max:100'],
            'selected_option_ids.*' => $adaptive ? ['string', 'distinct'] : ['string', 'exists:question_options,id'],
            'is_flagged' => ['boolean'],
            'time_spent_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'device_fingerprint' => ['nullable', 'string', 'max:255'],
            ...($adaptive ? [
                'commit' => ['required', 'boolean'],
                'state_version' => ['required', 'integer', 'min:0'],
                'idempotency_key' => ['required_if:commit,true', 'nullable', 'string', 'min:1', 'max:64'],
            ] : []),
        ];
    }
}
