<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Exam;
use App\Models\Organization;
use App\Models\ProfessionalModule;
use App\Models\ProfessionalSchool;
use App\Models\Programme;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\Subject;
use App\Models\TrainingBatch;
use App\Models\User;
use App\Support\ReferenceCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfessionalSchoolController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($this->canList($request->user()), 403);

        return Inertia::render('ProfessionalSchools/Index', [
            'professionalSchools' => $this->scope($request->user())
                ->with('organization:id,name')
                ->withCount(['programmes', 'candidates', 'exams'])
                ->latest()
                ->get()
                ->map(fn (ProfessionalSchool $school) => $this->row($school)),
            'can' => ['create' => $request->user()->isSuperAdmin()],
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        return Inertia::render('ProfessionalSchools/Create', $this->formOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        $data = $request->validate($this->rules());
        $data['code'] = $this->referenceCode($data['code'] ?? null, $data['name'], ProfessionalSchool::query());

        $school = ProfessionalSchool::query()->create($data);

        return redirect()->route('professional-schools.show', $school)->with('success', 'Professional school created.');
    }

    public function show(Request $request, ProfessionalSchool $professionalSchool): Response
    {
        $this->authorizeRecord($request->user(), $professionalSchool);

        $professionalSchool->load([
            'organization:id,name',
            'programmes' => fn ($query) => $query->withCount(['courses', 'trainingBatches'])->orderBy('name'),
            'courses' => fn ($query) => $query->with('programme:id,name')->orderBy('name'),
            'trainingBatches' => fn ($query) => $query->with('programme:id,name')->latest(),
            'candidates' => fn ($query) => $query->with(['programme', 'course', 'trainingBatch'])->latest()->limit(8),
            'exams' => fn ($query) => $query->latest()->limit(8),
            'certificates' => fn ($query) => $query->with('candidate')->latest('issued_at')->limit(8),
        ])->loadCount(['programmes', 'courses', 'modules', 'trainingBatches', 'candidates', 'questionBanks', 'exams', 'certificates']);

        return Inertia::render('ProfessionalSchools/Show', [
            'professionalSchool' => $this->detail($professionalSchool),
            'dashboard' => $this->dashboard($professionalSchool),
        ]);
    }

    public function edit(Request $request, ProfessionalSchool $professionalSchool): Response
    {
        $this->authorizeRecord($request->user(), $professionalSchool, update: true);

        return Inertia::render('ProfessionalSchools/Edit', [
            'professionalSchool' => $this->row($professionalSchool->load('organization:id,name')),
            ...$this->formOptions(),
        ]);
    }

    public function update(Request $request, ProfessionalSchool $professionalSchool): RedirectResponse
    {
        $this->authorizeRecord($request->user(), $professionalSchool, update: true);

        $data = $request->validate($this->rules($professionalSchool));
        $data['code'] = filled($data['code'] ?? null)
            ? strtoupper($data['code'])
            : ReferenceCode::unique($data['name'], ProfessionalSchool::query(), $professionalSchool);

        $professionalSchool->update($data);

        return redirect()->route('professional-schools.show', $professionalSchool)->with('success', 'Professional school updated.');
    }

    public function programmes(Request $request, ProfessionalSchool $professionalSchool): Response
    {
        $this->authorizeRecord($request->user(), $professionalSchool);

        return Inertia::render('ProfessionalSchools/Programmes', [
            'professionalSchool' => $this->row($professionalSchool),
            'programmes' => $professionalSchool->programmes()->withCount(['courses', 'trainingBatches', 'candidates', 'exams'])->orderBy('name')->get(),
        ]);
    }

    public function storeProgramme(Request $request, ProfessionalSchool $professionalSchool): RedirectResponse
    {
        $this->authorizeRecord($request->user(), $professionalSchool, update: true);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', 'alpha_dash', Rule::unique('programmes')->where('professional_school_id', $professionalSchool->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'duration' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::in([Programme::STATUS_ACTIVE, Programme::STATUS_INACTIVE])],
        ]);
        $data['code'] = $this->referenceCode($data['code'] ?? null, $data['name'], Programme::query()->where('professional_school_id', $professionalSchool->id));

        $professionalSchool->programmes()->create($data);

        return back()->with('success', 'Programme created.');
    }

    public function courses(Request $request, ProfessionalSchool $professionalSchool): Response
    {
        $this->authorizeRecord($request->user(), $professionalSchool);
        $courseQuery = $professionalSchool->courses()->with('programme:id,name')->orderBy('name');
        $this->scopeFacilitatorCourses($courseQuery, $request->user());

        return Inertia::render('ProfessionalSchools/Courses', [
            'professionalSchool' => $this->row($professionalSchool),
            'programmes' => $professionalSchool->programmes()->orderBy('name')->get(['id', 'name', 'code']),
            'courses' => $courseQuery->get(),
            'canManageStructure' => $request->user()->isSuperAdmin() || $request->user()->hasPermission('manageSchools'),
        ]);
    }

    public function storeCourse(Request $request, ProfessionalSchool $professionalSchool): RedirectResponse
    {
        $this->authorizeRecord($request->user(), $professionalSchool, update: true);

        $data = $request->validate([
            'programme_id' => ['required', 'exists:programmes,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', 'alpha_dash', Rule::unique('courses')->where('professional_school_id', $professionalSchool->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in([Course::STATUS_ACTIVE, Course::STATUS_INACTIVE])],
        ]);
        abort_unless($professionalSchool->programmes()->whereKey($data['programme_id'])->exists(), 422);
        $data['code'] = $this->referenceCode($data['code'] ?? null, $data['name'], Course::query()->where('professional_school_id', $professionalSchool->id));

        $professionalSchool->courses()->create($data);

        return back()->with('success', 'Course created.');
    }

    public function modules(Request $request, ProfessionalSchool $professionalSchool): Response
    {
        $this->authorizeRecord($request->user(), $professionalSchool);
        $courseQuery = $professionalSchool->courses()->orderBy('name');
        $moduleQuery = $professionalSchool->modules()->with(['programme:id,name', 'course:id,name'])->orderBy('name');
        $this->scopeFacilitatorCourses($courseQuery, $request->user());
        $this->scopeFacilitatorModules($moduleQuery, $request->user());

        return Inertia::render('ProfessionalSchools/Modules', [
            'professionalSchool' => $this->row($professionalSchool),
            'programmes' => $professionalSchool->programmes()->orderBy('name')->get(['id', 'name', 'code']),
            'courses' => $courseQuery->get(['id', 'programme_id', 'name', 'code']),
            'modules' => $moduleQuery->get(),
            'canManageStructure' => $request->user()->isSuperAdmin() || $request->user()->hasPermission('manageSchools'),
        ]);
    }

    public function storeModule(Request $request, ProfessionalSchool $professionalSchool): RedirectResponse
    {
        $this->authorizeRecord($request->user(), $professionalSchool, update: true);

        $data = $request->validate([
            'programme_id' => ['nullable', 'exists:programmes,id'],
            'course_id' => ['required', 'exists:courses,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', 'alpha_dash', Rule::unique('modules')->where('professional_school_id', $professionalSchool->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in([ProfessionalModule::STATUS_ACTIVE, ProfessionalModule::STATUS_INACTIVE])],
        ]);
        abort_unless($professionalSchool->courses()->whereKey($data['course_id'])->exists(), 422);
        $data['code'] = $this->referenceCode($data['code'] ?? null, $data['name'], ProfessionalModule::query()->where('professional_school_id', $professionalSchool->id));

        $professionalSchool->modules()->create($data);

        return back()->with('success', 'Module created.');
    }

    public function batches(Request $request, ProfessionalSchool $professionalSchool): Response
    {
        $this->authorizeRecord($request->user(), $professionalSchool);

        return Inertia::render('ProfessionalSchools/Batches', [
            'professionalSchool' => $this->row($professionalSchool),
            'programmes' => $professionalSchool->programmes()->orderBy('name')->get(['id', 'name', 'code']),
            'batches' => $professionalSchool->trainingBatches()->with('programme:id,name')->withCount('candidates')->latest()->get(),
        ]);
    }

    public function storeBatch(Request $request, ProfessionalSchool $professionalSchool): RedirectResponse
    {
        $this->authorizeRecord($request->user(), $professionalSchool, update: true);

        $data = $request->validate([
            'programme_id' => ['required', 'exists:programmes,id'],
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);
        abort_unless($professionalSchool->programmes()->whereKey($data['programme_id'])->exists(), 422);

        $professionalSchool->trainingBatches()->create([
            'programme_id' => $data['programme_id'],
            'name' => $data['name'],
            'code' => strtoupper(str($data['name'])->slug('-')->limit(40, '')->toString()),
            'starts_on' => $data['start_date'] ?? null,
            'ends_on' => $data['end_date'] ?? null,
            'status' => $data['status'],
        ]);

        return back()->with('success', 'Training batch created.');
    }

    public function updateBatch(Request $request, ProfessionalSchool $professionalSchool, TrainingBatch $trainingBatch): RedirectResponse
    {
        $this->authorizeRecord($request->user(), $professionalSchool, update: true);
        abort_unless((int) $trainingBatch->professional_school_id === (int) $professionalSchool->id, 403);

        $data = $request->validate([
            'programme_id' => ['required', 'exists:programmes,id'],
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);
        abort_unless($professionalSchool->programmes()->whereKey($data['programme_id'])->exists(), 422);

        $trainingBatch->update([
            'programme_id' => $data['programme_id'],
            'name' => $data['name'],
            'starts_on' => $data['start_date'] ?? null,
            'ends_on' => $data['end_date'] ?? null,
            'status' => $data['status'],
        ]);

        return back()->with('success', 'Training batch updated.');
    }

    public function destroyBatch(Request $request, ProfessionalSchool $professionalSchool, TrainingBatch $trainingBatch): RedirectResponse
    {
        $this->authorizeRecord($request->user(), $professionalSchool, update: true);
        abort_unless((int) $trainingBatch->professional_school_id === (int) $professionalSchool->id, 403);
        abort_if($trainingBatch->candidates()->exists(), 422, 'Move candidates out of this batch before deleting it.');

        $trainingBatch->delete();

        return back()->with('success', 'Training batch deleted.');
    }

    public function facilitators(Request $request, ProfessionalSchool $professionalSchool): Response
    {
        $this->authorizeRecord($request->user(), $professionalSchool, update: true);

        return Inertia::render('ProfessionalSchools/Facilitators', [
            'professionalSchool' => $this->row($professionalSchool),
            'courses' => $professionalSchool->courses()->with('programme:id,name')->orderBy('name')->get(['id', 'programme_id', 'name', 'code']),
            'facilitators' => User::query()
                ->where('role', User::ROLE_FACILITATOR)
                ->where('professional_school_id', $professionalSchool->id)
                ->with(['assignedCourses:id,name,code'])
                ->orderBy('name')
                ->get()
                ->map(fn (User $facilitator) => $this->facilitatorRow($facilitator)),
        ]);
    }

    public function storeFacilitator(Request $request, ProfessionalSchool $professionalSchool): RedirectResponse
    {
        $this->authorizeRecord($request->user(), $professionalSchool, update: true);
        $data = $request->validate($this->facilitatorRules());

        DB::transaction(function () use ($professionalSchool, $data): void {
            $facilitator = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => User::ROLE_FACILITATOR,
                'organization_id' => $professionalSchool->organization_id,
                'professional_school_id' => $professionalSchool->id,
                'active_context_type' => 'professional_school',
                'active_context_id' => $professionalSchool->id,
            ]);

            $this->syncFacilitatorAssignments($facilitator, $professionalSchool, $data['course_ids']);
        });

        return back()->with('success', 'Facilitator created.');
    }

    public function updateFacilitator(Request $request, ProfessionalSchool $professionalSchool, User $facilitator): RedirectResponse
    {
        $this->authorizeRecord($request->user(), $professionalSchool, update: true);
        $this->authorizeFacilitatorRecord($professionalSchool, $facilitator);
        $data = $request->validate($this->facilitatorRules($facilitator));

        DB::transaction(function () use ($facilitator, $professionalSchool, $data): void {
            $payload = [
                'name' => $data['name'],
                'email' => $data['email'],
                'organization_id' => $professionalSchool->organization_id,
                'professional_school_id' => $professionalSchool->id,
                'active_context_type' => 'professional_school',
                'active_context_id' => $professionalSchool->id,
            ];

            if (filled($data['password'] ?? null)) {
                $payload['password'] = Hash::make($data['password']);
            }

            $facilitator->update($payload);
            $this->syncFacilitatorAssignments($facilitator, $professionalSchool, $data['course_ids']);
        });

        return back()->with('success', 'Facilitator updated.');
    }

    public function destroyFacilitator(Request $request, ProfessionalSchool $professionalSchool, User $facilitator): RedirectResponse
    {
        $this->authorizeRecord($request->user(), $professionalSchool, update: true);
        $this->authorizeFacilitatorRecord($professionalSchool, $facilitator);

        $facilitator->delete();

        return back()->with('success', 'Facilitator deleted.');
    }

    public function questionBanks(Request $request, ProfessionalSchool $professionalSchool): Response
    {
        $this->authorizeRecord($request->user(), $professionalSchool);
        $courseQuery = $professionalSchool->courses()->orderBy('name');
        $moduleQuery = $professionalSchool->modules()->orderBy('name');
        $bankQuery = $professionalSchool->questionBanks()
            ->with(['programme:id,name', 'course:id,name', 'module:id,name', 'subject:id,name'])
            ->withCount('questions')
            ->orderBy('name');
        $this->scopeFacilitatorCourses($courseQuery, $request->user());
        $this->scopeFacilitatorModules($moduleQuery, $request->user());
        $this->scopeFacilitatorQuestionBanks($bankQuery, $request->user());

        return Inertia::render('ProfessionalSchools/QuestionBanks', [
            'professionalSchool' => $this->row($professionalSchool),
            'programmes' => $professionalSchool->programmes()->orderBy('name')->get(['id', 'name', 'code']),
            'courses' => $courseQuery->get(['id', 'programme_id', 'name', 'code']),
            'modules' => $moduleQuery->get(['id', 'programme_id', 'course_id', 'name', 'code']),
            'subjects' => Subject::query()->where('professional_school_id', $professionalSchool->id)->orderBy('name')->get(['id', 'name', 'code']),
            'questionBanks' => $bankQuery->get(),
        ]);
    }

    public function storeQuestionBank(Request $request, ProfessionalSchool $professionalSchool): RedirectResponse
    {
        $this->authorizeQuestionBankContent($request->user(), $professionalSchool);

        $data = $request->validate([
            'programme_id' => ['nullable', 'exists:programmes,id'],
            'course_id' => ['required', 'exists:courses,id'],
            'module_id' => ['required', 'exists:modules,id'],
            'subject_id' => ['nullable', 'exists:subjects,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', 'alpha_dash', Rule::unique('question_banks')->where('professional_school_id', $professionalSchool->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in([QuestionBank::STATUS_DRAFT, QuestionBank::STATUS_ACTIVE, QuestionBank::STATUS_ARCHIVED])],
        ]);

        $course = $professionalSchool->courses()->whereKey($data['course_id'])->firstOrFail();
        $data['programme_id'] = $course->programme_id;
        abort_unless($professionalSchool->modules()->whereKey($data['module_id'])->where('course_id', $data['course_id'])->exists(), 422);
        abort_unless(! filled($data['subject_id'] ?? null) || Subject::query()->whereKey($data['subject_id'])->where('professional_school_id', $professionalSchool->id)->exists(), 422);
        $this->authorizeFacilitatorCourseModule($request->user(), $data['course_id'], $data['module_id']);

        if (! filled($data['subject_id'] ?? null)) {
            $module = $professionalSchool->modules()->with('course:id,name,code')->whereKey($data['module_id'])->firstOrFail();
            $data['subject_id'] = $this->subjectForProfessionalModule($professionalSchool, $module)->id;
        }
        $data['code'] = $this->referenceCode($data['code'] ?? null, $data['name'], QuestionBank::query()->where('professional_school_id', $professionalSchool->id));

        $professionalSchool->questionBanks()->create([
            ...$data,
            'owner_type' => Exam::OWNER_PROFESSIONAL_SCHOOL,
            'owner_id' => $professionalSchool->id,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Question bank created.');
    }

    public function questions(Request $request, ProfessionalSchool $professionalSchool): Response
    {
        $this->authorizeRecord($request->user(), $professionalSchool);
        $questionBankScope = fn (Builder $query) => $this->scopeFacilitatorQuestionBanks($query->where('professional_school_id', $professionalSchool->id), $request->user());
        $bankQuery = $professionalSchool->questionBanks()
            ->where('status', QuestionBank::STATUS_ACTIVE)
            ->with(['course:id,name', 'module:id,name'])
            ->orderBy('name');
        $this->scopeFacilitatorQuestionBanks($bankQuery, $request->user());

        return Inertia::render('ProfessionalSchools/Questions', [
            'professionalSchool' => $this->row($professionalSchool),
            'questions' => Question::query()
                ->whereHas('questionBank', $questionBankScope)
                ->with(['questionBank:id,name,course_id,module_id', 'questionBank.course:id,name', 'questionBank.module:id,name', 'subject:id,name', 'topic:id,name', 'options'])
                ->latest()
                ->get()
                ->map(fn (Question $question) => [
                    'id' => $question->id,
                    'stem' => str($question->stem)->limit(140)->toString(),
                    'question_bank_name' => $question->questionBank?->name,
                    'course_name' => $question->questionBank?->course?->name,
                    'module_name' => $question->questionBank?->module?->name,
                    'topic_name' => $question->topic?->name,
                    'difficulty' => $question->difficulty,
                    'marks' => $question->marks,
                    'options_count' => $question->options->count(),
                    'status' => $question->status,
                ]),
            'questionBanks' => $bankQuery
                ->get(['id', 'name', 'code', 'course_id', 'module_id', 'subject_id'])
                ->map(fn (QuestionBank $bank) => [
                    'id' => $bank->id,
                    'name' => $bank->name,
                    'code' => $bank->code,
                    'course_name' => $bank->course?->name,
                    'module_name' => $bank->module?->name,
                ]),
            'importSummary' => $request->session()->get('professional_question_import_summary'),
        ]);
    }

    public function questionTemplate(Request $request, ProfessionalSchool $professionalSchool): StreamedResponse
    {
        $this->authorizeQuestionBankContent($request->user(), $professionalSchool);

        return response()->streamDownload(function (): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['difficulty', 'marks', 'question_text', 'explanation', 'status', 'option_a', 'option_b', 'option_c', 'option_d', 'option_e', 'correct_answer']);
            fputcsv($output, ['medium', '1', 'What is the purpose of internal control?', 'Internal control supports reliable reporting and risk reduction.', 'draft', 'To eliminate all risk', 'To support reliable operations', 'To replace audit work', 'To increase liabilities', '', 'B']);
            fclose($output);
        }, 'professional_question_template.csv', ['Content-Type' => 'text/csv']);
    }

    public function importQuestions(Request $request, ProfessionalSchool $professionalSchool): RedirectResponse
    {
        $this->authorizeQuestionBankContent($request->user(), $professionalSchool);

        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:4096'],
            'question_bank_id' => ['required', 'string', 'exists:question_banks,id'],
        ]);

        $questionBank = $professionalSchool->questionBanks()
            ->whereKey($data['question_bank_id'])
            ->firstOrFail();
        abort_unless($this->facilitatorCanAccessBank($request->user(), $questionBank), 403);

        $created = 0;
        $failed = [];

        DB::transaction(function () use ($request, $questionBank, &$created, &$failed): void {
            foreach ($this->csvRows($request->file('file')->getRealPath()) as $index => $row) {
                $line = $index + 2;
                $correctAnswer = strtoupper(trim((string) ($row['correct_answer'] ?? '')));
                $options = [
                    ['label' => 'A', 'option_text' => trim((string) ($row['option_a'] ?? '')), 'is_correct' => $correctAnswer === 'A'],
                    ['label' => 'B', 'option_text' => trim((string) ($row['option_b'] ?? '')), 'is_correct' => $correctAnswer === 'B'],
                    ['label' => 'C', 'option_text' => trim((string) ($row['option_c'] ?? '')), 'is_correct' => $correctAnswer === 'C'],
                    ['label' => 'D', 'option_text' => trim((string) ($row['option_d'] ?? '')), 'is_correct' => $correctAnswer === 'D'],
                    ['label' => 'E', 'option_text' => trim((string) ($row['option_e'] ?? '')), 'is_correct' => $correctAnswer === 'E'],
                ];

                try {
                    validator([
                        'stem' => trim((string) ($row['question_text'] ?? '')),
                        'difficulty' => trim((string) ($row['difficulty'] ?? 'medium')) ?: 'medium',
                        'marks' => trim((string) ($row['marks'] ?? '1')) ?: '1',
                        'status' => trim((string) ($row['status'] ?? Question::STATUS_DRAFT)) ?: Question::STATUS_DRAFT,
                        'options' => $options,
                    ], [
                        'stem' => ['required', 'string'],
                        'difficulty' => ['required', Rule::in(['easy', 'medium', 'hard'])],
                        'marks' => ['required', 'numeric', 'min:0.01'],
                        'status' => ['required', Rule::in([Question::STATUS_DRAFT, Question::STATUS_REVIEW, Question::STATUS_APPROVED, Question::STATUS_REJECTED, Question::STATUS_ARCHIVED])],
                    ])->after(function ($validator) use ($options): void {
                        $filledOptions = collect($options)->filter(fn (array $option) => filled($option['option_text'] ?? null));

                        if ($filledOptions->count() < 2) {
                            $validator->errors()->add('options', 'At least two options are required.');
                        }

                        if ($filledOptions->filter(fn (array $option) => $option['is_correct'])->count() !== 1) {
                            $validator->errors()->add('options', 'Choose exactly one correct answer.');
                        }
                    })->validate();

                    $question = Question::query()->create([
                        'question_bank_id' => $questionBank->id,
                        'subject_id' => $questionBank->subject_id,
                        'created_by' => $request->user()->id,
                        'question_type' => Question::TYPE_SINGLE_CHOICE,
                        'stem' => trim((string) ($row['question_text'] ?? '')),
                        'explanation' => filled($row['explanation'] ?? null) ? trim((string) $row['explanation']) : null,
                        'difficulty' => trim((string) ($row['difficulty'] ?? 'medium')) ?: 'medium',
                        'marks' => trim((string) ($row['marks'] ?? '1')) ?: '1',
                        'status' => trim((string) ($row['status'] ?? Question::STATUS_DRAFT)) ?: Question::STATUS_DRAFT,
                    ]);

                    $this->syncQuestionOptions($question, $options);
                    $created++;
                } catch (\Throwable $exception) {
                    $failed[] = [
                        'row' => $line,
                        'question' => str((string) ($row['question_text'] ?? ''))->limit(80)->toString(),
                        'reason' => $exception instanceof \Illuminate\Validation\ValidationException
                            ? $exception->errors()[array_key_first($exception->errors())][0]
                            : $exception->getMessage(),
                    ];
                }
            }
        });

        return back()
            ->with('success', "{$created} questions imported.")
            ->with('professional_question_import_summary', [
                'created' => $created,
                'failed' => $failed,
            ]);
    }

    public function certificates(Request $request, ProfessionalSchool $professionalSchool): Response
    {
        $this->authorizeRecord($request->user(), $professionalSchool);

        return Inertia::render('ProfessionalSchools/Certificates', [
            'professionalSchool' => $this->row($professionalSchool),
            'certificates' => $professionalSchool->certificates()
                ->with(['candidate:id,candidate_number,first_name,last_name', 'exam:id,title,code'])
                ->latest('issued_at')
                ->get()
                ->map(fn (Certificate $certificate) => [
                    'id' => $certificate->id,
                    'serial_number' => $certificate->serial_number,
                    'candidate_name' => trim(($certificate->candidate?->first_name ?? '').' '.($certificate->candidate?->last_name ?? '')),
                    'registration_number' => $certificate->candidate?->candidate_number,
                    'exam_title' => $certificate->exam?->title,
                    'exam_code' => $certificate->exam?->code,
                    'status' => $certificate->status,
                    'issued_at' => $certificate->issued_at?->toISOString(),
                    'expires_at' => $certificate->expires_at?->toISOString(),
                ]),
        ]);
    }

    public function candidates(Request $request, ProfessionalSchool $professionalSchool): Response
    {
        $this->authorizeRecord($request->user(), $professionalSchool);

        return Inertia::render('ProfessionalSchools/Candidates', [
            'professionalSchool' => $this->row($professionalSchool),
            'programmes' => $professionalSchool->programmes()->orderBy('name')->get(['id', 'name']),
            'courses' => $professionalSchool->courses()->orderBy('name')->get(['id', 'programme_id', 'name']),
            'batches' => $professionalSchool->trainingBatches()->orderBy('name')->get(['id', 'programme_id', 'name']),
            'candidates' => $professionalSchool->candidates()->with(['programme', 'course', 'trainingBatch'])->latest()->get()->map(fn (Candidate $candidate) => $this->candidateRow($candidate)),
            'importSummary' => $request->session()->get('professional_candidate_import_summary'),
        ]);
    }

    public function storeCandidate(Request $request, ProfessionalSchool $professionalSchool): RedirectResponse
    {
        $this->authorizeRecord($request->user(), $professionalSchool, update: true);

        $data = $request->validate([
            'training_batch_id' => ['nullable', 'exists:training_batches,id'],
            'registration_number' => ['required', 'string', 'max:100', Rule::unique('candidates', 'candidate_number')->where('professional_school_id', $professionalSchool->id)],
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'status' => ['required', Rule::in([Candidate::STATUS_ACTIVE, Candidate::STATUS_INACTIVE, Candidate::STATUS_SUSPENDED])],
        ]);
        $batch = filled($data['training_batch_id'] ?? null)
            ? $professionalSchool->trainingBatches()->whereKey($data['training_batch_id'])->firstOrFail()
            : null;

        [$firstName, $lastName] = $this->splitFullName($data['full_name']);
        $professionalSchool->candidates()->create([
            'programme_id' => $batch?->programme_id,
            'course_id' => null,
            'training_batch_id' => $batch?->id,
            'candidate_number' => strtoupper($data['registration_number']),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'status' => $data['status'],
            'metadata' => ['source' => 'professional_school'],
        ]);

        return back()->with('success', 'Candidate registered.');
    }

    public function candidateTemplate(Request $request, ProfessionalSchool $professionalSchool): StreamedResponse
    {
        $this->authorizeRecord($request->user(), $professionalSchool, update: true);

        return response()->streamDownload(function (): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['full_name', 'registration_number', 'email', 'phone', 'status']);
            fputcsv($output, ['Ada Okafor', 'REG-001', 'ada@example.com', '08030000000', 'active']);
            fclose($output);
        }, 'professional_candidate_template.csv', ['Content-Type' => 'text/csv']);
    }

    public function importCandidates(Request $request, ProfessionalSchool $professionalSchool): RedirectResponse
    {
        $this->authorizeRecord($request->user(), $professionalSchool, update: true);

        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:4096'],
            'training_batch_id' => ['required', 'exists:training_batches,id'],
        ]);

        $batch = $professionalSchool->trainingBatches()->whereKey($data['training_batch_id'])->firstOrFail();
        $created = 0;
        $skipped = 0;
        $failed = [];

        foreach ($this->csvRows($request->file('file')->getRealPath()) as $index => $row) {
            $line = $index + 2;
            $registrationNumber = strtoupper(trim((string) ($row['registration_number'] ?? '')));

            try {
                $status = filled($row['status'] ?? null) ? strtolower(trim((string) $row['status'])) : Candidate::STATUS_ACTIVE;
                $fullName = trim((string) ($row['full_name'] ?? ''));

                validator([
                    'registration_number' => $registrationNumber,
                    'full_name' => $fullName,
                    'email' => $row['email'] ?? null,
                    'phone' => $row['phone'] ?? null,
                    'status' => $status,
                ], [
                    'registration_number' => ['required', 'string', 'max:100'],
                    'full_name' => ['required', 'string', 'max:255'],
                    'email' => ['nullable', 'email', 'max:255'],
                    'phone' => ['nullable', 'string', 'max:50'],
                    'status' => [Rule::in([Candidate::STATUS_ACTIVE, Candidate::STATUS_INACTIVE, Candidate::STATUS_SUSPENDED])],
                ])->validate();

                if ($professionalSchool->candidates()->where('candidate_number', $registrationNumber)->exists()) {
                    $skipped++;
                    continue;
                }

                [$firstName, $lastName] = $this->splitFullName($fullName);
                $professionalSchool->candidates()->create([
                    'programme_id' => $batch->programme_id,
                    'course_id' => null,
                    'training_batch_id' => $batch->id,
                    'candidate_number' => $registrationNumber,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => filled($row['email'] ?? null) ? trim((string) $row['email']) : null,
                    'phone' => filled($row['phone'] ?? null) ? trim((string) $row['phone']) : null,
                    'status' => $status,
                    'metadata' => ['source' => 'professional_candidate_import'],
                ]);

                $created++;
            } catch (\Throwable $exception) {
                $failed[] = [
                    'row' => $line,
                    'registration_number' => $registrationNumber ?: 'N/A',
                    'reason' => $exception instanceof \Illuminate\Validation\ValidationException
                        ? $exception->errors()[array_key_first($exception->errors())][0]
                        : $exception->getMessage(),
                ];
            }
        }

        return back()
            ->with('success', "{$created} candidates imported. {$skipped} duplicates skipped.")
            ->with('professional_candidate_import_summary', [
                'created' => $created,
                'skipped' => $skipped,
                'failed' => $failed,
            ]);
    }

    private function canList(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->isOrganizationAdmin()
            || $user->isProfessionalSchoolAdmin()
            || $user->hasPermission('viewReports');
    }

    private function scope(User $user): Builder
    {
        return ProfessionalSchool::query()
            ->when(! $user->isSuperAdmin() && $user->organization_id, fn (Builder $query) => $query->where('organization_id', $user->organization_id))
            ->when(! $user->isSuperAdmin() && $user->professional_school_id, fn (Builder $query) => $query->whereKey($user->professional_school_id));
    }

    private function authorizeRecord(User $user, ProfessionalSchool $professionalSchool, bool $update = false): void
    {
        $allowed = $user->isSuperAdmin()
            || ($user->organization_id && (string) $user->organization_id === (string) $professionalSchool->organization_id)
            || ($user->professional_school_id && (string) $user->professional_school_id === (string) $professionalSchool->id);

        abort_unless($allowed, 403);
        abort_if($update && ! ($user->isSuperAdmin() || $user->hasPermission('manageSchools')), 403);
    }

    private function authorizeQuestionBankContent(User $user, ProfessionalSchool $professionalSchool): void
    {
        $this->authorizeRecord($user, $professionalSchool);
        abort_unless($user->isSuperAdmin() || $user->hasPermission('manageSchools') || $user->hasPermission('manageQuestionBank'), 403);
    }

    private function scopeFacilitatorCourses($query, User $user): void
    {
        if (! $user->isFacilitator()) {
            return;
        }

        $query->whereIn('id', $user->assignedCourses()->select('courses.id'));
    }

    private function scopeFacilitatorModules($query, User $user): void
    {
        if (! $user->isFacilitator()) {
            return;
        }

        $query->where(function (Builder $scope) use ($user): void {
            $scope
                ->whereIn('course_id', $user->assignedCourses()->select('courses.id'))
                ->orWhereIn('id', $user->assignedModules()->select('modules.id'));
        });
    }

    private function scopeFacilitatorQuestionBanks($query, User $user): void
    {
        if (! $user->isFacilitator()) {
            return;
        }

        $query->where(function (Builder $scope) use ($user): void {
            $scope
                ->whereIn('course_id', $user->assignedCourses()->select('courses.id'))
                ->orWhereIn('module_id', $user->assignedModules()->select('modules.id'));
        });
    }

    private function facilitatorCanAccessBank(User $user, QuestionBank $questionBank): bool
    {
        if (! $user->isFacilitator()) {
            return true;
        }

        return (string) $questionBank->professional_school_id === (string) $user->professional_school_id
            && (
                ($questionBank->course_id && $user->assignedCourses()->whereKey($questionBank->course_id)->exists())
                || ($questionBank->module_id && $user->assignedModules()->whereKey($questionBank->module_id)->exists())
            );
    }

    private function authorizeFacilitatorCourseModule(User $user, string|int $courseId, string|int $moduleId): void
    {
        if (! $user->isFacilitator()) {
            return;
        }

        $allowed = $user->assignedCourses()->whereKey($courseId)->exists()
            && ProfessionalModule::query()
                ->whereKey($moduleId)
                ->where('course_id', $courseId)
                ->where(function (Builder $query) use ($user): void {
                    $query
                        ->whereIn('course_id', $user->assignedCourses()->select('courses.id'))
                        ->orWhereIn('id', $user->assignedModules()->select('modules.id'));
                })
                ->exists();

        abort_unless($allowed, 403);
    }

    private function rules(?ProfessionalSchool $professionalSchool = null): array
    {
        return [
            'organization_id' => ['nullable', 'exists:organizations,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', 'alpha_dash', Rule::unique('professional_schools', 'code')->ignore($professionalSchool)],
            'contact_person' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('professional_schools', 'email')->ignore($professionalSchool)],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in([ProfessionalSchool::STATUS_ACTIVE, ProfessionalSchool::STATUS_INACTIVE])],
        ];
    }

    private function referenceCode(?string $code, string $name, $query): string
    {
        if (filled($code)) {
            return strtoupper($code);
        }

        return ReferenceCode::unique($name, $query);
    }

    private function formOptions(): array
    {
        return [
            'organizations' => Organization::query()->orderBy('name')->get(['id', 'name', 'code']),
            'statuses' => [
                ['value' => ProfessionalSchool::STATUS_ACTIVE, 'label' => 'Active'],
                ['value' => ProfessionalSchool::STATUS_INACTIVE, 'label' => 'Inactive'],
            ],
        ];
    }

    private function facilitatorRules(?User $facilitator = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($facilitator)],
            'password' => [$facilitator ? 'nullable' : 'required', 'string', 'min:8', 'max:255'],
            'course_ids' => ['required', 'array', 'min:1'],
            'course_ids.*' => ['required', 'integer', 'exists:courses,id', 'distinct'],
        ];
    }

    private function authorizeFacilitatorRecord(ProfessionalSchool $professionalSchool, User $facilitator): void
    {
        abort_unless($facilitator->role === User::ROLE_FACILITATOR && (string) $facilitator->professional_school_id === (string) $professionalSchool->id, 404);
    }

    private function syncFacilitatorAssignments(User $facilitator, ProfessionalSchool $professionalSchool, array $courseIds): void
    {
        $courses = $professionalSchool->courses()
            ->whereIn('id', $courseIds)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        abort_unless(count($courses) === count(array_unique($courseIds)), 422);

        DB::table('course_facilitator')->where('user_id', $facilitator->id)->delete();

        $now = now();
        $rows = collect($courses)->map(fn ($courseId) => [
            'user_id' => $facilitator->id,
            'professional_school_id' => $professionalSchool->id,
            'course_id' => $courseId,
            'module_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->values()->all();

        DB::table('course_facilitator')->insert($rows);
    }

    private function row(ProfessionalSchool $school): array
    {
        return [
            'id' => $school->id,
            'organization_id' => $school->organization_id,
            'organization_name' => $school->organization?->name,
            'name' => $school->name,
            'code' => $school->code,
            'contact_person' => $school->contact_person,
            'email' => $school->email,
            'phone' => $school->phone,
            'address' => $school->address,
            'status' => $school->status,
            'status_label' => str($school->status)->replace('_', ' ')->title()->toString(),
            'programmes_count' => $school->programmes_count ?? 0,
            'candidates_count' => $school->candidates_count ?? 0,
            'exams_count' => $school->exams_count ?? 0,
        ];
    }

    private function facilitatorRow(User $facilitator): array
    {
        return [
            'id' => $facilitator->id,
            'name' => $facilitator->name,
            'email' => $facilitator->email,
            'course_ids' => $facilitator->assignedCourses->pluck('id')->unique()->values()->all(),
            'courses' => $facilitator->assignedCourses->unique('id')->map(fn (Course $course) => [
                'id' => $course->id,
                'name' => $course->name,
                'code' => $course->code,
            ])->values()->all(),
        ];
    }

    private function detail(ProfessionalSchool $school): array
    {
        return [
            ...$this->row($school),
            'courses_count' => $school->courses_count ?? 0,
            'modules_count' => $school->modules_count ?? 0,
            'training_batches_count' => $school->training_batches_count ?? 0,
            'question_banks_count' => $school->question_banks_count ?? 0,
            'certificates_count' => $school->certificates_count ?? 0,
            'programmes' => $school->programmes->map(fn (Programme $programme) => [
                'id' => $programme->id,
                'name' => $programme->name,
                'code' => $programme->code,
                'duration' => $programme->duration,
                'courses_count' => $programme->courses_count ?? 0,
                'training_batches_count' => $programme->training_batches_count ?? 0,
            ]),
            'courses' => $school->courses->map(fn (Course $course) => [
                'id' => $course->id,
                'name' => $course->name,
                'code' => $course->code,
                'programme_name' => $course->programme?->name,
            ]),
            'batches' => $school->trainingBatches->map(fn (TrainingBatch $batch) => [
                'id' => $batch->id,
                'name' => $batch->name,
                'programme_name' => $batch->programme?->name,
                'status' => $batch->status,
            ]),
            'candidates' => $school->candidates->map(fn (Candidate $candidate) => $this->candidateRow($candidate)),
            'recent_exams' => $school->exams->map(fn (Exam $exam) => [
                'id' => $exam->id,
                'title' => $exam->title,
                'code' => $exam->code,
                'category' => $exam->exam_category,
                'mode' => $exam->exam_mode ?? $exam->mode,
                'status' => $exam->status,
            ]),
            'certificates' => $school->certificates->map(fn (Certificate $certificate) => [
                'id' => $certificate->id,
                'serial_number' => $certificate->serial_number,
                'candidate_name' => trim(($certificate->candidate?->first_name ?? '').' '.($certificate->candidate?->last_name ?? '')),
                'status' => $certificate->status,
            ]),
        ];
    }

    private function dashboard(ProfessionalSchool $school): array
    {
        $exams = $school->exams();

        return [
            'total_candidates' => $school->candidates()->count(),
            'total_programmes' => $school->programmes()->count(),
            'total_courses' => $school->courses()->count(),
            'total_modules' => $school->modules()->count(),
            'training_batches' => $school->trainingBatches()->count(),
            'professional_exams' => (clone $exams)->where('exam_category', Exam::CATEGORY_PROFESSIONAL)->count(),
            'adaptive_exams' => $school->exams()->where(fn (Builder $query) => $query->where('exam_mode', Exam::MODE_ADAPTIVE)->orWhere('mode', Exam::MODE_ADAPTIVE))->count(),
            'certification_exams' => $school->exams()->where('exam_category', Exam::CATEGORY_CERTIFICATION)->count(),
            'certificates_generated' => $school->certificates()->count(),
        ];
    }

    private function candidateRow(Candidate $candidate): array
    {
        return [
            'id' => $candidate->id,
            'registration_number' => $candidate->candidate_number,
            'full_name' => trim($candidate->first_name.' '.$candidate->last_name),
            'programme_name' => $candidate->programme?->name,
            'course_name' => $candidate->course?->name,
            'batch_name' => $candidate->trainingBatch?->name,
            'email' => $candidate->email,
            'phone' => $candidate->phone,
            'status' => $candidate->status,
        ];
    }

    private function subjectForProfessionalModule(ProfessionalSchool $professionalSchool, ProfessionalModule $module): Subject
    {
        $code = strtoupper($module->code ?: str($module->name)->slug('-')->limit(40, '')->toString());
        $name = trim(($module->course?->name ? "{$module->course->name} - " : '').$module->name);

        return Subject::query()->firstOrCreate(
            [
                'professional_school_id' => $professionalSchool->id,
                'code' => $code,
            ],
            [
                'organization_id' => $professionalSchool->organization_id,
                'owner_type' => Exam::OWNER_PROFESSIONAL_SCHOOL,
                'owner_id' => $professionalSchool->id,
                'name' => $name,
                'description' => 'Internal course/module mapping for professional exam paper generation.',
                'status' => Subject::STATUS_ACTIVE,
            ]
        );
    }

    private function splitFullName(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName), 2) ?: [];

        return [$parts[0] ?? '', $parts[1] ?? ''];
    }

    private function syncQuestionOptions(Question $question, array $options): void
    {
        collect($options)
            ->filter(fn (array $option) => filled($option['option_text'] ?? null))
            ->values()
            ->each(fn (array $option, int $index) => $question->options()->create([
                'label' => $option['label'],
                'option_text' => $option['option_text'],
                'display_order' => $index + 1,
                'is_correct' => (bool) $option['is_correct'],
            ]));
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function csvRows(string $path): array
    {
        $handle = fopen($path, 'r');
        $headers = array_map(
            fn ($header) => str((string) $header)->lower()->trim()->replace([' ', '-'], '_')->toString(),
            fgetcsv($handle) ?: []
        );
        $rows = [];

        while (($values = fgetcsv($handle)) !== false) {
            if (count(array_filter($values, fn ($value) => filled($value))) === 0) {
                continue;
            }

            $rows[] = array_combine($headers, array_pad($values, count($headers), '')) ?: [];
        }

        fclose($handle);

        return $rows;
    }

    private function lookupKey(string $value): string
    {
        return str($value)->lower()->trim()->squish()->toString();
    }
}
