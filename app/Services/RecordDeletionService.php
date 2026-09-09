<?php

namespace App\Services;

use App\Models\Question;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class RecordDeletionService
{
    public function delete(Model $record): void
    {
        $record->getConnection()->transaction(function () use ($record): void {
            $locked = $record->newQueryWithoutScopes()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();
            $this->assertUnused($locked);
            $locked->delete();
        }, 3);
    }

    public function assertUnused(Model $record): void
    {
        $connection = $record->getConnection();
        $schema = $connection->getSchemaBuilder();
        foreach ($schema->getTables() as $table) {
            // Options are part of a soft-deleted question, retained together for recovery.
            if ($record instanceof Question && $table['name'] === 'question_options') {
                continue;
            }
            foreach ($schema->getForeignKeys($table['name']) as $key) {
                if ($key['foreign_table'] !== $record->getTable()) {
                    continue;
                }
                $query = $connection->table($table['name']);
                foreach ($key['columns'] as $i => $column) {
                    $query->where($column, $record->getAttribute($key['foreign_columns'][$i]));
                }
                // Include soft-deleted dependants: their historical references still matter.
                if ($query->exists()) {
                    $this->blocked();
                }
            }
        }
        // These assignments use polymorphic IDs instead of database foreign keys.
        $participant = match ($record->getTable()) {
            'candidates' => 'candidate',
            'students' => 'student',
            default => null,
        };
        if ($participant && $schema->hasTable('exam_participants')
            && $connection->table('exam_participants')->where('participant_type', $participant)
                ->where('participant_id', $record->getKey())->exists()) {
            $this->blocked();
        }
    }

    private function blocked(): never
    {
        throw ValidationException::withMessages([
            'record' => 'This record is still linked to other records or examination history. Reassign its dependants, or set it inactive instead. Nothing was deleted.',
        ]);
    }
}
