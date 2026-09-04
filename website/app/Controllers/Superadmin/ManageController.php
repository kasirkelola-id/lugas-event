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
        
        $db = \Config\Database::connect();
        $data['users'] = $db->table('organization_members')
            ->select('users.id, users.nama_lengkap, users.username, users.no_whatsapp, organization_members.role_level, organization_members.status_aktif')
            ->join('users', 'users.id = organization_members.user_id')
            ->where('organization_members.karang_taruna_id', $kt_id)
            ->get()->getResultArray();
        
        return view('superadmin/manage/users', $data);
    }

    public function updateUserRole($kt_id, $user_id)
    {
        // Pastikan Karang Taruna ada
        $this->getKarangTaruna($kt_id);

        $memberModel = new \App\Models\OrganizationMemberModel();
        $membership = $memberModel->where('user_id', $user_id)->where('karang_taruna_id', $kt_id)->first();

        if (!$membership) {
            return redirect()->back()->with('error', 'Pengguna tidak ditemukan di Karang Taruna ini.');
        }

        $newRole = $this->request->getPost('role_level');
        $validRoles = ['ketua', 'wakil_ketua', 'sekretaris', 'wakil_sekretaris', 'bendahara', 'wakil_bendahara', 'pengelola', 'anggota'];
        
        if (!in_array($newRole, $validRoles)) {
            return redirect()->back()->with('error', 'Role tidak valid.');
        }

        $exclusiveRoles = ['ketua', 'wakil_ketua', 'sekretaris', 'wakil_sekretaris', 'bendahara', 'wakil_bendahara'];
        if (in_array($newRole, $exclusiveRoles)) {
            $existing = $memberModel->where('karang_taruna_id', $kt_id)
                                    ->where('role_level', $newRole)
                                    ->where('status_aktif', 1)
                                    ->where('id !=', $membership['id'])
                                    ->first();
            if ($existing) {
                $roleLabel = ucwords(str_replace('_', ' ', $newRole));
                return redirect()->back()->with('error', "Jabatan {$roleLabel} sudah diisi oleh pengguna aktif lain. Jabatan ini hanya boleh diisi oleh 1 orang per Karang Taruna.");
            }
        }

        $memberModel->update($membership['id'], ['role_level' => $newRole]);

        $userModel = new UserModel();
        $user = $userModel->find($user_id);
        $namaLengkap = $user ? $user['nama_lengkap'] : 'Pengguna';

        $roleLabel = ucwords(str_replace('_', ' ', $newRole));
        return redirect()->to("/superadmin/manage/{$kt_id}/users")->with('success', "Role {$namaLengkap} berhasil diubah menjadi {$roleLabel}");
    }

    public function toggleUserStatus($kt_id, $user_id)
    {
        $this->getKarangTaruna($kt_id);

        $memberModel = new \App\Models\OrganizationMemberModel();
        $membership = $memberModel->where('user_id', $user_id)->where('karang_taruna_id', $kt_id)->first();

        if (!$membership) {
            return redirect()->back()->with('error', 'Pengguna tidak ditemukan di Karang Taruna ini.');
        }

        $newStatus = (int)$membership['status_aktif'] === 1 ? 0 : 1;
        
        if ($newStatus === 1) {
            $exclusiveRoles = ['ketua', 'wakil_ketua', 'sekretaris', 'wakil_sekretaris', 'bendahara', 'wakil_bendahara'];
            if (in_array($membership['role_level'], $exclusiveRoles)) {
                $existing = $memberModel->where('karang_taruna_id', $kt_id)
                                        ->where('role_level', $membership['role_level'])
                                        ->where('status_aktif', 1)
                                        ->where('id !=', $membership['id'])
                                        ->first();
                if ($existing) {
                    $roleLabel = ucwords(str_replace('_', ' ', $membership['role_level']));
                    return redirect()->back()->with('error', "Tidak dapat mengaktifkan kembali pengguna ini karena jabatan {$roleLabel} sudah diisi oleh pengguna aktif lain.");
                }
            }
        }

        $memberModel->update($membership['id'], ['status_aktif' => $newStatus]);
        
        $userModel = new UserModel();
        $user = $userModel->find($user_id);
        $namaLengkap = $user ? $user['nama_lengkap'] : 'Pengguna';
        
        $statusStr = $newStatus === 1 ? 'diaktifkan' : 'dinonaktifkan';
        return redirect()->to("/superadmin/manage/{$kt_id}/users")->with('success', "Status {$namaLengkap} berhasil {$statusStr}.");
    }

    public function resetPassword($kt_id, $user_id)
    {
        $this->getKarangTaruna($kt_id);

        $memberModel = new \App\Models\OrganizationMemberModel();
        $membership = $memberModel->where('user_id', $user_id)->where('karang_taruna_id', $kt_id)->first();

        if (!$membership) {
            return redirect()->back()->with('error', 'Pengguna tidak ditemukan di Karang Taruna ini.');
        }

        $settingModel = new \App\Models\SettingModel();
        $tempPassSetting = $settingModel->where('karang_taruna_id', 0)->where('setting_key', 'temporary_reset_password')->first();
        
        if (!$tempPassSetting || empty(trim($tempPassSetting['setting_value']))) {
            return redirect()->back()->with('error', 'Password sementara global belum dikonfigurasi. Silakan periksa menu Pengaturan.');
        }

        $temporaryPassword = trim($tempPassSetting['setting_value']);

        $userModel = new UserModel();
        $userModel->update($user_id, [
            'password' => password_hash($temporaryPassword, PASSWORD_BCRYPT),
            'password_must_change' => 1
        ]);

        return redirect()->to("/superadmin/manage/{$kt_id}/users")->with('success', "Password pengguna berhasil direset menjadi: {$temporaryPassword}");
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
