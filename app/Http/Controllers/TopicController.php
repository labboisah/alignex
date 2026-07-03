<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTopicRequest;
use App\Http\Requests\UpdateTopicRequest;
use App\Http\Resources\SubjectResource;
use App\Http\Resources\TopicResource;
use App\Models\Subject;
use App\Models\Topic;
use App\Services\CurrentContextService;
use App\Support\ReferenceCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TopicController extends Controller
{
    public function index(Request $request): Response
    {
        $this->abortForSecondarySchool($request);
        Gate::authorize('viewAny', Topic::class);

        return Inertia::render('Topics/Index', [
            'topics' => TopicResource::collection(
                $this->scopedTopics($request)->with(['subject', 'parent'])->latest()->get()
            ),
            'can' => [
                'create' => $request->user()->can('create', Topic::class),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->abortForSecondarySchool($request);
        Gate::authorize('create', Topic::class);

        return Inertia::render('Topics/Create', [
            'statuses' => $this->statuses(),
            'subjects' => SubjectResource::collection($this->scopedSubjects($request)->orderBy('name')->get()),
            'topics' => TopicResource::collection($this->scopedTopics($request)->with('subject')->orderBy('name')->get()),
        ]);
    }

    public function store(StoreTopicRequest $request): RedirectResponse
    {
        $this->abortForSecondarySchool($request);
        $data = $request->validated();
        $subject = $this->authorizedSubject($request, $data['subject_id']);
        $this->authorizeParent($request, $data['parent_id'] ?? null, $subject);
        $data['code'] = $this->referenceCode($data['code'] ?? null, $data['name'], $subject);
        $this->ensureUniqueCode($data['code'], $subject);

        Topic::create($data);

        return redirect()->route('topics.index')->with('success', 'Topic created.');
    }

    public function edit(Request $request, Topic $topic): Response
    {
        $this->abortForSecondarySchool($request);
        Gate::authorize('update', $topic);

        return Inertia::render('Topics/Edit', [
            'topic' => TopicResource::make($topic->load(['subject', 'parent'])),
            'statuses' => $this->statuses(),
            'subjects' => SubjectResource::collection($this->scopedSubjects($request)->orderBy('name')->get()),
            'topics' => TopicResource::collection($this->scopedTopics($request)->whereKeyNot($topic->id)->with('subject')->orderBy('name')->get()),
        ]);
    }

    public function update(UpdateTopicRequest $request, Topic $topic): RedirectResponse
    {
        $this->abortForSecondarySchool($request);
        $data = $request->validated();
        $subject = $this->authorizedSubject($request, $data['subject_id']);
        $this->authorizeParent($request, $data['parent_id'] ?? null, $subject, $topic);
        $data['code'] = $this->referenceCode($data['code'] ?? null, $data['name'], $subject, $topic);
        $this->ensureUniqueCode($data['code'], $subject, $topic);

        $topic->update($data);

        return redirect()->route('topics.index')->with('success', 'Topic updated.');
    }

    public function destroy(Request $request, Topic $topic): RedirectResponse
    {
        $this->abortForSecondarySchool($request);
        Gate::authorize('delete', $topic);

        $topic->delete();

        return back()->with('success', 'Topic deleted.');
    }

    public function template()
    {
        $this->abortForSecondarySchool(request());
        Gate::authorize('create', Topic::class);

        return response()->streamDownload(function (): void {
            echo "subject_code,parent_code,name,code,description,status\n";
            echo "MATH,,Algebra,ALG,Algebra foundations,active\n";
        }, 'topics-template.csv', ['Content-Type' => 'text/csv']);
    }

    public function import(Request $request): RedirectResponse
    {
        $this->abortForSecondarySchool($request);
        Gate::authorize('create', Topic::class);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $rows = $this->csvRows($request->file('file')->getRealPath());
        $created = 0;

        DB::transaction(function () use ($request, $rows, &$created): void {
            foreach ($rows as $index => $row) {
                $subject = $this->subjectByCode($request, trim($row['subject_code'] ?? ''), $index + 2);
                $parent = $this->parentByCode($request, trim($row['parent_code'] ?? ''), $subject, $index + 2);
                $data = [
                    'subject_id' => $subject->id,
                    'parent_id' => $parent?->id,
                    'name' => trim($row['name'] ?? ''),
                    'code' => strtoupper(trim($row['code'] ?? '')),
                    'description' => $row['description'] ?? null,
                    'status' => trim($row['status'] ?? Topic::STATUS_ACTIVE) ?: Topic::STATUS_ACTIVE,
                ];

                validator($data, (new StoreTopicRequest())->rules())->validate();
                $this->ensureUniqueCode($data['code'], $subject, null, $index + 2);

                Topic::create($data);
                $created++;
            }
        });

        return back()->with('success', "{$created} topics imported.");
    }

    private function scopedTopics(Request $request)
    {
        return Topic::query()->whereHas('subject', fn ($query) => $this->applySubjectScope($query, $request));
    }

    private function abortForSecondarySchool(Request $request): void
    {
        $context = app(CurrentContextService::class)->current($request->user());

        abort_if(($context['type'] ?? null) === 'secondary_school' || $request->user()?->secondary_school_id !== null, 404);
    }

    private function scopedSubjects(Request $request)
    {
        return Subject::query()->tap(fn ($query) => $this->applySubjectScope($query, $request));
    }

    private function applySubjectScope($query, Request $request): void
    {
        $user = $request->user();

        $query
            ->when(! $user->isSuperAdmin() && $user->organization_id, fn ($subjectQuery) => $subjectQuery->where('organization_id', $user->organization_id))
            ->when(! $user->isSuperAdmin() && $user->school_id, fn ($subjectQuery) => $subjectQuery->where('school_id', $user->school_id))
            ->when(! $user->isSuperAdmin() && $user->center_id, fn ($subjectQuery) => $subjectQuery->where('center_id', $user->center_id))
            ->when(! $user->isSuperAdmin() && $user->secondary_school_id, fn ($subjectQuery) => $subjectQuery->where('secondary_school_id', $user->secondary_school_id));
    }

    private function authorizedSubject(Request $request, string $subjectId): Subject
    {
        $subject = $this->scopedSubjects($request)->whereKey($subjectId)->first();

        if (! $subject) {
            throw ValidationException::withMessages(['subject_id' => 'Choose a subject within your allowed scope.']);
        }

        return $subject;
    }

    private function authorizeParent(Request $request, ?string $parentId, Subject $subject, ?Topic $topic = null): void
    {
        if (! $parentId) {
            return;
        }

        if ($topic && $parentId === $topic->id) {
            throw ValidationException::withMessages(['parent_id' => 'A topic cannot be its own parent.']);
        }

        $parent = $this->scopedTopics($request)
            ->where('subject_id', $subject->id)
            ->whereKey($parentId)
            ->first();

        if (! $parent) {
            throw ValidationException::withMessages(['parent_id' => 'Choose a parent topic from the same subject and scope.']);
        }
    }

    private function subjectByCode(Request $request, string $code, int $row): Subject
    {
        $subject = $this->scopedSubjects($request)->where('code', strtoupper($code))->first();

        if (! $subject) {
            throw ValidationException::withMessages(['file' => "Row {$row}: Subject code was not found in your scope."]);
        }

        return $subject;
    }

    private function parentByCode(Request $request, string $code, Subject $subject, int $row): ?Topic
    {
        if ($code === '') {
            return null;
        }

        $parent = $this->scopedTopics($request)
            ->where('subject_id', $subject->id)
            ->where('code', strtoupper($code))
            ->first();

        if (! $parent) {
            throw ValidationException::withMessages(['file' => "Row {$row}: Parent topic code was not found for this subject."]);
        }

        return $parent;
    }

    private function ensureUniqueCode(string $code, Subject $subject, ?Topic $ignore = null, ?int $row = null): void
    {
        $exists = Topic::query()
            ->where('subject_id', $subject->id)
            ->where('code', $code)
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->id))
            ->exists();

        if ($exists) {
            $prefix = $row ? "Row {$row}: " : '';
            throw ValidationException::withMessages(['code' => "{$prefix}The topic code is already in use for this subject."]);
        }
    }

    private function referenceCode(?string $code, string $name, Subject $subject, ?Topic $ignore = null): string
    {
        if (filled($code)) {
            return strtoupper($code);
        }

        return ReferenceCode::unique($name, Topic::query()->where('subject_id', $subject->id), $ignore);
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

    private function statuses(): array
    {
        return [
            ['value' => Topic::STATUS_ACTIVE, 'label' => 'Active'],
            ['value' => Topic::STATUS_INACTIVE, 'label' => 'Inactive'],
        ];
    }
}
