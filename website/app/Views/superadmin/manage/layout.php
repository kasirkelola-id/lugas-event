<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $this->renderSection('title') ?> - <?= esc($kt['nama_organisasi']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #f4f7fe; color: #2b3674; }
        .sidebar { min-height: 100vh; background: #fff; box-shadow: 4px 0 20px rgba(0,0,0,0.05); padding: 20px 0; }
        .sidebar .nav-link { color: #a3aed1; font-weight: 600; padding: 12px 20px; border-radius: 10px; margin: 0 15px 5px; transition: all 0.3s; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background: linear-gradient(135deg, #4318FF 0%, #39B8FF 100%); color: white; }
        .sidebar .nav-link i { margin-right: 10px; font-size: 1.1rem; }
        .brand-section { padding: 0 25px 20px; border-bottom: 1px solid #f0f0f0; margin-bottom: 20px; }
        .brand-title { font-weight: 700; font-size: 1.2rem; color: #1e3c72; }
        .brand-subtitle { font-size: 0.85rem; color: #a3aed1; }
        .main-content { padding: 30px; width: 100%; overflow-x: hidden; }
        .card-custom { background: #fff; border-radius: 20px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.05); padding: 20px; }
        @media (max-width: 768px) {
            .sidebar { width: 100% !important; min-height: auto; padding: 10px; }
            .main-content { padding: 15px; }
            .nav { flex-direction: row; flex-wrap: wrap; }
            .nav-item { flex: 1 1 30%; }
            .nav-link { text-align: center; font-size: 0.75rem; padding: 8px 5px; margin: 5px; }
            .nav-link i { display: block; margin: 0 auto 5px; font-size: 1.2rem; }
        }
    </style>
</head>
<body>
    <div class="d-flex flex-column flex-md-row">
        <!-- Sidebar -->
        <div class="sidebar flex-shrink-0" style="width: 260px;">
            <div class="brand-section">
                <a href="/superadmin/karang_taruna" class="text-decoration-none d-block mb-3 text-muted small"><i class="bi bi-arrow-left"></i> Kembali ke Pusat</a>
                <div class="brand-title"><?= esc($kt['nama_organisasi']) ?></div>
                <div class="brand-subtitle">Panel Manajemen</div>
            </div>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link <?= current_url() == site_url("superadmin/manage/{$kt['id']}") ? 'active' : '' ?>" href="/superadmin/manage/<?= $kt['id'] ?>">
                        <i class="bi bi-grid-1x2-fill"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= strpos(current_url(), 'manage/'.$kt['id'].'/users') !== false ? 'active' : '' ?>" href="/superadmin/manage/<?= $kt['id'] ?>/users">
                        <i class="bi bi-people-fill"></i> Pengguna
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= strpos(current_url(), 'manage/'.$kt['id'].'/events') !== false ? 'active' : '' ?>" href="/superadmin/manage/<?= $kt['id'] ?>/events">
                        <i class="bi bi-calendar-event-fill"></i> Event & Absensi
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= strpos(current_url(), 'manage/'.$kt['id'].'/pengumuman') !== false ? 'active' : '' ?>" href="/superadmin/manage/<?= $kt['id'] ?>/pengumuman">
                        <i class="bi bi-megaphone-fill"></i> Pengumuman
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= strpos(current_url(), 'manage/'.$kt['id'].'/kas') !== false ? 'active' : '' ?>" href="/superadmin/manage/<?= $kt['id'] ?>/kas">
                        <i class="bi bi-wallet2"></i> Keuangan (Kas)
                    </a>
                </li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="main-content flex-grow-1">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
                <h2 class="fw-bold mb-0"><?= $this->renderSection('title') ?></h2>
                <div class="user-info bg-white rounded-pill px-4 py-2 shadow-sm fw-bold text-primary w-100 w-md-auto text-center">
                    <i class="bi bi-person-circle me-2"></i> <?= session()->get('superadmin_nama_lengkap') ?>
                </div>
            </div>
            
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

            <?= $this->renderSection('content') ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
