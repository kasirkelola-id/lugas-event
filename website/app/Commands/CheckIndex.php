<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class CheckIndex extends BaseCommand
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
    protected $name = 'command:name';

    /**
     * The Command's Description
     *
     * @var string
     */
    protected $description = '';

    /**
     * The Command's Usage
     *
     * @var string
     */
    protected $usage = 'command:name [arguments] [options]';

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
    protected $options = [];

    /**
     * Actually execute a command.
     *
     * @param array $params
     */
    public function run(array $params)
    {
        $db = \Config\Database::connect();
        
        \CodeIgniter\CLI\CLI::write('=== USERS ===', 'green');
        $usersIndexes = $db->query('SHOW INDEX FROM users')->getResultArray();
        print_r($usersIndexes);

        \CodeIgniter\CLI\CLI::write('=== ORGANIZATION_MEMBERS ===', 'green');
        $orgIndexes = $db->query('SHOW INDEX FROM organization_members')->getResultArray();
        print_r($orgIndexes);
    }
}
