<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreQuestionRequest;
use App\Http\Requests\UpdateQuestionRequest;
use App\Http\Resources\QuestionBankResource;
use App\Http\Resources\QuestionResource;
use App\Http\Resources\SubjectResource;
use App\Http\Resources\TopicResource;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\Subject;
use App\Models\Topic;
use App\Services\CurrentContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class QuestionController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Question::class);

        return Inertia::render('Questions/Index', [
            'questions' => QuestionResource::collection(
                $this->scopedQuestions($request)
                    ->with(['questionBank.course', 'questionBank.module', 'subject', 'topic', 'options'])
                    ->latest()
                    ->get()
            ),
            'can' => [
                'create' => $request->user()->can('create', Question::class),
            ],
            ...$this->formOptions($request),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Question::class);

        return Inertia::render('Questions/Create', [
            ...$this->formOptions($request),
        ]);
    }

    public function store(StoreQuestionRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $questionBank = $this->authorizedQuestionBank($request, $data['question_bank_id']);
        $this->ensureSubjectMatchesQuestionBank($data['subject_id'], $questionBank);
        $topicId = $this->topicIdForQuestion($request, $data['topic_id'] ?? null, $data['subject_id']);

        $question = DB::transaction(function () use ($request, $data, $topicId): Question {
            $imagePath = $request->file('image')?->store('question-images', 'public');

            $question = Question::create([
                'question_bank_id' => $data['question_bank_id'],
                'subject_id' => $data['subject_id'],
                'topic_id' => $topicId,
                'created_by' => $request->user()->id,
                'question_type' => Question::TYPE_SINGLE_CHOICE,
                'stem' => $data['stem'],
                'image_path' => $imagePath,
                'explanation' => $data['explanation'] ?? null,
                'difficulty' => $data['difficulty'],
                'marks' => $data['marks'],
                'status' => $data['status'],
            ]);

            $this->syncOptions($question, $data['options']);

            return $question;
        });

        return redirect()->route('questions.edit', $question)->with('success', 'Question created. You can continue editing it here.');
    }

    public function show(Request $request, Question $question): Response
    {
        Gate::authorize('view', $question);

        return Inertia::render('Questions/Show', [
            'question' => QuestionResource::make($question->load(['questionBank.course', 'questionBank.module', 'subject', 'topic', 'options'])),
            'can' => [
                'update' => $request->user()->can('update', $question),
                'delete' => $request->user()->can('delete', $question),
            ],
        ]);
    }

    public function edit(Request $request, Question $question): Response
    {
        Gate::authorize('update', $question);

        return Inertia::render('Questions/Edit', [
            'question' => QuestionResource::make($question->load(['questionBank.course', 'questionBank.module', 'subject', 'topic', 'options'])),
            ...$this->formOptions($request),
        ]);
    }

    public function update(UpdateQuestionRequest $request, Question $question): RedirectResponse
    {
        $data = $request->validated();
        $questionBank = $this->authorizedQuestionBank($request, $data['question_bank_id']);
        $this->ensureSubjectMatchesQuestionBank($data['subject_id'], $questionBank);
        $topicId = $this->topicIdForQuestion($request, $data['topic_id'] ?? null, $data['subject_id']);

        DB::transaction(function () use ($request, $question, $data, $topicId): void {
            $imagePath = $question->image_path;

            if ($request->boolean('remove_image') && $imagePath) {
                Storage::disk('public')->delete($imagePath);
                $imagePath = null;
            }

            if ($request->hasFile('image')) {
                if ($imagePath) {
                    Storage::disk('public')->delete($imagePath);
                }

                $imagePath = $request->file('image')->store('question-images', 'public');
            }

            $question->update([
                'question_bank_id' => $data['question_bank_id'],
                'subject_id' => $data['subject_id'],
                'topic_id' => $topicId,
                'stem' => $data['stem'],
                'image_path' => $imagePath,
                'explanation' => $data['explanation'] ?? null,
                'difficulty' => $data['difficulty'],
                'marks' => $data['marks'],
                'status' => $data['status'],
            ]);

            $this->syncOptions($question, $data['options']);
        });

        return redirect()->route('questions.show', $question)->with('success', 'Question updated.');
    }

    public function destroy(Question $question): RedirectResponse
    {
        Gate::authorize('delete', $question);

        DB::transaction(function () use ($question): void {
            if ($question->image_path) {
                Storage::disk('public')->delete($question->image_path);
            }

            $question->delete();
        });

        return back()->with('success', 'Question deleted.');
    }

    public function template()
    {
        Gate::authorize('create', Question::class);

        return response()->streamDownload(function (): void {
            echo "difficulty,marks,question_text,explanation,status,option_a,option_b,option_c,option_d,option_e,correct_answer\n";
            echo "medium,1,What is 2 + 2?,Two pairs make four.,draft,3,4,5,6,,B\n";
        }, 'questions-template.csv', ['Content-Type' => 'text/csv']);
    }

    public function import(Request $request): RedirectResponse
    {
        Gate::authorize('create', Question::class);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:4096'],
            'question_bank_id' => ['required', 'string', 'exists:question_banks,id'],
            'subject_id' => ['required', 'string', 'exists:subjects,id'],
            'topic_id' => ['nullable', 'string', 'exists:topics,id'],
        ]);

        $questionBank = $this->authorizedQuestionBank($request, $request->string('question_bank_id')->toString());
        $this->ensureSubjectMatchesQuestionBank($request->string('subject_id')->toString(), $questionBank);
        $topicId = $this->topicIdForQuestion($request, $request->input('topic_id'), $request->string('subject_id')->toString());

        $rows = $this->csvRows($request->file('file')->getRealPath());
        $created = 0;

        DB::transaction(function () use ($request, $rows, $questionBank, $topicId, &$created): void {
            foreach ($rows as $index => $row) {
                $correctAnswer = strtoupper(trim($row['correct_answer'] ?? ''));

                $data = [
                    'question_bank_id' => $questionBank->id,
                    'subject_id' => $questionBank->subject_id,
                    'topic_id' => $topicId,
                    'difficulty' => trim($row['difficulty'] ?? 'medium') ?: 'medium',
                    'marks' => trim($row['marks'] ?? '1') ?: '1',
                    'stem' => trim($row['question_text'] ?? ''),
                    'explanation' => $row['explanation'] ?? null,
                    'status' => trim($row['status'] ?? Question::STATUS_DRAFT) ?: Question::STATUS_DRAFT,
                    'options' => [
                        ['label' => 'A', 'option_text' => trim($row['option_a'] ?? ''), 'is_correct' => $correctAnswer === 'A'],
                        ['label' => 'B', 'option_text' => trim($row['option_b'] ?? ''), 'is_correct' => $correctAnswer === 'B'],
                        ['label' => 'C', 'option_text' => trim($row['option_c'] ?? ''), 'is_correct' => $correctAnswer === 'C'],
                        ['label' => 'D', 'option_text' => trim($row['option_d'] ?? ''), 'is_correct' => $correctAnswer === 'D'],
                        ['label' => 'E', 'option_text' => trim($row['option_e'] ?? ''), 'is_correct' => $correctAnswer === 'E'],
                    ],
                ];

                validator($data, (new StoreQuestionRequest())->rules())
                    ->after(function ($validator) use ($data): void {
                        $filledOptions = collect($data['options'])
                            ->filter(fn (array $option) => filled($option['option_text'] ?? null));

                        if ($filledOptions->count() < 2) {
                            $validator->errors()->add('options', 'At least two options are required.');
                        }

                        if ($filledOptions->filter(fn (array $option) => $option['is_correct'])->count() !== 1) {
                            $validator->errors()->add('options', 'Choose exactly one correct answer.');
                        }
                    })
                    ->validate();

                $question = Question::create([
                    'question_bank_id' => $data['question_bank_id'],
                    'subject_id' => $data['subject_id'],
                    'topic_id' => $data['topic_id'],
                    'created_by' => $request->user()->id,
                    'question_type' => Question::TYPE_SINGLE_CHOICE,
                    'stem' => $data['stem'],
                    'explanation' => $data['explanation'],
                    'difficulty' => $data['difficulty'],
                    'marks' => $data['marks'],
                    'status' => $data['status'],
                ]);

                $this->syncOptions($question, $data['options']);
                $created++;
            }
        });

        return back()->with('success', "{$created} questions imported.");
    }

    private function scopedQuestions(Request $request)
    {
        return Question::query()
            ->whereHas('questionBank', fn ($query) => $this->applyQuestionBankScope($query, $request));
    }

    private function scopedQuestionBanks(Request $request)
    {
        return QuestionBank::query()->tap(fn ($query) => $this->applyQuestionBankScope($query, $request));
    }

    private function scopedSubjects(Request $request)
    {
        return Subject::query()->whereHas('questionBanks', fn ($query) => $this->applyQuestionBankScope($query, $request));
    }

    private function scopedTopics(Request $request)
    {
        return Topic::query()->whereHas('subject.questionBanks', fn ($query) => $this->applyQuestionBankScope($query, $request));
    }

    private function applyQuestionBankScope($query, Request $request): void
    {
        $user = $request->user();
        $context = app(CurrentContextService::class)->current($user);

        if (($context['type'] ?? null) === 'organization') {
            $query
                ->where('organization_id', $context['id'])
                ->whereNull('secondary_school_id')
                ->whereNull('professional_school_id')
                ->whereNull('cbt_center_id');

            return;
        }

        if (($context['type'] ?? null) === 'secondary_school') {
            $query->where('secondary_school_id', $context['id']);

            if ($user->isTeacher()) {
                $query->whereIn('subject_id', $user->assignedSubjects()->select('subjects.id'));
            }

            return;
        }

        if (($context['type'] ?? null) === 'professional_school') {
            $query->where('professional_school_id', $context['id']);

            if ($user->isFacilitator()) {
                $this->scopeFacilitatorQuestionBanks($query, $user);
            }

            return;
        }

        if (($context['type'] ?? null) === 'cbt_center') {
            $query->where('cbt_center_id', $context['id']);

            return;
        }

        $query
            ->when(! $user->isSuperAdmin() && $user->organization_id, fn ($bankQuery) => $bankQuery->where('organization_id', $user->organization_id))
            ->when(! $user->isSuperAdmin() && $user->school_id, fn ($bankQuery) => $bankQuery->where('school_id', $user->school_id))
            ->when(! $user->isSuperAdmin() && $user->center_id, fn ($bankQuery) => $bankQuery->where('center_id', $user->center_id))
            ->when(! $user->isSuperAdmin() && $user->secondary_school_id, fn ($bankQuery) => $bankQuery->where('secondary_school_id', $user->secondary_school_id))
            ->when(! $user->isSuperAdmin() && $user->professional_school_id, fn ($bankQuery) => $bankQuery->where('professional_school_id', $user->professional_school_id))
            ->when(! $user->isSuperAdmin() && $user->cbt_center_id, fn ($bankQuery) => $bankQuery->where('cbt_center_id', $user->cbt_center_id))
            ->when($user->isTeacher(), fn ($bankQuery) => $bankQuery->whereIn('subject_id', $user->assignedSubjects()->select('subjects.id')))
            ->when($user->isFacilitator(), fn ($bankQuery) => $this->scopeFacilitatorQuestionBanks($bankQuery, $user));
    }

    private function scopeFacilitatorQuestionBanks($query, $user): void
    {
        $query->where(function ($scope) use ($user): void {
            $scope
                ->whereIn('course_id', $user->assignedCourses()->select('courses.id'))
                ->orWhereIn('module_id', $user->assignedModules()->select('modules.id'));
        });
    }

    private function authorizedQuestionBank(Request $request, string $questionBankId): QuestionBank
    {
        $questionBank = $this->scopedQuestionBanks($request)->whereKey($questionBankId)->first();

        if (! $questionBank) {
            throw ValidationException::withMessages(['question_bank_id' => 'Choose a question bank within your allowed scope.']);
        }

        return $questionBank;
    }

    private function ensureSubjectMatchesQuestionBank(string $subjectId, QuestionBank $questionBank): void
    {
        if ($questionBank->subject_id !== $subjectId) {
            throw ValidationException::withMessages(['subject_id' => 'The subject must match the selected question bank.']);
        }
    }

    private function ensureTopicBelongsToSubject(Request $request, ?string $topicId, string $subjectId): void
    {
        if (! $topicId) {
            return;
        }

        $topic = $this->scopedTopics($request)
            ->where('subject_id', $subjectId)
            ->whereKey($topicId)
            ->first();

        if (! $topic) {
            throw ValidationException::withMessages(['topic_id' => 'Choose a topic under the selected subject.']);
        }
    }

    private function topicIdForQuestion(Request $request, ?string $topicId, string $subjectId): ?string
    {
        if ($this->isSecondaryContext($request)) {
            if (filled($topicId)) {
                throw ValidationException::withMessages(['topic_id' => 'Secondary school questions do not use topics. Choose only class, subject, bank, and question.']);
            }

            return null;
        }

        $this->ensureTopicBelongsToSubject($request, $topicId, $subjectId);

        return filled($topicId) ? $topicId : null;
    }

    private function isSecondaryContext(Request $request): bool
    {
        $context = app(CurrentContextService::class)->current($request->user());

        return ($context['type'] ?? null) === 'secondary_school'
            || $request->user()?->secondary_school_id !== null;
    }

    private function syncOptions(Question $question, array $options): void
    {
        $filledOptions = collect($options)
            ->filter(fn (array $option) => filled($option['option_text'] ?? null))
            ->values();

        $question->options()->delete();

        $filledOptions->each(function (array $option, int $index) use ($question): void {
            $question->options()->create([
                'label' => $option['label'],
                'option_text' => $option['option_text'],
                'display_order' => $index + 1,
                'is_correct' => in_array($option['is_correct'] ?? false, [true, 1, '1', 'true', 'on'], true),
            ]);
        });
    }

    private function csvRows(string $path): array
    {
        $handle = fopen($path, 'r');
        $headers = array_map(fn ($header) => strtolower(trim((string) $header)), fgetcsv($handle) ?: []);
        $rows = [];

        while (($row = fgetcsv($handle)) !== false) {
            if (count(array_filter($row, fn ($value) => filled($value))) === 0) {
                continue;
            }

            $rows[] = array_combine($headers, array_pad($row, count($headers), null));
        }

        fclose($handle);

        return $rows;
    }

    private function formOptions(Request $request): array
    {
        return [
            'questionBanks' => QuestionBankResource::collection(
                $this->scopedQuestionBanks($request)
                    ->whereNotNull('subject_id')
                    ->with(['subject', 'course', 'module'])
                    ->orderBy('name')
                    ->get()
            ),
            'subjects' => SubjectResource::collection(
                $this->scopedSubjects($request)->orderBy('name')->get()
            ),
            'topics' => $this->isSecondaryContext($request)
                ? ['data' => []]
                : TopicResource::collection($this->scopedTopics($request)->with('subject')->orderBy('name')->get()),
            'difficulties' => [
                ['value' => 'easy', 'label' => 'Easy'],
                ['value' => 'medium', 'label' => 'Medium'],
                ['value' => 'hard', 'label' => 'Hard'],
            ],
            'statuses' => [
                ['value' => Question::STATUS_DRAFT, 'label' => 'Draft'],
                ['value' => Question::STATUS_REVIEW, 'label' => 'Review'],
                ['value' => Question::STATUS_APPROVED, 'label' => 'Approved'],
                ['value' => Question::STATUS_REJECTED, 'label' => 'Rejected'],
                ['value' => Question::STATUS_ARCHIVED, 'label' => 'Archived'],
            ],
        ];
    }
}
