<?php

use App\Http\Controllers\Api\V1\Humas\TahapDuaController;
use Illuminate\Support\Facades\Route;

$tambah = function (string $method, string $path, string $name, string $kind, string $action, ?string $mutation = null) {
    $r = Route::match([$method], $path, [TahapDuaController::class, $action])->name($name)->defaults('jenis_mobile', $kind)
        ->where(array_fill_keys(['id', 'anak', 'bukti', 'agenda'], '[0-9]{1,18}'));
    if ($mutation) {
        $r->defaults('aksi_mobile', $mutation);
    }
    if ($method !== 'GET') {
        $r->middleware('throttle:30,1,humas-tahap-dua:');
    }

    return $r;
};
foreach ([
    ['program', 'program', 'program'], ['komite', 'komite', 'komite'], ['mitra', 'mitra', 'mitra'], ['akreditasi', 'akreditasi', 'akreditasi'],
    ['program/{id}/laporan', 'program.laporan', 'laporan'], ['komite/{id}/program', 'komite.program', 'program-komite'],
    ['mitra/{id}/mou', 'mitra.mou', 'mou'], ['akreditasi/{id}/butir', 'akreditasi.butir', 'butir'],
] as [$base, $name, $kind]) {
    $id = str_contains($base, '{id}') ? '{anak}' : '{id}';
    $tambah('GET', $base, $name.'.index', $kind, 'index');
    $tambah('GET', $base.'/referensi', $name.'.referensi', $kind, 'referensi');
    $tambah('POST', $base, $name.'.store', $kind, 'mutation', 'store');
    $tambah('GET', $base.'/'.$id, $name.'.show', $kind, 'show');
    $tambah('PATCH', $base.'/'.$id, $name.'.update', $kind, 'mutation', 'update');
    if ($kind !== 'butir') {
        $tambah('GET', $base.'/'.$id.'/riwayat', $name.'.riwayat', $kind, 'riwayat');
    }
    if (in_array($kind, ['program', 'laporan', 'akreditasi'])) {
        $tambah('GET', $base.'/'.$id.'/cetak', $name.'.cetak', $kind, 'mutation', 'cetak');
    }
    if ($kind === 'komite') {
        $tambah('GET', $base.'/'.$id.'/berkas/{bukti}', $name.'.berkas', $kind, 'mutation', 'berkas');
    }
    if ($kind === 'akreditasi') {
        $tambah('POST', $base.'/'.$id.'/status', $name.'.status', $kind, 'mutation', 'status');
        $tambah('POST', $base.'/'.$id.'/bundel', $name.'.bundel', $kind, 'mutation', 'export');
    }
    if ($kind === 'laporan') {
        $tambah('POST', $base.'/'.$id.'/tindakan/{aksi}', $name.'.tindakan', $kind, 'mutation', 'tindakan')->whereIn('aksi', ['finalisasi', 'revisi', 'batalkan']);
    }
    if (in_array($kind, ['laporan', 'butir'])) {
        $tambah('POST', $base.'/'.$id.'/bukti', $name.'.bukti.store', $kind, 'mutation', 'tambahBukti');
        $tambah('DELETE', $base.'/'.$id.'/bukti/{bukti}', $name.'.bukti.destroy', $kind, 'mutation', 'lepasBukti');
        $tambah('GET', $base.'/'.$id.'/bukti/{bukti}', $name.'.bukti.berkas', $kind, 'mutation', 'berkas');
    }
    if ($kind === 'butir') {
        $tambah('DELETE', $base.'/'.$id, $name.'.destroy', $kind, 'mutation', 'destroy');
        $tambah('POST', $base.'/'.$id.'/periksa', $name.'.periksa', $kind, 'mutation', 'periksa');
    }
    if (in_array($kind, ['mitra', 'program-komite'])) {
        $segment = $kind === 'mitra' ? 'agenda' : 'rapat';
        $tambah('GET', $base.'/'.$id.'/'.$segment, $name.'.agenda.index', $kind, 'kegiatan');
        $tambah('POST', $base.'/'.$id.'/'.$segment, $name.'.agenda.store', $kind, 'mutation', $kind === 'mitra' ? 'hubungkanAgenda' : 'hubungkan');
        $tambah('DELETE', $base.'/'.$id.'/'.$segment.'/{agenda}', $name.'.agenda.destroy', $kind, 'mutation', $kind === 'mitra' ? 'lepasAgenda' : 'lepas');
    }
}
