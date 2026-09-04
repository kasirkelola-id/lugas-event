<?php

namespace App\Controllers\Superadmin;

use App\Controllers\BaseController;
use App\Models\KelurahanModel;

class KelurahanController extends BaseController
{
    public function index()
    {
        $kelurahanModel = new KelurahanModel();
        
        $data = [
            'title'     => 'Data Kelurahan',
            'kelurahan' => $kelurahanModel->findAll()
        ];
        
        return view('superadmin/kelurahan/index', $data);
    }

    public function store()
    {
        $kelurahanModel = new KelurahanModel();
        
        $data = [
            'nama' => $this->request->getPost('nama'),
        ];
        
        if (!$kelurahanModel->insert($data)) {
            return redirect()->back()->withInput()->with('error', 'Gagal menambahkan data kelurahan.');
        }
        
        return redirect()->to('/superadmin/kelurahan')->with('success', 'Kelurahan berhasil ditambahkan.');
    }

    public function update($id)
    {
        $kelurahanModel = new KelurahanModel();
        
        $data = [
            'nama' => $this->request->getPost('nama'),
        ];
        
        if (!$kelurahanModel->update($id, $data)) {
            return redirect()->back()->withInput()->with('error', 'Gagal memperbarui data kelurahan.');
        }
        
        return redirect()->to('/superadmin/kelurahan')->with('success', 'Kelurahan berhasil diperbarui.');
    }

    public function delete($id)
    {
        $kelurahanModel = new KelurahanModel();
        
        try {
            $kelurahanModel->delete($id);
            return redirect()->to('/superadmin/kelurahan')->with('success', 'Kelurahan berhasil dihapus.');
        } catch (\Exception $e) {
            return redirect()->to('/superadmin/kelurahan')->with('error', 'Gagal menghapus kelurahan karena mungkin sedang digunakan.');
        }
    }
}
