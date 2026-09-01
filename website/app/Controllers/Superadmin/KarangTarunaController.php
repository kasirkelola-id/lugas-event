<?php

namespace App\Controllers\Superadmin;

use App\Controllers\BaseController;
use App\Models\KarangTarunaModel;

class KarangTarunaController extends BaseController
{
    public function index()
    {
        $ktModel = new KarangTarunaModel();
        $data['karang_taruna'] = $ktModel->findAll();

        return view('superadmin/karang_taruna/index', $data);
    }

    public function create()
    {
        $ktModel = new KarangTarunaModel();
        
        $kode_pin = $this->request->getPost('kode_pin');
        
        // Ensure PIN is unique
        if ($ktModel->where('kode_pin', $kode_pin)->first()) {
            return redirect()->back()->with('error', 'PIN tersebut sudah digunakan oleh Karang Taruna lain. Silakan pilih PIN yang berbeda.');
        }

        $data = [
            'nama_organisasi' => $this->request->getPost('nama_organisasi'),
            'kode_pin'        => $kode_pin,
            'alamat_lengkap'  => $this->request->getPost('alamat_lengkap'),
            'nama_ketua'      => $this->request->getPost('nama_ketua'),
            'status_aktif'    => $this->request->getPost('status_aktif') ?? 1,
        ];

        $ktModel->insert($data);

        return redirect()->to('/superadmin/karang_taruna')->with('success', 'Karang Taruna berhasil ditambahkan dengan PIN: ' . $kode_pin);
    }

    public function update($id)
    {
        $ktModel = new KarangTarunaModel();
        
        $data = [
            'nama_organisasi' => $this->request->getPost('nama_organisasi'),
            'alamat_lengkap'  => $this->request->getPost('alamat_lengkap'),
            'nama_ketua'      => $this->request->getPost('nama_ketua'),
            'status_aktif'    => $this->request->getPost('status_aktif'),
        ];

        $ktModel->update($id, $data);

        return redirect()->to('/superadmin/karang_taruna')->with('success', 'Data Karang Taruna berhasil diperbarui');
    }

    public function delete($id)
    {
        $ktModel = new KarangTarunaModel();
        $ktModel->delete($id);

        return redirect()->to('/superadmin/karang_taruna')->with('success', 'Karang Taruna berhasil dihapus');
    }

    public function users($id)
    {
        $ktModel = new KarangTarunaModel();
        $userModel = new \App\Models\UserModel();

        $karang_taruna = $ktModel->find($id);
        
        if (!$karang_taruna) {
            return redirect()->to('/superadmin/karang_taruna')->with('error', 'Data Karang Taruna tidak ditemukan');
        }

        $data['karang_taruna'] = $karang_taruna;
        $data['users'] = $userModel->where('karang_taruna_id', $id)->findAll();

        return view('superadmin/karang_taruna/users', $data);
    }
}
