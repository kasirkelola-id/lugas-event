<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/** Atomic loan transitions. The controller supplies the authorized tenant. */
class InventoryLoanTransitionService
{
    public function __construct(private readonly BaseConnection $db)
    {
    }

    public function change(int $tenantId, int $loanId, string $status): array
    {
        if ($tenantId < 1 || !in_array($status, ['approved', 'returned', 'rejected'], true)) {
            throw new InventoryTransitionException('Status tidak valid', 422);
        }
        // A nested CI transaction cannot guarantee a durable commit before FCM.
        if ($this->db->transDepth !== 0 || !$this->db->transStatus() || !$this->db->transBegin()) {
            throw new \RuntimeException('Inventory transaction could not begin');
        }
        $committed = false;
        try {
            $scope = 'FROM inventory_loans l WHERE l.id = ? AND EXISTS '
                . '(SELECT 1 FROM inventories i WHERE i.id = l.inventory_id AND i.karang_taruna_id = ?)';
            // Observe the source state inside this transaction. A competing
            // approve/reject must not reinterpret a pending decision as a new
            // cancellation after waiting for its lock. Later requests may still
            // intentionally reject an already-approved loan.
            $observed = $this->row('SELECT l.status ' . $scope, [$loanId, $tenantId]);
            if (!$observed) {
                throw new InventoryTransitionException('Data pinjaman tidak ditemukan', 404);
            }
            $lock = $this->db->DBDriver === 'SQLite3' ? '' : ' FOR UPDATE';
            // Consistent locking order: scoped loan first, inventory second.
            // The EXISTS subquery is an ownership predicate, not a locking read.
            $loan = $this->row('SELECT l.* ' . $scope . $lock, [$loanId, $tenantId]);
            if (!$loan) {
                throw new InventoryTransitionException('Data pinjaman tidak ditemukan', 404);
            }
            $inventory = $this->row(
                'SELECT * FROM inventories WHERE id = ? AND karang_taruna_id = ?' . $lock,
                [$loan['inventory_id'], $tenantId]
            );
            if (!$inventory) {
                throw new InventoryTransitionException('Barang tidak ditemukan atau akses ditolak', 404);
            }
            // Ownership is established before every success, including repeats.
            if ($loan['status'] === $status) {
                return ['changed' => false, 'loan' => $loan, 'inventory' => $inventory];
            }
            if ($loan['status'] !== $observed['status']) {
                throw new InventoryTransitionException('Status pinjaman telah berubah; muat ulang dan coba lagi', 409);
            }
            $quantity = (int) $loan['quantity'];
            $available = (int) $inventory['available_quantity'];
            $total = (int) $inventory['total_quantity'];
            if ($quantity < 1 || $available < 0 || $available > $total) {
                throw new InventoryTransitionException('Data jumlah atau stok tidak valid', 409);
            }
            $next = $available;
            if ($status === 'approved') {
                if ($loan['status'] !== 'pending') {
                    throw new InventoryTransitionException('Transisi tidak valid: Hanya pinjaman pending yang dapat disetujui', 409);
                }
                if ($available < $quantity) {
                    throw new InventoryTransitionException('Stok tidak mencukupi untuk disetujui', 409);
                }
                $next -= $quantity;
            } elseif ($status === 'returned') {
                if ($loan['status'] !== 'approved') {
                    throw new InventoryTransitionException('Transisi tidak valid: Hanya pinjaman yang disetujui yang dapat dikembalikan', 409);
                }
                $next += $quantity;
            } elseif ($loan['status'] === 'approved') {
                $next += $quantity;
            } elseif ($loan['status'] !== 'pending') {
                throw new InventoryTransitionException('Transisi tidak valid: Tidak dapat menolak pinjaman pada status ini', 409);
            }
            if ($next < 0 || $next > $total) {
                throw new InventoryTransitionException('Perubahan stok melebihi batas jumlah barang', 409);
            }
            $now = date('Y-m-d H:i:s');
            if ($next !== $available) {
                $ok = $this->db->table('inventories')->where('id', $inventory['id'])
                    ->where('karang_taruna_id', $tenantId)->where('available_quantity', $available)
                    ->update(['available_quantity' => $next, 'updated_at' => $now]);
                if (!$ok || $this->db->affectedRows() !== 1) {
                    throw new \RuntimeException('Inventory stock write failed');
                }
            }
            $ok = $this->db->table('inventory_loans')->where('id', $loan['id'])
                ->where('inventory_id', $inventory['id'])->where('status', $loan['status'])
                ->update(['status' => $status, 'updated_at' => $now]);
            if (!$ok || $this->db->affectedRows() !== 1 || !$this->db->transStatus()) {
                throw new \RuntimeException('Inventory loan write failed');
            }
            if (!$this->db->transCommit()) {
                throw new \RuntimeException('Inventory commit failed');
            }
            $committed = true;
            return ['changed' => true, 'loan' => $loan, 'inventory' => $inventory];
        } finally {
            if (!$committed && $this->db->transDepth > 0) {
                $this->db->transRollback();
            }
        }
    }

    private function row(string $sql, array $params): ?array
    {
        $result = $this->db->query($sql, $params);
        if ($result === false) {
            throw new \RuntimeException('Inventory read failed');
        }
        return $result->getRowArray();
    }
}
