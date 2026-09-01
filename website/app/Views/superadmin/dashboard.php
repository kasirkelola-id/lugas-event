<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Superadmin</title>
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
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            overflow: hidden;
            position: relative;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        }
        .stat-icon {
            position: absolute;
            right: -20px;
            top: -20px;
            font-size: 8rem;
            opacity: 0.05;
            color: inherit;
        }
        .stat-title {
            color: #a3aed1;
            font-size: 1rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: #2b3674;
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
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container">
            <a class="navbar-brand fw-bold" href="#"><i class="bi bi-shield-lock-fill me-2"></i>Lugasku Superadmin</a>
            <button class="navbar-toggler border-0 text-white" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <i class="bi bi-list fs-2"></i>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link active" href="/superadmin/dashboard">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="/superadmin/karang_taruna">Karang Taruna</a></li>
                </ul>
                <span class="navbar-text me-4">
                    <i class="bi bi-person-circle me-1"></i> Halo, <?= session()->get('superadmin_nama_lengkap') ?>
                </span>
                <a href="/superadmin/logout" class="btn btn-sm btn-custom-outline px-3">Logout <i class="bi bi-box-arrow-right ms-1"></i></a>
            </div>
        </div>
    </nav>

    <div class="container dashboard-header">
        <h2>Dashboard Overview</h2>
        <p class="text-muted">Pantau keseluruhan data Karang Taruna Anda di sini.</p>
    </div>

    <div class="container">
        <div class="row g-4">
            <div class="col-md-6 col-lg-3">
                <div class="card stat-card p-4">
                    <i class="bi bi-diagram-3-fill stat-icon text-primary"></i>
                    <div class="stat-title mb-2">Karang Taruna</div>
                    <div class="stat-value"><?= $total_karang_taruna ?></div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="card stat-card p-4">
                    <i class="bi bi-people-fill stat-icon text-success"></i>
                    <div class="stat-title mb-2">Pengguna Aktif</div>
                    <div class="stat-value"><?= $total_users ?></div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="card stat-card p-4">
                    <i class="bi bi-calendar-event-fill stat-icon text-warning"></i>
                    <div class="stat-title mb-2">Total Acara</div>
                    <div class="stat-value"><?= $total_events ?></div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="card stat-card p-4">
                    <i class="bi bi-megaphone-fill stat-icon text-danger"></i>
                    <div class="stat-title mb-2">Pengumuman</div>
                    <div class="stat-value"><?= $total_pengumuman ?></div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
