<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\VotingModel;
use App\Models\VotingOptionModel;
use App\Models\VotingVoteModel;
use App\Services\AuthService;
use CodeIgniter\API\ResponseTrait;

class VotingController extends BaseApiController
{
    protected $votingModel;
    protected $optionModel;
    protected $voteModel;

    public function __construct()
    {
        $this->votingModel = new VotingModel();
        $this->optionModel = new VotingOptionModel();
        $this->voteModel = new VotingVoteModel();
    }

    private function getDynamicStatus($voting) {
        if ($voting['status'] === 'closed') return 'ended';
        $nowDate = date('Y-m-d');
        $startDate = date('Y-m-d', strtotime($voting['waktu_mulai']));
        $endDate = date('Y-m-d', strtotime($voting['waktu_selesai']));
        
        if ($nowDate < $startDate) return 'scheduled';
        if ($nowDate > $endDate) return 'ended';
        return 'active';
    }

    public function index()
    {
        $tenantId = AuthService::getTenantId();
        $userId = AuthService::getGlobalUserId();
        if (!$tenantId || !AuthService::can('voting.view')) return $this->sendError('Tidak diizinkan', null, 403);

        $votings = $this->votingModel->where('karang_taruna_id', $tenantId)
                                     ->orderBy('created_at', 'DESC')
                                     ->findAll();

        foreach ($votings as &$voting) {
            $voting['status'] = $this->getDynamicStatus($voting);
            $hasVoted = $this->voteModel->where('voting_id', $voting['id'])
                                        ->where('user_id', $userId)
                                        ->first();
            $voting['has_voted'] = $hasVoted ? true : false;
            
            if ($voting['status'] === 'ended') {
                $voting['total_votes'] = $this->voteModel->where('voting_id', $voting['id'])->countAllResults();
            } else {
                $voting['total_votes'] = null;
            }
        }

        return $this->sendSuccess('Daftar voting', $votings);
    }

    public function show($id = null)
    {
        $tenantId = AuthService::getTenantId();
        $userId = AuthService::getGlobalUserId();
        if (!$tenantId || !AuthService::can('voting.view')) return $this->sendError('Tidak diizinkan', null, 403);

        $voting = $this->votingModel->where('karang_taruna_id', $tenantId)
                                    ->where('id', $id)
                                    ->first();

        if (!$voting) return $this->sendError('Voting tidak ditemukan', null, 404);

        $voting['status'] = $this->getDynamicStatus($voting);
        $options = $this->optionModel->where('voting_id', $id)->findAll();
        $hasVoted = $this->voteModel->where('voting_id', $id)->where('user_id', $userId)->first();

        $voting['has_voted'] = $hasVoted ? true : false;
        $voting['voted_option_id'] = $hasVoted ? $hasVoted['option_id'] : null;

        if ($voting['status'] === 'ended') {
            $voting['total_votes'] = $this->voteModel->where('voting_id', $id)->countAllResults();
            foreach ($options as &$option) {
                $optionVotes = $this->voteModel->where('option_id', $option['id'])->countAllResults();
                $option['vote_count'] = $optionVotes;
                $option['percentage'] = $voting['total_votes'] > 0 ? round(($optionVotes / $voting['total_votes']) * 100, 1) : 0;
            }
        } else {
            $voting['total_votes'] = null;
            foreach ($options as &$option) {
                $option['vote_count'] = null;
                $option['percentage'] = null;
            }
        }

        $voting['options'] = $options;

        return $this->sendSuccess('Detail voting', $voting);
    }

