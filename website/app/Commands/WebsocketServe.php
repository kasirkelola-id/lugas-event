<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use App\Libraries\ChatServer;

class WebsocketServe extends BaseCommand
{
    /**
     * The Command's Group
     *
     * @var string
     */
    protected $group = 'App';

    /**
     * The Command's Name
     *
     * @var string
     */
    protected $name = 'websocket:serve';

    /**
     * The Command's Description
     *
     * @var string
     */
    protected $description = 'Start the Ratchet WebSocket server for Lugasku chat';

    /**
     * The Command's Usage
     *
     * @var string
     */
    protected $usage = 'websocket:serve';

    /**
     * The Command's Arguments
     *
     * @var array
     */
    protected $arguments = [];

    /**
     * The Command's Options
     *
     * @var array
     */
    protected $options = [
        '--port' => 'Port to run the server on (default: 8081)'
    ];

    /**
     * Actually execute a command.
     *
     * @param array $params
     */
    public function run(array $params)
    {
        $port = CLI::getOption('port') ?? 8081;

        CLI::write("Starting WebSocket Server on port {$port}...", 'green');

        $server = IoServer::factory(
            new HttpServer(
                new WsServer(
                    new ChatServer()
                )
            ),
            $port
        );

        $server->run();
    }
}
