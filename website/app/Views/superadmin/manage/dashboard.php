<?= $this->extend('superadmin/manage/layout') ?>

<?= $this->section('title') ?>
Dashboard
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row g-4 mb-4">
    <div class="col-md-6 col-lg-3">
        <div class="card card-custom h-100 p-4 border-bottom border-primary border-4 text-center">
            <i class="bi bi-people-fill text-primary mb-3" style="font-size: 2.5rem;"></i>
            <h6 class="text-muted text-uppercase fw-bold mb-2">Total Pengguna</h6>
            <h2 class="fw-bold mb-0 text-primary"><?= $total_users ?></h2>
        </div>
    </div>
    <div class="col-md-6 col-lg-3">
        <div class="card card-custom h-100 p-4 border-bottom border-info border-4 text-center">
            <i class="bi bi-calendar-event-fill text-info mb-3" style="font-size: 2.5rem;"></i>
            <h6 class="text-muted text-uppercase fw-bold mb-2">Total Event</h6>
            <h2 class="fw-bold mb-0 text-info"><?= $total_events ?></h2>
        </div>
    </div>
    <div class="col-md-6 col-lg-3">
        <div class="card card-custom h-100 p-4 border-bottom border-warning border-4 text-center">
            <i class="bi bi-megaphone-fill text-warning mb-3" style="font-size: 2.5rem;"></i>
            <h6 class="text-muted text-uppercase fw-bold mb-2">Pengumuman</h6>
            <h2 class="fw-bold mb-0 text-warning"><?= $total_pengumuman ?></h2>
        </div>
    </div>
    <div class="col-md-6 col-lg-3">
        <div class="card card-custom h-100 p-4 border-bottom border-success border-4 text-center">
            <i class="bi bi-wallet2 text-success mb-3" style="font-size: 2.5rem;"></i>
            <h6 class="text-muted text-uppercase fw-bold mb-2">Saldo Kas</h6>
            <h3 class="fw-bold mb-0 text-success">Rp <?= number_format($saldo_kas, 0, ',', '.') ?></h3>
        </div>
    </div>
</div>

<div class="card card-custom">
    <div class="card-body">
        <h5 class="fw-bold mb-3"><i class="bi bi-info-circle text-primary me-2"></i>Informasi Karang Taruna</h5>
        <table class="table table-borderless w-auto">
            <tr>
                <td class="text-muted fw-bold pe-4">Nama Organisasi</td>
                <td>: <?= esc($kt['nama_organisasi']) ?></td>
            </tr>
            <tr>
                <td class="text-muted fw-bold pe-4">PIN Akses Mobile</td>
                <td>: <strong class="text-primary bg-primary bg-opacity-10 px-2 py-1 rounded"><?= esc($kt['kode_pin']) ?></strong></td>
            </tr>
            <tr>
                <td class="text-muted fw-bold pe-4">Ketua / PIC</td>
                <td>: <?= esc($kt['nama_ketua']) ?: '-' ?></td>
            </tr>
            <tr>
                <td class="text-muted fw-bold pe-4">Alamat</td>
                <td>: <?= esc($kt['alamat_lengkap']) ?: '-' ?></td>
            </tr>
            <tr>
                <td class="text-muted fw-bold pe-4">Status</td>
                <td>: <?= $kt['status_aktif'] == 1 ? '<span class="badge bg-success">Aktif</span>' : '<span class="badge bg-danger">Nonaktif</span>' ?></td>
            </tr>
        </table>
    </div>
</div>
<?= $this->endSection() ?>
