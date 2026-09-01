<?php

namespace App\Controllers\Api;

use App\Models\KasModel;
use App\Models\UserModel;
use App\Services\AuthService;

class KasController extends BaseApiController
{
    private function checkBendaharaAtauKetua()
    {
        $user = AuthService::getUser();
        if (!$user || !in_array($user['role_level'], ['admin', 'ketua', 'bendahara'])) {
            return false;
        }
        return true;
    }

    public function index()
    {
        $user = AuthService::getUser();
        if (!$user) {
            return $this->sendError('Unauthorized', null, 401);
        }

        $kasModel = new KasModel();
        
        $builder = $kasModel->builder();
        $builder->select('kas.*, users.nama_lengkap as pembuat');
        $builder->join('users', 'users.id = kas.dibuat_oleh', 'left');
        $builder->orderBy('kas.tanggal', 'DESC');
        $builder->orderBy('kas.created_at', 'DESC');
        $transaksi = $builder->get()->getResultArray();

        $saldo = $kasModel->getTotalSaldo();

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
        if (!$this->checkBendaharaAtauKetua()) {
            return $this->sendError('Forbidden: Akses khusus Bendahara, Ketua, dan Admin.', null, 403);
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

        $user = AuthService::getUser();
        
        $data = [
            'jenis'       => $rawInput['jenis'],
            'nominal'     => $rawInput['nominal'],
            'keterangan'  => $rawInput['keterangan'],
            'tanggal'     => $rawInput['tanggal'],
            'dibuat_oleh' => $user['id'],
        ];

        $kasModel = new KasModel();
        $kasModel->insert($data);

        return $this->sendSuccess('Transaksi berhasil dicatat.', $data, 201);
    }

    public function delete($id = null)
    {
        if (!$this->checkBendaharaAtauKetua()) {
            return $this->sendError('Forbidden: Akses khusus Bendahara, Ketua, dan Admin.', null, 403);
        }

        $kasModel = new KasModel();
        $transaksi = $kasModel->find($id);

        if (!$transaksi) {
            return $this->sendError('Transaksi tidak ditemukan.', null, 404);
        }

        $kasModel->delete($id);

        return $this->sendSuccess('Transaksi berhasil dihapus.');
    }
}
