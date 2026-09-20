<?php

namespace App\Console\Commands;

use Database\Seeders\AutobootSyntheticDataSeeder;
use Illuminate\Console\Command;

class SeedAutobootDataCommand extends Command
{
    protected $signature = 'autoboot:seed-data
        {--questions=50 : Number of questions to create per subject}
        {--fresh : Rebuild only the synthetic Autoboot question banks before seeding}';

    protected $description = 'Create synthetic Autoboot subjects, question banks, questions, and options';

    public function handle(AutobootSyntheticDataSeeder $seeder): int
    {
        $questions = (int) $this->option('questions');

        if ($questions < 1) {
            $this->error('The --questions value must be at least 1.');

            return self::INVALID;
        }

        $summary = $seeder->run($questions, (bool) $this->option('fresh'));

        $this->table(
            ['Subjects', 'Banks', 'Questions created', 'Options created', 'Questions per subject'],
            [[
                $summary['subjects'],
                $summary['banks'],
                $summary['createdQuestions'],
                $summary['createdOptions'],
                $summary['questionsPerSubject'],
            ]],
        );
        $this->info('Synthetic Autoboot data is ready for package creation.');

        return self::SUCCESS;
    }
}
