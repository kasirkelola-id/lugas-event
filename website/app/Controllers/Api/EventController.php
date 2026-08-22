<?php

namespace App\Controllers\Api;

use App\Models\EventModel;
use App\Services\AuthService;

class EventController extends BaseApiController
{
    private function checkPengelola()
    {
        $user = AuthService::getUser();
        if (!$user || !in_array($user['role_level'], ['pengelola', 'admin'])) {
            return false;
        }
        return true;
    }

    private function checkEventOwnership($eventId)
    {
        $user = AuthService::getUser();
        if (in_array($user['role_level'], ['admin', 'pengelola'])) {
            return true; // Admin and pengelola can access any event
        }
        $eventModel = new EventModel();
        $event = $eventModel->find($eventId);
        if ($event && (int)$event['dibuat_oleh'] === (int)$user['id']) {
            return true;
        }
        return false;
    }

    public function index()
    {
        $user = AuthService::getUser();
        $eventModel = new EventModel();
        $builder = $eventModel->builder();

        if ($user['role_level'] === 'anggota') {
            // Anggota can only see active events (if they need to list them)
            $builder->groupStart()
                    ->where('status_aktif', 1)
                    ->orWhere('status_aktif', '1')
                    ->orWhere('LOWER(status_aktif)', 'aktif')
                    ->groupEnd();
        }
        // Admin and Pengelola sees all, so no filter needed

        $builder->orderBy('created_at', 'DESC');
        $events = $builder->get()->getResultArray();

        $data = array_map(function ($event) {
            return [
                'id' => (int)$event['id'],
                'nama_acara' => $event['nama_acara'],
                'tanggal_acara' => $event['tanggal_acara'],
                'kode_qr' => $event['kode_qr'],
                'dibuat_oleh' => (int)$event['dibuat_oleh'],
                'status_aktif' => $event['status_aktif'] === 1 || $event['status_aktif'] === '1' || strtolower((string)$event['status_aktif']) === 'aktif' ? 1 : 0,
                'created_at' => $event['created_at'],
            ];
        }, $events);

        return $this->sendSuccess('Daftar event', $data);
    }

    public function show($id = null)
    {
        if (!$this->checkPengelola()) {
            return $this->sendError('Forbidden', null, 403);
        }

        if (!$this->checkEventOwnership($id)) {
            return $this->sendError('Forbidden: Anda bukan pengelola acara ini', null, 403);
        }

        $eventModel = new EventModel();
        $event = $eventModel->find($id);

        if (!$event) {
            return $this->sendError('Event tidak ditemukan', null, 404);
        }

        $data = [
            'id' => (int)$event['id'],
            'nama_acara' => $event['nama_acara'],
            'tanggal_acara' => $event['tanggal_acara'],
            'kode_qr' => $event['kode_qr'],
            'status_aktif' => $event['status_aktif'] === 1 || $event['status_aktif'] === '1' || strtolower((string)$event['status_aktif']) === 'aktif' ? 1 : 0,
            'dibuat_oleh' => (int)$event['dibuat_oleh'],
            'jumlah_hadir' => (new \App\Models\AbsensiModel())->where('event_id', $id)->countAllResults(),
            'created_at' => $event['created_at'],
            'updated_at' => $event['updated_at'],
        ];

        return $this->sendSuccess('Detail event', $data);
    }

    public function create()
    {
        if (!$this->checkPengelola()) {
            return $this->sendError('Forbidden', null, 403);
        }

        $rules = [
            'nama_acara'    => 'required|max_length[255]',
            'tanggal_acara' => 'required|valid_date[Y-m-d]'
        ];

        if (!$this->validate($rules)) {
            return $this->sendError('Validasi gagal', $this->validator->getErrors(), 422);
        }

        $user = AuthService::getUser();
        $namaAcara = $this->request->getVar('nama_acara');
        $tanggalAcara = $this->request->getVar('tanggal_acara');

        // Generate QR code token: LGS-xxxxxxxx
        $kodeQr = 'LGS-' . bin2hex(random_bytes(4));

        $eventModel = new EventModel();
        while ($eventModel->where('kode_qr', $kodeQr)->first()) {
            $kodeQr = 'LGS-' . bin2hex(random_bytes(4));
        }

        $eventData = [
            'nama_acara'    => $namaAcara,
            'tanggal_acara' => $tanggalAcara,
            'kode_qr'       => $kodeQr,
            'dibuat_oleh'   => $user['id'],
            'status_aktif'  => 'aktif'
        ];

        $eventModel->insert($eventData);
        $eventId = $eventModel->getInsertID();

        $eventData['id'] = $eventId;

        return $this->sendSuccess('Event berhasil dibuat', $eventData, 201);
    }

    public function update($id = null)
    {
        if (!$this->checkPengelola()) {
            return $this->sendError('Forbidden', null, 403);
        }

        if (!$this->checkEventOwnership($id)) {
            return $this->sendError('Forbidden: Anda bukan pengelola acara ini', null, 403);
        }

        $eventModel = new EventModel();
        $event = $eventModel->find($id);

        if (!$event) {
            return $this->sendError('Event tidak ditemukan', null, 404);
        }

        $rawInput = $this->request->getJSON(true) ?? $this->request->getRawInput();
        $validationData = [];
        if (isset($rawInput['nama_acara'])) $validationData['nama_acara'] = $rawInput['nama_acara'];
        if (isset($rawInput['tanggal_acara'])) $validationData['tanggal_acara'] = $rawInput['tanggal_acara'];

        if (empty($validationData)) {
            return $this->sendError('Tidak ada data yang diubah', null, 422);
        }

        $rules = [];
        if (isset($validationData['nama_acara'])) $rules['nama_acara'] = 'required|max_length[255]';
        if (isset($validationData['tanggal_acara'])) $rules['tanggal_acara'] = 'required|valid_date[Y-m-d]';

        if (!$this->validateData($validationData, $rules)) {
            return $this->sendError('Validasi gagal', $this->validator->getErrors(), 422);
        }

        $eventModel->update($id, $validationData);

        return $this->sendSuccess('Event berhasil diperbarui');
    }

    public function close($id)
    {
        if (!$this->checkPengelola()) {
            return $this->sendError('Forbidden', null, 403);
        }

        if (!$this->checkEventOwnership($id)) {
            return $this->sendError('Forbidden: Anda bukan pengelola acara ini', null, 403);
        }

        $rawInput = $this->request->getJSON(true) ?? $this->request->getRawInput();
        
        if (!isset($rawInput['status_aktif']) || (int)$rawInput['status_aktif'] !== 0) {
             return $this->sendError('Validasi gagal', ['status_aktif' => 'Status aktif harus bernilai 0'], 422);
        }

        $eventModel = new EventModel();
        $event = $eventModel->find($id);

        if (!$event) {
            return $this->sendError('Event tidak ditemukan', null, 404);
        }

        $eventModel->update($id, ['status_aktif' => 'selesai']);

        return $this->sendSuccess('Event berhasil ditutup');
    }
}
