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

        if ($user && password_verify($password, $user['password'])) {
            $sessionData = [
                'superadmin_id'           => $user['id'],
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
        session()->destroy();
        return redirect()->to('/superadmin/login');
    }
}
