<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Fail-closed tombstone for old startup scripts. This command opens no listener.
 * The supported realtime service is chat-server/server.js (Socket.IO).
 */
class WebsocketServe extends BaseCommand
{
    protected $group = 'App';
    protected $name = 'websocket:serve';
    protected $description = 'Retired legacy realtime command; use the Node Socket.IO service';
    protected $usage = 'websocket:serve';

    public function run(array $params)
    {
        CLI::error('Legacy websocket:serve is retired. Start chat-server/server.js for Socket.IO.');
        return EXIT_ERROR;
    }
}
