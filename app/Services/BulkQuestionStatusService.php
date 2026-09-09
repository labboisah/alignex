<?php

namespace App\Services;

use App\Models\Question;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class BulkQuestionStatusService
{
    public function update(User $actor, array $ids, string $status): int
    {
        return DB::transaction(function () use ($actor, $ids, $status): int {
            $questions = Question::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            if ($questions->count() !== count($ids)) {
                throw ValidationException::withMessages(['question_ids' => 'Some selected questions are no longer available. Refresh the list and try again.']);
            }
            foreach ($questions as $question) {
                Gate::forUser($actor)->authorize('update', $question);
            }
            $changed = 0;
            foreach ($questions as $question) {
                if ($question->status === $status) {
                    continue;
                }
                $previous = $question->status;
                $question->update(['status' => $status]);
                Log::info('Question status changed', ['question_id' => $question->id, 'question_bank_id' => $question->question_bank_id, 'actor_id' => $actor->id, 'previous_status' => $previous, 'status' => $status, 'bulk' => true]);
                $changed++;
            }

            return $changed;
        });
    }
}
