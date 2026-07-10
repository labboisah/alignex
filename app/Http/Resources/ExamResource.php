<?php

namespace App\Http\Resources;

use App\Models\Exam;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExamResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'organization_name' => $this->whenLoaded('organization', fn () => $this->organization?->name),
            'center_id' => $this->center_id,
            'center_name' => $this->whenLoaded('center', fn () => $this->center?->name),
            'school_id' => $this->school_id,
            'school_name' => $this->whenLoaded('school', fn () => $this->school?->name),
            'cbt_center_id' => $this->cbt_center_id,
            'cbt_center_name' => $this->whenLoaded('cbtCenter', fn () => $this->cbtCenter?->name),
            'owner_context' => $this->effectiveOwnerType(),
            'owner_context_label' => str($this->effectiveOwnerType() ?? 'exam')->replace('_', ' ')->headline()->toString(),
            'title' => $this->title,
            'exam_code' => $this->code,
            'exam_type' => $this->whenLoaded('examType', fn () => $this->examType?->code),
            'exam_type_label' => $this->whenLoaded('examType', fn () => $this->examType?->name),
            'exam_category' => $this->exam_category,
            'mode' => $this->mode,
            'exam_mode' => $this->exam_mode,
            'delivery_mode' => $this->delivery_mode,
            'question_bank_id' => $this->question_bank_id,
            'question_bank_name' => $this->whenLoaded('questionBank', fn () => $this->questionBank?->name),
            'candidate_group_id' => data_get($this->settings ?? [], 'participant_candidate_group_id') ?? data_get($this->settings ?? [], 'cbt_candidate_group_id'),
            'candidate_group_ids' => data_get($this->settings ?? [], 'participant_candidate_group_ids') ?? data_get($this->settings ?? [], 'cbt_candidate_group_ids') ?? [],
            'secondary_school_id' => $this->secondary_school_id,
            'academic_session_id' => $this->academic_session_id,
            'academic_term_id' => $this->academic_term_id,
            'school_class_id' => $this->school_class_id,
            'subject_id' => $this->subject_id,
            'student_group_id' => data_get($this->settings ?? [], 'secondary_student_group_id'),
            'start_at' => $this->starts_at?->format('Y-m-d\TH:i'),
            'end_at' => $this->ends_at?->format('Y-m-d\TH:i'),
            'duration_minutes' => $this->duration_minutes,
            'total_marks' => $this->total_marks,
            'pass_mark' => $this->pass_mark,
            'status' => $this->status,
            'status_label' => str($this->status)->replace('_', ' ')->title()->toString(),
            'settings' => $this->settings ?? [],
            'subjects' => $this->whenLoaded('examSubjects', fn () => $this->examSubjects
                ->sortBy('display_order')
                ->values()
                ->map(fn ($examSubject) => [
                    'id' => $examSubject->id,
                    'subject_id' => $examSubject->subject_id,
                    'subject_name' => $examSubject->subject?->name,
                    'course_id' => $examSubject->questionBank?->course_id,
                    'module_id' => $examSubject->questionBank?->module_id,
                    'question_bank_id' => $examSubject->question_bank_id,
                    'question_bank_ids' => data_get($examSubject->selection_rules ?? [], 'question_bank_ids', $examSubject->question_bank_id ? [$examSubject->question_bank_id] : []),
                    'question_bank_name' => $examSubject->questionBank?->name,
                    'number_of_questions' => $examSubject->question_count,
                    'marks_per_question' => $examSubject->marks_per_question,
                    'duration_minutes' => $examSubject->duration_minutes,
                    'total_marks' => $examSubject->total_marks,
                    'difficulty_distribution' => $examSubject->difficulty_distribution,
                ])),
            'subjects_count' => $this->whenCounted('examSubjects'),
            'participants_count' => $this->whenCounted('participants'),
            'attempts_count' => $this->whenCounted('attempts'),
            'paper_generation_status' => $this->attempts()->whereHas('papers')->count().' generated',
            'submission_status' => $this->attempts()->whereIn('status', ['submitted', 'auto_submitted'])->count().' submitted',
            'results_summary' => [
                'submitted' => $this->attempts()->whereIn('status', ['submitted', 'auto_submitted'])->count(),
                'passed' => $this->attempts()->where('result_status', 'passed')->count(),
                'failed' => $this->attempts()->where('result_status', 'failed')->count(),
            ],
            'can' => [
                'view' => $request->user()?->can('view', $this->resource) ?? false,
                'update' => $request->user()?->can('update', $this->resource) ?? false,
                'delete' => $request->user()?->can('delete', $this->resource) ?? false,
                'cancel' => ($request->user()?->can('update', $this->resource) ?? false) && $this->status !== Exam::STATUS_CANCELLED,
            ],
            'candidate_ids' => $this->whenLoaded('candidates', fn () => $this->candidates->pluck('id')->values()),
            'student_ids' => data_get($this->settings ?? [], 'secondary_student_ids', []),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
