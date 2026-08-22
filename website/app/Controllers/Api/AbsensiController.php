<?php

namespace App\Controllers\Api;

use App\Models\AbsensiModel;
use App\Models\EventModel;
use App\Models\UserModel;
use App\Services\AuthService;

class AbsensiController extends BaseApiController
{
    private function checkPengelola()
    {
        $user = AuthService::getUser();
        if (!$user || !in_array($user['role_level'], ['pengelola', 'admin'])) {
            return false;
        }
        return true;
    }

    public function create()
    {
        $user = AuthService::getUser();
        if (!$user) {
            return $this->sendError('Unauthorized', null, 401);
        }

        $userModel = new UserModel();
        $dbUser = $userModel->find($user['id']);
        if (!$dbUser || (int)$dbUser['status_aktif'] !== 1) {
            return $this->sendError('User tidak aktif', null, 403);
        }

        $rules = [
            'kode_qr' => 'required'
        ];

        $rawInput = $this->request->getJSON(true) ?? $this->request->getRawInput();
        if (!$this->validateData($rawInput, $rules)) {
            return $this->sendError('Validasi gagal', $this->validator->getErrors(), 422);
        }

        $kodeQr = $rawInput['kode_qr'];

        $eventModel = new EventModel();
        $event = $eventModel->where('kode_qr', $kodeQr)->first();

        if (!$event) {
            return $this->sendError('QR Code Tidak Valid', null, 404);
        }

        $statusAktif = $event['status_aktif'] === 1 || $event['status_aktif'] === '1' || strtolower((string)$event['status_aktif']) === 'aktif' ? 1 : 0;
        if ($statusAktif !== 1) {
            return $this->sendError('Acara sudah ditutup', null, 422);
        }

        // Users are no longer required to be pre-registered in event_participants to attend

        $absensiModel = new AbsensiModel();
        
        $existing = $absensiModel->where('event_id', $event['id'])
                                 ->where('user_id', $user['id'])
                                 ->first();
        if ($existing) {
            return $this->sendError('Anda sudah melakukan absensi pada acara ini.', null, 409);
        }

        $waktuAbsen = date('Y-m-d H:i:s');

        try {
            $absensiModel->insert([
                'event_id'    => $event['id'],
                'user_id'     => $user['id'],
                'waktu_absen' => $waktuAbsen
            ]);
        } catch (\Exception $e) {
            if (strpos(strtolower($e->getMessage()), 'duplicate') !== false || strpos(strtolower($e->getMessage()), 'unique') !== false) {
                return $this->sendError('Anda sudah melakukan absensi pada acara ini.', null, 409);
            }
            return $this->sendError('Terjadi kesalahan sistem saat menyimpan absensi.', null, 500);
        }

        return $this->sendSuccess('Absensi berhasil', [
            'event_id' => (int)$event['id'],
            'nama_acara' => $event['nama_acara'],
            'waktu_absen' => $waktuAbsen
        ], 201);
    }

    public function myHistory()
    {
        $user = AuthService::getUser();
        if (!$user) {
            return $this->sendError('Unauthorized', null, 401);
        }

        $absensiModel = new AbsensiModel();
        $builder = $absensiModel->builder();
        $builder->select('absensi.id as absensi_id, absensi.event_id, absensi.waktu_absen, events.nama_acara, events.tanggal_acara, events.status_aktif as status_event');
        $builder->join('events', 'events.id = absensi.event_id');
        $builder->where('absensi.user_id', $user['id']);
        $builder->orderBy('absensi.waktu_absen', 'DESC');
        
        $history = $builder->get()->getResultArray();

        $history = array_map(function($h) {
            $h['absensi_id'] = (int)$h['absensi_id'];
            $h['event_id'] = (int)$h['event_id'];
            $h['status_event'] = $h['status_event'] === 1 || $h['status_event'] === '1' || strtolower((string)$h['status_event']) === 'aktif' ? 1 : 0;
            return $h;
        }, $history);

        return $this->sendSuccess('Histori absensi', $history);
    }

    public function eventAttendees($eventId)
    {
        if (!$this->checkPengelola()) {
            return $this->sendError('Forbidden', null, 403);
        }

        $user = AuthService::getUser();
        $eventModel = new EventModel();
        $event = $eventModel->find($eventId);

        if (!$event) {
            return $this->sendError('Acara tidak ditemukan', null, 404);
        }

        if ($user['role_level'] === 'pengelola' && (int)$event['dibuat_oleh'] !== (int)$user['id']) {
            return $this->sendError('Forbidden: Anda bukan pengelola acara ini', null, 403);
        }

        $absensiModel = new AbsensiModel();
        $builder = $absensiModel->builder();
        $builder->select('absensi.id as absensi_id, absensi.user_id, absensi.waktu_absen, users.nama_lengkap, users.nama_panggilan');
        $builder->join('users', 'users.id = absensi.user_id');
        $builder->where('absensi.event_id', $eventId);
        $builder->orderBy('absensi.waktu_absen', 'ASC');

        $attendees = $builder->get()->getResultArray();

        // Convert types
        $attendees = array_map(function($a) {
            $a['absensi_id'] = (int)$a['absensi_id'];
            $a['user_id'] = (int)$a['user_id'];
            return $a;
        }, $attendees);

        return $this->sendSuccess('Daftar hadir', $attendees);
    }
}
