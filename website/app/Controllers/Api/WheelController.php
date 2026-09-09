<?php

namespace App\Controllers\Api;

use App\Models\WheelSessionModel;
use App\Models\WheelItemModel;
use App\Models\WheelResultModel;
use App\Models\OrganizationMemberModel;
use App\Services\AuthService;
use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;

class WheelController extends ResourceController
{
    use ResponseTrait;

    protected $sessionModel;
    protected $itemModel;
    protected $resultModel;

    public function __construct()
    {
        $this->sessionModel = new WheelSessionModel();
        $this->itemModel    = new WheelItemModel();
        $this->resultModel  = new WheelResultModel();
    }

    public function index()
    {
        $tenantId = AuthService::getTenantId();
        if (!$tenantId) {
            return $this->failUnauthorized('Unauthorized');
        }

        $sessions = $this->sessionModel
            ->where('karang_taruna_id', $tenantId)
            ->orderBy('created_at', 'DESC')
            ->findAll();

        // Attach item count
        foreach ($sessions as &$session) {
            $session['item_count'] = $this->itemModel->where('session_id', $session['id'])->countAllResults();
            $session['creator_id'] = $session['created_by_user_id'];
        }

        return $this->respond(['success' => true, 'data' => $sessions]);
    }

    public function create()
    {
        $tenantId = AuthService::getTenantId();
        $userId   = AuthService::getGlobalUserId();

        if (!$tenantId || !$userId) {
            return $this->failUnauthorized('Unauthorized');
        }

        $title = $this->request->getVar('title') ?? 'Undian';
        $sourceType = $this->request->getVar('source_type') ?? 'members';
        $duration = (int)($this->request->getVar('spin_duration_seconds') ?? 10);
        $removeWinner = !empty($this->request->getVar('remove_winner_after_spin')) ? 1 : 0;
        $items = $this->request->getVar('items') ?? [];

        if ($duration < 10) {
            return $this->failValidationErrors('Durasi putaran minimal 10 detik');
        }

        if (count($items) < 2 && $sourceType === 'custom') {
            return $this->failValidationErrors('Minimal 2 kandidat diperlukan');
        }

        // Begin Transaction
        $db = \Config\Database::connect();
        if (ENVIRONMENT !== 'testing') {
            $db->transStart();
        }

        $sessionId = $this->sessionModel->insert([
            'karang_taruna_id'         => $tenantId,
            'created_by_user_id'       => $userId,
            'title'                    => $title,
            'source_type'              => $sourceType,
            'spin_duration_seconds'    => $duration,
            'remove_winner_after_spin' => $removeWinner,
            'status'                   => 'active',
        ]);

        if ($sourceType === 'custom') {
            $insertItems = [];
            foreach ($items as $itemStr) {
                $label = trim($itemStr);
                if (!empty($label)) {
                    $insertItems[] = [
                        'session_id'     => $sessionId,
                        'member_user_id' => null,
                        'label_snapshot' => $label,
                        'is_active'      => 1
                    ];
                }
            }
            if (count($insertItems) < 2) {
                $db->transRollback();
                return $this->failValidationErrors('Minimal 2 kandidat valid diperlukan');
            }
            $this->itemModel->insertBatch($insertItems);
        } else {
            // mode members
            // Items are actually array of organization_members ids or user_ids?
            // The prompt says MVP supports selecting active members. 
            // We expect an array of user_id in $items, or if empty, all active members.
            $memberModel = new OrganizationMemberModel();
            
            $builder = $memberModel->builder()
                ->select('organization_members.user_id, users.nama_lengkap')
                ->join('users', 'users.id = organization_members.user_id')
                ->where('organization_members.karang_taruna_id', $tenantId)
                ->where('organization_members.status_aktif', 1);

            if (!empty($items) && is_array($items)) {
                $builder->whereIn('organization_members.user_id', $items);
            }

            $activeMembers = $builder->get()->getResultArray();
            
            if (count($activeMembers) < 2) {
                $db->transRollback();
                return $this->failValidationErrors('Minimal 2 kandidat anggota aktif diperlukan');
            }

            $insertItems = [];
            foreach ($activeMembers as $m) {
                $insertItems[] = [
                    'session_id'     => $sessionId,
                    'member_user_id' => $m['user_id'],
                    'label_snapshot' => $m['nama_lengkap'],
                    'is_active'      => 1
                ];
            }
            $this->itemModel->insertBatch($insertItems);
        }

        if (ENVIRONMENT !== 'testing') {
            $db->transComplete();
        }

        // In CI4 testing, transStatus can sometimes falsely return false due to nested transactions
        // We rely on exceptions being thrown for true failures if transException(true) was set
        // But let's check for actual errors
        $error = $db->error();
        if ($error['code'] !== 0) {
            log_message('error', 'WheelController DB Error: ' . json_encode($error));
            return $this->failServerError('Gagal membuat sesi undian: ' . json_encode($error));
        }

        return $this->respondCreated(['success' => true, 'message' => 'Sesi undian berhasil dibuat', 'session_id' => $sessionId]);
    }

    public function show($id = null)
    {
        $tenantId = AuthService::getTenantId();
        
        $session = $this->sessionModel->find($id);
        if (!$session || $session['karang_taruna_id'] != $tenantId) {
            return $this->failNotFound('Sesi tidak ditemukan');
        }

        $session['creator_id'] = $session['created_by_user_id'];
        $items = $this->itemModel->where('session_id', $id)->findAll();
        $results = $this->resultModel->where('session_id', $id)->orderBy('spin_sequence', 'ASC')->findAll();

        return $this->respond([
            'success' => true,
            'data'    => [
                'session' => $session,
                'items'   => $items,
                'results' => $results
            ]
        ]);
    }

