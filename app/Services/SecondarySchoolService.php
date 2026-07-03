<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\Candidate;
use App\Models\CandidatePerformanceProfile;
use App\Models\Exam;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SecondarySchoolService
{
    public function scope(User $user): array
    {
        return [
            'school_id' => $user->school_id,
            'secondary_school_id' => $user->secondary_school_id,
        ];
    }

    public function sessions(User $user): Collection
    {
        return $this->owned(AcademicSession::query()->withCount('terms'), $user)->latest()->get();
    }

    public function classes(User $user): Collection
    {
        return $this->owned(SchoolClass::query()->withCount('groups'), $user)->orderBy('level_order')->get();
    }

    public function secondaryExams(User $user): Collection
    {
        return $this->owned(Exam::query()->with('examType'), $user)
            ->whereHas('examType', fn (Builder $query) => $query->where('code', 'secondary'))
            ->latest()
            ->get();
    }

    public function candidates(User $user): Collection
    {
        return $this->owned(Candidate::query(), $user)->orderBy('candidate_number')->get();
    }

    public function subjects(User $user): Collection
    {
        return $this->owned(Subject::query(), $user)->orderBy('name')->get();
    }

    public function teacherDashboard(User $user): array
    {
        $exams = $this->secondaryExams($user);

        return [
            'secondary_exams' => $exams->count(),
            'students' => $this->candidates($user)->count(),
            'classes' => $this->classes($user)->count(),
        ];
    }

    public function weaknessReport(User $user, ?Exam $exam = null): array
    {
        $examIds = $exam ? collect([$exam->id]) : $this->secondaryExams($user)->pluck('id');

        return CandidatePerformanceProfile::query()
            ->whereIn('exam_id', $examIds)
            ->where('mastery_level', CandidatePerformanceProfile::MASTERY_WEAK)
            ->with(['candidate', 'subject', 'topic'])
            ->orderBy('score_percentage')
            ->limit(40)
            ->get()
            ->map(fn (CandidatePerformanceProfile $profile) => [
                'candidate_name' => trim(($profile->candidate?->first_name ?? '').' '.($profile->candidate?->last_name ?? '')),
                'registration_number' => $profile->candidate?->candidate_number,
                'subject' => $profile->subject?->name ?? 'General',
                'topic' => $profile->topic?->name ?? 'Unspecified topic',
                'score_percentage' => (float) $profile->score_percentage,
                'mastery_level' => $profile->mastery_level,
            ])
            ->all();
    }

    public function grade(float $score): string
    {
        return match (true) {
            $score >= 70 => 'A',
            $score >= 60 => 'B',
            $score >= 50 => 'C',
            $score >= 45 => 'D',
            $score >= 40 => 'E',
            default => 'F',
        };
    }

    public function owned(Builder $query, User $user): Builder
    {
        return $query->when(! $user->isSuperAdmin(), function (Builder $query) use ($user): void {
            $query->where(function (Builder $inner) use ($user): void {
                $inner->whereRaw('1 = 0')
                    ->when($user->school_id, fn (Builder $scope) => $scope->orWhere('school_id', $user->school_id))
                    ->when($user->secondary_school_id, fn (Builder $scope) => $scope->orWhere('secondary_school_id', $user->secondary_school_id));
            });
        });
    }
}
