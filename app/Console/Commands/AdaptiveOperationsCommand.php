<?php

namespace App\Console\Commands;

use App\Services\AdaptiveOperationsService;
use Illuminate\Console\Command;

class AdaptiveOperationsCommand extends Command
{
    protected $signature = 'adaptive:operations {--hours=24 : Look back 1 to 720 hours} {--owner= : Exact frozen owner key}';

    protected $description = 'Read-only aggregate adaptive cohort, stop and shadow evaluation metrics (JSON)';

    public function handle(AdaptiveOperationsService $service): int
    {
        $hours = filter_var($this->option('hours'), FILTER_VALIDATE_INT);
        $owner = $this->option('owner') ?: null;
        if ($hours === false || $hours < 1 || $hours > 720) {
            $this->error('Hours must be an integer between 1 and 720.');

            return self::INVALID;
        }
        if ($owner !== null && ! preg_match('/^(organization|institution|professional_school|secondary_school|cbt_center):[A-Za-z0-9-]+$/D', $owner)) {
            $this->error('Owner must be an exact context:id key.');

            return self::INVALID;
        }
        $this->line(json_encode($service->report($hours, $owner), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
