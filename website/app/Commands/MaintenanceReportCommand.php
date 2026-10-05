<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

final class MaintenanceReportCommand extends BaseCommand
{
    protected $group = 'Maintenance';
    protected $name = 'maintenance:report';
    protected $description = 'Dry-run expired sessions/stale bound devices and read-only managed upload candidates.';
    protected $options = ['--apply-sessions' => 'Explicitly apply the bounded session cleanup; uploads remain read-only.'];

    public function run(array $params)
    {
        try {
            $uploads = (new \App\Services\OrphanUploadReport())->run();
            $sessions = (new \App\Services\SessionCleanupService())->run(CLI::getOption('apply-sessions') !== null);
            CLI::write(json_encode(['sessions' => $sessions, 'uploads' => $uploads], JSON_THROW_ON_ERROR));
            return EXIT_SUCCESS;
        } catch (\Throwable $error) {
            log_message('error', 'Maintenance report failed'); CLI::error('Maintenance failed; inspect safe application error labels.');
            return EXIT_ERROR;
        }
    }
}
