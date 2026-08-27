<?php
$file = 'website/app/Controllers/Api/EventController.php';
$content = file_get_contents($file);

$search = "/'status_aktif'(.*?)\? 1 : 0,\s*'created_at'/s";
$replace = "'status_aktif'$1? 1 : 0,\n                'jumlah_hadir' => (new \App\Models\AbsensiModel())->where('event_id', \$event['id'])->countAllResults(),\n                'created_at'";

$content = preg_replace($search, $replace, $content);
file_put_contents($file, $content);
