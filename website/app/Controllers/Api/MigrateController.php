<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;

class MigrateController extends ResourceController
{
    private $secret = 'SUPER_SECRET_LUGAS_2026';

    public function status()
    {
        if ($this->request->getGet('secret') !== $this->secret) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'Forbidden']);
        }

        try {
            $output = command('migrate:status');
            return $this->response->setJSON([
                'success' => true,
                'status_output' => explode("\n", $output)
            ]);
        } catch (\Throwable $e) {
            return $this->response->setJSON([
                'success' => false,
                'error' => 'Failed to get status. Check logs.'
            ]);
        }
    }

    public function run()
    {
        if ($this->request->getGet('secret') !== $this->secret) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'Forbidden']);
        }

        try {
            $output = command('migrate');
            return $this->response->setJSON([
                'success' => true,
                'run_output' => explode("\n", $output)
            ]);
        } catch (\Throwable $e) {
            return $this->response->setJSON([
                'success' => false,
                'error' => 'Failed to run migration. Check logs.'
            ]);
        }
    }
}
