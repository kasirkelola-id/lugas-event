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
        $user = AuthService::getUser();

        if (!$user || !in_array($user['role_level'], ['admin', 'pengelola'])) {
            return $this->sendError('Forbidden', null, 403);
        }

        $eventModel = new EventModel();
        $builder = $eventModel->builder();
        
        if ($user['role_level'] === 'pengelola') {
            $builder->where('dibuat_oleh', $user['id']);
        }

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

        // Hitung total peserta berdasarkan event_participants
        $totalPeserta = 0;
        if (!empty($eventIds)) {
            $participantModel = new \App\Models\EventParticipantModel();
            $totalPeserta = $participantModel->whereIn('event_id', $eventIds)->countAllResults();
        }

        // Hitung total hadir dari event in scope
        $totalHadir = 0;
        if (!empty($eventIds)) {
            $absensiModel = new AbsensiModel();
            $totalHadir = $absensiModel->whereIn('event_id', $eventIds)->countAllResults();
        }

        // Since attendance is open to all members, "belum hadir" and "persentase" relative to event_participants 
        // are no longer logically sound. Default to 0 to prevent misleading metrics.
        $totalBelumHadir = 0;
        $persentaseKehadiran = 0;

        $data = [
            'total_acara' => $totalAcara,
            'acara_aktif' => $acaraAktif,
            'acara_selesai' => $acaraSelesai,
            'total_peserta' => $totalPeserta,
            'total_hadir' => $totalHadir,
            'total_belum_hadir' => $totalBelumHadir,
            'persentase_kehadiran' => round($persentaseKehadiran, 2),
        ];

        return $this->sendSuccess('Ringkasan laporan', $data);
    }
}
