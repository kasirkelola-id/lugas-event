<?= $this->extend('superadmin/manage/layout') ?>

<?= $this->section('title') ?>
Keuangan (Kas)
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card card-custom bg-primary text-white border-0 h-100 p-4">
            <h6 class="text-white-50 text-uppercase fw-bold mb-2">Total Saldo Kas</h6>
            <h2 class="fw-bold mb-0">Rp <?= number_format($saldo_kas, 0, ',', '.') ?></h2>
        </div>
    </div>
    <div class="col-md-6 d-flex align-items-center justify-content-end">
        <button class="btn btn-success shadow-sm rounded-pill px-4 py-2 me-2" data-bs-toggle="modal" data-bs-target="#addKasMasukModal">
            <i class="bi bi-arrow-down-circle me-1"></i> Kas Masuk
        </button>
        <button class="btn btn-danger shadow-sm rounded-pill px-4 py-2" data-bs-toggle="modal" data-bs-target="#addKasKeluarModal">
            <i class="bi bi-arrow-up-circle me-1"></i> Kas Keluar
        </button>
    </div>
</div>

<div class="card card-custom">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="py-3 px-4">No</th>
                        <th class="py-3">Tanggal</th>
                        <th class="py-3">Keterangan</th>
                        <th class="py-3">Nominal</th>
                        <th class="py-3 text-end px-4">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($kas)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-5 text-muted">
                            <i class="bi bi-wallet2 fs-1 d-block mb-3"></i>
                            Belum ada riwayat transaksi kas
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php $no=1; foreach($kas as $k): ?>
                        <tr>
                            <td class="px-4 fw-bold text-muted"><?= $no++ ?></td>
                            <td><i class="bi bi-calendar3 me-1 text-muted"></i> <?= date('d M Y', strtotime($k['tanggal'])) ?></td>
                            <td><?= esc($k['keterangan']) ?></td>
                            <td>
                                <?php if($k['jenis_kas'] === 'masuk'): ?>
                                    <span class="text-success fw-bold">+ Rp <?= number_format($k['nominal'], 0, ',', '.') ?></span>
                                <?php else: ?>
                                    <span class="text-danger fw-bold">- Rp <?= number_format($k['nominal'], 0, ',', '.') ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end px-4">
                                <button class="btn btn-sm btn-outline-danger" title="Hapus"><i class="bi bi-trash"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Dummy for Kas Masuk -->
<div class="modal fade" id="addKasMasukModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-light border-0">
        <h5 class="modal-title fw-bold"><i class="bi bi-arrow-down-circle me-2 text-success"></i>Tambah Kas Masuk</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4 text-center">
        <p class="text-muted">Fitur Input Kas Masuk akan dihubungkan ke backend.</p>
      </div>
    </div>
  </div>
</div>

<!-- Modal Dummy for Kas Keluar -->
<div class="modal fade" id="addKasKeluarModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-light border-0">
        <h5 class="modal-title fw-bold"><i class="bi bi-arrow-up-circle me-2 text-danger"></i>Tambah Kas Keluar</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4 text-center">
        <p class="text-muted">Fitur Input Kas Keluar akan dihubungkan ke backend.</p>
      </div>
    </div>
  </div>
</div>
<?= $this->endSection() ?>
