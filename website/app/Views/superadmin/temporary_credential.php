<!doctype html>
<html lang="id"><head><meta charset="utf-8"><title>Password sementara</title></head>
<body>
    <h1>Password berhasil direset</h1>
    <p>Berikan kredensial ini kepada pengguna. Password hanya ditampilkan pada respons ini.</p>
    <p>Username: <strong><?= esc($username) ?></strong></p>
    <p>Password sementara: <strong><?= esc($temporary_password) ?></strong></p>
    <p>Pengguna wajib mengganti password sebelum menggunakan aplikasi.</p>
    <a href="<?= esc($return_url, 'attr') ?>">Kembali ke pengguna</a>
</body></html>
