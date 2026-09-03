<?php

namespace App\Controllers\Superadmin;

use App\Controllers\BaseController;
use App\Models\KarangTarunaModel;

class KarangTarunaController extends BaseController
{
    public function index()
    {
        $ktModel = new KarangTarunaModel();
        $data['karang_taruna'] = $ktModel->findAll();

        return view('superadmin/karang_taruna/index', $data);
    }

    public function create()
    {
        $ktModel = new KarangTarunaModel();
        
        $kode_pin = $this->request->getPost('kode_pin');
        
        // Ensure PIN is unique
        if ($ktModel->where('kode_pin', $kode_pin)->first()) {
            return redirect()->back()->with('error', 'PIN tersebut sudah digunakan oleh Karang Taruna lain. Silakan pilih PIN yang berbeda.');
        }
        
        $logoPath = $this->handleLogoUpload();
        if ($logoPath === false) {
            return redirect()->back()->with('error', 'Logo gagal diunggah atau format tidak sesuai. Pastikan file gambar berukuran maksimal 2MB (jpg/png/webp).');
        }

        $data = [
            'nama_organisasi' => $this->request->getPost('nama_organisasi'),
            'kode_pin'        => $kode_pin,
            'alamat_lengkap'  => $this->request->getPost('alamat_lengkap'),
            'nama_ketua'      => $this->request->getPost('nama_ketua'),
            'status_aktif'    => $this->request->getPost('status_aktif') ?? 1,
            'logo_path'       => $logoPath,
        ];

        $ktModel->insert($data);

        return redirect()->to('/superadmin/karang_taruna')->with('success', 'Karang Taruna berhasil ditambahkan dengan PIN: ' . $kode_pin);
    }

    public function update($id)
    {
        $ktModel = new KarangTarunaModel();
        
        $kt = $ktModel->find($id);
        if (!$kt) {
            return redirect()->to('/superadmin/karang_taruna')->with('error', 'Data Karang Taruna tidak ditemukan');
        }

        $data = [
            'nama_organisasi' => $this->request->getPost('nama_organisasi'),
            'alamat_lengkap'  => $this->request->getPost('alamat_lengkap'),
            'nama_ketua'      => $this->request->getPost('nama_ketua'),
            'status_aktif'    => $this->request->getPost('status_aktif'),
        ];
        
        $logoPath = $this->handleLogoUpload();
        if ($logoPath === false) {
            return redirect()->back()->with('error', 'Logo gagal diunggah atau format tidak sesuai. Pastikan file gambar berukuran maksimal 2MB (jpg/png/webp).');
        }
        
        if ($logoPath !== null) {
            $data['logo_path'] = $logoPath;
            // Delete old logo if exists
            if (!empty($kt['logo_path']) && file_exists(FCPATH . $kt['logo_path'])) {
                @unlink(FCPATH . $kt['logo_path']);
            }
        } else if ($this->request->getPost('remove_logo') == '1') {
            $data['logo_path'] = null;
            if (!empty($kt['logo_path']) && file_exists(FCPATH . $kt['logo_path'])) {
                @unlink(FCPATH . $kt['logo_path']);
            }
        }

        $ktModel->update($id, $data);

        return redirect()->to('/superadmin/karang_taruna')->with('success', 'Data Karang Taruna berhasil diperbarui');
    }

    public function delete($id)
    {
        $ktModel = new KarangTarunaModel();
        $kt = $ktModel->find($id);
        if ($kt) {
            if (!empty($kt['logo_path']) && file_exists(FCPATH . $kt['logo_path'])) {
                @unlink(FCPATH . $kt['logo_path']);
            }
            $ktModel->delete($id);
        }

        return redirect()->to('/superadmin/karang_taruna')->with('success', 'Karang Taruna berhasil dihapus');
    }

    private function handleLogoUpload()
    {
        $file = $this->request->getFile('logo');
        if (!$file || !$file->isValid()) {
            return null; // No file uploaded, or upload failed without selecting
        }

        $validationRule = [
            'logo' => [
                'label' => 'Logo',
                'rules' => 'uploaded[logo]'
                    . '|is_image[logo]'
                    . '|mime_in[logo,image/jpeg,image/png,image/webp]'
                    . '|max_size[logo,2048]',
            ],
        ];

        if (!$this->validate($validationRule)) {
            return false; // Validation failed
        }

        // Generate random name
        $newName = $file->getRandomName();
        $uploadDir = FCPATH . 'uploads/karang_taruna/logos/';
        
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        // We use CI4 image processing to resize/compress
        try {
            $image = \Config\Services::image()
                ->withFile($file->getTempName())
                ->resize(512, 512, true, 'auto') // preserve aspect ratio
                ->save($uploadDir . $newName, 85); // 85% quality
                
            return 'uploads/karang_taruna/logos/' . $newName;
        } catch (\Exception $e) {
            log_message('error', 'Image processing failed: ' . $e->getMessage());
            // Fallback to moving the file directly if image processing fails (e.g. GD not installed)
            $file->move($uploadDir, $newName);
            return 'uploads/karang_taruna/logos/' . $newName;
        }
    }

    public function users($id)
    {
        $ktModel = new KarangTarunaModel();
        $userModel = new \App\Models\UserModel();

        $karang_taruna = $ktModel->find($id);
        
        if (!$karang_taruna) {
            return redirect()->to('/superadmin/karang_taruna')->with('error', 'Data Karang Taruna tidak ditemukan');
        }

        $data['karang_taruna'] = $karang_taruna;
        $data['users'] = $userModel->where('karang_taruna_id', $id)->findAll();

        return view('superadmin/karang_taruna/users', $data);
    }
}
