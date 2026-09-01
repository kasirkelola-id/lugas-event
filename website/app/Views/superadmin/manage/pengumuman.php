<?= $this->extend('superadmin/manage/layout') ?>

<?= $this->section('title') ?>
Manajemen Pengumuman
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <p class="text-muted mb-0">Kelola pengumuman untuk anggota Karang Taruna ini.</p>
    <button class="btn btn-primary shadow-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#addPengumumanModal">
        <i class="bi bi-plus-circle me-1"></i> Tambah Pengumuman
    </button>
</div>

<div class="card card-custom">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="py-3 px-4">No</th>
                        <th class="py-3">Judul Pengumuman</th>
                        <th class="py-3">Tanggal Dibuat</th>
                        <th class="py-3 text-end px-4">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($pengumuman)): ?>
                    <tr>
                        <td colspan="4" class="text-center py-5 text-muted">
                            <i class="bi bi-megaphone fs-1 d-block mb-3"></i>
                            Belum ada pengumuman yang dibuat
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php $no=1; foreach($pengumuman as $p): ?>
                        <tr>
                            <td class="px-4 fw-bold text-muted"><?= $no++ ?></td>
                            <td>
                                <div class="fw-bold fs-6"><?= esc($p['judul']) ?></div>
                                <small class="text-muted text-truncate d-inline-block" style="max-width: 300px;"><?= esc($p['isi']) ?></small>
                            </td>
                            <td>
                                <div><i class="bi bi-calendar3 me-1"></i><?= date('d M Y', strtotime($p['created_at'])) ?></div>
                            </td>
                            <td class="text-end px-4">
                                <a href="/superadmin/manage/<?= $kt['id'] ?>/pengumuman/delete/<?= $p['id'] ?>" class="btn btn-sm btn-outline-danger" title="Hapus" onclick="return confirm('Yakin ingin menghapus pengumuman ini?')"><i class="bi bi-trash"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal for Add Pengumuman -->
<div class="modal fade" id="addPengumumanModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <form action="/superadmin/manage/<?= $kt['id'] ?>/pengumuman/create" method="post" class="w-100">
        <?= csrf_field() ?>
        <div class="modal-content border-0 shadow">
          <div class="modal-header bg-light border-0">
            <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2 text-primary"></i>Tambah Pengumuman Baru</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body p-4">
            <div class="mb-3">
                <label class="form-label text-muted fw-bold small">Judul Pengumuman</label>
                <input type="text" name="judul" class="form-control bg-light border-0" required>
            </div>
            <div class="mb-3">
                <label class="form-label text-muted fw-bold small">Isi Pengumuman</label>
                <textarea name="isi" class="form-control bg-light border-0" rows="5" required></textarea>
            </div>
          </div>
          <div class="modal-footer border-0">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-primary px-4">Terbitkan Pengumuman</button>
          </div>
        </div>
    </form>
  </div>
</div>
<?= $this->endSection() ?>
