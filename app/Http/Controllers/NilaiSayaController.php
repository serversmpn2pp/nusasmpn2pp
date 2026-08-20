<?php

namespace App\Http\Controllers;

use App\Models\Pengguna;
use App\Models\Siswa;
use App\Services\Nilai\RingkasanNilaiSiswaService;
use Illuminate\Http\Request;

class NilaiSayaController extends Controller
{
    public function __construct(private RingkasanNilaiSiswaService $ringkasanNilai) {}

    public function index(Request $request)
    {
        $siswa = $this->siswaDariPengguna($request->user());

        return view('nilai-saya.index', $this->ringkasanNilai->siapkan(
            $siswa,
            $request->integer('tahun_pelajaran_id') ?: null,
            $request->input('semester'),
        ));
    }

    private function siswaDariPengguna(?Pengguna $pengguna): ?Siswa
    {
        abort_unless($pengguna?->akunSiswa() || $pengguna?->memilikiPeran('siswa'), 403);

        return $pengguna->siswa()->first();
    }
}
