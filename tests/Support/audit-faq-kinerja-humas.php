<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Read-only fingerprints: this script never modifies the database.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$db = DB::connection();
$db->beginTransaction();
try {
    if ($db->getDriverName() === 'pgsql') {
        $db->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');
    }
    $tables = ['dokumen_humas', 'riwayat_dokumen_humas', 'agenda_humas', 'program_kerja_humas', 'laporan_pelaksanaan_humas',
        'kerja_sama_humas', 'publikasi_humas', 'kontak_humas', 'pengaduan_humas', 'umpan_balik_humas', 'sasaran_umpan_balik_humas', 'pengguna_peran'];
    $data = [];
    foreach ($tables as $table) {
        $q = $db->table($table);
        if ($table === 'pengguna_peran') {
            $q->orderBy('pengguna_id')->orderBy('peran_id');
        } else {
            $q->orderBy('id');
        }
        $rows = $q->get();
        $data['existing'][$table] = ['count' => $rows->count(), 'sha256' => hash('sha256', $rows->toJson())];
    }
    foreach (['faq_humas', 'kinerja_semester_humas', 'riwayat_faq_humas', 'riwayat_kinerja_semester_humas'] as $table) {
        $data['new'][$table] = Schema::hasTable($table) ? $db->table($table)->count() : null;
    }
    $data['permissions'] = $db->table('peran_izin')->join('peran', 'peran.id', '=', 'peran_izin.peran_id')->join('izin', 'izin.id', '=', 'peran_izin.izin_id')
        ->whereIn('izin.kode', ['faq_humas.kelola', 'kinerja_humas.lihat', 'kinerja_humas.kelola'])->orderBy('izin.kode')->orderBy('peran.kode')
        ->get(['peran.kode as peran', 'izin.kode as izin']);
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
} finally {
    $db->rollBack();
}
