<?php

namespace App\Controllers\Api;

use App\Models\EventModel;
use App\Services\AuthService;

class EventController extends BaseApiController
{
    // checkPengelola replaced by RBAC

    private function checkEventOwnership($eventId)
    {
        $tenantId = AuthService::getTenantId();
        $userId = AuthService::getGlobalUserId();
        
        if (AuthService::can('event.manage')) {
            return true; // Admin, ketua, and pengelola can access any event
        }
        $eventModel = new EventModel();
        $event = $eventModel->where('karang_taruna_id', $tenantId)->find($eventId);
        if ($event && (int)$event['dibuat_oleh'] === (int)$userId) {
            return true;
        }
        return false;
    }

    public function index()
    {
        $tenantId = AuthService::getTenantId();
        if (!$tenantId || !AuthService::can('event.view')) {
            return $this->sendError('Forbidden', null, 403);
        }
        
        $eventModel = new EventModel();
        $builder = $eventModel->builder();
        $builder->where('karang_taruna_id', $tenantId);

        if (!AuthService::can('event.manage')) {
            // Anggota can only see active events (if they need to list them)
            $builder->groupStart()
                    ->where('status_aktif', 1)
                    ->orWhere('status_aktif', '1')
                    ->orWhere('LOWER(status_aktif)', 'aktif')
                    ->groupEnd();
        }
        // Admin and Pengelola sees all, so no filter needed

        // Order: Selesai is last. Upcoming is first (sorted by tanggal_acara ASC).
        // Then Past (but not selesai) is sorted by tanggal_acara DESC.
        $today = date('Y-m-d');
        $builder->orderBy("CASE WHEN status_aktif = 'selesai' THEN 2 WHEN tanggal_acara >= '$today' THEN 0 ELSE 1 END", 'ASC');
        $builder->orderBy("CASE WHEN tanggal_acara >= '$today' THEN tanggal_acara ELSE NULL END", 'ASC');
        $builder->orderBy("CASE WHEN tanggal_acara < '$today' THEN tanggal_acara ELSE NULL END", 'DESC');
        $builder->orderBy("waktu_mulai", 'ASC');
        $events = $builder->get()->getResultArray();

        $data = array_map(function ($event) {
            // Hitung status_kegiatan
            $statusKegiatan = 'akan_datang';
            if ($event['status_aktif'] === 'selesai') {
                $statusKegiatan = 'selesai';
            } else {
                $now = date('Y-m-d H:i:s');
                $start = $event['tanggal_acara'] . ' ' . ($event['waktu_mulai'] ?: '00:00:00');
                $end = $event['tanggal_acara'] . ' ' . ($event['waktu_selesai'] ?: '23:59:59');
                if ($now > $end) {
                    $statusKegiatan = 'selesai';
                } elseif ($now >= $start && $now <= $end) {
                    $statusKegiatan = 'berlangsung';
                }
            }

            return [
                'id' => (int)$event['id'],
                'nama_acara' => $event['nama_acara'],
                'tanggal_acara' => $event['tanggal_acara'],
                'waktu_mulai' => $event['waktu_mulai'],
                'waktu_selesai' => $event['waktu_selesai'],
                'kode_qr' => $event['kode_qr'],
                'dibuat_oleh' => (int)$event['dibuat_oleh'],
                'status_aktif' => $event['status_aktif'] === 1 || $event['status_aktif'] === '1' || strtolower((string)$event['status_aktif']) === 'aktif' ? 1 : 0,
                'status_kegiatan' => $statusKegiatan,
                'jumlah_hadir' => (new \App\Models\AbsensiModel())->where('event_id', $event['id'])->countAllResults(),
                'require_gps' => (int)$event['require_gps'],
                'latitude' => $event['latitude'] ? (float)$event['latitude'] : null,
                'longitude' => $event['longitude'] ? (float)$event['longitude'] : null,
                'radius' => $event['radius'] ? (int)$event['radius'] : null,
                'created_at' => $event['created_at'],
            ];
        }, $events);

        return $this->sendSuccess('Daftar event', $data);
    }

