<?php

namespace App\Controllers\Api;

use App\Models\PengumumanModel;
use App\Services\AuthService;

class AnnouncementController extends BaseApiController
{
    private function checkAdminAndSekretaris()
    {
        $user = AuthService::getUser();
        if (!$user || !in_array($user['role_level'], ['admin', 'ketua', 'sekretaris'])) {
            return false;
        }
        return true;
    }

    public function index()
    {
        $user = AuthService::getUser();
        $model = new PengumumanModel();
        
        $builder = $model->builder();
        $builder->select('pengumuman.*, users.nama_lengkap as pembuat');
        $builder->join('users', 'users.id = pengumuman.dibuat_oleh', 'left');

        if (!in_array($user['role_level'], ['admin', 'ketua', 'sekretaris'])) {
            $builder->where('pengumuman.status_aktif', 1);
            $builder->groupStart()
                    ->where('pengumuman.target_role', 'semua')
                    ->orWhere('pengumuman.target_role', $user['role_level'])
                    ->groupEnd();
        }

        $builder->orderBy('pengumuman.created_at', 'DESC');
        $announcements = $builder->get()->getResultArray();

        // Cast types
        $announcements = array_map(function($a) {
            $a['id'] = (int)$a['id'];
            $a['dibuat_oleh'] = (int)$a['dibuat_oleh'];
            $a['status_aktif'] = (int)$a['status_aktif'];
            return $a;
        }, $announcements);

        return $this->sendSuccess('Daftar pengumuman', $announcements);
    }

    public function create()
    {
        if (!$this->checkAdminAndSekretaris()) {
            return $this->sendError('Forbidden', null, 403);
        }

        $rules = [
            'judul' => 'required|max_length[255]',
            'isi' => 'required',
            'target_role' => 'required|in_list[semua,admin,ketua,sekretaris,bendahara,pengelola,anggota]'
        ];

        $rawInput = $this->request->getJSON(true) ?? $this->request->getRawInput();

        if (!$this->validateData($rawInput, $rules)) {
            return $this->sendError('Validasi gagal', $this->validator->getErrors(), 422);
        }

        $user = AuthService::getUser();
        $model = new PengumumanModel();

        $data = [
            'judul' => $rawInput['judul'],
            'isi' => $rawInput['isi'],
            'target_role' => $rawInput['target_role'],
            'status_aktif' => isset($rawInput['status_aktif']) ? (int)$rawInput['status_aktif'] : 1,
            'dibuat_oleh' => $user['id']
        ];

        $id = $model->insert($data);
        $data['id'] = $id;

        return $this->sendSuccess('Pengumuman berhasil dibuat', $data, 201);
    }

    public function update($id = null)
    {
        if (!$this->checkAdminAndSekretaris()) {
            return $this->sendError('Forbidden', null, 403);
        }

        $model = new PengumumanModel();
        $announcement = $model->find($id);

        if (!$announcement) {
            return $this->sendError('Pengumuman tidak ditemukan', null, 404);
        }

        $rules = [
            'judul' => 'required|max_length[255]',
            'isi' => 'required',
            'target_role' => 'required|in_list[semua,admin,ketua,sekretaris,bendahara,pengelola,anggota]'
        ];

        $rawInput = $this->request->getJSON(true) ?? $this->request->getRawInput();

        if (!$this->validateData($rawInput, $rules)) {
            return $this->sendError('Validasi gagal', $this->validator->getErrors(), 422);
        }

        $data = [
            'judul' => $rawInput['judul'],
            'isi' => $rawInput['isi'],
            'target_role' => $rawInput['target_role']
        ];
        
        if (isset($rawInput['status_aktif'])) {
            $data['status_aktif'] = (int)$rawInput['status_aktif'];
        }

        $model->update($id, $data);

        return $this->sendSuccess('Pengumuman berhasil diperbarui', $data);
    }

    public function toggleStatus($id = null)
    {
        if (!$this->checkAdminAndSekretaris()) {
            return $this->sendError('Forbidden', null, 403);
        }

        $model = new PengumumanModel();
        $announcement = $model->find($id);

        if (!$announcement) {
            return $this->sendError('Pengumuman tidak ditemukan', null, 404);
        }

        $newStatus = $announcement['status_aktif'] == 1 ? 0 : 1;
        $model->update($id, ['status_aktif' => $newStatus]);

        return $this->sendSuccess('Status pengumuman berhasil diubah');
    }

    public function delete($id = null)
    {
        if (!$this->checkAdminAndSekretaris()) {
            return $this->sendError('Forbidden', null, 403);
        }

        $model = new PengumumanModel();
        if (!$model->find($id)) {
            return $this->sendError('Pengumuman tidak ditemukan', null, 404);
        }

        $model->delete($id);

        return $this->sendSuccess('Pengumuman berhasil dihapus');
    }
}
