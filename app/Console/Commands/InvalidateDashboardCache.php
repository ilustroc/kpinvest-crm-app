<?php

namespace App\Console\Commands;

use App\Services\Panel\PanelDashboardService;
use Illuminate\Console\Command;

class InvalidateDashboardCache extends Command
{
    protected $signature = 'dashboard:invalidate';
    protected $description = 'Atomically increment the Meridian dashboard cache version';

    public function handle(): int
    {
        PanelDashboardService::doAtomicInvalidate();
        $this->info('Version: ' . cache(PanelDashboardService::CACHE_VERSION_KEY));
        return Command::SUCCESS;
    }
}
