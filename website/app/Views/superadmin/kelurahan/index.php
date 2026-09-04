<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?> - Superadmin</title>
    <link rel="icon" href="/assets/images/logo.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.12/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #f4f7fe; color: #2b3674; }
        .navbar-custom { background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); padding: 15px 0; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        .navbar-custom .navbar-brand, .navbar-custom .nav-link, .navbar-custom .navbar-text { color: #fff !important; }
        .navbar-custom .nav-link:hover, .navbar-custom .nav-link.active { font-weight: 600; opacity: 0.9; }
        .content-header { margin-top: 30px; margin-bottom: 30px; }
        .content-header h2 { font-weight: 700; font-size: 2rem; letter-spacing: -0.5px; }
        .card-custom { background: #fff; border-radius: 15px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container">
            <a class="navbar-brand fw-bold" href="/superadmin/dashboard">
                <i class="bi bi-shield-check me-2"></i>Lugasku Superadmin
            </a>
            <button class="navbar-toggler text-white border-white" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <i class="bi bi-list"></i>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link" href="/superadmin/dashboard">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="/superadmin/karang_taruna">Karang Taruna</a></li>
                    <li class="nav-item"><a class="nav-link active" href="/superadmin/kelurahan">Kelurahan</a></li>
                    <li class="nav-item"><a class="nav-link" href="/superadmin/settings">Pengaturan</a></li>
                </ul>
                <span class="navbar-text me-4">
                    <i class="bi bi-person-circle me-1"></i> Halo, <?= session()->get('superadmin_nama_lengkap') ?>
                </span>
                <a href="/superadmin/logout" class="btn btn-sm btn-outline-light px-3">Logout <i class="bi bi-box-arrow-right ms-1"></i></a>
            </div>
        </div>
    </nav>

    <div class="container content-header d-flex justify-content-between align-items-center">
        <div>
            <h2>Data Kelurahan</h2>
            <p class="text-muted">Kelola data wilayah kelurahan / desa.</p>
        </div>
        <button class="btn btn-primary px-4 py-2" data-bs-toggle="modal" data-bs-target="#addKelurahanModal">
            <i class="bi bi-plus-lg me-1"></i> Tambah Kelurahan
        </button>
    </div>

    <div class="container mb-5">
        <?= view('superadmin/partials/sweetalert') ?>
        
        <div class="card card-custom p-4">
            <div class="table-responsive">
                <table id="kelurahanTable" class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th width="5%">No</th>
                            <th>Nama Kelurahan/Desa</th>
                            <th width="20%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; foreach($kelurahan as $k): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td class="fw-semibold text-primary"><?= esc($k['nama']) ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary me-2 btn-edit" data-id="<?= $k['id'] ?>" data-nama="<?= esc($k['nama']) ?>" data-bs-toggle="modal" data-bs-target="#editKelurahanModal">
                                    <i class="bi bi-pencil"></i> Edit
                                </button>
                                <a href="/superadmin/kelurahan/delete/<?= $k['id'] ?>" class="btn btn-sm btn-outline-danger btn-delete">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal Tambah -->
    <div class="modal fade" id="addKelurahanModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">Tambah Kelurahan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="/superadmin/kelurahan/store" method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nama Kelurahan / Desa</label>
                            <input type="text" class="form-control" name="nama" required placeholder="Contoh: Kelurahan Setia Budi">
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary px-4">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Edit -->
    <div class="modal fade" id="editKelurahanModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">Edit Kelurahan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="" method="POST" id="formEdit">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nama Kelurahan / Desa</label>
                            <input type="text" class="form-control" name="nama" id="edit_nama" required>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary px-4">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.12/dist/sweetalert2.all.min.js"></script>
    
    <script>
        $(document).ready(function() {
            $('#kelurahanTable').DataTable({
                language: { url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/id.json' }
            });

            $('.btn-edit').click(function() {
                const id = $(this).data('id');
                const nama = $(this).data('nama');
                
                $('#edit_nama').val(nama);
                $('#formEdit').attr('action', '/superadmin/kelurahan/update/' + id);
            });

            $('.btn-delete').click(function(e) {
                e.preventDefault();
                const url = $(this).attr('href');
                Swal.fire({
                    title: 'Hapus Kelurahan?',
                    text: "Data yang dihapus tidak dapat dikembalikan!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Ya, Hapus!',
                    cancelButtonText: 'Batal'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = url;
                    }
                });
            });
        });
    </script>
</body>
</html>
