<?php if (isset($pagination)): ?>
<nav class="d-flex justify-content-center align-items-center gap-3 p-3" aria-label="Halaman daftar">
    <?php if ($pagination['page'] > 1): ?>
    <a href="?page=<?= $pagination['page'] - 1 ?>&amp;limit=<?= $pagination['limit'] ?>">Sebelumnya</a>
    <?php endif ?>
    <span>Halaman <?= $pagination['page'] ?> / <?= max(1, $pagination['total_pages']) ?> (<?= $pagination['total'] ?> data)</span>
    <?php if ($pagination['has_more']): ?>
    <a href="?page=<?= $pagination['page'] + 1 ?>&amp;limit=<?= $pagination['limit'] ?>">Berikutnya</a>
    <?php endif ?>
</nav>
<?php endif ?>