    public function create()
    {
        $tenantId = AuthService::getTenantId();
        $userId = AuthService::getGlobalUserId();
        if (!$tenantId || !AuthService::can('voting.manage')) {
            return $this->sendError('Akses ditolak', null, 403);
        }

        $rawInput = $this->request->getJSON(true) ?? $this->request->getRawInput();

        $rules = [
            'title'   => 'required|min_length[3]',
            'options' => 'required',
            'waktu_mulai'   => 'required',
            'waktu_selesai' => 'required',
        ];

        if (!$this->validateData($rawInput, $rules)) {
            return $this->sendError('Validasi gagal', $this->validator->getErrors(), 422);
        }

        $startTs = strtotime($rawInput['waktu_mulai']);
        $endTs = strtotime($rawInput['waktu_selesai']);

        if (!$startTs || !$endTs) {
            return $this->sendError('Validasi gagal', ['waktu' => 'Format tanggal tidak valid'], 422);
        }
        
        $startDate = date('Y-m-d', $startTs);
        $endDate = date('Y-m-d', $endTs);

        if ($startDate > $endDate) {
             return $this->sendError('Validasi gagal', ['waktu_selesai' => 'Tanggal selesai harus setelah atau sama dengan tanggal mulai'], 422);
        }

        $options = $rawInput['options'] ?? null;
        if (!is_array($options) || count($options) < 2) {
            return $this->sendError('Validasi gagal', ['options' => 'Minimal 2 pilihan (options) harus diberikan'], 422);
        }

        $db = \Config\Database::connect();
        $db->transStart();

        $votingData = [
            'karang_taruna_id' => $tenantId,
            'title'            => $rawInput['title'],
            'description'      => $rawInput['description'] ?? null,
            'waktu_mulai'      => $startDate . ' 00:00:00',
            'waktu_selesai'    => $endDate . ' 23:59:59',
            'status'           => 'active',
            'created_by'       => $userId
        ];

        $this->votingModel->insert($votingData);
        $votingId = $this->votingModel->getInsertID();

        foreach ($options as $opt) {
            if (is_string($opt) && trim($opt) != '') {
                $this->optionModel->insert([
                    'voting_id'   => $votingId,
                    'option_name' => trim($opt)
                ]);
            } else if (is_array($opt) && isset($opt['option_name']) && trim($opt['option_name']) != '') {
                $this->optionModel->insert([
                    'voting_id'   => $votingId,
                    'option_name' => trim($opt['option_name'])
                ]);
            }
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->sendError('Gagal membuat voting', null, 500);
        }

        return $this->sendSuccess('Voting berhasil dibuat', null, 201);
    }

    public function vote($id = null)
    {
        $tenantId = AuthService::getTenantId();
        $userId = AuthService::getGlobalUserId();
        if (!$tenantId || !AuthService::can('voting.vote')) return $this->sendError('Tidak diizinkan', null, 403);

        $voting = $this->votingModel->where('karang_taruna_id', $tenantId)
                                    ->where('id', $id)
                                    ->first();

        if (!$voting) return $this->sendError('Voting tidak ditemukan', null, 404);
        
        $dynamicStatus = $this->getDynamicStatus($voting);
        if ($dynamicStatus === 'ended' || $dynamicStatus === 'scheduled') {
            return $this->sendError('Voting tidak aktif', null, 400);
        }

        $hasVoted = $this->voteModel->where('voting_id', $id)->where('user_id', $userId)->first();
        if ($hasVoted) return $this->sendError('Anda sudah memberikan suara', null, 400);

        $rawInput = $this->request->getJSON(true) ?? $this->request->getRawInput();
        $optionId = $rawInput['option_id'] ?? null;
        if (!$optionId) return $this->sendError('Validasi gagal', ['option_id' => 'Option ID wajib diisi'], 422);

        $option = $this->optionModel->where('voting_id', $id)->where('id', $optionId)->first();
        if (!$option) return $this->sendError('Pilihan tidak valid', null, 404);

        try {
            $this->voteModel->insert([
                'voting_id' => $id,
                'option_id' => $optionId,
                'user_id'   => $userId
            ]);
        } catch (\Exception $e) {
            if (strpos(strtolower($e->getMessage()), 'duplicate') !== false || strpos(strtolower($e->getMessage()), 'unique') !== false) {
                return $this->sendError('Anda sudah memberikan suara', null, 400);
            }
            return $this->sendError('Gagal menyimpan suara', null, 500);
        }

        return $this->sendSuccess('Berhasil memberikan suara');
    }

    public function changeStatus($id = null)
    {
        $tenantId = AuthService::getTenantId();
        if (!$tenantId || !AuthService::can('voting.manage')) {
            return $this->sendError('Akses ditolak', null, 403);
        }

        $voting = $this->votingModel->where('karang_taruna_id', $tenantId)
                                    ->where('id', $id)
                                    ->first();

        if (!$voting) return $this->sendError('Voting tidak ditemukan', null, 404);

        $rawInput = $this->request->getJSON(true) ?? $this->request->getRawInput();
        $status = $rawInput['status'] ?? null;
        if (!in_array($status, ['active', 'closed'])) {
            return $this->sendError('Validasi gagal', ['status' => 'Status tidak valid'], 422);
        }

        $this->votingModel->update($id, ['status' => $status]);
        return $this->sendSuccess('Status voting berhasil diubah');
    }
}
