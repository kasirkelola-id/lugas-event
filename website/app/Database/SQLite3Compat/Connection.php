<?php namespace App\Database\SQLite3Compat; use CodeIgniter\Database\SQLite3\Connection as BaseConnection; class Connection extends BaseConnection { public $DBDriver = "SQLite3";    protected function execute(string $sql)
    {
        $ignoredKeywords = ['ADD CONSTRAINT', 'DROP FOREIGN KEY', 'SET FOREIGN_KEY_CHECKS', 'DROP INDEX', 'ADD UNIQUE INDEX', 'ADD INDEX'];
        foreach ($ignoredKeywords as $keyword) {
            if (stripos($sql, $keyword) !== false) {
                return true;
            }
        }
        return parent::execute($sql);
    }}
