<?php

namespace App\Controllers\Api;

use App\Models\EventModel;
use App\Models\EventParticipantModel;
use App\Models\UserModel;
use App\Services\AuthService;

class ParticipantController extends BaseApiController
{
    // checkPengelola replaced by RBAC

    private function checkEventOwnership($eventId)
    {
        $tenantId = AuthService::getTenantId();
        $role = AuthService::getRole();
        $userId = AuthService::getGlobalUserId();

        $eventModel = new EventModel();
        $event = $eventModel->where('karang_taruna_id', $tenantId)->find($eventId);
        if (!$event) {
            return false;
        }
        if (AuthService::can('event.manage')) {
            return true;
        }
        if ((int)$event['dibuat_oleh'] === (int)$userId) {
            return true;
        }
        return false;
    }

    public function index($eventId = null)
    {
        if (!AuthService::can('event.manage')) {
            return $this->sendError('Forbidden', null, 403);
        }

        if (!$this->checkEventOwnership($eventId)) {
            return $this->sendError('Forbidden: Anda bukan pengelola acara ini', null, 403);
        }

        $participantModel = new EventParticipantModel();
        $builder = $participantModel->builder();
        $builder->select('event_participants.id as participant_id, event_participants.user_id, users.nama_lengkap, users.nama_panggilan, users.no_whatsapp as whatsapp, users.role_level, users.rt as user_rt');
        $builder->join('users', 'users.id = event_participants.user_id');
        $builder->where('event_participants.event_id', $eventId);
        try { $pagination = \App\Services\CollectionPage::fromRequest($this->request)->apply($builder, 'event_participants.id'); }
        catch (\InvalidArgumentException $error) { return $this->sendError('Pagination tidak valid', null, 422); }
        $participants = $builder->get()->getResultArray();

        $participants = array_map(function($p) {
            $p['participant_id'] = (int)$p['participant_id'];
            $p['user_id'] = (int)$p['user_id'];
            return $p;
        }, $participants);

        return $this->sendSuccess('Daftar peserta', $participants, 200, $pagination);
    }

    public function add($eventId)
    {
        if (!AuthService::can('event.manage')) {
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
        if (count($userIds) > 100 || array_filter($userIds, static fn($id) => !is_scalar($id) || !ctype_digit((string)$id) || (int)$id < 1 || (int)$id > 4294967295)) {
            return $this->sendError('Daftar peserta maksimal 100 ID valid', null, 422);
        }
        try {
            $added = \App\Services\ParticipantBatchService::add((int)AuthService::getTenantId(), (int)$eventId, $userIds);
        } catch (\Throwable $error) {
            return $this->sendError('Peserta gagal disimpan', null, 500);
        }

        return $this->sendSuccess("Berhasil menambahkan $added peserta");
    }

    public function remove($eventId, $userId)
    {
        if (!AuthService::can('event.manage')) {
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
