<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\InventoryModel;
use App\Models\InventoryLoanModel;
use App\Services\AuthService;
use CodeIgniter\API\ResponseTrait;

class InventoryController extends BaseController
{
    use ResponseTrait;

    protected $inventoryModel;
    protected $loanModel;

    public function __construct()
    {
        $this->inventoryModel = new InventoryModel();
        $this->loanModel = new InventoryLoanModel();
    }

    public function index()
    {
        $tenantId = AuthService::getTenantId();
        if (!$tenantId || !AuthService::can('inventory.view')) return $this->failForbidden('Tidak diizinkan');

        $inventories = $this->inventoryModel->where('karang_taruna_id', $tenantId)
                                            ->orderBy('name', 'ASC')
                                            ->findAll();

        return $this->respond(['status' => true, 'data' => $inventories], 200);
    }

    public function create()
    {
        $tenantId = AuthService::getTenantId();
        if (!$tenantId || !AuthService::can('inventory.create')) {
            return $this->failForbidden('Akses ditolak');
        }

        $rules = [
            'name'           => 'required',
            'total_quantity' => 'required|is_natural_no_zero',
        ];

        if (!$this->validate($rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        $data = [
            'karang_taruna_id'   => $tenantId,
            'name'               => $this->request->getVar('name'),
            'total_quantity'     => $this->request->getVar('total_quantity'),
            'available_quantity' => $this->request->getVar('total_quantity'),
            'condition'          => $this->request->getVar('condition') ?? 'Baik',
        ];

        $this->inventoryModel->insert($data);
        return $this->respondCreated(['status' => true, 'message' => 'Barang berhasil ditambahkan']);
    }

    public function getLoans()
    {
        $tenantId = AuthService::getTenantId();
        $userId = AuthService::getGlobalUserId();
        
        if (!$tenantId || !AuthService::can('inventory.view')) return $this->failForbidden('Tidak diizinkan');

        $db = \Config\Database::connect();
        $builder = $db->table('inventory_loans');
        $builder->select('inventory_loans.*, inventories.name as inventory_name, users.nama_lengkap as user_name');
        $builder->join('inventories', 'inventories.id = inventory_loans.inventory_id');
        $builder->join('users', 'users.id = inventory_loans.user_id');
        $builder->where('inventories.karang_taruna_id', $tenantId);

        if (!AuthService::can('inventory.approve')) {
            $builder->where('inventory_loans.user_id', $userId);
        }

        $builder->orderBy('inventory_loans.created_at', 'DESC');
        $loans = $builder->get()->getResultArray();

        return $this->respond(['status' => true, 'data' => $loans], 200);
    }

    public function requestLoan()
    {
        $tenantId = AuthService::getTenantId();
        $userId = AuthService::getGlobalUserId();
        
        if (!$tenantId || !AuthService::can('inventory.borrow')) return $this->failForbidden('Tidak diizinkan');

        $rules = [
            'inventory_id' => 'required|numeric',
            'quantity'     => 'required|is_natural_no_zero',
            'borrow_date'  => 'required|valid_date',
            'return_date'  => 'required|valid_date',
        ];

        if (!$this->validate($rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        $inventoryId = (int)$this->request->getVar('inventory_id');
        $quantity = (int)$this->request->getVar('quantity');

        $inventory = $this->inventoryModel->where('karang_taruna_id', $tenantId)
                                          ->where('id', $inventoryId)
                                          ->first();

        if (!$inventory) return $this->failNotFound('Barang tidak ditemukan');
        if ($inventory['available_quantity'] < $quantity) {
            return $this->fail('Stok barang tidak mencukupi. Tersedia: ' . $inventory['available_quantity']);
        }

        $data = [
            'inventory_id' => $inventoryId,
            'user_id'      => $userId,
            'quantity'     => $quantity,
            'borrow_date'  => $this->request->getVar('borrow_date'),
            'return_date'  => $this->request->getVar('return_date'),
            'status'       => 'pending',
        ];

        $this->loanModel->insert($data);
        return $this->respondCreated(['status' => true, 'message' => 'Permintaan peminjaman berhasil diajukan']);
    }

    public function changeLoanStatus($id)
    {
        $tenantId = AuthService::getTenantId();
        
        if (!$tenantId || !AuthService::can('inventory.approve')) {
            return $this->failForbidden('Akses ditolak');
        }

        $status = $this->request->getVar('status');
        if (!in_array($status, ['approved', 'rejected', 'returned'])) {
            return $this->failValidationErrors('Status tidak valid');
        }

        $db = \Config\Database::connect();
        $db->transStart();

        $forUpdate = $db->DBDriver === 'SQLite3' ? '' : 'FOR UPDATE';
        $loan = $db->query("SELECT * FROM inventory_loans WHERE id = ? {$forUpdate}", [$id])->getRowArray();
        
        if (!$loan) {
            $db->transRollback();
            return $this->failNotFound('Data pinjaman tidak ditemukan');
        }

        // Idempotency: Jika status sudah sama, anggap sukses dan hentikan eksekusi tanpa mengubah apapun
        if ($loan['status'] === $status) {
            $db->transRollback();
            return $this->respond(['status' => true, 'message' => 'Status peminjaman berhasil diproses']);
        }

        $inventory = $db->query("SELECT * FROM inventories WHERE id = ? AND karang_taruna_id = ? {$forUpdate}", [$loan['inventory_id'], $tenantId])->getRowArray();
        
        if (!$inventory) {
            $db->transRollback();
            return $this->failNotFound('Barang tidak ditemukan atau akses ditolak');
        }

        $qty = (int)$loan['quantity'];
        $newQuantity = (int)$inventory['available_quantity'];
        $stockChanged = false;

        // Logic stok dan validasi State Machine
        if ($status === 'approved') {
            if ($loan['status'] !== 'pending') {
                $db->transRollback();
                return $this->fail('Transisi tidak valid: Hanya pinjaman pending yang dapat disetujui', 409);
            }
            if ($newQuantity < $qty) {
                $db->transRollback();
                return $this->fail('Stok tidak mencukupi untuk disetujui', 409);
            }
            $newQuantity -= $qty;
            $stockChanged = true;
        } elseif ($status === 'returned') {
            if ($loan['status'] !== 'approved') {
                $db->transRollback();
                return $this->fail('Transisi tidak valid: Hanya pinjaman yang disetujui yang dapat dikembalikan', 409);
            }
            $newQuantity += $qty;
            $stockChanged = true;
        } elseif ($status === 'rejected') {
            if ($loan['status'] === 'approved') {
                // Rollback stok pembatalan
                $newQuantity += $qty;
                $stockChanged = true;
            } elseif ($loan['status'] !== 'pending') {
                $db->transRollback();
                return $this->fail('Transisi tidak valid: Tidak dapat menolak pinjaman pada status ini', 409);
            }
        }

        if ($stockChanged) {
            $db->table('inventories')->where('id', $inventory['id'])->update(['available_quantity' => $newQuantity]);
        }

        $db->table('inventory_loans')->where('id', $loan['id'])->update(['status' => $status, 'updated_at' => date('Y-m-d H:i:s')]);

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->failServerError('Gagal mengubah status peminjaman');
        }

        // Trigger push notification to borrower
        $dbDevices = \Config\Database::connect();
        $devices = $dbDevices->table('user_devices')->where('user_id', $loan['user_id'])->get()->getResultArray();
        $tokens = array_filter(array_column($devices, 'fcm_token'));
        if (!empty($tokens)) {
            $ktModel = new \App\Models\KarangTarunaModel();
            $kt = $ktModel->find($tenantId);
            $ktName = $kt ? $kt['nama_organisasi'] : 'Karang Taruna';
            
            $statusIndo = $status === 'approved' ? 'disetujui' : ($status === 'rejected' ? 'ditolak' : 'dikembalikan');
            $title = "Peminjaman Barang: " . $ktName;
            $body = "Status peminjaman Anda untuk barang {$inventory['name']} telah " . $statusIndo . ".";
            
            \App\Services\NotificationService::sendPushNotification($tokens, $title, $body, [
                'type' => 'inventory_loan',
                'tenant_id' => (string)$tenantId,
                'loan_id' => (string)$loan['id'],
                'status' => $status
            ]);
        }

        return $this->respond(['status' => true, 'message' => 'Status peminjaman berhasil diubah']);
    }
}
