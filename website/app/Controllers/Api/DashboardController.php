<?php

namespace App\Controllers\Api;

use App\Models\EventModel;
use App\Models\PengumumanModel;
use App\Models\VotingModel;
use App\Models\InventoryLoanModel;
use App\Models\InventoryModel;
use App\Models\OrganizationMemberModel;
use App\Models\ParticipantModel;
use App\Services\AuthService;
use App\Services\SettingService;

class DashboardController extends BaseApiController
{
    public function index()
    {
        $tenantId = AuthService::getTenantId();
        $userId = AuthService::getGlobalUserId();
        $role = AuthService::getRole();

        if (!$tenantId || $userId === null) {
            return $this->sendError('Unauthorized', null, 401);
        }

        $now = date('Y-m-d H:i:s');
        $today = date('Y-m-d');

        $data = [
            'upcoming_event' => null,
            'latest_announcement' => null,
            'active_voting' => null,
            'my_active_loan' => null,
            'kas_balance' => 0,
            'management' => null
        ];

        // 1. Upcoming Event
        $eventModel = new EventModel();
        
        $todayEvents = $eventModel
            ->where('karang_taruna_id', $tenantId)
            ->whereIn('status_aktif', [1, '1', 'aktif', 'Aktif'])
            ->where('tanggal_acara >=', $today)
            ->orderBy('tanggal_acara', 'ASC')
            ->orderBy('waktu_mulai', 'ASC')
            ->findAll();

        $ongoingEvent = null;
        $upcomingEvent = null;

        $nowTime = time();
        
        $beforeMinutes = (int)SettingService::getSetting($tenantId, 'attendance_before_minutes', 30);
        $afterMinutes = (int)SettingService::getSetting($tenantId, 'attendance_after_minutes', 30);

        foreach ($todayEvents as $evt) {
            $startStr = $evt['tanggal_acara'] . ' ' . ($evt['waktu_mulai'] ?: '00:00:00');
            $endStr = $evt['tanggal_acara'] . ' ' . ($evt['waktu_selesai'] ?: '23:59:59');
            $startTime = strtotime($startStr) - ($beforeMinutes * 60);
            $endTime = strtotime($endStr) + ($afterMinutes * 60);

            if ($nowTime >= $startTime && $nowTime <= $endTime) {
                $ongoingEvent = $evt;
                break; // Found an ongoing one
            } elseif ($nowTime < $startTime && !$upcomingEvent) {
                $upcomingEvent = $evt;
            }
        }

        $selectedEvent = $ongoingEvent ?? $upcomingEvent ?? null;

        if ($selectedEvent) {
            $data['upcoming_event'] = [
                'id' => $selectedEvent['id'],
                'title' => $selectedEvent['nama_acara'],
                'date' => $selectedEvent['tanggal_acara'],
                'time' => $selectedEvent['waktu_mulai'],
                'status' => $selectedEvent['status_aktif'],
                'is_ongoing' => !empty($ongoingEvent)
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
        $nowStr = date('Y-m-d H:i:s');
        $activeVoting = $votingModel
            ->where('karang_taruna_id', $tenantId)
            ->where('status !=', 'closed')
            ->groupStart()
                ->where('waktu_mulai <=', $nowStr)
                ->orWhere('waktu_mulai IS NULL')
            ->groupEnd()
            ->groupStart()
                ->where('waktu_selesai >', $nowStr)
                ->orWhere('waktu_selesai IS NULL')
            ->groupEnd()
            ->orderBy('created_at', 'DESC')
            ->first();

        if ($activeVoting) {
            $data['active_voting'] = [
                'id' => $activeVoting['id'],
                'title' => $activeVoting['title'],
                'status' => 'active'
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

        // 4.5. Kas Balance
        $kasModel = new \App\Models\KasModel();
        $data['kas_balance'] = (int) $kasModel->getTotalSaldo($tenantId);

        $currentMonth = date('Y-m');
        $data['kas_pemasukan'] = (int) $kasModel->where('karang_taruna_id', $tenantId)
                                                ->where('jenis', 'pemasukan')
                                                ->like('tanggal', $currentMonth, 'after')
                                                ->selectSum('nominal')
                                                ->get()
                                                ->getRow()
                                                ->nominal ?? 0;

        $data['kas_pengeluaran'] = (int) $kasModel->where('karang_taruna_id', $tenantId)
                                                  ->where('jenis', 'pengeluaran')
                                                  ->like('tanggal', $currentMonth, 'after')
                                                  ->selectSum('nominal')
                                                  ->get()
                                                  ->getRow()
                                                  ->nominal ?? 0;

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
