<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\CandidateExamAttempt;
use App\Models\Exam;
use App\Models\Question;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class OfflineExamPackageController extends Controller
{
    public function show(Request $request, string $examCode): JsonResponse
    {
        $user = $this->authenticateSyncAdmin($request);

        if (! $user) {
            return response()->json(['message' => 'Offline sync admin credentials are invalid.'], 401);
        }

        $exam = Exam::query()
            ->with(['organization', 'school', 'secondarySchool', 'professionalSchool', 'cbtCenter', 'center', 'examSubjects.subject'])
            ->where('code', strtoupper(trim($examCode)))
            ->first();

        if (! $exam) {
            return response()->json(['message' => 'Exam was not found for this exam code.'], 404);
        }

        if (! $this->canSyncExam($user, $exam)) {
            return response()->json(['message' => 'This admin is not allowed to import this exam for offline delivery.'], 403);
        }

        if (in_array($exam->status, [Exam::STATUS_CANCELLED, Exam::STATUS_COMPLETED], true)) {
            return response()->json(['message' => 'Exam is not available for offline import.'], 409);
        }

        $candidates = $exam->candidates()->orderBy('candidate_number')->get();

        if ($candidates->isEmpty()) {
            return response()->json(['message' => 'Assign candidates before importing this exam offline.'], 422);
        }

        $attempts = CandidateExamAttempt::query()
            ->where('exam_id', $exam->id)
            ->whereIn('candidate_id', $candidates->pluck('id'))
            ->with(['papers.question.subject', 'papers.question.options'])
            ->get()
            ->keyBy('candidate_id');

        $missingPaperCandidates = $candidates->filter(fn (Candidate $candidate) => ! $attempts->get($candidate->id)?->papers->isNotEmpty());

        if ($missingPaperCandidates->isNotEmpty()) {
            return response()->json([
                'message' => 'Generate candidate papers on the portal before importing this exam offline.',
                'missing_candidates' => $missingPaperCandidates->pluck('candidate_number')->values(),
            ], 422);
        }

        $papers = $attempts
            ->values()
            ->flatMap(fn (CandidateExamAttempt $attempt) => $attempt->papers)
            ->values();
        $questions = $papers
            ->pluck('question')
            ->filter()
            ->unique('id')
            ->values();

        if ($questions->isEmpty()) {
            return response()->json(['message' => 'No generated questions are available for this exam. Generate candidate papers first.'], 422);
        }

        $subjects = $questions
            ->pluck('subject')
            ->filter()
            ->unique('id')
            ->values();

        $package = [
            'manifest' => [
                'package_id' => 'exam-'.$exam->id.'-'.optional($exam->updated_at)->timestamp,
                'exam_id' => (string) $exam->id,
                'exam_code' => (string) $exam->code,
                'title' => (string) $exam->title,
                'organization_name' => $this->ownerName($exam),
                'center_id' => (string) ($exam->center_id ?? $exam->cbt_center_id ?? 'online'),
                'start_at' => optional($exam->starts_at)->toISOString() ?? now()->toISOString(),
                'end_at' => optional($exam->ends_at)->toISOString() ?? now()->addMinutes((int) $exam->duration_minutes)->toISOString(),
                'duration_minutes' => (int) $exam->duration_minutes,
                'total_questions' => $questions->count(),
                'candidate_count' => $candidates->count(),
                'shuffle_questions' => (bool) data_get($exam->settings ?? [], 'shuffle_questions', false),
                'shuffle_options' => (bool) data_get($exam->settings ?? [], 'shuffle_options', false),
            ],
            'subjects' => $subjects->map(fn ($subject) => [
                'id' => (string) $subject->id,
                'exam_id' => (string) $exam->id,
                'name' => (string) $subject->name,
                'code' => $subject->code ? (string) $subject->code : null,
            ])->values(),
            'questions' => $questions->values()->map(fn (Question $question, int $index) => [
                'id' => (string) $question->id,
                'exam_id' => (string) $exam->id,
                'subject_id' => (string) $question->subject_id,
                'question_type' => $this->offlineQuestionType((string) $question->question_type),
                'body' => (string) $question->stem,
                'marks' => (float) $question->marks,
                'display_order' => $index + 1,
            ]),
            'options' => $questions->values()->flatMap(fn (Question $question) => $question->options
                ->sortBy('display_order')
                ->values()
                ->map(fn ($option, int $index) => [
                    'id' => (string) $option->id,
                    'question_id' => (string) $question->id,
                    'option_label' => (string) $option->label,
                    'body' => (string) $option->option_text,
                    'is_correct' => (bool) $option->is_correct,
                    'display_order' => $index + 1,
                ]))->values(),
            'papers' => $candidates->map(function (Candidate $candidate) use ($attempts) {
                $attempt = $attempts->get($candidate->id);

                return [
                    'candidate_id' => (string) $candidate->id,
                    'questions' => $attempt?->papers
                        ->sortBy('question_order')
                        ->values()
                        ->map(fn ($paper) => [
                            'question_id' => (string) $paper->question_id,
                            'display_order' => (int) $paper->question_order,
                            'option_order' => collect($paper->option_order ?? [])
                                ->map(fn ($optionId) => (string) $optionId)
                                ->values()
                                ->all(),
                        ])
                        ->values()
                        ->all() ?? [],
                ];
            })->values(),
            'candidates' => $candidates->map(function (Candidate $candidate) use ($exam, $attempts) {
                $attempt = $attempts->get($candidate->id);

                return [
                    'id' => (string) $candidate->id,
                    'exam_id' => (string) $exam->id,
                    'candidate_no' => (string) $candidate->candidate_number,
                    'full_name' => trim($candidate->first_name.' '.$candidate->last_name),
                    'access_code_hash' => (string) ($attempt?->access_code_hash ?: Hash::make('offline-'.$exam->id.'-'.$candidate->id)),
                    'group_name' => null,
                ];
            })->values(),
        ];

        return response()->json(['package' => $package]);
    }

    private function authenticateSyncAdmin(Request $request): ?User
    {
        $email = trim((string) $request->header('X-AlignEx-Admin-Email'));
        $password = (string) $request->header('X-AlignEx-Admin-Password');

        if ($email === '' || $password === '') {
            return null;
        }

        $user = User::query()->where('email', $email)->first();

        if (! $user || ! $user->isPortalUser() || ! Hash::check($password, $user->password)) {
            return null;
        }

        return $user;
    }

    private function canSyncExam(User $user, Exam $exam): bool
    {
        if (! $user->hasPermission('manageExams') && ! $user->hasPermission('viewSupervisorMonitor')) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        if ((string) $exam->created_by === (string) $user->id) {
            return true;
        }

        return $user->canAccessOrganization($exam->organization_id)
            || $user->canAccessSecondarySchool($exam->secondary_school_id ?? $exam->school_id)
            || $user->canAccessProfessionalSchool($exam->professional_school_id)
            || $user->canAccessCbtCenter($exam->cbt_center_id ?? $exam->center_id);
    }

    /**
     * @return Collection<int, Question>
     */
    private function questionsFor(Exam $exam): Collection
    {
        $paperQuestionIds = CandidateExamAttempt::query()
            ->where('exam_id', $exam->id)
            ->whereHas('papers')
            ->with('papers')
            ->get()
            ->flatMap(fn (CandidateExamAttempt $attempt) => $attempt->papers->pluck('question_id'))
            ->unique()
            ->values();

        if ($paperQuestionIds->isNotEmpty()) {
            return Question::query()
                ->whereIn('id', $paperQuestionIds)
                ->with(['subject', 'options'])
                ->get()
                ->sortBy(fn (Question $question) => $paperQuestionIds->search($question->id))
                ->values();
        }

        return $exam->examSubjects
            ->sortBy('display_order')
            ->values()
            ->flatMap(function ($examSubject) use ($exam) {
                $bankIds = collect(data_get($examSubject->selection_rules ?? [], 'question_bank_ids', []))
                    ->filter()
                    ->map(fn ($id) => (string) $id)
                    ->values()
                    ->all();

                if ($bankIds === []) {
                    $bankIds = array_filter([$examSubject->question_bank_id ?? $exam->question_bank_id]);
                }

                return Question::query()
                    ->whereIn('question_bank_id', $bankIds)
                    ->where('subject_id', $examSubject->subject_id)
                    ->whereIn('status', [Question::STATUS_DRAFT, Question::STATUS_REVIEW, Question::STATUS_APPROVED])
                    ->with(['subject', 'options'])
                    ->orderBy('id')
                    ->limit((int) $examSubject->question_count)
                    ->get();
            })
            ->values();
    }

    private function ownerName(Exam $exam): string
    {
        return (string) (
            $exam->organization?->name
            ?? $exam->secondarySchool?->name
            ?? $exam->professionalSchool?->name
            ?? $exam->school?->name
            ?? $exam->cbtCenter?->name
            ?? $exam->center?->name
            ?? config('app.name', 'AlignEx')
        );
    }

    private function offlineQuestionType(string $type): string
    {
        return match ($type) {
            Question::TYPE_TRUE_FALSE => Question::TYPE_SINGLE_CHOICE,
            default => Str::of($type)->replace('-', '_')->toString(),
        };
    }
}
