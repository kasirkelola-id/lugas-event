<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\VotingModel;
use App\Models\VotingOptionModel;
use App\Models\VotingVoteModel;
use App\Services\AuthService;
use CodeIgniter\API\ResponseTrait;

class VotingController extends BaseController
{
    use ResponseTrait;

    protected $votingModel;
    protected $optionModel;
    protected $voteModel;

    public function __construct()
    {
        $this->votingModel = new VotingModel();
        $this->optionModel = new VotingOptionModel();
        $this->voteModel = new VotingVoteModel();
    }

    public function index()
    {
        $tenantId = AuthService::getTenantId();
        $userId = AuthService::getGlobalUserId();
        if (!$tenantId || !AuthService::can('voting.view')) return $this->failForbidden('Tidak diizinkan');

        $votings = $this->votingModel->where('karang_taruna_id', $tenantId)
                                     ->orderBy('created_at', 'DESC')
                                     ->findAll();

        foreach ($votings as &$voting) {
            $hasVoted = $this->voteModel->where('voting_id', $voting['id'])
                                        ->where('user_id', $userId)
                                        ->first();
            $voting['has_voted'] = $hasVoted ? true : false;
            $voting['total_votes'] = $this->voteModel->where('voting_id', $voting['id'])->countAllResults();
        }

        return $this->respond(['status' => true, 'data' => $votings], 200);
    }

    public function show($id)
    {
        $tenantId = AuthService::getTenantId();
        $userId = AuthService::getGlobalUserId();
        if (!$tenantId || !AuthService::can('voting.view')) return $this->failForbidden('Tidak diizinkan');

        $voting = $this->votingModel->where('karang_taruna_id', $tenantId)
                                    ->where('id', $id)
                                    ->first();

        if (!$voting) return $this->failNotFound('Voting tidak ditemukan');

        $options = $this->optionModel->where('voting_id', $id)->findAll();
        $hasVoted = $this->voteModel->where('voting_id', $id)->where('user_id', $userId)->first();
        
        $voting['has_voted'] = $hasVoted ? true : false;
        $voting['voted_option_id'] = $hasVoted ? $hasVoted['option_id'] : null;
        $voting['total_votes'] = $this->voteModel->where('voting_id', $id)->countAllResults();

        // Calculate percentages if user has voted or voting is closed
        if ($voting['has_voted'] || $voting['status'] == 'closed') {
            foreach ($options as &$option) {
                $optionVotes = $this->voteModel->where('option_id', $option['id'])->countAllResults();
                $option['vote_count'] = $optionVotes;
                $option['percentage'] = $voting['total_votes'] > 0 ? round(($optionVotes / $voting['total_votes']) * 100, 1) : 0;
            }
        }

        $voting['options'] = $options;

        return $this->respond(['status' => true, 'data' => $voting], 200);
    }

    public function create()
    {
        $tenantId = AuthService::getTenantId();
        if (!$tenantId || !AuthService::can('voting.manage')) {
            return $this->failForbidden('Akses ditolak');
        }

        $rules = [
            'title'   => 'required|min_length[3]',
            'options' => 'required',
        ];

        if (!$this->validate($rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        $options = $this->request->getVar('options');
        if (!is_array($options) || count($options) < 2) {
            return $this->failValidationErrors('Minimal 2 pilihan (options) harus diberikan');
        }

        $db = \Config\Database::connect();
        $db->transStart();

        $votingData = [
            'karang_taruna_id' => $tenantId,
            'title'            => $this->request->getVar('title'),
            'description'      => $this->request->getVar('description'),
            'status'           => 'active',
            'created_by'       => $userId
        ];

        $this->votingModel->insert($votingData);
        $votingId = $this->votingModel->getInsertID();

        foreach ($options as $opt) {
            if (trim($opt) != '') {
                $this->optionModel->insert([
                    'voting_id'   => $votingId,
                    'option_name' => trim($opt)
                ]);
            }
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->failServerError('Gagal membuat voting');
        }

        return $this->respondCreated(['status' => true, 'message' => 'Voting berhasil dibuat']);
    }

    public function vote($id)
    {
        $tenantId = AuthService::getTenantId();
        $userId = AuthService::getGlobalUserId();
        if (!$tenantId || !AuthService::can('voting.vote')) return $this->failForbidden('Tidak diizinkan');

        $voting = $this->votingModel->where('karang_taruna_id', $tenantId)
                                    ->where('id', $id)
                                    ->first();

        if (!$voting) return $this->failNotFound('Voting tidak ditemukan');
        if ($voting['status'] == 'closed') return $this->fail('Voting telah ditutup');

        $hasVoted = $this->voteModel->where('voting_id', $id)->where('user_id', $userId)->first();
        if ($hasVoted) return $this->fail('Anda sudah memberikan suara');

        $optionId = $this->request->getVar('option_id');
        if (!$optionId) return $this->failValidationErrors('Option ID wajib diisi');

        $option = $this->optionModel->where('voting_id', $id)->where('id', $optionId)->first();
        if (!$option) return $this->failNotFound('Pilihan tidak valid');

        try {
            $this->voteModel->insert([
                'voting_id' => $id,
                'option_id' => $optionId,
                'user_id'   => $userId
            ]);
        } catch (\Exception $e) {
            if (strpos(strtolower($e->getMessage()), 'duplicate') !== false || strpos(strtolower($e->getMessage()), 'unique') !== false) {
                return $this->fail('Anda sudah memberikan suara');
            }
            return $this->failServerError('Gagal menyimpan suara');
        }

        return $this->respond(['status' => true, 'message' => 'Berhasil memberikan suara'], 200);
    }

    public function changeStatus($id)
    {
        $tenantId = AuthService::getTenantId();
        if (!$tenantId || !AuthService::can('voting.manage')) {
            return $this->failForbidden('Akses ditolak');
        }

        $voting = $this->votingModel->where('karang_taruna_id', $tenantId)
                                    ->where('id', $id)
                                    ->first();

        if (!$voting) return $this->failNotFound('Voting tidak ditemukan');

        $status = $this->request->getVar('status');
        if (!in_array($status, ['active', 'closed'])) {
            return $this->failValidationErrors('Status tidak valid');
        }

        $this->votingModel->update($id, ['status' => $status]);
        return $this->respond(['status' => true, 'message' => 'Status voting berhasil diubah']);
    }
}
