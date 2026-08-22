<?php

namespace App\Controllers\Api;

use App\Models\EventModel;
use App\Models\EventParticipantModel;
use App\Models\UserModel;
use App\Services\AuthService;

class ParticipantController extends BaseApiController
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
        if ($user['role_level'] === 'admin') {
            return true;
        }
        $eventModel = new EventModel();
        $event = $eventModel->find($eventId);
        if ($event && (int)$event['dibuat_oleh'] === (int)$user['id']) {
            return true;
        }
        return false;
    }

    public function index($eventId)
    {
        if (!$this->checkPengelola()) {
            return $this->sendError('Forbidden', null, 403);
        }

        if (!$this->checkEventOwnership($eventId)) {
            return $this->sendError('Forbidden: Anda bukan pengelola acara ini', null, 403);
        }

        $participantModel = new EventParticipantModel();
        $builder = $participantModel->builder();
        $builder->select('event_participants.id as participant_id, event_participants.user_id, users.nama_lengkap, users.nama_panggilan, users.whatsapp, users.role_level');
        $builder->join('users', 'users.id = event_participants.user_id');
        $builder->where('event_participants.event_id', $eventId);
        $participants = $builder->get()->getResultArray();

        $participants = array_map(function($p) {
            $p['participant_id'] = (int)$p['participant_id'];
            $p['user_id'] = (int)$p['user_id'];
            return $p;
        }, $participants);

        return $this->sendSuccess('Daftar peserta', $participants);
    }

    public function add($eventId)
    {
        if (!$this->checkPengelola()) {
            return $this->sendError('Forbidden', null, 403);
        }

        if (!$this->checkEventOwnership($eventId)) {
            return $this->sendError('Forbidden: Anda bukan pengelola acara ini', null, 403);
        }

        $rules = [
            'user_ids' => 'required' // expected array of user IDs
        ];

        $rawInput = $this->request->getJSON(true) ?? $this->request->getRawInput();
        
        if (!isset($rawInput['user_ids']) || !is_array($rawInput['user_ids'])) {
             return $this->sendError('Validasi gagal', ['user_ids' => 'Daftar user_id harus berupa array'], 422);
        }

        $userIds = $rawInput['user_ids'];
        $participantModel = new EventParticipantModel();
        $userModel = new UserModel();

        $added = 0;
        foreach ($userIds as $userId) {
            $user = $userModel->find($userId);
            if ($user && $user['role_level'] === 'anggota' && (int)$user['status_aktif'] === 1) {
                // Check if already registered
                $exists = $participantModel->where('event_id', $eventId)->where('user_id', $userId)->first();
                if (!$exists) {
                    $participantModel->insert([
                        'event_id' => $eventId,
                        'user_id' => $userId,
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                    $added++;
                }
            }
        }

        return $this->sendSuccess("Berhasil menambahkan $added peserta");
    }

    public function remove($eventId, $userId)
    {
        if (!$this->checkPengelola()) {
            return $this->sendError('Forbidden', null, 403);
        }

        if (!$this->checkEventOwnership($eventId)) {
            return $this->sendError('Forbidden: Anda bukan pengelola acara ini', null, 403);
        }

        $participantModel = new EventParticipantModel();
        $exists = $participantModel->where('event_id', $eventId)->where('user_id', $userId)->first();

        if (!$exists) {
            return $this->sendError('Peserta tidak ditemukan', null, 404);
        }

        $participantModel->delete($exists['id']);
        
        return $this->sendSuccess('Peserta berhasil dihapus');
    }
}
