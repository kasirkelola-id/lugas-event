<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Karang Taruna - Superadmin</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { 
            font-family: 'Outfit', sans-serif;
            background-color: #f4f7fe;
            color: #2b3674;
        }
        .navbar-custom {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            padding: 15px 0;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        .navbar-custom .navbar-brand, .navbar-custom .nav-link, .navbar-custom .navbar-text {
            color: #fff !important;
        }
        .navbar-custom .nav-link:hover, .navbar-custom .nav-link.active {
            font-weight: 600;
            opacity: 0.9;
        }
        .dashboard-header {
            margin-top: 30px;
            margin-bottom: 30px;
        }
        .dashboard-header h2 {
            font-weight: 700;
            font-size: 2rem;
            letter-spacing: -0.5px;
        }
        .card-custom {
            background: #fff;
            border-radius: 20px;
            border: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            padding: 20px;
        }
        .table {
            color: #2b3674;
            vertical-align: middle;
        }
        .table thead th {
            border-bottom: 2px solid #e2e8f0;
            color: #a3aed1;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }
        .table tbody tr {
            transition: all 0.2s;
        }
        .table tbody tr:hover {
            background-color: #f8f9fc;
        }
        .badge-active {
            background-color: #d1fae5;
            color: #059669;
        }
        .badge-inactive {
            background-color: #fee2e2;
            color: #dc2626;
        }
        .btn-custom-outline {
            border: 1px solid rgba(255,255,255,0.5);
            color: white;
            border-radius: 10px;
            transition: all 0.3s;
        }
        .btn-custom-outline:hover {
            background: white;
            color: #1e3c72;
        }
        .btn-add {
            background: linear-gradient(135deg, #4318FF 0%, #39B8FF 100%);
            border: none;
            border-radius: 10px;
            font-weight: 600;
            padding: 10px 20px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(67, 24, 255, 0.3);
            color: white;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container">
            <a class="navbar-brand fw-bold d-flex align-items-center" href="#">
                <img src="/assets/images/logo.png" alt="Logo" width="30" height="30" class="me-2" style="border-radius: 4px;">
                Karang Taruna App
            </a>
            <button class="navbar-toggler border-0 text-white" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <i class="bi bi-list fs-2"></i>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="/superadmin/dashboard">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link active" href="/superadmin/karang_taruna">Karang Taruna</a></li>
                    <li class="nav-item"><a class="nav-link" href="/superadmin/settings">Pengaturan</a></li>
                </ul>
                <span class="navbar-text me-4">
                    <i class="bi bi-person-circle me-1"></i> Halo, <?= session()->get('superadmin_nama_lengkap') ?>
                </span>
                <a href="/superadmin/logout" class="btn btn-sm btn-custom-outline px-3">Logout <i class="bi bi-box-arrow-right ms-1"></i></a>
            </div>
        </div>
    </nav>

    <div class="container dashboard-header">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
            <div>
                <h2>Daftar Karang Taruna</h2>
                <p class="text-muted mb-0">Manajemen keanggotaan dan PIN akses.</p>
            </div>
            <button class="btn btn-primary btn-add w-100 w-md-auto" data-bs-toggle="modal" data-bs-target="#addModal">
                <i class="bi bi-plus-circle me-1"></i> Tambah Baru
            </button>
        </div>
    </div>

    <div class="container">
        <?php if(session()->getFlashdata('success')): ?>
            <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> <?= session()->getFlashdata('success') ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if(session()->getFlashdata('error')): ?>
            <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= session()->getFlashdata('error') ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card card-custom">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="py-3 px-4">No</th>
                                <th class="py-3">Nama Organisasi</th>
                                <th class="py-3">6-Digit PIN (Kode Akses)</th>
                                <th class="py-3">Ketua/Kontak</th>
                                <th class="py-3">Status</th>
                                <th class="py-3 text-end px-4">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($karang_taruna)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-1 d-block mb-3"></i>
                                    Belum ada data Karang Taruna terdaftar
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php $no=1; foreach($karang_taruna as $kt): ?>
                                <tr>
                                    <td class="px-4 fw-bold text-muted"><?= $no++ ?></td>
                                    <td>
                                        <div class="fw-bold fs-6"><?= esc($kt['nama_organisasi']) ?></div>
                                        <small class="text-muted"><i class="bi bi-geo-alt me-1"></i><?= esc($kt['alamat_lengkap']) ?></small>
                                    </td>
                                    <td>
                                        <div class="d-inline-flex align-items-center bg-light rounded px-3 py-1 border border-primary border-opacity-25">
                                            <i class="bi bi-key-fill text-primary me-2"></i>
                                            <strong class="fs-5 text-primary tracking-widest"><?= esc($kt['kode_pin']) ?></strong>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="bg-primary bg-opacity-10 rounded-circle p-2 me-2">
                                                <i class="bi bi-person text-primary"></i>
                                            </div>
                                            <?= esc($kt['nama_ketua']) ?: '-' ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if($kt['status_aktif'] == 1): ?>
                                            <span class="badge badge-active px-3 py-2 rounded-pill"><i class="bi bi-check-circle me-1"></i>Aktif</span>
                                        <?php else: ?>
                                            <span class="badge badge-inactive px-3 py-2 rounded-pill"><i class="bi bi-x-circle me-1"></i>Nonaktif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end px-4">
                                        <a href="/superadmin/manage/<?= $kt['id'] ?>" class="btn btn-sm btn-primary me-1" title="Kelola Data"><i class="bi bi-gear-fill"></i> Kelola</a>
                                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editModal<?= $kt['id'] ?>" title="Edit"><i class="bi bi-pencil"></i></button>
                                        <a href="/superadmin/karang_taruna/delete/<?= $kt['id'] ?>" class="btn btn-sm btn-outline-danger ms-1" onclick="return confirm('Yakin ingin menghapus data ini?')" title="Hapus"><i class="bi bi-trash"></i></a>
                                    </td>
                                </tr>

                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Modals -->
    <?php if(!empty($karang_taruna)): ?>
        <?php foreach($karang_taruna as $kt): ?>
        <div class="modal fade" id="editModal<?= $kt['id'] ?>" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
            <form action="/superadmin/karang_taruna/update/<?= $kt['id'] ?>" method="post" enctype="multipart/form-data" class="w-100">
                <?= csrf_field() ?>
                <div class="modal-content border-0 shadow">
                    <div class="modal-header bg-light border-0">
                    <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2 text-primary"></i>Edit Karang Taruna</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label text-muted fw-bold small">Nama Organisasi</label>
                        <input type="text" name="nama_organisasi" class="form-control form-control-lg bg-light border-0" value="<?= esc($kt['nama_organisasi']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted fw-bold small">Alamat Lengkap</label>
                        <textarea name="alamat_lengkap" class="form-control form-control-lg bg-light border-0" rows="3"><?= esc($kt['alamat_lengkap']) ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted fw-bold small">Nama Ketua / PIC</label>
                        <input type="text" name="nama_ketua" class="form-control form-control-lg bg-light border-0" value="<?= esc($kt['nama_ketua']) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted fw-bold small">Status Aktif</label>
                        <select name="status_aktif" class="form-select form-control-lg bg-light border-0">
                            <option value="1" <?= $kt['status_aktif'] == 1 ? 'selected' : '' ?>>Aktif</option>
                            <option value="0" <?= $kt['status_aktif'] == 0 ? 'selected' : '' ?>>Nonaktif</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted fw-bold small">Logo Karang Taruna (Opsional)</label>
                        <?php if (!empty($kt['logo_path'])): ?>
                            <div class="mb-2">
                                <img src="<?= base_url($kt['logo_path']) ?>" alt="Logo Current" class="img-thumbnail" style="max-height: 100px;">
                                <div class="form-check mt-1">
                                    <input class="form-check-input" type="checkbox" name="remove_logo" value="1" id="removeLogo<?= $kt['id'] ?>">
                                    <label class="form-check-label text-danger small" for="removeLogo<?= $kt['id'] ?>">
                                        Hapus Logo Ini
                                    </label>
                                </div>
                            </div>
                        <?php endif; ?>
                        <input type="file" name="logo" class="form-control bg-light border-0" accept="image/jpeg,image/png,image/webp">
                        <small class="text-muted d-block mt-1">Format: JPG, PNG, WEBP. Maks: 2MB.</small>
                    </div>
                    </div>
                    <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary px-4">Simpan Perubahan</button>
                    </div>
                </div>
            </form>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Add Modal -->
    <div class="modal fade" id="addModal" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <form action="/superadmin/karang_taruna/create" method="post" enctype="multipart/form-data" class="w-100">
            <?= csrf_field() ?>
            <div class="modal-content border-0 shadow">
              <div class="modal-header bg-light border-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2 text-primary"></i>Tambah Karang Taruna Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body p-4">
                <div class="alert alert-primary bg-primary bg-opacity-10 border-0 text-primary">
                    <i class="bi bi-info-circle-fill me-2"></i> PIN akan digunakan untuk login di aplikasi Mobile. Pastikan PIN unik dan mudah diingat.
                </div>
                
                <div class="mb-3">
                    <label class="form-label text-muted fw-bold small">Nama Organisasi</label>
                    <input type="text" name="nama_organisasi" class="form-control form-control-lg bg-light border-0" placeholder="Contoh: Karang Taruna Mekar Jaya" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label text-muted fw-bold small">PIN Unik (6 Karakter)</label>
                    <input type="text" name="kode_pin" class="form-control form-control-lg bg-light border-0 fs-4 tracking-widest font-monospace" placeholder="123456" minlength="6" maxlength="6" required>
                </div>

                <div class="mb-3">
                    <label class="form-label text-muted fw-bold small">Alamat Lengkap</label>
                    <textarea name="alamat_lengkap" class="form-control bg-light border-0" placeholder="Nama desa, kelurahan, dsb"></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label text-muted fw-bold small">Nama Ketua / PIC</label>
                    <input type="text" name="nama_ketua" class="form-control bg-light border-0" placeholder="Nama penanggung jawab">
                </div>
                <div class="mb-3">
                    <label class="form-label text-muted fw-bold small">Logo Karang Taruna (Opsional)</label>
                    <input type="file" name="logo" class="form-control bg-light border-0" accept="image/jpeg,image/png,image/webp">
                    <small class="text-muted d-block mt-1">Format: JPG, PNG, WEBP. Maks: 2MB.</small>
                </div>
              </div>
              <div class="modal-footer border-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary px-4">Simpan & Daftarkan</button>
              </div>
            </div>
        </form>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
