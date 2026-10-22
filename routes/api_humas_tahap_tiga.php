<?php

use App\Http\Controllers\Api\V1\Humas\TahapTigaController;
use Illuminate\Support\Facades\Route;

foreach (array_keys(TahapTigaController::MODUL) as $kind) {
    $add = function ($method, $path, $handler, $action = null) use ($kind) {
        $route = Route::match([$method], $kind.$path, [TahapTigaController::class, $handler])
            ->name($kind.'.'.($action ?? $handler))->defaults('jenis_mobile', $kind)
            ->where(['id' => '[0-9]{1,18}', 'bukti' => '[0-9]{1,18}']);
        if ($action) {
            $route->defaults('aksi_mobile', $action);
        }
        if ($method !== 'GET') {
            $route->middleware('throttle:30,1,humas-tahap-tiga:');
        }
    };
    $add('GET', '', 'index');
    $add('GET', '/referensi', 'referensi');
    if (in_array($kind, ['prestasi', 'alumni'])) {
        $add('GET', '/cetak', 'mutation', 'cetak');
        $add('GET', '/ekspor', 'mutation', 'export');
        $add('GET', '/pilihan', 'mutation', $kind === 'prestasi' ? 'pilihan' : 'siswa');
    }
    $add('POST', '', 'mutation', 'store');
    $add('GET', '/{id}', 'show');
    $add('PATCH', '/{id}', 'mutation', 'update');
    $add('GET', '/{id}/riwayat', 'riwayat');
    if (in_array($kind, ['aset', 'kliping', 'prestasi'])) {
        $add('GET', '/{id}/berkas/{bukti}', 'mutation', 'berkas');
    }
    if ($kind === 'aset') {
        $add('GET', '/{id}/pemakaian', 'pemakaian');
    }
}
