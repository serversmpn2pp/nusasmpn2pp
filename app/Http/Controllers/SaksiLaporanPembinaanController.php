<?php

namespace App\Http\Controllers;

use App\Models\LaporanPembinaanSiswa;
use App\Models\Pegawai;
use App\Models\SaksiLaporanPembinaanSiswa;
use App\Models\Siswa;
use App\Services\Pembinaan\AksesLaporanPembinaanService;
use App\Services\Pembinaan\CatatRiwayatPembinaanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SaksiLaporanPembinaanController extends Controller
{
    public function __construct(
        private AksesLaporanPembinaanService $akses,
        private CatatRiwayatPembinaanService $riwayat,
    ) {
    }

    public function store(Request $request, LaporanPembinaanSiswa $laporanPembinaanSiswa)
    {
        abort_unless($this->akses->bolehKelolaFakta($request->user(), $laporanPembinaanSiswa), 403);
        $data = $request->validate([
            'jenis_saksi' => ['required', Rule::in(array_keys(SaksiLaporanPembinaanSiswa::DAFTAR_JENIS))],
            'siswa_id' => ['nullable', 'integer', Rule::exists('siswa', 'id')],
            'pegawai_id' => ['nullable', 'integer', Rule::exists('pegawai', 'id')],
            'nama_saksi' => ['nullable', 'string', 'max:160'],
            'pernyataan' => ['required', 'string', 'max:5000'],
        ]);

        $data = $this->lengkapiIdentitas($data);
        DB::transaction(function () use ($request, $laporanPembinaanSiswa, $data) {
            $laporanPembinaanSiswa->saksiLaporanPembinaanSiswa()->create(array_merge($data, [
                'dibuat_oleh_pengguna_id' => $request->user()?->id,
            ]));
            $this->riwayat->catat(
                $laporanPembinaanSiswa,
                'saksi_ditambahkan',
                'Pernyataan saksi ditambahkan',
                $data['nama_saksi'] . ' dicatat sebagai saksi.',
                $laporanPembinaanSiswa->status_verifikasi,
                $laporanPembinaanSiswa->status_verifikasi,
                $request->user()?->id,
            );
        });

        return back()->with('berhasil', 'Pernyataan saksi berhasil ditambahkan.');
    }

    public function destroy(Request $request, SaksiLaporanPembinaanSiswa $saksiLaporanPembinaanSiswa)
    {
        $laporan = $saksiLaporanPembinaanSiswa->laporanPembinaanSiswa;
        abort_unless($this->akses->bolehKelolaFakta($request->user(), $laporan), 403);
        abort_unless($this->akses->bolehMenghapusCatatan($request->user(), $saksiLaporanPembinaanSiswa->dibuat_oleh_pengguna_id), 403);

        DB::transaction(function () use ($request, $laporan, $saksiLaporanPembinaanSiswa) {
            $nama = $saksiLaporanPembinaanSiswa->nama_saksi;
            $saksiLaporanPembinaanSiswa->delete();
            $this->riwayat->catat(
                $laporan,
                'saksi_dihapus',
                'Pernyataan saksi dihapus',
                'Catatan saksi ' . $nama . ' dihapus.',
                $laporan->status_verifikasi,
                $laporan->status_verifikasi,
                $request->user()?->id,
            );
        });

        return back()->with('berhasil', 'Pernyataan saksi berhasil dihapus.');
    }

    private function lengkapiIdentitas(array $data): array
    {
        $data['siswa_id'] = $data['jenis_saksi'] === 'siswa' ? ($data['siswa_id'] ?? null) : null;
        $data['pegawai_id'] = $data['jenis_saksi'] === 'pegawai' ? ($data['pegawai_id'] ?? null) : null;

        $nama = match ($data['jenis_saksi']) {
            'siswa' => filled($data['siswa_id']) ? Siswa::find($data['siswa_id'])?->nama_lengkap : null,
            'pegawai' => filled($data['pegawai_id']) ? Pegawai::find($data['pegawai_id'])?->nama_lengkap : null,
            default => $data['nama_saksi'] ?? null,
        };

        if (! filled($nama)) {
            throw ValidationException::withMessages(['nama_saksi' => 'Pilih identitas saksi atau tuliskan nama saksi.']);
        }

        $data['nama_saksi'] = trim($nama);
        $data['pernyataan'] = trim($data['pernyataan']);

        return $data;
    }
}
