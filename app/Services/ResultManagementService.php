<?php

namespace App\Services;

use App\Models\CandidateExamAttempt;
use App\Models\Exam;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ResultManagementService
{
    public function queryForExam(Exam $exam): Builder
    {
        return CandidateExamAttempt::query()
            ->where('exam_id', $exam->id)
            ->whereIn('status', [
                CandidateExamAttempt::STATUS_SUBMITTED,
                CandidateExamAttempt::STATUS_AUTO_SUBMITTED,
            ])
            ->with(['candidate', 'exam', 'answers.subject', 'proctoringEvents']);
    }

    /**
     * @return array<string, mixed>
     */
    public function row(CandidateExamAttempt $attempt): array
    {
        $attempt->loadMissing(['candidate', 'exam', 'answers.subject', 'proctoringEvents']);
        $totalMarks = max((float) ($attempt->total_marks ?? $attempt->exam?->total_marks ?? 0), 0);
        $score = (float) ($attempt->score ?? 0);
        $percentage = $totalMarks > 0 ? round(($score / $totalMarks) * 100, 2) : 0.0;
        $passMark = (float) ($attempt->exam?->pass_mark ?? 0);
        $durationUsed = $attempt->started_at && $attempt->submitted_at
            ? max(0, $attempt->started_at->diffInSeconds($attempt->submitted_at))
            : null;

        return [
            'attempt_id' => $attempt->id,
            'exam_id' => $attempt->exam_id,
            'exam_title' => $attempt->exam?->title,
            'exam_code' => $attempt->exam?->code,
            'candidate_id' => $attempt->candidate_id,
            'candidate_name' => trim(($attempt->candidate?->first_name ?? '').' '.($attempt->candidate?->last_name ?? '')),
            'registration_number' => $attempt->candidate?->candidate_number,
            'score' => round($score, 2),
            'total_marks' => round($totalMarks, 2),
            'percentage' => $percentage,
            'grade' => $this->grade($percentage),
            'passed' => $score >= $passMark,
            'status' => $score >= $passMark ? 'Pass' : 'Fail',
            'submitted_at' => $attempt->submitted_at?->toISOString(),
            'duration_used_seconds' => $durationUsed,
            'duration_used' => $durationUsed === null ? 'N/A' : $this->formatDuration($durationUsed),
            'suspicious_event_count' => $attempt->proctoringEvents
                ->whereIn('severity', ['warning', 'critical', 'high'])
                ->count(),
            'result_hash' => $attempt->result_hash ?? $this->ensureHash($attempt),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboard(Collection $attempts): array
    {
        $rows = $attempts->map(fn (CandidateExamAttempt $attempt) => $this->row($attempt))->values();
        $passed = $rows->where('passed', true)->count();
        $failed = $rows->count() - $passed;

        return [
            'summary' => [
                'total' => $rows->count(),
                'passed' => $passed,
                'failed' => $failed,
                'average_percentage' => round($rows->avg('percentage') ?? 0, 2),
            ],
            'pass_fail' => [
                ['name' => 'Pass', 'value' => $passed],
                ['name' => 'Fail', 'value' => $failed],
            ],
            'score_distribution' => $this->scoreDistribution($rows),
            'average_by_subject' => $this->averageBySubject($attempts),
        ];
    }

    public function ensureHash(CandidateExamAttempt $attempt): string
    {
        if ($attempt->result_hash) {
            return $attempt->result_hash;
        }

        $hash = substr(hash('sha256', implode('|', [
            config('app.key'),
            $attempt->id,
            $attempt->candidate_id,
            $attempt->exam_id,
            $attempt->score,
            $attempt->submitted_at?->timestamp,
        ])), 0, 24);

        $attempt->forceFill(['result_hash' => strtoupper($hash)])->save();

        return $attempt->result_hash;
    }

    public function formatDuration(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remainingSeconds = $seconds % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $remainingSeconds);
    }

    public function csv(Collection $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, ['Candidate Name', 'Registration Number', 'Score', 'Total Marks', 'Percentage', 'Grade', 'Pass/Fail', 'Submitted At', 'Duration Used', 'Suspicious Events', 'Verification Hash']);

        foreach ($rows as $row) {
            fputcsv($handle, [
                $row['candidate_name'],
                $row['registration_number'],
                $row['score'],
                $row['total_marks'],
                $row['percentage'],
                $row['grade'],
                $row['status'],
                $row['submitted_at'],
                $row['duration_used'],
                $row['suspicious_event_count'],
                $row['result_hash'],
            ]);
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return $content ?: '';
    }

    public function pdf(string $title, array $lines): string
    {
        $text = $title."\n\n".implode("\n", array_map(fn ($line) => preg_replace('/[^\x20-\x7E]/', '', $line), $lines));
        $stream = "BT /F1 12 Tf 50 780 Td 14 TL ";

        foreach (explode("\n", $text) as $line) {
            $stream .= '('.str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line).") Tj T* ";
        }

        $stream .= 'ET';
        $objects = [
            "1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj",
            "2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj",
            "3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >> endobj",
            "4 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj",
            "5 0 obj << /Length ".strlen($stream)." >> stream\n{$stream}\nendstream endobj",
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object."\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";

        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf."trailer << /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
    }

    private function grade(float $percentage): string
    {
        return match (true) {
            $percentage >= 70 => 'A',
            $percentage >= 60 => 'B',
            $percentage >= 50 => 'C',
            $percentage >= 45 => 'D',
            $percentage >= 40 => 'E',
            default => 'F',
        };
    }

    private function scoreDistribution(Collection $rows): array
    {
        return collect(['0-39', '40-49', '50-59', '60-69', '70-100'])
            ->map(fn ($bucket) => ['range' => $bucket, 'count' => $rows->filter(fn ($row) => $this->inBucket($row['percentage'], $bucket))->count()])
            ->all();
    }

    private function inBucket(float $percentage, string $bucket): bool
    {
        [$min, $max] = array_map('intval', explode('-', $bucket));

        return $percentage >= $min && $percentage <= $max;
    }

    private function averageBySubject(Collection $attempts): array
    {
        return $attempts
            ->flatMap(fn (CandidateExamAttempt $attempt) => $attempt->answers)
            ->groupBy(fn ($answer) => $answer->subject?->name ?? 'General')
            ->map(fn ($answers, $subject) => [
                'subject' => $subject,
                'average' => round($answers->avg(fn ($answer) => (float) ($answer->score_awarded ?? 0)) ?? 0, 2),
            ])
            ->values()
            ->all();
    }
}
