<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\ChatModel;
use App\Services\ChatCleanupService;

class ChatCleanupCommand extends BaseCommand
{
    protected $group       = 'Chat';
    protected $name        = 'chat:cleanup';
    protected $description = 'Deletes chat messages older than 30 days in batches to prevent table locks.';

    public function run(array $params)
    {
        $startedAt = microtime(true);
        $cutoff = (new ChatModel())->getRetentionCutoff();
        CLI::write("Starting chat cleanup; cutoff: {$cutoff} UTC", 'green');

        try {
            $result = (new ChatCleanupService())->deleteExpired($cutoff);
            $duration = number_format(microtime(true) - $startedAt, 3);
            $state = $result['busy'] ? 'busy; no work' : ($result['bounded_stop'] ? 'bounded run; schedule next pass' : 'completed');
            CLI::write("Cleanup {$state}. Deleted {$result['deleted']} rows in {$result['batches']} batches ({$duration}s).", 'green');

            return EXIT_SUCCESS;
        } catch (\Throwable $error) {
            log_message('error', 'Chat cleanup failed');
            CLI::error('Chat cleanup failed; see application logs for details.');

            return EXIT_ERROR;
        }
    }
}