    public function show($id = null)
    {
        if (!AuthService::can('event.manage')) {
            return $this->sendError('Forbidden', null, 403);
        }

        if (!$this->checkEventOwnership($id)) {
            return $this->sendError('Forbidden: Anda bukan pengelola acara ini', null, 403);
        }

        $tenantId = AuthService::getTenantId();
        $eventModel = new EventModel();
        $event = $eventModel->where('karang_taruna_id', $tenantId)->find($id);

        if (!$event) {
            return $this->sendError('Event tidak ditemukan', null, 404);
        }

        $statusKegiatan = 'akan_datang';
        if ($event['status_aktif'] === 'selesai') {
            $statusKegiatan = 'selesai';
        } else {
            $now = date('Y-m-d H:i:s');
            $start = $event['tanggal_acara'] . ' ' . ($event['waktu_mulai'] ?: '00:00:00');
            $end = $event['tanggal_acara'] . ' ' . ($event['waktu_selesai'] ?: '23:59:59');
            if ($now > $end) {
                $statusKegiatan = 'selesai';
            } elseif ($now >= $start && $now <= $end) {
                $statusKegiatan = 'berlangsung';
            }
        }

        $userId = AuthService::getGlobalUserId();
        $isAttended = (new \App\Models\AbsensiModel())->where('event_id', $id)->where('user_id', $userId)->countAllResults() > 0;
        $userAttendanceStatus = $isAttended ? 'sudah_absen' : 'belum_absen';

        $data = [
            'id' => (int)$event['id'],
            'nama_acara' => $event['nama_acara'],
            'tanggal_acara' => $event['tanggal_acara'],
            'waktu_mulai' => $event['waktu_mulai'],
            'waktu_selesai' => $event['waktu_selesai'],
            'kode_qr' => $event['kode_qr'],
            'status_aktif' => $event['status_aktif'] === 1 || $event['status_aktif'] === '1' || strtolower((string)$event['status_aktif']) === 'aktif' ? 1 : 0,
            'status_kegiatan' => $statusKegiatan,
            'dibuat_oleh' => (int)$event['dibuat_oleh'],
            'jumlah_hadir' => (new \App\Models\AbsensiModel())->where('event_id', $id)->countAllResults(),
            'require_gps' => (int)$event['require_gps'],
            'latitude' => $event['latitude'] ? (float)$event['latitude'] : null,
            'longitude' => $event['longitude'] ? (float)$event['longitude'] : null,
            'radius' => $event['radius'] ? (int)$event['radius'] : null,
            'user_attendance_status' => $userAttendanceStatus,
            'created_at' => $event['created_at'],
            'updated_at' => $event['updated_at'],
        ];

        return $this->sendSuccess('Detail event', $data);
    }

    public function create()
    {
        if (!AuthService::can('event.manage')) {
            return $this->sendError('Forbidden', null, 403);
        }

        $rules = [
            'nama_acara'    => 'required|max_length[255]',
            'tanggal_acara' => 'required|valid_date[Y-m-d]'
        ];

        if (!$this->validate($rules)) {
            return $this->sendError('Validasi gagal', $this->validator->getErrors(), 422);
        }

        $tenantId = AuthService::getTenantId();
        $userId = AuthService::getGlobalUserId();
        
        $rawInput = $this->request->getJSON(true) ?? $this->request->getRawInput();
        
        $namaAcara = $rawInput['nama_acara'] ?? $this->request->getVar('nama_acara');
        $tanggalAcara = $rawInput['tanggal_acara'] ?? $this->request->getVar('tanggal_acara');
        $waktuMulai = $rawInput['waktu_mulai'] ?? null;
        $waktuSelesai = $rawInput['waktu_selesai'] ?? null;
        $requireGps = isset($rawInput['require_gps']) ? (int)$rawInput['require_gps'] : 0;
        
        if ($requireGps === 1) {
            if (!isset($rawInput['latitude']) || !isset($rawInput['longitude']) || !isset($rawInput['radius'])) {
                return $this->sendError('Validasi gagal', ['gps' => 'Koordinat dan radius wajib diisi jika fitur GPS diaktifkan.'], 422);
            }
        }

        // Generate QR code token: LGS-xxxxxxxx
        $kodeQr = 'LGS-' . bin2hex(random_bytes(4));

        $eventModel = new EventModel();
        while ($eventModel->where('kode_qr', $kodeQr)->first()) {
            $kodeQr = 'LGS-' . bin2hex(random_bytes(4));
        }

        $eventData = [
            'karang_taruna_id' => $tenantId,
            'nama_acara'    => $namaAcara,
            'tanggal_acara' => $tanggalAcara,
            'waktu_mulai'   => $waktuMulai,
            'waktu_selesai' => $waktuSelesai,
            'kode_qr'       => $kodeQr,
            'dibuat_oleh'   => $userId,
            'status_aktif'  => 'aktif',
            'require_gps'   => $requireGps,
            'latitude'      => $requireGps === 1 ? $rawInput['latitude'] : null,
            'longitude'     => $requireGps === 1 ? $rawInput['longitude'] : null,
            'radius'        => $requireGps === 1 ? $rawInput['radius'] : null,
        ];

        $eventModel->insert($eventData);
        $eventId = $eventModel->getInsertID();

        $eventData['id'] = $eventId;

        // Trigger push notification for new event
        $excludeUsers = [$userId];
        $tokens = \App\Services\NotificationService::getTokensForTenant($tenantId, $excludeUsers);
        if (!empty($tokens)) {
            $ktModel = new \App\Models\KarangTarunaModel();
            $kt = $ktModel->find($tenantId);
            $ktName = $kt ? $kt['nama_organisasi'] : 'Karang Taruna';
            
            $title = "Event Baru: " . $ktName;
            $body = mb_substr($namaAcara, 0, 100);
            
            \App\Services\NotificationService::sendPushNotification($tokens, $title, $body, [
                'type' => 'event',
                'tenant_id' => (string)$tenantId,
                'event_id' => (string)$eventId
            ]);
        }

        return $this->sendSuccess('Event berhasil dibuat', $eventData, 201);
    }

