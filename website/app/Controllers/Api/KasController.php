<?php

namespace App\Controllers\Api;

use App\Models\KasModel;
use App\Models\UserModel;
use App\Services\AuthService;

class KasController extends BaseApiController
{
    // checkBendaharaAtauKetua removed in favor of RBAC

    public function index()
    {
        $tenantId = AuthService::getTenantId();
        if (!$tenantId || !AuthService::can('cash.view')) {
            return $this->sendError('Forbidden', null, 403);
        }

        $kasModel = new KasModel();
        
        $builder = $kasModel->builder();
        $builder->select('kas.*, users.nama_lengkap as pembuat');
        $builder->join('users', 'users.id = kas.dibuat_oleh', 'left');
        $builder->where('kas.karang_taruna_id', $tenantId);
        $builder->orderBy('kas.tanggal', 'DESC');
        $builder->orderBy('kas.created_at', 'DESC');
        $transaksi = $builder->get()->getResultArray();

        $saldo = $kasModel->getTotalSaldo($tenantId);

        // Convert types
        $transaksi = array_map(function($t) {
            $t['id'] = (int)$t['id'];
            $t['nominal'] = (int)$t['nominal'];
            $t['dibuat_oleh'] = (int)$t['dibuat_oleh'];
            return $t;
        }, $transaksi);

        return $this->sendSuccess('Data Kas', [
            'saldo' => (int)$saldo,
            'transaksi' => $transaksi
        ]);
    }

    public function create()
    {
        if (!AuthService::can('cash.create')) {
            return $this->sendError('Forbidden: Akses ditolak.', null, 403);
        }

        $rules = [
            'jenis'      => 'required|in_list[pemasukan,pengeluaran]',
            'nominal'    => 'required|numeric|greater_than[0]',
            'keterangan' => 'required|max_length[255]',
            'tanggal'    => 'required|valid_date[Y-m-d]'
        ];

        $rawInput = $this->request->getJSON(true) ?? $this->request->getRawInput();
        if (!$this->validateData($rawInput, $rules)) {
            return $this->sendError('Validasi gagal', $this->validator->getErrors(), 422);
        }

        $settingModel = new \App\Models\SettingModel();
        $limitSetting = $settingModel->find('kas_backdate_limit');
        $limitDays = $limitSetting ? (int)$limitSetting['setting_value'] : 30;

        $inputDate = new \DateTime($rawInput['tanggal']);
        $today = new \DateTime(date('Y-m-d'));
        
        if ($inputDate < $today) {
            $diff = $today->diff($inputDate);
            if ($diff->days > $limitDays) {
                return $this->sendError('Validasi gagal', ['tanggal' => 'Tanggal transaksi melebihi batas maksimal backdate (' . $limitDays . ' hari).'], 422);
            }
        }

        $tenantId = AuthService::getTenantId();
        $userId = AuthService::getGlobalUserId();
        
        $data = [
            'karang_taruna_id' => $tenantId,
            'jenis'       => $rawInput['jenis'],
            'nominal'     => $rawInput['nominal'],
            'keterangan'  => $rawInput['keterangan'],
            'tanggal'     => $rawInput['tanggal'],
            'dibuat_oleh' => $userId,
        ];

        $kasModel = new KasModel();
        $kasModel->insert($data);

        return $this->sendSuccess('Transaksi berhasil dicatat.', $data, 201);
    }

    public function delete($id = null)
    {
        if (!AuthService::can('cash.delete')) {
            return $this->sendError('Forbidden: Akses ditolak.', null, 403);
        }

        $tenantId = AuthService::getTenantId();
        $kasModel = new KasModel();
        $transaksi = $kasModel->where('karang_taruna_id', $tenantId)->find($id);

        if (!$transaksi) {
            return $this->sendError('Transaksi tidak ditemukan.', null, 404);
        }

        $kasModel->delete($id);

        return $this->sendSuccess('Transaksi berhasil dihapus.');
    }

    public function summary()
    {
        $tenantId = AuthService::getTenantId();
        if (!$tenantId || !AuthService::can('cash.view')) {
            return $this->sendError('Forbidden', null, 403);
        }

        $kasModel = new KasModel();
        $saldo = $kasModel->getTotalSaldo($tenantId);

        // Calculate this month's income and expense
        $currentMonth = date('Y-m');
        $thisMonthPemasukan = $kasModel->where('karang_taruna_id', $tenantId)
                                       ->where('jenis', 'pemasukan')
                                       ->like('tanggal', $currentMonth, 'after')
                                       ->selectSum('nominal')
                                       ->get()
                                       ->getRow()
                                       ->nominal ?? 0;
                                       
        $thisMonthPengeluaran = $kasModel->where('karang_taruna_id', $tenantId)
                                         ->where('jenis', 'pengeluaran')
                                         ->like('tanggal', $currentMonth, 'after')
                                         ->selectSum('nominal')
                                         ->get()
                                         ->getRow()
                                         ->nominal ?? 0;

        return $this->sendSuccess('Ringkasan Kas', [
            'saldo_akhir' => (int)$saldo,
            'pemasukan_bulan_ini' => (int)$thisMonthPemasukan,
            'pengeluaran_bulan_ini' => (int)$thisMonthPengeluaran
        ]);
    }
}
