<?php

namespace App\Controllers\Api;

use App\Models\PengumumanModel;
use App\Services\AuthService;

class AnnouncementController extends BaseApiController
{
    // checkAdminAndSekretaris replaced by RBAC

    public function index()
    {
        $tenantId = AuthService::getTenantId();
        $role = AuthService::getRole();
        
        if (!$tenantId || !AuthService::can('announcement.view')) {
            return $this->sendError('Forbidden', null, 403);
        }
        
        $model = new PengumumanModel();
        
        $builder = $model->builder();
        $builder->select('pengumuman.*, users.nama_lengkap as pembuat');
        $builder->join('users', 'users.id = pengumuman.dibuat_oleh', 'left');
        $builder->where('pengumuman.karang_taruna_id', $tenantId);

        if (!AuthService::can('announcement.manage')) {
            $builder->where('pengumuman.status_aktif', 1);
            $builder->groupStart()
                    ->where('pengumuman.target_role', 'semua')
                    ->orWhere('pengumuman.target_role', $role)
                    ->groupEnd();
        }

        $search = $this->request->getVar('search');
        if (!empty($search)) {
            $builder->groupStart()
                    ->like('pengumuman.judul', $search)
                    ->orLike('pengumuman.isi', $search)
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
        if (!AuthService::can('announcement.manage')) {
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

        $tenantId = AuthService::getTenantId();
        $userId = AuthService::getGlobalUserId();
        $model = new PengumumanModel();

        $data = [
            'karang_taruna_id' => $tenantId,
            'judul' => trim($rawInput['judul']),
            'isi' => trim($rawInput['isi']),
            'target_role' => $rawInput['target_role'],
            'status_aktif' => isset($rawInput['status_aktif']) ? (int)$rawInput['status_aktif'] : 1,
            'dibuat_oleh' => $userId
        ];

        $id = $model->insert($data);
        $data['id'] = $id;

        if ($data['status_aktif'] == 1) {
            // Trigger push notification (asynchronously ideally, but curl is fairly fast, or we just do it synchronously for MVP)
            $excludeUsers = [$userId];
            $tokens = \App\Services\NotificationService::getTokensForTenant($tenantId, $excludeUsers);
            if (!empty($tokens)) {
                $ktModel = new \App\Models\KarangTarunaModel();
                $kt = $ktModel->find($tenantId);
                $ktName = $kt ? $kt['nama_organisasi'] : 'Karang Taruna';
                
                $title = "Pengumuman: " . $ktName;
                $body = mb_substr($data['judul'], 0, 100);
                
                // Do not block if it fails
                \App\Services\NotificationService::sendPushNotification($tokens, $title, $body, [
                    'type' => 'announcement',
                    'tenant_id' => (string)$tenantId,
                    'announcement_id' => (string)$id
                ]);
            }
        }

        return $this->sendSuccess('Pengumuman berhasil dibuat', $data, 201);
    }

    public function update($id = null)
    {
        if (!AuthService::can('announcement.manage')) {
            return $this->sendError('Forbidden', null, 403);
        }

        $tenantId = AuthService::getTenantId();
        $model = new PengumumanModel();
        $announcement = $model->where('karang_taruna_id', $tenantId)->find($id);

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
            'judul' => trim($rawInput['judul']),
            'isi' => trim($rawInput['isi']),
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
        if (!AuthService::can('announcement.manage')) {
            return $this->sendError('Forbidden', null, 403);
        }

        $tenantId = AuthService::getTenantId();
        $model = new PengumumanModel();
        $announcement = $model->where('karang_taruna_id', $tenantId)->find($id);

        if (!$announcement) {
            return $this->sendError('Pengumuman tidak ditemukan', null, 404);
        }

        $newStatus = $announcement['status_aktif'] == 1 ? 0 : 1;
        $model->update($id, ['status_aktif' => $newStatus]);

        return $this->sendSuccess('Status pengumuman berhasil diubah');
    }

    public function delete($id = null)
    {
        if (!AuthService::can('announcement.manage')) {
            return $this->sendError('Forbidden', null, 403);
        }

        $tenantId = AuthService::getTenantId();
        $model = new PengumumanModel();
        if (!$model->where('karang_taruna_id', $tenantId)->find($id)) {
            return $this->sendError('Pengumuman tidak ditemukan', null, 404);
        }

        $model->delete($id);

        return $this->sendSuccess('Pengumuman berhasil dihapus');
    }
}