    public function update($id = null)
    {
        if (!AuthService::can('event.manage')) {
            return $this->sendError('Forbidden', null, 403);
        }

        if (!$this->checkEventOwnership($id)) {
            return $this->sendError('Forbidden: Anda bukan pengelola acara ini', null, 403);
        }

        $tenantId = AuthService::getTenantId();
        $eventModel = new EventModel();
        $event = $eventModel->where('karang_taruna_id', $tenantId)->find($id);

        if (!$event) {
            return $this->sendError('Event tidak ditemukan', null, 404);
        }

        $rawInput = $this->request->getJSON(true) ?? $this->request->getRawInput();
        $validationData = [];
        if (isset($rawInput['nama_acara'])) $validationData['nama_acara'] = $rawInput['nama_acara'];
        if (isset($rawInput['tanggal_acara'])) $validationData['tanggal_acara'] = $rawInput['tanggal_acara'];
        if (isset($rawInput['waktu_mulai'])) $validationData['waktu_mulai'] = $rawInput['waktu_mulai'];
        if (isset($rawInput['waktu_selesai'])) $validationData['waktu_selesai'] = $rawInput['waktu_selesai'];
        if (isset($rawInput['require_gps'])) {
            $validationData['require_gps'] = (int)$rawInput['require_gps'];
            if ($validationData['require_gps'] === 1) {
                $validationData['latitude'] = $rawInput['latitude'] ?? $event['latitude'];
                $validationData['longitude'] = $rawInput['longitude'] ?? $event['longitude'];
                $validationData['radius'] = $rawInput['radius'] ?? $event['radius'];
                
                if (empty($validationData['latitude']) || empty($validationData['longitude']) || empty($validationData['radius'])) {
                    return $this->sendError('Validasi gagal', ['gps' => 'Koordinat dan radius wajib diisi jika fitur GPS diaktifkan.'], 422);
                }
            } else {
                $validationData['latitude'] = null;
                $validationData['longitude'] = null;
                $validationData['radius'] = null;
            }
        }

        if (empty($validationData)) {
            return $this->sendError('Tidak ada data yang diubah', null, 422);
        }

        $rules = [];
        if (isset($validationData['nama_acara'])) $rules['nama_acara'] = 'required|max_length[255]';
        if (isset($validationData['tanggal_acara'])) $rules['tanggal_acara'] = 'required|valid_date[Y-m-d]';

        if (!empty($rules)) {
            if (!$this->validateData($validationData, $rules)) {
                return $this->sendError('Validasi gagal', $this->validator->getErrors(), 422);
            }
        }

        $absensiCount = (new \App\Models\AbsensiModel())->where('event_id', $id)->countAllResults();
        if ($absensiCount > 0) {
            $blockedFields = ['tanggal_acara', 'waktu_mulai', 'waktu_selesai', 'require_gps', 'latitude', 'longitude', 'radius'];
            foreach ($blockedFields as $field) {
                if (array_key_exists($field, $validationData)) {
                    // Check if value actually changed
                    $oldValue = $event[$field];
                    $newValue = $validationData[$field];
                    // Strict type check or conversion might be needed, but simple comparison works for most string/null
                    if ((string)$oldValue !== (string)$newValue) {
                        return $this->sendError('Validasi gagal', ['umum' => 'Tidak dapat mengubah jadwal, koordinat, atau radius acara karena sudah ada absensi peserta.'], 422);
                    }
                }
            }
        }

        $eventModel->update($id, $validationData);

        return $this->sendSuccess('Event berhasil diperbarui');
    }

    public function close($id)
    {
        if (!AuthService::can('event.manage')) {
            return $this->sendError('Forbidden', null, 403);
        }

        if (!$this->checkEventOwnership($id)) {
            return $this->sendError('Forbidden: Anda bukan pengelola acara ini', null, 403);
        }

        $rawInput = $this->request->getJSON(true) ?? $this->request->getRawInput();
        
        if (!isset($rawInput['status_aktif']) || (int)$rawInput['status_aktif'] !== 0) {
             return $this->sendError('Validasi gagal', ['status_aktif' => 'Status aktif harus bernilai 0'], 422);
        }

        $tenantId = AuthService::getTenantId();
        $eventModel = new EventModel();
        $event = $eventModel->where('karang_taruna_id', $tenantId)->find($id);

        if (!$event) {
            return $this->sendError('Event tidak ditemukan', null, 404);
        }

        $eventModel->update($id, ['status_aktif' => 'selesai']);

        return $this->sendSuccess('Event berhasil ditutup');
    }
}
