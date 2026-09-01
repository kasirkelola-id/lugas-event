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
    public function getTotalSaldo()
    {
        $db = \Config\Database::connect();
        
        $pemasukan = $db->table($this->table)->selectSum('nominal')->where('jenis', 'pemasukan')->get()->getRow()->nominal ?? 0;
        $pengeluaran = $db->table($this->table)->selectSum('nominal')->where('jenis', 'pengeluaran')->get()->getRow()->nominal ?? 0;
        
        return $pemasukan - $pengeluaran;
    }
}
