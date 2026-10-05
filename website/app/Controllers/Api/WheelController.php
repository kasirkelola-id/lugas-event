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
        try {
            $tenantId = AuthService::getTenantId();
            if (!$tenantId) {
                return $this->failUnauthorized('Unauthorized');
            }

            $builder = $this->sessionModel->builder()->where('karang_taruna_id', $tenantId)->orderBy('created_at', 'DESC');
            try { $pagination = \App\Services\CollectionPage::fromRequest($this->request)->apply($builder, 'wheel_sessions.id'); }
            catch (\InvalidArgumentException $error) { return $this->fail('Pagination tidak valid', 422); }
            $sessions = $builder->get()->getResultArray();

            $counts = [];
            if ($sessions) {
                $rows = \Config\Database::connect()->table('wheel_items')->select('wheel_items.session_id, COUNT(*) AS total')
                    ->join('wheel_sessions', 'wheel_sessions.id = wheel_items.session_id')->where('wheel_sessions.karang_taruna_id', $tenantId)
                    ->whereIn('wheel_items.session_id', array_column($sessions, 'id'))->groupBy('wheel_items.session_id')->get()->getResultArray();
                $counts = array_column($rows, 'total', 'session_id');
            }
            // Attach item count
            foreach ($sessions as &$session) {
                $session['item_count'] = (int)($counts[$session['id']] ?? 0);
                $session['creator_id'] = $session['created_by_user_id'];
            }

            return $this->respond(['success' => true, 'data' => $sessions, 'pagination' => $pagination]);
        } catch (\Throwable $error) {
            return $this->safeFailure();
        }
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
        if (!is_array($items) || count($items) > 1000 || $duration > 120
            || !in_array($sourceType, ['members', 'custom'], true)) {
            return $this->fail('Maksimal 1000 peserta dan durasi 10-120 detik', 422);
        }

        if ($duration < 10) {
            return $this->failValidationErrors('Durasi putaran minimal 10 detik');
        }

        if (count($items) < 2 && $sourceType === 'custom') {
            return $this->failValidationErrors('Minimal 2 kandidat diperlukan');
        }
        foreach ($items as $item) {
            if (($sourceType === 'custom' && (!is_string($item) || trim($item) === '' || mb_strlen($item) > 255))
                || ($sourceType === 'members' && (!is_scalar($item) || !ctype_digit((string)$item) || (int)$item < 1))) {
                return $this->fail('Kandidat tidak valid', 422);
            }
        }

        $ownsTransaction = false;
        try {
            // Begin Transaction
            $db = \Config\Database::connect();
            $this->beginMutationTransaction($db);
            $ownsTransaction = true;

            $data = [
                'karang_taruna_id'         => $tenantId,
                'created_by_user_id'       => $userId,
                'title'                    => $title,
                'source_type'              => $sourceType,
                'spin_duration_seconds'    => $duration,
                'remove_winner_after_spin' => $removeWinner,
                'status'                   => 'active',
            ];

            if (!empty($this->request->getVar('dashboard_until'))) {
                $data['dashboard_until'] = $this->request->getVar('dashboard_until');
            } else {
                // Default 1 hour if not provided
                $data['dashboard_until'] = date('Y-m-d H:i:s', strtotime('+1 hour'));
            }

            $sessionId = $this->sessionModel->insert($data);
            if (!$sessionId) throw new \RuntimeException('Wheel session write failed');

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
                if ($this->itemModel->insertBatch($insertItems) === false) throw new \RuntimeException('Wheel items write failed');
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

                $activeMembers = $builder->limit(1001)->get()->getResultArray();
                if (count($activeMembers) > 1000) {
                    $db->transRollback();
                    return $this->fail('Pilih maksimal 1000 kandidat', 422);
                }

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
                if ($this->itemModel->insertBatch($insertItems) === false) throw new \RuntimeException('Wheel items write failed');
            }

            if (!$db->transStatus() || !$db->transCommit()) throw new \RuntimeException('Wheel commit failed');

            return $this->respondCreated(['success' => true, 'message' => 'Sesi undian berhasil dibuat', 'session_id' => $sessionId]);
        } catch (\Throwable $error) {
            if ($ownsTransaction && $db->transDepth > 0) $db->transRollback();
            return $this->safeFailure();
        }
    }

    public function show($id = null)
    {
        try {
            $tenantId = AuthService::getTenantId();

            $session = $this->sessionModel->find($id);
            if (!$session || $session['karang_taruna_id'] != $tenantId) {
                return $this->failNotFound('Sesi tidak ditemukan');
            }

            $session['creator_id'] = $session['created_by_user_id'];
            $items = $this->itemModel->where('session_id', $id)->findAll();
            $builder = $this->resultModel->builder()->where('session_id', $id)->orderBy('spin_sequence', 'DESC');
            try { $resultsPagination = \App\Services\CollectionPage::fromRequest($this->request, 100)->apply($builder, 'wheel_results.id'); }
            catch (\InvalidArgumentException $error) { return $this->fail('Pagination tidak valid', 422); }
            $results = array_reverse($builder->get()->getResultArray());

            return $this->respond([
                'success' => true,
                'data'    => [
                    'session' => $session,
                    'items'   => $items,
                    'results' => $results,
                    'results_pagination' => $resultsPagination
                ]
            ]);
        } catch (\Throwable $error) {
            return $this->safeFailure();
        }
    }

    public function close($id = null)
    {
        $db = \Config\Database::connect();
        $ownsTransaction = false;
        try {
            $this->beginMutationTransaction($db);
            $ownsTransaction = true;
            $session = $this->lockedSession($db, $id, AuthService::getTenantId());
            if (!$session) return $this->failNotFound('Sesi tidak ditemukan');
            if ($session['created_by_user_id'] != AuthService::getGlobalUserId()) {
                return $this->failForbidden('Hanya pembuat sesi yang dapat mengakhiri undian');
            }
            $changed = $session['status'] !== 'closed';
            if ($changed && !$this->sessionModel->update($id, ['status' => 'closed'])) throw new \RuntimeException('Wheel close write failed');
            if (!$db->transStatus() || !$db->transCommit()) throw new \RuntimeException('Wheel close commit failed');
            if ($changed) $this->triggerSocketEvent($id, 'wheel_closed', ['session_id' => $id]);
            return $this->respond(['success' => true, 'message' => 'Sesi berhasil diakhiri']);
        } catch (\Throwable $error) {
            return $this->safeFailure();
        } finally {
            if ($ownsTransaction && $db->transDepth > 0) $db->transRollback();
        }
    }

    public function spin($id = null)
    {
        $db = \Config\Database::connect();
        $ownsTransaction = false;
        try {
            $this->beginMutationTransaction($db);
            $ownsTransaction = true;
            $session = $this->lockedSession($db, $id, AuthService::getTenantId());
            if (!$session) return $this->failNotFound('Sesi tidak ditemukan');
            if ($session['created_by_user_id'] != AuthService::getGlobalUserId()) {
                return $this->failForbidden('Hanya pembuat sesi yang dapat memutar roda');
            }
            if ($session['status'] === 'closed') return $this->failValidationErrors('Sesi undian sudah diakhiri');
            $closed = false;

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

            $activeItems = $this->itemModel->where('session_id', $id)->where('is_active', 1)->findAll();
            if (count($activeItems) < 1) {
                throw new \Exception('Kandidat sudah habis', 400);
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

            if (!$resultId) throw new \RuntimeException('Wheel result write failed');

            // Remove winner if ON
            if ($session['remove_winner_after_spin'] == 1) {
                if (!$this->itemModel->update($winnerItem['id'], ['is_active' => 0])) throw new \RuntimeException('Wheel winner write failed');

                // Auto-finish if 1 or 0 remaining items
                $remainingItems = $this->itemModel->where('session_id', $id)->where('is_active', 1)->countAllResults();
                if ($remainingItems <= 1) {
                    if (!$this->sessionModel->update($id, ['status' => 'closed'])) throw new \RuntimeException('Wheel close write failed');
                    $closed = true;
                }
            }

            $resultData = $this->resultModel->find($resultId);
            if (!$resultData || !$db->transStatus() || !$db->transCommit()) throw new \RuntimeException('Wheel result commit failed');
            if ($closed) $this->triggerSocketEvent($id, 'wheel_closed', ['session_id' => $id]);

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

        } catch (\Throwable $e) {
            $code = $e->getCode();
            $msg = $e->getMessage();
            if ($code === 409 && $msg === 'Putaran sebelumnya masih berlangsung') return $this->failResourceExists($msg);
            if ($code === 404 && $msg === 'Sesi undian tidak ditemukan atau sudah dihapus') return $this->failNotFound($msg);
            if ($code === 400 && $msg === 'Kandidat sudah habis') return $this->failValidationErrors($msg);
            return $this->safeFailure();
        } finally {
            if ($ownsTransaction && $db->transDepth > 0) $db->transRollback();
        }
    }

    public function duplicate($id = null)
    {
        $ownsTransaction = false;
        try {
            $tenantId = AuthService::getTenantId();
            $userId   = AuthService::getGlobalUserId();

            if (!$tenantId || !$userId) {
                return $this->failUnauthorized('Unauthorized');
            }

            $session = $this->sessionModel->find($id);
            if (!$session || $session['karang_taruna_id'] != $tenantId) {
                return $this->failNotFound('Sesi tidak ditemukan');
            }

            $db = \Config\Database::connect();
            $this->beginMutationTransaction($db);
            $ownsTransaction = true;

            $newSessionData = [
                'karang_taruna_id'         => $tenantId,
                'created_by_user_id'       => $userId, // Current creator
                'title'                    => $session['title'] . ' (Copy)',
                'source_type'              => $session['source_type'],
                'spin_duration_seconds'    => $session['spin_duration_seconds'],
                'remove_winner_after_spin' => $session['remove_winner_after_spin'],
                'status'                   => 'active',
                'dashboard_until'          => date('Y-m-d H:i:s', strtotime('+1 hour')),
            ];

            $newSessionId = $this->sessionModel->insert($newSessionData);
            if (!$newSessionId) throw new \RuntimeException('Wheel session write failed');

            $oldItems = $this->itemModel->where('session_id', $id)->findAll();
            $insertItems = [];
            foreach ($oldItems as $item) {
                $insertItems[] = [
                    'session_id'     => $newSessionId,
                    'member_user_id' => $item['member_user_id'],
                    'label_snapshot' => $item['label_snapshot'],
                    'is_active'      => 1 // Reset to active for all items in the new session
                ];
            }

            if (!empty($insertItems)) {
                if ($this->itemModel->insertBatch($insertItems) === false) throw new \RuntimeException('Wheel items write failed');
            }

            if (!$db->transStatus() || !$db->transCommit()) throw new \RuntimeException('Wheel commit failed');

            return $this->respondCreated([
                'success' => true,
                'message' => 'Sesi undian berhasil diduplikasi',
                'session_id' => $newSessionId
            ]);
        } catch (\Throwable $error) {
            if ($ownsTransaction && $db->transDepth > 0) $db->transRollback();
            return $this->safeFailure();
        }
    }

    private function beginMutationTransaction($db): void
    {
        if ($db->transDepth !== 0 || !$db->transStatus() || !$db->transBegin()) throw new \RuntimeException('Wheel transaction unavailable');
    }

    private function lockedSession($db, $id, $tenantId): ?array
    {
        $lock = $db->DBDriver === 'SQLite3' ? '' : ' FOR UPDATE';
        return $db->query('SELECT * FROM wheel_sessions WHERE id = ? AND karang_taruna_id = ?' . $lock,
            [$id, $tenantId])->getRowArray();
    }

    private function triggerSocketEvent($sessionId, $event, $payload, $tenantId = null)
    {
        $tenantId = $tenantId ?? AuthService::getTenantId();
        $nodeUrl = env('NODE_SOCKET_URL', 'http://localhost:3000');
        $apiUrl = $nodeUrl . '/internal/wheel-event';
        $secret = \App\Services\InternalSecret::configured();
        if ($secret === null || !in_array(parse_url($nodeUrl, PHP_URL_HOST), ['localhost', '127.0.0.1', '::1'], true)) {
            log_message('error', 'Wheel socket delivery failed');
            return;
        }

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
        } catch (\Throwable $e) {
            log_message('error', 'Wheel socket delivery failed');
        }
    }

    private function safeFailure()
    {
        $id = bin2hex(random_bytes(8));
        log_message('error', 'Wheel operation failed', ['correlation_id' => $id, 'status' => 500]);
        $this->response->setHeader('X-Correlation-ID', $id);
        return $this->failServerError('Operasi undian gagal. Silakan coba lagi.');
    }
}
