<?php

namespace App\Controllers\Superadmin;

use App\Controllers\BaseController;
use App\Models\KarangTarunaModel;
use App\Models\UserModel;

class DashboardController extends BaseController
{
    public function index()
    {
        $ktModel = new KarangTarunaModel();
        $userModel = new UserModel();
        $eventModel = new \App\Models\EventModel();
        $pengumumanModel = new \App\Models\PengumumanModel();

        $data = [
            'total_karang_taruna' => $ktModel->countAllResults(),
            'total_users'         => $userModel->countAllResults(),
            'total_events'        => $eventModel->countAllResults(),
            'total_pengumuman'    => $pengumumanModel->countAllResults(),
        ];

        return view('superadmin/dashboard', $data);
    }
}
