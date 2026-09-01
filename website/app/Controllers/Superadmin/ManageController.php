<?php

namespace App\Controllers\Superadmin;

use App\Controllers\BaseController;
use App\Models\KarangTarunaModel;
use App\Models\UserModel;
use App\Models\EventModel;
use App\Models\PengumumanModel;
use App\Models\KasModel;

class ManageController extends BaseController
{
    protected $ktModel;

    public function __construct()
    {
        $this->ktModel = new KarangTarunaModel();
    }

    private function getKarangTaruna($id)
    {
        $kt = $this->ktModel->find($id);
        if (!$kt) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Karang Taruna tidak ditemukan.');
        }
        return $kt;
    }

    public function dashboard($kt_id)
    {
        $data['kt'] = $this->getKarangTaruna($kt_id);
        
        $userModel = new UserModel();
        $eventModel = new EventModel();
        $pengumumanModel = new PengumumanModel();
        $kasModel = new KasModel();

        $data['total_users'] = $userModel->where('karang_taruna_id', $kt_id)->countAllResults();
        $data['total_events'] = $eventModel->where('karang_taruna_id', $kt_id)->countAllResults();
        $data['total_pengumuman'] = $pengumumanModel->where('karang_taruna_id', $kt_id)->countAllResults();
        $data['saldo_kas'] = $kasModel->getTotalSaldo($kt_id);

        return view('superadmin/manage/dashboard', $data);
    }

    public function users($kt_id)
    {
        $data['kt'] = $this->getKarangTaruna($kt_id);
        $userModel = new UserModel();
        $data['users'] = $userModel->where('karang_taruna_id', $kt_id)->findAll();
        
        return view('superadmin/manage/users', $data);
    }

    public function updateUserRole($kt_id, $user_id)
    {
        // Pastikan Karang Taruna ada
        $this->getKarangTaruna($kt_id);

        $userModel = new UserModel();
        $user = $userModel->find($user_id);

        if (!$user || $user['karang_taruna_id'] != $kt_id) {
            return redirect()->back()->with('error', 'Pengguna tidak ditemukan di Karang Taruna ini.');
        }

        $newRole = $this->request->getPost('role_level');
        $validRoles = ['ketua', 'sekretaris', 'bendahara', 'pengelola', 'anggota'];
        
        if (!in_array($newRole, $validRoles)) {
            return redirect()->back()->with('error', 'Role tidak valid.');
        }

        $userModel->update($user_id, ['role_level' => $newRole]);

        return redirect()->to("/superadmin/manage/{$kt_id}/users")->with('success', "Role {$user['nama_lengkap']} berhasil diubah menjadi " . ucfirst($newRole));
    }

    public function toggleUserStatus($kt_id, $user_id)
    {
        $this->getKarangTaruna($kt_id);

        $userModel = new UserModel();
        $user = $userModel->find($user_id);

        if (!$user || $user['karang_taruna_id'] != $kt_id) {
            return redirect()->back()->with('error', 'Pengguna tidak ditemukan di Karang Taruna ini.');
        }

        $newStatus = (int)$user['status_aktif'] === 1 ? 0 : 1;
        $userModel->update($user_id, ['status_aktif' => $newStatus]);
        
        $statusStr = $newStatus === 1 ? 'diaktifkan' : 'dinonaktifkan';
        return redirect()->to("/superadmin/manage/{$kt_id}/users")->with('success', "Status pengguna {$user['nama_lengkap']} berhasil {$statusStr}.");
    }

    public function events($kt_id)
    {
        $data['kt'] = $this->getKarangTaruna($kt_id);
        $eventModel = new EventModel();
        $data['events'] = $eventModel->where('karang_taruna_id', $kt_id)->orderBy('created_at', 'DESC')->findAll();
        
        return view('superadmin/manage/events', $data);
    }

    public function pengumuman($kt_id)
    {
        $data['kt'] = $this->getKarangTaruna($kt_id);
        $pengumumanModel = new PengumumanModel();
        $data['pengumuman'] = $pengumumanModel->where('karang_taruna_id', $kt_id)->orderBy('created_at', 'DESC')->findAll();
        
        return view('superadmin/manage/pengumuman', $data);
    }

    public function createPengumuman($kt_id)
    {
        $pengumumanModel = new PengumumanModel();
        $pengumumanModel->insert([
            'karang_taruna_id' => $kt_id,
            'judul' => $this->request->getPost('judul'),
            'isi' => $this->request->getPost('isi'),
            'penulis_id' => null, // Superadmin doesn't have a user ID in the users table
            'status_aktif' => 1
        ]);
        
        return redirect()->to("/superadmin/manage/{$kt_id}/pengumuman")->with('success', 'Pengumuman berhasil ditambahkan');
    }

    public function deletePengumuman($kt_id, $id)
    {
        $pengumumanModel = new PengumumanModel();
        $pengumumanModel->delete($id);
        
        return redirect()->to("/superadmin/manage/{$kt_id}/pengumuman")->with('success', 'Pengumuman berhasil dihapus');
    }

    public function kas($kt_id)
    {
        $data['kt'] = $this->getKarangTaruna($kt_id);
        $kasModel = new KasModel();
        $data['kas'] = $kasModel->where('karang_taruna_id', $kt_id)->orderBy('tanggal', 'DESC')->findAll();
        $data['saldo_kas'] = $kasModel->getTotalSaldo($kt_id);
        
        return view('superadmin/manage/kas', $data);
    }
}
