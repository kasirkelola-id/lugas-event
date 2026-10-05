<?php

namespace App\Controllers\Superadmin;

use App\Controllers\BaseController;
use App\Models\SuperadminModel;

class AuthController extends BaseController
{
    public function login()
    {
        if (session()->get('is_superadmin_logged_in')) {
            return redirect()->to('/superadmin/dashboard');
        }
        
        return view('superadmin/login');
    }

    public function processLogin()
    {
        $username = $this->request->getPost('username');
        $password = $this->request->getPost('password');

        $superadminModel = new SuperadminModel();
        $user = $superadminModel->where('username', $username)->first();

        if ($user && \App\Services\CredentialPolicy::verify((string) $password, $user)) {
            session()->regenerate(true);
            $sessionData = [
                'superadmin_id'           => $user['id'],
                'superadmin_credential_version' => hash('sha256', $user['password']),
                'superadmin_username'     => $user['username'],
                'superadmin_nama_lengkap' => $user['nama_lengkap'],
                'is_superadmin_logged_in' => true,
            ];
            session()->set($sessionData);
            return redirect()->to('/superadmin/dashboard');
        }

        return redirect()->to('/superadmin/login')->with('error', 'Username atau Password salah');
    }

    public function logout()
    {
        session()->remove(['is_superadmin_logged_in', 'superadmin_id', 'superadmin_username', 'superadmin_nama_lengkap', 'superadmin_credential_version']);
        session()->destroy();
        return redirect()->to('/superadmin/login');
    }
}
