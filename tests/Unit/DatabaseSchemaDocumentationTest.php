<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class DatabaseSchemaDocumentationTest extends TestCase
{
    public function test_database_schema_document_covers_required_core_tables(): void
    {
        $contents = $this->databaseSchemaContents();

        foreach ($this->requiredTables() as $table) {
            $this->assertStringContainsString("## {$table}", $contents);
        }
    }

    public function test_each_core_table_documents_required_schema_concerns(): void
    {
        $contents = $this->databaseSchemaContents();
        $headings = [
            '**Purpose:**',
            '**Important columns:**',
            '**Relationships:**',
            '**Indexes needed for performance:**',
            '**Security-sensitive fields:**',
            '**Online, offline, or both:**',
        ];

        foreach ($this->requiredTables() as $index => $table) {
            $nextTable = $this->requiredTables()[$index + 1] ?? 'Migration Order Recommendation';
            $section = $this->sectionFor($contents, $table, $nextTable);

            foreach ($headings as $heading) {
                $this->assertStringContainsString($heading, $section, "Missing {$heading} in {$table} section.");
            }
        }
    }

    public function test_candidate_security_rules_are_documented(): void
    {
        $contents = $this->databaseSchemaContents();

        $this->assertStringContainsString('Never serialize `question_options.is_correct`.', $contents);
        $this->assertStringContainsString('Never serialize answer keys, explanations, or rubrics to `/exam/*`.', $contents);
        $this->assertStringContainsString('Candidate answer APIs should return only save/submission status, server timestamps, and the next safe action.', $contents);
    }

    private function databaseSchemaContents(): string
    {
        $path = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'docs'.DIRECTORY_SEPARATOR.'database-schema.md';

        $this->assertFileExists($path);

        return file_get_contents($path) ?: '';
    }

    /**
     * @return array<int, string>
     */
    private function requiredTables(): array
    {
        return [
            'users',
            'organizations',
            'centers',
            'schools',
            'exam_types',
            'exams',
            'subjects',
            'topics',
            'question_banks',
            'questions',
            'question_options',
            'candidates',
            'exam_sessions',
            'exam_subjects',
            'candidate_exam_attempts',
            'candidate_answers',
            'exam_audit_logs',
            'proctoring_events',
        ];
    }

    private function sectionFor(string $contents, string $table, string $nextTable): string
    {
        $pattern = "/## {$table}\R(.*?)(?=## {$nextTable}\R|$)/s";

        preg_match($pattern, $contents, $matches);

        $this->assertNotEmpty($matches[1] ?? '', "Missing section body for {$table}.");

        return $matches[1];
    }
}