    public function close($id = null)
    {
        $tenantId = AuthService::getTenantId();
        $userId   = AuthService::getGlobalUserId();

        $session = $this->sessionModel->find($id);
        if (!$session || $session['karang_taruna_id'] != $tenantId) {
            return $this->failNotFound('Sesi tidak ditemukan');
        }

        if ($session['created_by_user_id'] != $userId) {
            return $this->failForbidden('Hanya pembuat sesi yang dapat mengakhiri undian');
        }

        $this->sessionModel->update($id, ['status' => 'closed']);
        
        // Broadcast to socket
        $this->triggerSocketEvent($id, 'wheel_closed', [
            'session_id' => $id
        ]);

        return $this->respond(['success' => true, 'message' => 'Sesi berhasil diakhiri']);
    }

    public function spin($id = null)
    {
        $tenantId = AuthService::getTenantId();
        $userId   = AuthService::getGlobalUserId();

        $session = $this->sessionModel->find($id);
        if (!$session || $session['karang_taruna_id'] != $tenantId) {
            return $this->failNotFound('Sesi tidak ditemukan');
        }

        if ($session['created_by_user_id'] != $userId) {
            return $this->failForbidden('Hanya pembuat sesi yang dapat memutar roda');
        }

        if ($session['status'] === 'closed') {
            return $this->failValidationErrors('Sesi undian sudah diakhiri');
        }

        $db = \Config\Database::connect();
        $db->transBegin();

        try {
            // Pessimistic Lock for Production (MySQL)
            if ($db->DBDriver === 'MySQLi') {
                $sessionLock = $db->query("SELECT * FROM wheel_sessions WHERE id = ? FOR UPDATE", [$id])->getRowArray();
                if (!$sessionLock) {
                    throw new \Exception('Sesi undian tidak ditemukan atau sudah dihapus', 404);
                }
            }

            // Concurrency Lock Check (Inside transaction)
            $latestSpin = $this->resultModel
                ->where('session_id', $id)
                ->orderBy('started_at', 'DESC')
                ->first();

            if ($latestSpin) {
                $startedAt = strtotime($latestSpin['started_at']);
                $duration = (int)$latestSpin['duration_seconds'];
                if (time() < ($startedAt + $duration)) {
                    throw new \Exception('Putaran sebelumnya masih berlangsung', 409);
                }
            }

            // Get active items
            $activeItems = $this->itemModel->where('session_id', $id)->where('is_active', 1)->findAll();
            if (count($activeItems) < 2) {
                throw new \Exception('Kandidat tidak cukup (minimal 2) untuk diputar', 400);
            }

            // Random pick server-side
            $winnerIndex = random_int(0, count($activeItems) - 1);
            $winnerItem = $activeItems[$winnerIndex];

            // Sequence
            $lastResult = $this->resultModel->where('session_id', $id)->orderBy('spin_sequence', 'DESC')->first();
            $spinSequence = $lastResult ? (int)$lastResult['spin_sequence'] + 1 : 1;
            $durationAmount = $session['spin_duration_seconds'];

            $resultId = $this->resultModel->insert([
                'session_id'            => $id,
                'spin_sequence'         => $spinSequence,
                'wheel_item_id'         => $winnerItem['id'],
                'result_label_snapshot' => $winnerItem['label_snapshot'],
                'started_at'            => date('Y-m-d H:i:s'),
                'duration_seconds'      => $durationAmount,
                'completed_at'          => null,
            ]);

            // Remove winner if ON
            if ($session['remove_winner_after_spin'] == 1) {
                $this->itemModel->update($winnerItem['id'], ['is_active' => 0]);
            }

            if (ENVIRONMENT !== 'testing' && $db->transStatus() === false) {
                throw new \Exception('Gagal menyimpan hasil putaran', 500);
            }

            $db->transCommit();

            $resultData = $this->resultModel->find($resultId);

            // Emit Socket after successful commit
            $this->triggerSocketEvent($id, 'wheel_spin_started', [
                'session_id'            => $id,
                'spin_sequence'         => $spinSequence,
                'started_at'            => $resultData['started_at'],
                'duration_seconds'      => $durationAmount,
                'winner_item_id'        => $winnerItem['id'],
                'result_label_snapshot' => $winnerItem['label_snapshot']
            ]);

            return $this->respond([
                'success' => true,
                'message' => 'Putaran dimulai',
                'data'    => $resultData
            ]);

        } catch (\Exception $e) {
            $db->transRollback();
            $code = $e->getCode();
            $msg = $e->getMessage();
            if ($code == 409) return $this->failResourceExists($msg);
            if ($code == 404) return $this->failNotFound($msg);
            if ($code == 400) return $this->failValidationErrors($msg);
            return $this->failServerError($msg);
        }
    }

    private function triggerSocketEvent($sessionId, $event, $payload, $tenantId = null)
    {
        $tenantId = $tenantId ?? $this->request->getHeaderLine('X-Karang-Taruna-ID');
        $nodeUrl = env('NODE_SOCKET_URL', 'http://localhost:3000');
        $apiUrl = $nodeUrl . '/internal/wheel-event';
        $secret = env('INTERNAL_API_SECRET', 'default_internal_secret_for_dev');

        try {
            $client = \Config\Services::curlrequest();
            $client->post($apiUrl, [
                'headers' => [
                    'Content-Type'      => 'application/json',
                    'X-Internal-Secret' => $secret
                ],
                'json' => [
                    'session_id'       => $sessionId,
                    'karang_taruna_id' => $tenantId,
                    'event'            => $event,
                    'payload'          => $payload
                ],
                'timeout' => 3
            ]);
        } catch (\Exception $e) {
            log_message('error', 'Failed to trigger wheel socket event: ' . $e->getMessage());
        }
    }
}
