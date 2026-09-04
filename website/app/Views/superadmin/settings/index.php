<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title) ?> - Superadmin</title>
    <link rel="icon" href="/assets/images/logo.png" type="image/png">
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
        .stat-card {
            background: #fff;
            border-radius: 20px;
            border: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            padding: 24px;
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
        .form-control {
            border-radius: 10px;
            padding: 12px;
            border-color: #e2e8f0;
        }
        .form-control:focus {
            box-shadow: none;
            border-color: #1e3c72;
        }
        .btn-primary {
            background-color: #1e3c72;
            border: none;
            border-radius: 10px;
            padding: 12px 24px;
            font-weight: 600;
        }
        .btn-primary:hover {
            background-color: #2a5298;
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
                    <li class="nav-item"><a class="nav-link" href="/superadmin/karang_taruna">Karang Taruna</a></li>
                    <li class="nav-item"><a class="nav-link" href="/superadmin/kelurahan">Kelurahan</a></li>
                    <li class="nav-item"><a class="nav-link active" href="/superadmin/settings">Pengaturan</a></li>
                </ul>
                <span class="navbar-text me-4">
                    <i class="bi bi-person-circle me-1"></i> Halo, <?= session()->get('superadmin_nama_lengkap') ?>
                </span>
                <a href="/superadmin/logout" class="btn btn-sm btn-custom-outline px-3">Logout <i class="bi bi-box-arrow-right ms-1"></i></a>
            </div>
        </div>
    </nav>

    <div class="container dashboard-header">
        <h2>Pengaturan Global</h2>
        <p class="text-muted">Konfigurasi pengaturan global sistem.</p>
    </div>

    <div class="container mb-5">


        <div class="row g-4">
            <div class="col-md-8 col-lg-6">
                <div class="card stat-card">
                    <h5 class="fw-bold mb-4">Pengaturan Akun</h5>
                    <form action="/superadmin/settings" method="post">
                        <?= csrf_field() ?>
                        <div class="mb-4">
                            <label class="form-label fw-bold text-muted small text-uppercase">Password Sementara (Reset)</label>
                            <div class="input-group">
                                <input type="password" id="temp_password" name="temporary_reset_password" class="form-control" 
                                       value="<?= esc($settings['temporary_reset_password'] ?? '') ?>" 
                                       placeholder="Contoh: kartarjosjis" required minlength="8">
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <div class="form-text">
                                Password sementara ini akan digunakan ketika Admin / Ketua mereset password anggotanya. <br>
                                Pengguna yang login dengan password ini akan dipaksa mengganti passwordnya.
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Simpan Pengaturan</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('togglePassword').addEventListener('click', function (e) {
            const passwordInput = document.getElementById('temp_password');
            const icon = this.querySelector('i');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        });
    </script>
    <?= $this->include('superadmin/partials/sweetalert') ?>
</body>
</html>
