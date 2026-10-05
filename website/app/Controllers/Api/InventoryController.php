<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\InventoryModel;
use App\Models\InventoryLoanModel;
use App\Services\AuthService;
use CodeIgniter\API\ResponseTrait;

class InventoryController extends BaseApiController
{
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
        if (!$tenantId || !AuthService::can('inventory.view')) return $this->sendError('Tidak diizinkan', null, 403);

        $builder = $this->inventoryModel->builder()->where('karang_taruna_id', $tenantId)->orderBy('name', 'ASC');
        try { $pagination = \App\Services\CollectionPage::fromRequest($this->request)->apply($builder, 'inventories.id'); }
        catch (\InvalidArgumentException $error) { return $this->sendError('Pagination tidak valid', null, 422); }
        $inventories = $builder->get()->getResultArray();

        return $this->sendSuccess('Daftar inventory', $inventories, 200, $pagination);
    }

    public function create()
    {
        $tenantId = AuthService::getTenantId();
        if (!$tenantId || !AuthService::can('inventory.create')) {
            return $this->sendError('Akses ditolak', null, 403);
        }

        $rawInput = $this->request->getJSON(true) ?? $this->request->getRawInput();

        $rules = [
            'name'           => 'required',
            'total_quantity' => 'required|is_natural_no_zero',
        ];

        if (!$this->validateData($rawInput, $rules)) {
            return $this->sendError('Validasi gagal', $this->validator->getErrors(), 422);
        }

        $data = [
            'karang_taruna_id'   => $tenantId,
            'name'               => $rawInput['name'],
            'total_quantity'     => $rawInput['total_quantity'],
            'available_quantity' => $rawInput['total_quantity'],
            'condition'          => $rawInput['condition'] ?? 'Baik',
        ];

        $this->inventoryModel->insert($data);
        return $this->sendSuccess('Barang berhasil ditambahkan', null, 201);
    }

    public function getLoans()
    {
        $tenantId = AuthService::getTenantId();
        $userId = AuthService::getGlobalUserId();

        if (!$tenantId || !AuthService::can('inventory.view')) return $this->sendError('Tidak diizinkan', null, 403);

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
        try { $pagination = \App\Services\CollectionPage::fromRequest($this->request)->apply($builder, 'inventory_loans.id'); }
        catch (\InvalidArgumentException $error) { return $this->sendError('Pagination tidak valid', null, 422); }
        $loans = $builder->get()->getResultArray();

        return $this->sendSuccess('Daftar pinjaman', $loans, 200, $pagination);
    }

    public function requestLoan()
    {
        $tenantId = AuthService::getTenantId();
        $userId = AuthService::getGlobalUserId();

        if (!$tenantId || !AuthService::can('inventory.borrow')) return $this->sendError('Tidak diizinkan', null, 403);

        $rawInput = $this->request->getJSON(true) ?? $this->request->getRawInput();

        $rules = [
            'inventory_id' => 'required|numeric',
            'quantity'     => 'required|is_natural_no_zero',
            'borrow_date'  => 'required|valid_date',
            'return_date'  => 'required|valid_date',
        ];

        if (!$this->validateData($rawInput, $rules)) {
            return $this->sendError('Validasi gagal', $this->validator->getErrors(), 400);
        }

        $inventoryId = (int)$rawInput['inventory_id'];
        $quantity = (int)$rawInput['quantity'];

        $inventory = $this->inventoryModel->where('karang_taruna_id', $tenantId)
                                          ->where('id', $inventoryId)
                                          ->first();

        if (!$inventory) return $this->sendError('Barang tidak ditemukan', null, 404);
        if ($inventory['available_quantity'] < $quantity) {
            return $this->sendError('Stok barang tidak mencukupi. Tersedia: ' . $inventory['available_quantity'], ['quantity' => 'Stok tidak cukup'], 400);
        }

        $data = [
            'inventory_id' => $inventoryId,
            'user_id'      => $userId,
            'quantity'     => $quantity,
            'borrow_date'  => $rawInput['borrow_date'],
            'return_date'  => $rawInput['return_date'],
            'status'       => 'pending',
        ];

        $this->loanModel->insert($data);
        return $this->sendSuccess('Permintaan peminjaman berhasil diajukan', null, 201);
    }

    public function changeLoanStatus($id = null)
    {
        $tenantId = AuthService::getTenantId();

        if (!$tenantId || !AuthService::can('inventory.approve')) {
            return $this->sendError('Akses ditolak', null, 403);
        }

        $rawInput = $this->request->getJSON(true) ?? $this->request->getRawInput();
        $status = $rawInput['status'] ?? null;
        if (!in_array($status, ['approved', 'rejected', 'returned'])) {
            return $this->sendError('Validasi gagal', ['status' => 'Status tidak valid'], 422);
        }

        try {
            $transition = (new \App\Services\InventoryLoanTransitionService(\Config\Database::connect()))
                ->change((int) $tenantId, (int) $id, $status);
        } catch (\App\Services\InventoryTransitionException $error) {
            return $this->sendError($error->getMessage(), null, $error->getCode());
        } catch (\Throwable $error) {
            log_message('error', 'Inventory loan transaction failed');
            return $this->sendError('Gagal memproses status peminjaman', null, 500);
        }
        if (!$transition['changed']) {
            return $this->sendSuccess('Status peminjaman berhasil diproses');
        }
        // The status-update trigger committed its notification job with the stock transition.
        return $this->sendSuccess('Status peminjaman berhasil diubah');
    }

}
