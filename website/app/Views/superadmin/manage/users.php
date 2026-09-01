<?= $this->extend('superadmin/manage/layout') ?>

<?= $this->section('title') ?>
Manajemen Pengguna
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="card card-custom">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="py-3 px-4">No</th>
                        <th class="py-3">Nama Lengkap</th>
                        <th class="py-3">Username</th>
                        <th class="py-3">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($users)): ?>
                    <tr>
                        <td colspan="4" class="text-center py-5 text-muted">
                            <i class="bi bi-people fs-1 d-block mb-3"></i>
                            Belum ada pengguna yang mendaftar di Karang Taruna ini
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php $no=1; foreach($users as $user): ?>
                        <tr>
                            <td class="px-4 fw-bold text-muted"><?= $no++ ?></td>
                            <td>
                                <div class="fw-bold fs-6"><?= esc($user['nama_lengkap']) ?></div>
                                <small class="text-muted"><i class="bi bi-telephone me-1"></i><?= esc($user['no_whatsapp']) ?></small>
                            </td>
                            <td>
                                <div class="bg-light px-2 py-1 rounded d-inline-block border">
                                    <strong><?= esc($user['username']) ?></strong>
                                </div>
                            </td>
                            <td>
                                <?php if($user['status_aktif'] == 1): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-pill"><i class="bi bi-check-circle-fill me-1"></i> Aktif</span>
                                <?php else: ?>
                                    <span class="badge bg-danger bg-opacity-10 text-danger px-3 py-2 rounded-pill"><i class="bi bi-x-circle-fill me-1"></i> Nonaktif</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
