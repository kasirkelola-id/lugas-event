<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

final class NotificationWork extends BaseCommand
{
    protected $group = 'Notifications';
    protected $name = 'notifications:work';
    protected $description = 'Process one durable notification job with at most five device sends, then exit.';

    public function run(array $params)
    {
        try {
            $result = (new \App\Services\NotificationWorker())->runOne();
            CLI::write(json_encode($result, JSON_THROW_ON_ERROR));
            return $result['errors'] ? EXIT_ERROR : EXIT_SUCCESS;
        } catch (\Throwable $error) {
            CLI::error('Notification worker failed; inspect safe backlog metrics.');
            return EXIT_ERROR;
        }
    }
}
