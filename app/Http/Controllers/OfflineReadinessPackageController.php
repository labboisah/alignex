<?php

namespace App\Http\Controllers;

use App\Models\OfflineReadinessPackage;
use App\Models\QuestionBank;
use App\Models\Subject;
use App\Models\Question;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OfflineReadinessPackageController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('OfflineReadinessPackages/Index', [
            'packages' => OfflineReadinessPackage::query()
                ->with('creator:id,name,email')
                ->latest()
                ->get()
                ->map(fn (OfflineReadinessPackage $package): array => $this->row($package)),
            'capacities' => OfflineReadinessPackage::CAPACITIES,
            'statuses' => OfflineReadinessPackage::STATUSES,
            'subjects' => $this->autobootSubjects()->where('status', Subject::STATUS_ACTIVE)->whereHas('questionBanks.questions', fn ($query) => $query->whereIn('status', [Question::STATUS_DRAFT, Question::STATUS_REVIEW, Question::STATUS_APPROVED]))->orderBy('name')->get(['id', 'name', 'code']),
            'questionBanks' => $this->autobootQuestionBanks()->where('status', QuestionBank::STATUS_ACTIVE)->whereHas('questions', fn ($query) => $query->whereIn('status', [Question::STATUS_DRAFT, Question::STATUS_REVIEW, Question::STATUS_APPROVED]))->with('subject:id,name')->withCount('questions')->orderBy('name')->get(['id', 'name', 'code', 'subject_id']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $package = OfflineReadinessPackage::query()->create($this->validated($request));

        return redirect()->route('offline-readiness-packages.index')
            ->with('success', "Readiness package {$package->code} created.");
    }

    public function update(Request $request, OfflineReadinessPackage $offlineReadinessPackage): RedirectResponse
    {
        abort_if($offlineReadinessPackage->status === OfflineReadinessPackage::STATUS_ACTIVE, 409, 'Retire an active package before changing its content.');

        $offlineReadinessPackage->update($this->validated($request, $offlineReadinessPackage));

        return redirect()->route('offline-readiness-packages.index')
            ->with('success', "Readiness package {$offlineReadinessPackage->code} updated.");
    }

    public function generatePaper(OfflineReadinessPackage $offlineReadinessPackage): RedirectResponse
    {
        abort_if($offlineReadinessPackage->status === OfflineReadinessPackage::STATUS_ACTIVE, 409, 'Retire the active package before regenerating its question paper.');

        $paperRows = $offlineReadinessPackage->paper_rows ?? [];
        if ($paperRows === []) {
            throw ValidationException::withMessages(['paper_rows' => 'This package has no saved subject rows. Edit the package before generating its paper.']);
        }

        $payload = $this->generatePaperPayload($paperRows);
        $offlineReadinessPackage->update([
            'payload' => $payload,
            'question_count' => (int) collect($paperRows)->sum('question_count'),
            'checksum_sha256' => hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
        ]);

        return back()->with('success', "Question paper generated for {$offlineReadinessPackage->code}.");
    }

    public function paper(OfflineReadinessPackage $offlineReadinessPackage): Response
    {
        return Inertia::render('OfflineReadinessPackages/Paper', [
            'package' => [
                'id' => $offlineReadinessPackage->id,
                'code' => $offlineReadinessPackage->code,
                'version' => $offlineReadinessPackage->version,
                'capacity_profile' => $offlineReadinessPackage->capacity_profile,
                'candidate_count' => $offlineReadinessPackage->candidate_count,
                'question_count' => $offlineReadinessPackage->question_count,
                'status' => $offlineReadinessPackage->status,
                'checksum_sha256' => $offlineReadinessPackage->checksum_sha256,
                'paper_rows' => $offlineReadinessPackage->paper_rows ?? [],
                'payload' => $offlineReadinessPackage->payload ?? ['subjects' => []],
                'created_at' => $offlineReadinessPackage->created_at?->toISOString(),
                'updated_at' => $offlineReadinessPackage->updated_at?->toISOString(),
            ],
        ]);
    }

    public function destroy(OfflineReadinessPackage $offlineReadinessPackage): RedirectResponse
    {
        abort_if($offlineReadinessPackage->status === OfflineReadinessPackage::STATUS_ACTIVE, 409, 'Retire an active package before deleting it.');
        $offlineReadinessPackage->delete();

        return redirect()->route('offline-readiness-packages.index')
            ->with('success', 'Readiness package deleted.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?OfflineReadinessPackage $package = null): array
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9][a-z0-9._-]+$/', Rule::unique(OfflineReadinessPackage::class, 'code')->where('version', $request->input('version'))->ignore($package)],
            'version' => ['required', 'string', 'max:80', 'regex:/^[a-zA-Z0-9._-]+$/'],
            'capacity_profile' => ['required', 'integer', Rule::in(OfflineReadinessPackage::CAPACITIES)],
            'question_count' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:10000'],
            'subject_count' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
            'status' => ['required', Rule::in(OfflineReadinessPackage::STATUSES)],
            'candidate_count' => ['required', 'integer', 'min:1', 'max:10000'],
            'paper_rows' => ['required', 'array', 'min:1', 'max:100'],
            'paper_rows.*.subject_id' => ['required', 'string', 'exists:subjects,id'],
            'paper_rows.*.question_bank_id' => ['required', 'string', 'exists:question_banks,id'],
            'paper_rows.*.question_count' => ['required', 'integer', 'min:1', 'max:10000'],
        ]);

        $capacity = (int) $validated['capacity_profile'];
        if ((int) $validated['candidate_count'] !== $capacity) {
            throw ValidationException::withMessages(['candidate_count' => "Candidate count must match the selected capacity of {$capacity}."]);
        }
        $rows = collect($validated['paper_rows'])->values();
        $subjectIds = $rows->pluck('subject_id')->unique()->values();
        $bankIds = $rows->pluck('question_bank_id')->unique()->values();
        $validSubjectCount = $this->autobootSubjects()->whereIn('id', $subjectIds)->count();
        $validBankCount = $this->autobootQuestionBanks()->whereIn('id', $bankIds)->whereIn('subject_id', $subjectIds)->count();
        if ($validSubjectCount !== $subjectIds->count()) {
            throw ValidationException::withMessages(['paper_rows' => 'Every subject must belong to the synthetic Autoboot resource scope.']);
        }
        if ($validBankCount !== $bankIds->count()) {
            throw ValidationException::withMessages(['paper_rows' => 'Every question bank must belong to the subject selected in its row.']);
        }
        $validated['question_count'] = (int) $rows->sum('question_count');
        $payload = $this->generatePaperPayload(collect($rows)->values()->all());
        $validated['payload'] = $payload;
        $validated['subject_count'] = $subjectIds->unique()->count();
        $validated['subject_ids'] = collect($subjectIds)->values()->all();
        $validated['question_bank_ids'] = collect($bankIds)->values()->all();
        $validated['backup_percent'] = 15.00;
        $validated['autoboot_target_clients'] = (int) ceil($capacity * 1.15);
        $validated['checksum_sha256'] = hash('sha256', json_encode($validated['payload'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $validated['created_by'] = $request->user()->id;

        if (($validated['status'] ?? null) === OfflineReadinessPackage::STATUS_ACTIVE) {
            DB::transaction(function () use (&$validated, $capacity, $package): void {
                OfflineReadinessPackage::query()
                    ->where('capacity_profile', $capacity)
                    ->when($package, fn ($query) => $query->where('id', '!=', $package->id))
                    ->where('status', OfflineReadinessPackage::STATUS_ACTIVE)
                    ->update(['status' => OfflineReadinessPackage::STATUS_RETIRED]);
            });
        }

        return $validated;
    }

    /** @return array<string, mixed> */
    private function generatePaperPayload(array $paperRows): array
    {
        $questions = collect($paperRows)->flatMap(function (array $row): Collection {
            $query = Question::query()
                ->where('subject_id', $row['subject_id'])
                ->where('question_bank_id', $row['question_bank_id'])
                ->whereHas('questionBank.organization', fn ($organization) => $organization->where('code', 'AUTOBOOT-SYNTHETIC'))
                ->whereIn('status', [Question::STATUS_DRAFT, Question::STATUS_REVIEW, Question::STATUS_APPROVED])
                ->with(['subject:id,name,code', 'options' => fn ($query) => $query->orderBy('display_order')])
                ->inRandomOrder()
                ->limit((int) $row['question_count']);
            $selected = $query->get();
            if ($selected->count() < (int) $row['question_count']) {
                throw ValidationException::withMessages(['paper_rows' => "The selected bank has only {$selected->count()} eligible questions for the requested {$row['question_count']}."]);
            }
            return $selected;
        })->values();

        return [
            'mode' => 'readiness_exam',
            'paper_rows' => $paperRows,
            'subjects' => $questions->groupBy('subject_id')->map(fn ($rows, $subjectId) => [
                'id' => (string) $subjectId,
                'name' => (string) $rows->first()->subject?->name,
                'code' => $rows->first()->subject?->code,
                'questions' => $rows->values()->map(fn (Question $question, int $index) => [
                    'id' => (string) $question->id,
                    'subject_id' => (string) $question->subject_id,
                    'body' => (string) $question->stem,
                    'marks' => (float) $question->marks,
                    'options' => $question->options->map(fn ($option) => [
                        'label' => (string) $option->label,
                        'text' => (string) $option->option_text,
                    ])->values()->all(),
                ])->all(),
            ])->values()->all(),
        ];
    }

    private function autobootSubjects()
    {
        return Subject::query()->whereHas('organization', fn ($organization) => $organization->where('code', 'AUTOBOOT-SYNTHETIC'));
    }

    private function autobootQuestionBanks()
    {
        return QuestionBank::query()->whereHas('organization', fn ($organization) => $organization->where('code', 'AUTOBOOT-SYNTHETIC'));
    }

    /** @return array<string, mixed> */
    private function row(OfflineReadinessPackage $package): array
    {
        return [
            'id' => $package->id,
            'code' => $package->code,
            'version' => $package->version,
            'capacity_profile' => $package->capacity_profile,
            'candidate_count' => $package->candidate_count,
            'backup_percent' => (float) $package->backup_percent,
            'autoboot_target_clients' => $package->autoboot_target_clients,
            'question_count' => $package->question_count,
            'subject_count' => $package->subject_count,
            'status' => $package->status,
            'checksum_sha256' => $package->checksum_sha256,
            'candidate_ids' => $package->candidate_ids ?? [],
            'subject_ids' => $package->subject_ids ?? [],
            'question_bank_ids' => $package->question_bank_ids ?? [],
            'paper_rows' => $package->paper_rows ?? [],
            'created_by' => $package->creator?->name,
            'created_at' => $package->created_at?->toISOString(),
        ];
    }
}
