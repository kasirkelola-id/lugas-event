<?php

namespace App\Models;

use CodeIgniter\Model;

class KasModel extends Model
{
    protected $table            = 'kas';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'karang_taruna_id',
        'jenis', 
        'nominal', 
        'keterangan', 
        'tanggal', 
        'dibuat_oleh'
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Mendapatkan total kas
    public function getTotalSaldo($karangTarunaId = null)
    {
        $db = \Config\Database::connect();
        
        $pemasukanBuilder = $db->table($this->table)->selectSum('nominal')->where('jenis', 'pemasukan');
        $pengeluaranBuilder = $db->table($this->table)->selectSum('nominal')->where('jenis', 'pengeluaran');

        if ($karangTarunaId !== null) {
            $pemasukanBuilder->where('karang_taruna_id', $karangTarunaId);
            $pengeluaranBuilder->where('karang_taruna_id', $karangTarunaId);
        }
        
        $pemasukan = $pemasukanBuilder->get()->getRow()->nominal ?? 0;
        $pengeluaran = $pengeluaranBuilder->get()->getRow()->nominal ?? 0;
        
        return $pemasukan - $pengeluaran;
    }
}
