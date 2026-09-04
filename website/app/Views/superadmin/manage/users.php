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
                        <th class="py-3">Jabatan</th>
                        <th class="py-3">Status</th>
                        <th class="py-3 text-end px-4">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($users)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
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
                                <span class="badge bg-secondary bg-opacity-10 text-secondary px-3 py-2 rounded-pill border">
                                    <i class="bi bi-person-badge me-1"></i> <?= strtoupper(esc($user['role_level'])) ?>
                                </span>
                            </td>
                            <td>
                                <?php if($user['status_aktif'] == 1): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-pill"><i class="bi bi-check-circle-fill me-1"></i> Aktif</span>
                                <?php else: ?>
                                    <span class="badge bg-danger bg-opacity-10 text-danger px-3 py-2 rounded-pill"><i class="bi bi-x-circle-fill me-1"></i> Nonaktif</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end px-4">
                                <a href="/superadmin/manage/<?= $kt['id'] ?>/users/<?= $user['id'] ?>/status" class="btn btn-sm <?= $user['status_aktif'] == 1 ? 'btn-outline-danger' : 'btn-outline-success' ?> me-1" onclick="return confirm('Yakin ingin <?= $user['status_aktif'] == 1 ? 'menonaktifkan' : 'mengaktifkan' ?> pengguna ini?');" title="<?= $user['status_aktif'] == 1 ? 'Nonaktifkan' : 'Aktifkan' ?>">
                                    <i class="bi <?= $user['status_aktif'] == 1 ? 'bi-person-x' : 'bi-person-check' ?>"></i> 
                                </a>
                                <a href="/superadmin/manage/<?= $kt['id'] ?>/users/<?= $user['id'] ?>/reset-password" class="btn btn-sm btn-outline-warning me-1" onclick="return confirm('Yakin ingin mereset password pengguna ini? Pengguna akan diminta mengubah password pada saat login berikutnya.');" title="Reset Password">
                                    <i class="bi bi-key"></i> 
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#roleModal<?= $user['id'] ?>">
                                    <i class="bi bi-pencil-square"></i> Ubah Role
                                </button>

                                <!-- Modal Ubah Role -->
                                <div class="modal fade text-start" id="roleModal<?= $user['id'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Ubah Role Pengguna</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <form action="/superadmin/manage/<?= $kt['id'] ?>/users/<?= $user['id'] ?>/role" method="post">
                                                <div class="modal-body">
                                                    <p>Pilih jabatan baru untuk <strong><?= esc($user['nama_lengkap']) ?></strong>:</p>
                                                    <select class="form-select" name="role_level" required>
                                                        <option value="ketua" <?= $user['role_level'] == 'ketua' ? 'selected' : '' ?>>Ketua</option>
                                                        <option value="sekretaris" <?= $user['role_level'] == 'sekretaris' ? 'selected' : '' ?>>Sekretaris</option>
                                                        <option value="bendahara" <?= $user['role_level'] == 'bendahara' ? 'selected' : '' ?>>Bendahara</option>
                                                        <option value="pengelola" <?= $user['role_level'] == 'pengelola' ? 'selected' : '' ?>>Pengelola</option>
                                                        <option value="anggota" <?= $user['role_level'] == 'anggota' ? 'selected' : '' ?>>Anggota</option>
                                                    </select>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                                                    <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
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
