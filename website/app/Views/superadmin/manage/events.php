<?= $this->extend('superadmin/manage/layout') ?>

<?= $this->section('title') ?>
Manajemen Event
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <p class="text-muted mb-0">Kelola event dan absensi khusus untuk Karang Taruna ini.</p>
    <button class="btn btn-primary shadow-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#addEventModal">
        <i class="bi bi-plus-circle me-1"></i> Tambah Event
    </button>
</div>

<div class="card card-custom">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="py-3 px-4">No</th>
                        <th class="py-3">Nama Event</th>
                        <th class="py-3">Waktu</th>
                        <th class="py-3">Status</th>
                        <th class="py-3 text-end px-4">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($events)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-5 text-muted">
                            <i class="bi bi-calendar-x fs-1 d-block mb-3"></i>
                            Belum ada event yang dibuat
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php $no=1; foreach($events as $event): ?>
                        <tr>
                            <td class="px-4 fw-bold text-muted"><?= $no++ ?></td>
                            <td>
                                <div class="fw-bold fs-6"><?= esc($event['nama_event']) ?></div>
                                <small class="text-muted"><i class="bi bi-geo-alt me-1"></i><?= esc($event['lokasi']) ?></small>
                            </td>
                            <td>
                                <div><i class="bi bi-calendar3 me-1"></i><?= date('d M Y', strtotime($event['waktu_mulai'])) ?></div>
                                <small class="text-muted"><i class="bi bi-clock me-1"></i><?= date('H:i', strtotime($event['waktu_mulai'])) ?> - <?= date('H:i', strtotime($event['waktu_selesai'])) ?></small>
                            </td>
                            <td>
                                <?php if($event['status'] === 'berjalan'): ?>
                                    <span class="badge bg-success px-3 py-2 rounded-pill">Berjalan</span>
                                <?php elseif($event['status'] === 'selesai'): ?>
                                    <span class="badge bg-secondary px-3 py-2 rounded-pill">Selesai</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark px-3 py-2 rounded-pill"><?= esc($event['status']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end px-4">
                                <button class="btn btn-sm btn-outline-info me-1" title="Lihat Absensi"><i class="bi bi-list-check"></i> Absensi</button>
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

<!-- Modal Dummy for Add Event -->
<div class="modal fade" id="addEventModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <form action="#" method="post" class="w-100">
        <div class="modal-content border-0 shadow">
          <div class="modal-header bg-light border-0">
            <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2 text-primary"></i>Tambah Event Baru</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body p-4 text-center">
            <p class="text-muted">Fitur Create Event untuk Superadmin akan dihubungkan ke backend.</p>
          </div>
        </div>
    </form>
  </div>
</div>
<?= $this->endSection() ?>
