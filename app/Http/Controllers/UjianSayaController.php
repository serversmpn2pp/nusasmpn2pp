<?php

namespace App\Http\Controllers;

use App\Models\Pengguna;
use App\Models\Siswa;
use App\Services\Cbt\DaftarUjianSiswaService;
use Illuminate\Http\Request;

class UjianSayaController extends Controller
{
    public function __construct(private readonly DaftarUjianSiswaService $daftarUjianSiswa) {}

    public function index(Request $request)
    {
        $siswa = $this->siswaDariPengguna($request->user());

        return view('ujian-saya.index', [
            'siswa' => $siswa,
            'tabUjian' => $request->query('tab') === 'riwayat' ? 'riwayat' : 'jadwal',
            ...$this->daftarUjianSiswa->siapkan($siswa),
        ]);
    }

    private function siswaDariPengguna(?Pengguna $pengguna): Siswa
    {
        abort_unless($pengguna?->akunSiswa(), 403);

        return $pengguna->siswa()->firstOrFail();
    }
}
