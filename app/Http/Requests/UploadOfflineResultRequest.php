<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadOfflineResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Activation and exam policy are enforced by the receiver.
    }

    public function rules(): array
    {
        return [
            'contract' => ['required', 'in:alignex.offline-result.v1'],
            'upload_id' => ['required', 'string', 'max:100'],
            'package_id' => ['required', 'string', 'max:200'],
            'exam_id' => ['required', 'string', 'max:26'],
            'candidate_id' => ['required', 'string', 'max:26'],
            'attempt_id' => ['nullable', 'string', 'max:26'],
            'upload_proof' => ['nullable', 'string', 'max:10000'],
            'status' => ['required', 'in:submitted,auto_submitted,disqualified'],
            'started_at' => ['required', 'date'],
            'submitted_at' => ['required', 'date', 'after_or_equal:started_at', 'before_or_equal:now'],
            'local_score' => ['nullable', 'numeric', 'between:-1000000,1000000'],
            'paper' => ['required', 'array', 'min:1', 'max:2000'],
            'paper.*' => ['required', 'array:question_id,marks,option_ids,correct_option_ids'],
            'paper.*.question_id' => ['required', 'string', 'max:26', 'distinct'],
            'paper.*.marks' => ['required', 'numeric', 'between:0,1000000'],
            'paper.*.option_ids' => ['present', 'array', 'max:100'],
            'paper.*.option_ids.*' => ['string', 'max:26'],
            'paper.*.correct_option_ids' => ['present', 'array', 'max:100'],
            'paper.*.correct_option_ids.*' => ['string', 'max:26'],
            'answers' => ['present', 'array', 'max:2000'],
            'answers.*' => ['array:question_id,selected_option_ids,answer_text,saved_at'],
            'answers.*.question_id' => ['required', 'string', 'max:26', 'distinct'],
            'answers.*.selected_option_ids' => ['present', 'array', 'max:100'],
            'answers.*.selected_option_ids.*' => ['string', 'max:26'],
            'answers.*.answer_text' => ['nullable', 'string', 'max:20000'],
            'answers.*.saved_at' => ['required', 'date', 'after_or_equal:started_at', 'before_or_equal:submitted_at'],
            'events' => ['present', 'array', 'max:10000'],
            'events.*' => ['array:event_type,severity,message,occurred_at'],
            'events.*.event_type' => ['required', 'string', 'max:100'],
            'events.*.severity' => ['required', 'in:info,warning,high,critical'],
            'events.*.message' => ['required', 'string', 'max:2000'],
            'events.*.occurred_at' => ['required', 'date', 'before_or_equal:now'],
        ];
    }
}
