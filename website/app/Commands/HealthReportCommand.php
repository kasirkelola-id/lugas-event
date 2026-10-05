<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

final class HealthReportCommand extends BaseCommand
{
    protected $group = 'Maintenance';
    protected $name = 'health:report';
    protected $description = 'Read-only database and durable notification aggregate health for operators.';

    public function run(array $params)
    {
        try {
            CLI::write(json_encode((new \App\Services\OperationalHealth())->report(), JSON_THROW_ON_ERROR));
            return EXIT_SUCCESS;
        } catch (\Throwable $error) {
            CLI::error('Operational health unavailable; inspect safe error labels and database readiness.');
            return EXIT_ERROR;
        }
    }
}
