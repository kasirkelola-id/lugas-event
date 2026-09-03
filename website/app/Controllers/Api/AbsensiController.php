<?php

namespace App\Controllers\Api;

use App\Models\AbsensiModel;
use App\Models\EventModel;
use App\Models\UserModel;
use App\Services\AuthService;

class AbsensiController extends BaseApiController
{
    // checkPengelola replaced by RBAC

    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371000; // Radius Bumi dalam meter
        
        $latFrom = deg2rad((float)$lat1);
        $lonFrom = deg2rad((float)$lon1);
        $latTo = deg2rad((float)$lat2);
        $lonTo = deg2rad((float)$lon2);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
          cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
          
        return $angle * $earthRadius;
    }

    public function checkin()
    {
        $tenantId = AuthService::getTenantId();
        $userId = AuthService::getGlobalUserId();
        if (!$tenantId || !AuthService::can('attendance.checkin')) {
            return $this->sendError('Forbidden', null, 403);
        }

        $userModel = new UserModel();
        $dbUser = $userModel->find($userId);
        if (!$dbUser || (int)$dbUser['status_aktif'] !== 1) {
            return $this->sendError('User tidak aktif', null, 403);
        }

        $rules = [
            'event_id' => 'required|numeric'
        ];

        $rawInput = $this->request->getJSON(true) ?? $this->request->getRawInput();
        if (!$this->validateData($rawInput, $rules)) {
            return $this->sendError('Validasi gagal', $this->validator->getErrors(), 422);
        }

        $eventId = $rawInput['event_id'];

        $eventModel = new EventModel();
        $event = $eventModel->where('karang_taruna_id', $tenantId)->find($eventId);

        if (!$event) {
            return $this->sendError('Acara tidak ditemukan', null, 404);
        }

        $statusAktif = $event['status_aktif'] === 1 || $event['status_aktif'] === '1' || strtolower((string)$event['status_aktif']) === 'aktif' ? 1 : 0;
        if ($statusAktif !== 1) {
            return $this->sendError('Acara sudah ditutup', null, 422);
        }

        // --- Time Window Enforcement ---
        $today = date('Y-m-d');
        if ($event['tanggal_acara'] !== $today) {
            if ($today < $event['tanggal_acara']) {
                return $this->sendError('Belum Waktunya', ['time' => 'Acara ini dijadwalkan pada ' . $event['tanggal_acara'] . '. Absensi belum dibuka.'], 422);
            } else {
                return $this->sendError('Waktu Habis', ['time' => 'Acara ini sudah selesai pada ' . $event['tanggal_acara'] . '.'], 422);
            }
        }

        if (!empty($event['waktu_mulai']) && !empty($event['waktu_selesai'])) {
            $nowStr = date('H:i:s');
            $startStr = $event['waktu_mulai'];
            $endStr = $event['waktu_selesai'];

            // Allow 30 mins early
            $startTime = strtotime($startStr) - (30 * 60);
            // Allow 30 mins late
            $endTime = strtotime($endStr) + (30 * 60);
            $nowTime = strtotime($nowStr);

            if ($nowTime < $startTime) {
                return $this->sendError('Belum Waktunya', ['time' => 'Absensi belum dibuka. Silakan kembali nanti.'], 422);
            }
            if ($nowTime > $endTime) {
                return $this->sendError('Waktu Habis', ['time' => 'Absensi sudah ditutup.'], 422);
            }
        }
        // --------------------------------

        $userLat = $rawInput['user_lat'] ?? null;
        $userLng = $rawInput['user_lng'] ?? null;
        $accuracy = $rawInput['accuracy'] ?? null;
        
        $distance = null;
        if (isset($event['require_gps']) && (int)$event['require_gps'] === 1) {
            if ($event['latitude'] === null || $event['longitude'] === null || $event['radius'] === null) {
                return $this->sendError('Konfigurasi Gagal', ['gps' => 'Lokasi acara belum diatur oleh admin. Tidak dapat melakukan absensi berbasisi GPS.'], 422);
            }
            if ($userLat === null || $userLng === null) {
                return $this->sendError('Akses Lokasi Diperlukan', ['gps' => 'Koordinat GPS Anda diperlukan untuk melakukan absensi pada acara ini.'], 422);
            }
            $distance = $this->calculateDistance($event['latitude'], $event['longitude'], $userLat, $userLng);
            if ($distance > $event['radius']) {
                return $this->sendError('Lokasi Di Luar Jangkauan', ['gps' => 'Anda berada di luar area yang diizinkan untuk absensi ini. Jarak Anda: ' . round($distance) . 'm. Radius maksimal: ' . $event['radius'] . 'm.'], 422);
            }
        }

        $absensiModel = new AbsensiModel();
        
        // This 'SELECT before INSERT' check is kept for general fast path
        $existing = $absensiModel->where('event_id', $event['id'])
                                 ->where('user_id', $userId)
                                 ->first();
        if ($existing) {
            return $this->sendError('Anda sudah melakukan check-in pada acara ini.', null, 409);
        }

        $waktuAbsen = date('Y-m-d H:i:s');

        try {
            $absensiModel->insert([
                'karang_taruna_id' => $tenantId,
                'event_id'    => $event['id'],
                'user_id'     => $userId,
                'waktu_absen' => $waktuAbsen,
                'latitude'    => $userLat,
                'longitude'   => $userLng,
                'accuracy'    => $accuracy,
                'distance_m'  => $distance !== null ? round($distance) : null
            ]);
        } catch (\Exception $e) {
            // Graceful duplicate constraint handler
            if (strpos(strtolower($e->getMessage()), 'duplicate') !== false || strpos(strtolower($e->getMessage()), 'unique') !== false) {
                return $this->sendError('Anda sudah melakukan check-in pada acara ini.', null, 409);
            }
            return $this->sendError('Terjadi kesalahan sistem saat menyimpan absensi.', null, 500);
        }

        return $this->sendSuccess('Check-in berhasil', [
            'event_id' => (int)$event['id'],
            'nama_acara' => $event['nama_acara'],
            'waktu_checkin' => $waktuAbsen
        ], 201);
    }

    public function checkout()
    {
        $tenantId = AuthService::getTenantId();
        $userId = AuthService::getGlobalUserId();
        if (!$tenantId || !AuthService::can('attendance.checkin')) {
            return $this->sendError('Forbidden', null, 403);
        }

        $rules = [
            'event_id' => 'required|numeric'
        ];

        $rawInput = $this->request->getJSON(true) ?? $this->request->getRawInput();
        if (!$this->validateData($rawInput, $rules)) {
            return $this->sendError('Validasi gagal', $this->validator->getErrors(), 422);
        }

        $eventId = $rawInput['event_id'];
        $eventModel = new EventModel();
        $event = $eventModel->where('karang_taruna_id', $tenantId)->find($eventId);

        if (!$event) {
            return $this->sendError('Acara tidak ditemukan', null, 404);
        }

        $userLat = $rawInput['user_lat'] ?? null;
        $userLng = $rawInput['user_lng'] ?? null;
        
        if (isset($event['require_gps']) && (int)$event['require_gps'] === 1) {
            if ($event['latitude'] === null || $event['longitude'] === null || $event['radius'] === null) {
                return $this->sendError('Konfigurasi Gagal', ['gps' => 'Lokasi acara belum diatur oleh admin.'], 422);
            }
            if ($userLat === null || $userLng === null) {
                return $this->sendError('Akses Lokasi Diperlukan', ['gps' => 'Koordinat GPS Anda diperlukan untuk melakukan check-out.'], 422);
            }
            $distance = $this->calculateDistance($event['latitude'], $event['longitude'], $userLat, $userLng);
            if ($distance > $event['radius']) {
                return $this->sendError('Lokasi Di Luar Jangkauan', ['gps' => 'Anda berada di luar area yang diizinkan untuk check-out ini. Jarak Anda: ' . round($distance) . 'm. Radius maksimal: ' . $event['radius'] . 'm.'], 422);
            }
        }

        $absensiModel = new AbsensiModel();
        
        $existing = $absensiModel->where('event_id', $event['id'])
                                 ->where('user_id', $userId)
                                 ->first();
        
        if (!$existing) {
            return $this->sendError('Anda belum melakukan check-in pada acara ini.', null, 404);
        }

        if (!empty($existing['waktu_checkout'])) {
            return $this->sendError('Anda sudah melakukan check-out.', null, 409);
        }

        $waktuCheckout = date('Y-m-d H:i:s');
        $absensiModel->update($existing['id'], ['waktu_checkout' => $waktuCheckout]);

        return $this->sendSuccess('Check-out berhasil', [
            'event_id' => (int)$event['id'],
            'nama_acara' => $event['nama_acara'],
            'waktu_checkout' => $waktuCheckout
        ]);
    }

    public function status()
    {
        $userId = AuthService::getGlobalUserId();
        if (!$userId) {
            return $this->sendError('Forbidden', null, 403);
        }

        $absensiModel = new AbsensiModel();
        // Cari absensi hari ini yang belum checkout
        $today = date('Y-m-d');
        $activeAbsensi = $absensiModel->where('user_id', $userId)
                                      ->where('waktu_checkout IS NULL')
                                      ->where('waktu_absen >=', $today . ' 00:00:00')
                                      ->where('waktu_absen <=', $today . ' 23:59:59')
                                      ->findAll();

        $activeEventIds = array_column($activeAbsensi, 'event_id');
        
        return $this->sendSuccess('Status absensi', ['active_event_ids' => $activeEventIds]);
    }

    public function myHistory()
    {
        $tenantId = AuthService::getTenantId();
        $userId = AuthService::getGlobalUserId();
        if (!$tenantId) {
            return $this->sendError('Forbidden', null, 403);
        }

        $absensiModel = new AbsensiModel();
        $builder = $absensiModel->builder();
        $builder->select('absensi.id as absensi_id, absensi.event_id, absensi.waktu_absen, absensi.waktu_checkout, events.nama_acara, events.tanggal_acara, events.status_aktif as status_event');
        $builder->join('events', 'events.id = absensi.event_id');
        $builder->where('events.karang_taruna_id', $tenantId);
        $builder->where('absensi.user_id', $userId);
        $builder->orderBy('absensi.waktu_absen', 'DESC');
        
        $history = $builder->get()->getResultArray();

        $history = array_map(function($h) {
            $h['absensi_id'] = (int)$h['absensi_id'];
            $h['event_id'] = (int)$h['event_id'];
            $h['status_event'] = $h['status_event'] === 1 || $h['status_event'] === '1' || strtolower((string)$h['status_event']) === 'aktif' ? 1 : 0;
            
            // Calculate duration in minutes
            $h['durasi'] = null;
            if (!empty($h['waktu_absen']) && !empty($h['waktu_checkout'])) {
                $checkin = new \DateTime($h['waktu_absen']);
                $checkout = new \DateTime($h['waktu_checkout']);
                $diff = $checkin->diff($checkout);
                $h['durasi'] = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i;
            }
            return $h;
        }, $history);

        return $this->sendSuccess('Histori absensi', $history);
    }

    public function eventAttendees($eventId)
    {
        if (!AuthService::can('attendance.manage')) {
            return $this->sendError('Forbidden', null, 403);
        }

        $tenantId = AuthService::getTenantId();
        $eventModel = new EventModel();
        $event = $eventModel->where('karang_taruna_id', $tenantId)->find($eventId);

        if (!$event) {
            return $this->sendError('Acara tidak ditemukan', null, 404);
        }

        $absensiModel = new AbsensiModel();
        $builder = $absensiModel->builder();
        $builder->select('absensi.id as absensi_id, absensi.user_id, absensi.waktu_absen, absensi.waktu_checkout, users.nama_lengkap, users.nama_panggilan');
        $builder->join('users', 'users.id = absensi.user_id');
        $builder->where('absensi.event_id', $eventId);
        $builder->orderBy('absensi.waktu_absen', 'ASC');

        $attendees = $builder->get()->getResultArray();

        // Convert types and calculate duration
        $attendees = array_map(function($a) {
            $a['absensi_id'] = (int)$a['absensi_id'];
            $a['user_id'] = (int)$a['user_id'];
            
            $a['durasi'] = null;
            if (!empty($a['waktu_absen']) && !empty($a['waktu_checkout'])) {
                $checkin = new \DateTime($a['waktu_absen']);
                $checkout = new \DateTime($a['waktu_checkout']);
                $diff = $checkin->diff($checkout);
                $a['durasi'] = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i;
            }
            return $a;
        }, $attendees);

        return $this->sendSuccess('Daftar hadir', $attendees);
    }
}
