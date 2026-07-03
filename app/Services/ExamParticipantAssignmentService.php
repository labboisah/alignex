<?php

namespace App\Services;

use App\Models\Candidate;
use App\Models\Exam;
use App\Models\ExamParticipant;
use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExamParticipantAssignmentService
{
    /**
     * @param array<int, string> $candidateIds
     */
    public function syncCandidates(Exam $exam, array $candidateIds): Collection
    {
        return DB::transaction(function () use ($exam, $candidateIds): Collection {
            $candidateIds = collect($candidateIds)->filter()->unique()->values();
            $this->ensureCandidatesBelongToExamScope($exam, $candidateIds);

            $exam->candidates()->sync(
                $candidateIds->mapWithKeys(fn (string $id) => [$id => ['status' => ExamParticipant::STATUS_ASSIGNED]])->all()
            );

            $this->removeMissingParticipants($exam, ExamParticipant::TYPE_CANDIDATE, $candidateIds);

            return $candidateIds
                ->map(fn (string $candidateId) => ExamParticipant::query()->updateOrCreate(
                    [
                        'exam_id' => $exam->id,
                        'participant_type' => ExamParticipant::TYPE_CANDIDATE,
                        'participant_id' => $candidateId,
                    ],
                    ['status' => ExamParticipant::STATUS_ASSIGNED]
                ));
        });
    }

    /**
     * @param array<int, string|int> $studentIds
     */
    public function syncStudents(Exam $exam, array $studentIds): Collection
    {
        return DB::transaction(function () use ($exam, $studentIds): Collection {
            $studentIds = collect($studentIds)->filter()->unique()->map(fn ($id) => (string) $id)->values();
            $this->ensureStudentsBelongToExamScope($exam, $studentIds);
            $this->removeMissingParticipants($exam, ExamParticipant::TYPE_STUDENT, $studentIds);
            $candidateIds = $this->candidateIdsForStudents($exam, $studentIds);

            $exam->candidates()->sync(
                $candidateIds->mapWithKeys(fn (string $id) => [$id => ['status' => ExamParticipant::STATUS_ASSIGNED]])->all()
            );

            return $studentIds
                ->map(fn (string $studentId) => ExamParticipant::query()->updateOrCreate(
                    [
                        'exam_id' => $exam->id,
                        'participant_type' => ExamParticipant::TYPE_STUDENT,
                        'participant_id' => $studentId,
                    ],
                    ['status' => ExamParticipant::STATUS_ASSIGNED]
                ));
        });
    }

    public function syncFromExamSettings(Exam $exam): void
    {
        $studentIds = data_get($exam->settings ?? [], 'secondary_student_ids', []);

        if (is_array($studentIds) && $studentIds !== []) {
            $this->syncStudents($exam, $studentIds);
            return;
        }

        $candidateIds = data_get($exam->settings ?? [], 'participant_candidate_ids', data_get($exam->settings ?? [], 'cbt_candidate_ids', []));

        if (is_array($candidateIds) && $candidateIds !== []) {
            $this->syncCandidates($exam, $candidateIds);
        }
    }

    private function candidateIdsForStudents(Exam $exam, Collection $studentIds): Collection
    {
        return Student::query()
            ->whereIn('id', $studentIds)
            ->get()
            ->map(function (Student $student) use ($exam): string {
                if (! $student->candidate_id) {
                    $candidate = Candidate::query()->firstOrCreate(
                        [
                            'secondary_school_id' => $student->secondary_school_id,
                            'candidate_number' => $student->admission_number,
                        ],
                        [
                            'owner_type' => Exam::OWNER_SECONDARY_SCHOOL,
                            'owner_id' => $student->secondary_school_id,
                            'organization_id' => $exam->organization_id,
                            'school_id' => $exam->school_id,
                            'center_id' => null,
                            'student_id' => $student->id,
                            'first_name' => $student->first_name,
                            'last_name' => $student->last_name ?: $student->first_name,
                            'email' => $student->email,
                            'phone' => $student->phone,
                            'status' => $student->status,
                            'metadata' => ['source' => 'secondary_student_assignment'],
                        ]
                    );

                    $student->forceFill(['candidate_id' => $candidate->id])->save();
                }

                return (string) $student->candidate_id;
            })
            ->filter()
            ->unique()
            ->values();
    }

    private function removeMissingParticipants(Exam $exam, string $type, Collection $ids): void
    {
        $exam->participants()
            ->where('participant_type', $type)
            ->when($ids->isNotEmpty(), fn ($query) => $query->whereNotIn('participant_id', $ids->all()))
            ->delete();
    }

    private function ensureCandidatesBelongToExamScope(Exam $exam, Collection $candidateIds): void
    {
        if ($candidateIds->isEmpty()) {
            return;
        }

        $count = Candidate::query()
            ->whereIn('id', $candidateIds)
            ->when($exam->organization_id, fn ($query) => $query->where('organization_id', $exam->organization_id))
            ->when($exam->professional_school_id, fn ($query) => $query->where('professional_school_id', $exam->professional_school_id))
            ->when($exam->cbt_center_id, fn ($query) => $query->where('cbt_center_id', $exam->cbt_center_id))
            ->count();

        if ($count !== $candidateIds->count()) {
            throw ValidationException::withMessages(['candidate_ids' => 'Choose participants within this exam owner scope.']);
        }
    }

    private function ensureStudentsBelongToExamScope(Exam $exam, Collection $studentIds): void
    {
        if ($studentIds->isEmpty()) {
            return;
        }

        $count = Student::query()
            ->whereIn('id', $studentIds)
            ->when($exam->secondary_school_id, fn ($query) => $query->where('secondary_school_id', $exam->secondary_school_id))
            ->when($exam->school_class_id, fn ($query) => $query->where('school_class_id', $exam->school_class_id))
            ->count();

        if ($count !== $studentIds->count()) {
            throw ValidationException::withMessages(['student_ids' => 'Choose students within this secondary school exam scope.']);
        }
    }
}
