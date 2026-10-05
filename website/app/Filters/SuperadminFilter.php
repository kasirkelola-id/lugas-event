<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class SuperadminFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $session = session();
        if (!$session->get('is_superadmin_logged_in')) {
            return redirect()->to('/superadmin/login')->with('error', 'Silakan login terlebih dahulu');
        }
        $admin = (new \App\Models\SuperadminModel())->find((int)$session->get('superadmin_id'));
        if (!$admin || !hash_equals(hash('sha256', $admin['password']), (string)$session->get('superadmin_credential_version'))) {
            $session->remove(['is_superadmin_logged_in', 'superadmin_id', 'superadmin_username', 'superadmin_nama_lengkap', 'superadmin_credential_version']);
            return redirect()->to('/superadmin/login')->with('error', 'Silakan login kembali');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Do something here
    }
}
