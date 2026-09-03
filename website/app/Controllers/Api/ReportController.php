<?php

namespace App\Controllers\Api;

use App\Models\EventModel;
use App\Models\AbsensiModel;
use App\Models\UserModel;
use App\Services\AuthService;

class ReportController extends BaseApiController
{
    public function summary()
    {
        $tenantId = AuthService::getTenantId();
        $role = AuthService::getRole();

        if (!$tenantId || !AuthService::can('report.view')) {
            return $this->sendError('Forbidden', null, 403);
        }

        $eventModel = new EventModel();
        $builder = $eventModel->builder();
        
        // Admin and Pengelola can view report for their events
        $builder->where('karang_taruna_id', $tenantId);

        // Hitung acara
        $events = $builder->get()->getResultArray();
        $totalAcara = count($events);
        $acaraAktif = 0;
        $acaraSelesai = 0;
        $eventIds = [];

        foreach ($events as $event) {
            $eventIds[] = (int)$event['id'];
            $isActive = $event['status_aktif'] === 1 || $event['status_aktif'] === '1' || strtolower((string)$event['status_aktif']) === 'aktif';
            if ($isActive) {
                $acaraAktif++;
            } else {
                $acaraSelesai++;
            }
        }

        // Hitung total hadir dari event in scope
        $totalHadir = 0;
        if (!empty($eventIds)) {
            $absensiModel = new AbsensiModel();
            $totalHadir = $absensiModel->whereIn('event_id', $eventIds)->countAllResults();
        }

        // Since attendance is open to all members, "peserta terdaftar", "belum hadir", and 
        // "persentase" relative to event_participants are no longer logically sound. 
        // Default to 0 to prevent misleading metrics.
        $totalPeserta = 0;
        $totalBelumHadir = 0;
        $persentaseKehadiran = 0;

        $data = [
            'total_acara' => $totalAcara,
            'acara_aktif' => $acaraAktif,
            'acara_selesai' => $acaraSelesai,
            'total_peserta' => $totalPeserta, // legacy compatibility
            'total_hadir' => $totalHadir,
            'total_belum_hadir' => $totalBelumHadir, // legacy compatibility
            'persentase_kehadiran' => round($persentaseKehadiran, 2), // legacy compatibility
        ];

        return $this->sendSuccess('Ringkasan laporan', $data);
    }
}
