<?php

namespace App\Services;

use App\Models\CandidateExamAttempt;
use App\Models\Exam;
use App\Models\ExamAuditLog;
use App\Models\OfflineServerActivation;
use App\Models\ProctoringEvent;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OfflineResultUploadService
{
    public function receive(array $data, Exam $exam, OfflineServerActivation $activation, User $user): array
    {
        return DB::transaction(function () use ($data, $exam, $activation, $user): array {
            $exam = Exam::whereKey($exam->id)->lockForUpdate()->firstOrFail();
            app(OfflineExamCapabilityService::class)->ensureExportAllowed($exam, $user);
            abort_if($exam->status === Exam::STATUS_CANCELLED, 409, 'Cancelled exams cannot receive results.');
            $query = CandidateExamAttempt::where('exam_id', $exam->id)->where('candidate_id', $data['candidate_id']);
            if (! empty($data['attempt_id'])) {
                $query->whereKey($data['attempt_id']);
            }
            $attempts = $query->lockForUpdate()->get();
            abort_unless($attempts->count() === 1, 409, 'The original candidate attempt cannot be identified uniquely.');
            $attempt = $attempts->first();
            abort_unless($exam->candidates()->where('candidates.id', $data['candidate_id'])->exists(), 409, 'Candidate is no longer assigned to this exam.');
            $hash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
            $receipt = DB::table('offline_result_receipts')->where('attempt_id', $attempt->id)->first();
            if ($receipt) {
                abort_unless((string) $receipt->activation_id === (string) $activation->id && $receipt->upload_id === $data['upload_id'] && hash_equals($receipt->payload_hash, $hash), 409, 'This attempt already has a different upload. Review it on the portal.');

                return $this->receipt($receipt, $attempt, $exam);
            }
            abort_if(DB::table('offline_result_receipts')->where('activation_id', $activation->id)->where('upload_id', $data['upload_id'])->exists(), 409, 'Upload ID already belongs to another attempt.');
            abort_unless($attempt->status === CandidateExamAttempt::STATUS_NOT_STARTED && ! $attempt->started_at && ! $attempt->result_hash && ! $attempt->answers()->exists(), 409, 'This attempt has online activity or an existing result. It cannot be overwritten.');
            abort_if(empty($data['upload_proof']) && DB::table('offline_paper_exports')->where('attempt_id', $attempt->id)->exists(), 409, 'This paper was exported with a signed reference. Its original upload proof is required.');
            $proofs = app(OfflinePaperProofService::class);
            $expected = $proofs->paper($attempt);
            $received = collect($data['paper'])->sortBy('question_id')->map(fn ($p) => [
                'question_id' => $p['question_id'],
                'marks' => number_format((float) $p['marks'], 2, '.', ''),
                'option_ids' => collect($p['option_ids'])->sort()->values()->all(),
                'correct_option_ids' => collect($p['correct_option_ids'])->sort()->values()->all(),
            ])->values()->all();
            abort_unless($expected !== [] && $expected === $received, 409, 'The downloaded paper or answer key no longer matches the portal. Manual review is required.');
            if (! empty($data['upload_proof'])) {
                try {
                    $proof = json_decode(Crypt::decryptString($data['upload_proof']), true, flags: JSON_THROW_ON_ERROR);
                } catch (\Throwable) {
                    abort(409, 'Invalid paper upload proof.');
                }
                abort_unless(($proof['attempt_id'] ?? null) === $attempt->id
                    && ($proof['activation_id'] ?? null) === (string) $activation->id
                    && ($proof['package_id'] ?? null) === $data['package_id']
                    && hash_equals($proofs->fingerprint($attempt), $proof['fingerprint'] ?? ''), 409, 'The signed paper or scoring policy changed. Manual review is required.');
            }
            foreach ($data['answers'] as $answer) {
                $paper = $attempt->papers->firstWhere('question_id', $answer['question_id']);
                abort_unless($paper && count(array_unique($answer['selected_option_ids'])) === count($answer['selected_option_ids'])
                    && array_diff($answer['selected_option_ids'], $paper->question->options->pluck('id')->all()) === [], 422, 'An answer contains an unassigned question or invalid options.');
                $attempt->answers()->create([
                    'question_id' => $paper->question_id,
                    'subject_id' => $paper->question->subject_id,
                    'selected_option_ids' => $answer['selected_option_ids'],
                    'answer_text' => $answer['answer_text'] ?? null,
                    'saved_at' => $answer['saved_at'],
                    'submitted_at' => $data['submitted_at'],
                ]);
            }
            foreach ($data['events'] as $event) {
                ProctoringEvent::create([
                    'exam_id' => $exam->id, 'candidate_exam_attempt_id' => $attempt->id,
                    'candidate_id' => $attempt->candidate_id, 'center_id' => $attempt->center_id,

                    'event_type' => $event['event_type'], 'severity' => $event['severity'], 'source' => 'offline_server',
                    'payload' => ['message' => $event['message'], 'offline_upload_id' => $data['upload_id']],
                    'occurred_at' => $event['occurred_at'],
                ]);
            }
            $attempt->update([
                'status' => $data['status'], 'started_at' => $data['started_at'],
                'submitted_at' => $data['submitted_at'],
                'auto_submitted_at' => $data['status'] === 'auto_submitted' ? $data['submitted_at'] : null,
                'disqualified_at' => $data['status'] === 'disqualified' ? $data['submitted_at'] : null,
                'disqualification_reason' => $data['status'] === 'disqualified' ? 'Disqualified by offline center server.' : null,
                'total_marks' => $attempt->papers->sum(fn ($p) => $p->scoringMarks()),
            ]);
            if ($data['status'] !== 'disqualified') {
                $attempt = app(ExamResultService::class)->calculate($attempt);
            }
            $id = (string) Str::ulid();
            DB::table('offline_result_receipts')->insert([
                'id' => $id, 'attempt_id' => $attempt->id, 'activation_id' => $activation->id,
                'upload_id' => $data['upload_id'], 'package_id' => $data['package_id'], 'payload_hash' => $hash,
                'local_score' => $data['local_score'] ?? null,
                'official_score' => $data['status'] === 'disqualified' ? null : $attempt->score,
                'legacy_package' => empty($data['upload_proof']), 'created_at' => now(), 'updated_at' => now(),
            ]);
            ExamAuditLog::create([
                'exam_id' => $exam->id,
                'candidate_exam_attempt_id' => $attempt->id, 'actor_user_id' => $user->id,
                'actor_type' => 'user', 'event_type' => 'offline_result_uploaded',
                'description' => 'Offline attempt received and official result calculated.',
                'metadata' => ['receipt_id' => $id, 'activation_id' => $activation->id, 'local_score' => $data['local_score'] ?? null, 'official_score' => $attempt->score, 'legacy_package' => empty($data['upload_proof'])],
                'occurred_at' => now(),
            ]);

            return $this->receipt(DB::table('offline_result_receipts')->where('id', $id)->first(), $attempt, $exam);
        }, 3);
    }

    private function receipt(object $receipt, CandidateExamAttempt $attempt, Exam $exam): array
    {
        return [
            'id' => $receipt->id, 'upload_id' => $receipt->upload_id, 'attempt_id' => $attempt->id,
            'status' => 'accepted', 'local_score' => $receipt->local_score,
            'official_score' => $receipt->official_score,
            'score_difference' => $receipt->official_score === null || $receipt->local_score === null ? null : round($receipt->official_score - $receipt->local_score, 2),
            'legacy_package' => (bool) $receipt->legacy_package,
            'visibility' => $attempt->status === 'disqualified' ? 'disqualified' : (app(CandidateResultVisibilityService::class)->allows($exam) ? 'released' : 'held'),
            'result_url' => url('/candidate-result'), 'received_at' => $receipt->created_at,
        ];
    }
}
