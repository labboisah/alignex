<?php

namespace App\Http\Resources;

use App\Models\CandidateExamAttempt;
use App\Services\CandidateExamSessionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CandidateExamPayloadResource extends JsonResource
{
    public function __construct(CandidateExamAttempt $resource, private readonly ?string $token = null)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var CandidateExamAttempt $attempt */
        $attempt = $this->resource;
        $session = app(CandidateExamSessionService::class);
        $answers = $attempt->answers()
            ->get()
            ->keyBy('question_id');

        return [
            'candidate' => [
                'id' => $attempt->candidate?->id,
                'full_name' => trim($attempt->candidate?->first_name.' '.$attempt->candidate?->last_name),
                'registration_number' => $attempt->candidate?->candidate_number,
                'phone' => $attempt->candidate?->phone,
            ],
            'exam' => [
                'id' => $attempt->exam?->id,
                'title' => $attempt->exam?->title,
                'exam_code' => $attempt->exam?->code,
                'duration_minutes' => $attempt->exam?->duration_minutes,
                'settings' => [
                    'allow_back_navigation' => (bool) data_get($attempt->exam?->settings ?? [], 'allow_back_navigation', true),
                    'require_fullscreen' => (bool) data_get($attempt->exam?->settings ?? [], 'require_fullscreen', false),
                    'require_webcam' => (bool) data_get($attempt->exam?->settings ?? [], 'require_webcam', false),
                    'max_tab_switches' => (int) data_get($attempt->exam?->settings ?? [], 'max_tab_switches', 0),
                ],
            ],
            'attempt' => [
                'id' => $attempt->id,
                'status' => $attempt->status,
                'started_at' => $attempt->started_at?->toISOString(),
                'server_due_at' => $attempt->server_due_at?->toISOString(),
                'submitted_at' => $attempt->submitted_at?->toISOString(),
            ],
            'remaining_time' => $session->remainingSeconds($attempt),
            'exam_token' => $this->token,
            'questions' => CandidatePaperResource::collection(
                $attempt->papers
                    ->sortBy('question_order')
                    ->values()
            )->resolve($request),
            'answers' => $answers->map(fn ($answer) => [
                'question_id' => $answer->question_id,
                'selected_option_ids' => $answer->selected_option_ids ?? [],
                'is_flagged' => $answer->is_flagged,
                'saved_at' => $answer->saved_at?->toISOString(),
            ])->values(),
        ];
    }
}
