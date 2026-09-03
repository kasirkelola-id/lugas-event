<?php

namespace App\Controllers\Api;

use App\Models\EventModel;
use App\Models\PengumumanModel;
use App\Models\VotingModel;
use App\Models\InventoryLoanModel;
use App\Models\InventoryModel;
use App\Models\OrganizationMemberModel;
use App\Services\AuthService;

class DashboardController extends BaseApiController
{
    public function index()
    {
        $tenantId = AuthService::getTenantId();
        $userId = AuthService::getGlobalUserId();
        $role = AuthService::getRole();

        if (!$tenantId || !$userId) {
            return $this->sendError('Unauthorized', null, 401);
        }

        $now = date('Y-m-d H:i:s');
        $today = date('Y-m-d');
        
        $data = [
            'upcoming_event' => null,
            'latest_announcement' => null,
            'active_voting' => null,
            'my_active_loan' => null,
            'management' => null
        ];

        // 1. Upcoming Event
        $eventModel = new EventModel();
        // Cek event yang sedang berlangsung, prioritas utama
        $ongoingEvent = $eventModel
            ->where('karang_taruna_id', $tenantId)
            ->whereIn('status_aktif', [1, '1', 'aktif', 'Aktif'])
            ->where('tanggal_acara', $today)
            ->where('waktu_mulai <=', date('H:i:s'))
            ->where('waktu_selesai >=', date('H:i:s'))
            ->first();

        if (!$ongoingEvent) {
            // Jika tidak ada yang berlangsung, cari yang akan datang terdekat
            $upcomingEvent = $eventModel
                ->where('karang_taruna_id', $tenantId)
                ->whereIn('status_aktif', [1, '1', 'aktif', 'Aktif'])
                ->groupStart()
                    ->where('tanggal_acara >', $today)
                    ->orGroupStart()
                        ->where('tanggal_acara', $today)
                        ->where('waktu_mulai >', date('H:i:s'))
                    ->groupEnd()
                ->groupEnd()
                ->orderBy('tanggal_acara', 'ASC')
                ->orderBy('waktu_mulai', 'ASC')
                ->first();
        }
        
        $selectedEvent = $ongoingEvent ?? $upcomingEvent ?? null;

        if ($selectedEvent) {
            $data['upcoming_event'] = [
                'id' => $selectedEvent['id'],
                'title' => $selectedEvent['nama_acara'],
                'date' => $selectedEvent['tanggal_acara'],
                'time' => $selectedEvent['waktu_mulai'],
                'status' => $selectedEvent['status_aktif']
            ];
        }

        // 2. Latest Announcement
        $pengumumanModel = new PengumumanModel();
        $latestAnnouncement = $pengumumanModel
            ->where('karang_taruna_id', $tenantId)
            ->where('status_aktif', 1)
            ->orderBy('created_at', 'DESC')
            ->first();
            
        if ($latestAnnouncement) {
            $data['latest_announcement'] = [
                'id' => $latestAnnouncement['id'],
                'title' => $latestAnnouncement['judul'],
                'preview' => substr($latestAnnouncement['isi'], 0, 100),
                'date' => $latestAnnouncement['created_at']
            ];
        }

        // 3. Active Voting
        $votingModel = new VotingModel();
        $activeVoting = $votingModel
            ->where('karang_taruna_id', $tenantId)
            ->where('status', 'active')
            ->orderBy('created_at', 'DESC')
            ->first();
            
        if ($activeVoting) {
            $data['active_voting'] = [
                'id' => $activeVoting['id'],
                'title' => $activeVoting['title'],
                'status' => $activeVoting['status']
            ];
        }

        // 4. My Active Loan
        $loanModel = new InventoryLoanModel();
        // Prioritaskan pending, baru approved
        $myLoan = $loanModel
            ->select('inventory_loans.*, inventories.name as inventory_name')
            ->join('inventories', 'inventories.id = inventory_loans.inventory_id')
            ->where('inventory_loans.user_id', $userId)
            ->where('inventories.karang_taruna_id', $tenantId)
            ->whereIn('inventory_loans.status', ['pending', 'approved'])
            // Status pending(p) lebih dulu daripada approved(a)
            ->orderBy('inventory_loans.status', 'DESC') 
            ->orderBy('inventory_loans.created_at', 'DESC')
            ->first();

        if ($myLoan) {
            $data['my_active_loan'] = [
                'id' => $myLoan['id'],
                'inventory_name' => $myLoan['inventory_name'],
                'quantity' => $myLoan['quantity'],
                'status' => $myLoan['status'],
                'borrow_date' => $myLoan['borrow_date'],
                'return_date' => $myLoan['return_date']
            ];
        }

        // 5. Management Metrics
        // Gunakan RBAC permission untuk melihat metrik
        $canSeeManagement = AuthService::can('inventory.approve') || AuthService::can('members.manage') || AuthService::can('report.view');
        
        if ($canSeeManagement) {
            $pendingLoansCount = $loanModel
                ->join('inventories', 'inventories.id = inventory_loans.inventory_id')
                ->where('inventories.karang_taruna_id', $tenantId)
                ->where('inventory_loans.status', 'pending')
                ->countAllResults();

            $memberModel = new OrganizationMemberModel();
            $activeMembersCount = $memberModel
                ->where('karang_taruna_id', $tenantId)
                ->where('status_aktif', 1)
                ->countAllResults();

            $inventoryModel = new InventoryModel();
            $outOfStockCount = $inventoryModel
                ->where('karang_taruna_id', $tenantId)
                ->where('available_quantity <=', 0)
                ->countAllResults();

            $data['management'] = [
                'pending_loans' => $pendingLoansCount,
                'active_members' => $activeMembersCount,
                'out_of_stock' => $outOfStockCount
            ];
        }

        return $this->sendSuccess('Dashboard summary', $data);
    }
}
