<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\AcademicTerm;
use App\Models\Candidate;
use App\Models\CandidateExamAttempt;
use App\Models\CandidatePerformanceProfile;
use App\Models\ContinuousAssessment;
use App\Models\Exam;
use App\Models\SchoolClass;
use App\Models\StudentGroup;
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

    public function saveCaSetup(Exam $exam, array $data): Exam
    {
        $exam->update([
            'settings' => [
                ...($exam->settings ?? []),
                'secondary_academic_session_id' => $data['academic_session_id'] ?? null,
                'secondary_academic_term_id' => $data['academic_term_id'] ?? null,
                'secondary_school_class_id' => $data['school_class_id'] ?? null,
                'secondary_student_group_id' => $data['student_group_id'] ?? null,
                'secondary_ca_max_score' => (float) $data['ca_max_score'],
                'secondary_exam_max_score' => (float) $data['exam_max_score'],
            ],
        ]);

        return $exam->refresh();
    }

    public function saveAssessment(Exam $exam, array $data): ContinuousAssessment
    {
        $subject = Subject::query()->findOrFail($data['subject_id']);
        $candidate = Candidate::query()->findOrFail($data['candidate_id']);
        $attempt = CandidateExamAttempt::query()
            ->where('exam_id', $exam->id)
            ->where('candidate_id', $candidate->id)
            ->whereIn('status', [CandidateExamAttempt::STATUS_SUBMITTED, CandidateExamAttempt::STATUS_AUTO_SUBMITTED])
            ->with('answers')
            ->first();

        $examScore = $this->subjectExamScore($attempt, $subject);
        $caScore = (float) $data['ca_score'];
        $total = $caScore + $examScore;

        return ContinuousAssessment::query()->updateOrCreate(
            [
                'exam_id' => $exam->id,
                'candidate_id' => $candidate->id,
                'subject_id' => $subject->id,
            ],
            [
                'academic_session_id' => data_get($exam->settings ?? [], 'secondary_academic_session_id'),
                'academic_term_id' => data_get($exam->settings ?? [], 'secondary_academic_term_id'),
                'school_class_id' => data_get($exam->settings ?? [], 'secondary_school_class_id'),
                'student_group_id' => data_get($exam->settings ?? [], 'secondary_student_group_id'),
                'ca_score' => $caScore,
                'exam_score' => $examScore,
                'total_score' => $total,
                'grade' => $this->grade($total),
                'teacher_comment' => $data['teacher_comment'] ?? null,
            ]
        );
    }

    public function resultSheet(Exam $exam): Collection
    {
        return ContinuousAssessment::query()
            ->where('exam_id', $exam->id)
            ->with(['candidate', 'subject', 'academicSession', 'academicTerm', 'schoolClass', 'studentGroup'])
            ->orderBy('subject_id')
            ->get()
            ->map(fn (ContinuousAssessment $assessment) => [
                'id' => $assessment->id,
                'candidate_id' => $assessment->candidate_id,
                'candidate_name' => trim(($assessment->candidate?->first_name ?? '').' '.($assessment->candidate?->last_name ?? '')),
                'registration_number' => $assessment->candidate?->candidate_number,
                'subject' => $assessment->subject?->name,
                'ca_score' => (float) $assessment->ca_score,
                'exam_score' => (float) $assessment->exam_score,
                'total_score' => (float) $assessment->total_score,
                'grade' => $assessment->grade,
                'teacher_comment' => $assessment->teacher_comment,
            ]);
    }

    public function teacherDashboard(User $user): array
    {
        $exams = $this->secondaryExams($user);
        $assessments = ContinuousAssessment::query()->whereIn('exam_id', $exams->pluck('id'))->get();

        return [
            'secondary_exams' => $exams->count(),
            'students' => $this->candidates($user)->count(),
            'classes' => $this->classes($user)->count(),
            'assessments_recorded' => $assessments->count(),
            'average_total' => round($assessments->avg(fn (ContinuousAssessment $row) => (float) $row->total_score) ?? 0, 2),
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

    public function reportCardPdf(Exam $exam, Candidate $candidate, User $user): string
    {
        $rows = ContinuousAssessment::query()
            ->where('exam_id', $exam->id)
            ->where('candidate_id', $candidate->id)
            ->with('subject')
            ->orderBy('subject_id')
            ->get();

        $lines = [
            'AlignEx Secondary School Report Card',
            'Student: '.trim($candidate->first_name.' '.$candidate->last_name),
            'Registration Number: '.$candidate->candidate_number,
            'Exam: '.$exam->title.' ('.$exam->code.')',
            'Generated By: '.$user->name.' ('.$user->email.')',
            'Generated: '.now()->toDateTimeString(),
            '',
            sprintf('%-24s %8s %8s %8s %6s %s', 'Subject', 'CA', 'Exam', 'Total', 'Grade', 'Comment'),
            str_repeat('-', 92),
        ];

        foreach ($rows as $row) {
            $lines[] = sprintf(
                '%-24s %8s %8s %8s %6s %s',
                str($row->subject?->name ?? 'Subject')->limit(24, '')->toString(),
                $row->ca_score,
                $row->exam_score,
                $row->total_score,
                $row->grade,
                str($row->teacher_comment ?? '')->limit(32, '')->toString(),
            );
        }

        $lines[] = '';
        $lines[] = 'Average: '.round($rows->avg(fn (ContinuousAssessment $row) => (float) $row->total_score) ?? 0, 2);

        return app(ResultManagementService::class)->pdf('AlignEx Report Card', $lines);
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
                    ->when($user->school_id, fn (Builder $scope) => $scope->orWhere('school_id', $user->school_id));
            });
        });
    }

    private function subjectExamScore(?CandidateExamAttempt $attempt, Subject $subject): float
    {
        if (! $attempt) {
            return 0.0;
        }

        return (float) $attempt->answers
            ->where('subject_id', $subject->id)
            ->sum(fn ($answer) => (float) ($answer->score_awarded ?? 0));
    }
}
