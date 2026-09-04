<?php

namespace App\Models;

use CodeIgniter\Model;

class KarangTarunaModel extends Model
{
    protected $table            = 'karang_taruna';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['nama_organisasi', 'kode_pin', 'alamat_lengkap', 'nama_ketua', 'status_aktif', 'logo_path', 'kelurahan_id'];

    protected bool $allowEmptyInserts = false;

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
