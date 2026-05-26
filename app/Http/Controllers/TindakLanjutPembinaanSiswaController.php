<?php

namespace App\Http\Controllers;

use App\Models\LaporanPembinaanSiswa;
use App\Models\Pegawai;
use App\Models\TindakLanjutPembinaanSiswa;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TindakLanjutPembinaanSiswaController extends Controller
{
    public function create(LaporanPembinaanSiswa $laporanPembinaanSiswa)
    {
        if ($laporanPembinaanSiswa->status === 'dibatalkan') {
            return redirect()
                ->route('laporan-pembinaan-siswa.show', $laporanPembinaanSiswa)
                ->with('gagal', 'Laporan yang dibatalkan tidak dapat diberi tindak lanjut.');
        }

        $tindakLanjutPembinaanSiswa = new TindakLanjutPembinaanSiswa([
            'tanggal_tindak_lanjut' => now()->toDateString(),
            'jenis_tindak_lanjut' => 'konseling_siswa',
            'status_laporan' => $this->statusAwal($laporanPembinaanSiswa),
            'petugas_pegawai_id' => auth()->user()?->pegawai_id,
        ]);

        return view('tindak-lanjut-pembinaan-siswa.create', array_merge(
            compact('laporanPembinaanSiswa', 'tindakLanjutPembinaanSiswa'),
            $this->pilihanForm(),
        ));
    }

    public function store(Request $request, LaporanPembinaanSiswa $laporanPembinaanSiswa)
    {
        if ($laporanPembinaanSiswa->status === 'dibatalkan') {
            return redirect()
                ->route('laporan-pembinaan-siswa.show', $laporanPembinaanSiswa)
                ->with('gagal', 'Laporan yang dibatalkan tidak dapat diberi tindak lanjut.');
        }

        $data = $this->rapikanData($request->validate($this->aturanValidasi()));
        $data['dibuat_oleh_pengguna_id'] = auth()->id();

        $laporanPembinaanSiswa->tindakLanjutPembinaanSiswa()->create($data);
        $laporanPembinaanSiswa->update(['status' => $data['status_laporan']]);

        return redirect()
            ->route('laporan-pembinaan-siswa.show', $laporanPembinaanSiswa)
            ->with('berhasil', 'Tindak lanjut pembinaan berhasil ditambahkan.');
    }

    public function edit(TindakLanjutPembinaanSiswa $tindakLanjutPembinaanSiswa)
    {
        $tindakLanjutPembinaanSiswa->load('laporanPembinaanSiswa.siswa', 'laporanPembinaanSiswa.kelas');
        $laporanPembinaanSiswa = $tindakLanjutPembinaanSiswa->laporanPembinaanSiswa;

        return view('tindak-lanjut-pembinaan-siswa.edit', array_merge(
            compact('laporanPembinaanSiswa', 'tindakLanjutPembinaanSiswa'),
            $this->pilihanForm(),
        ));
    }

    public function update(Request $request, TindakLanjutPembinaanSiswa $tindakLanjutPembinaanSiswa)
    {
        $data = $this->rapikanData($request->validate($this->aturanValidasi()));

        $tindakLanjutPembinaanSiswa->update($data);
        $tindakLanjutPembinaanSiswa->laporanPembinaanSiswa->update(['status' => $data['status_laporan']]);

        return redirect()
            ->route('laporan-pembinaan-siswa.show', $tindakLanjutPembinaanSiswa->laporanPembinaanSiswa)
            ->with('berhasil', 'Tindak lanjut pembinaan berhasil diperbarui.');
    }

    public function destroy(TindakLanjutPembinaanSiswa $tindakLanjutPembinaanSiswa)
    {
        $laporanPembinaanSiswa = $tindakLanjutPembinaanSiswa->laporanPembinaanSiswa;

        $tindakLanjutPembinaanSiswa->delete();
        $this->sinkronkanStatusLaporan($laporanPembinaanSiswa);

        return redirect()
            ->route('laporan-pembinaan-siswa.show', $laporanPembinaanSiswa)
            ->with('berhasil', 'Tindak lanjut pembinaan berhasil dihapus.');
    }

    private function aturanValidasi(): array
    {
        return [
            'tanggal_tindak_lanjut' => ['required', 'date'],
            'waktu_tindak_lanjut' => ['nullable', 'date_format:H:i'],
            'jenis_tindak_lanjut' => ['required', Rule::in(array_keys(TindakLanjutPembinaanSiswa::DAFTAR_JENIS))],
            'petugas_pegawai_id' => ['nullable', 'integer', Rule::exists('pegawai', 'id')],
            'pihak_terlibat' => ['nullable', 'string', 'max:180'],
            'ringkasan' => ['required', 'string'],
            'hasil' => ['nullable', 'string'],
            'rencana_lanjutan' => ['nullable', 'string'],
            'status_laporan' => ['required', Rule::in(array_keys(TindakLanjutPembinaanSiswa::DAFTAR_STATUS_LAPORAN))],
            'catatan_rahasia' => ['nullable', 'string'],
        ];
    }

    private function rapikanData(array $data): array
    {
        foreach (['waktu_tindak_lanjut', 'petugas_pegawai_id', 'pihak_terlibat', 'hasil', 'rencana_lanjutan', 'catatan_rahasia'] as $field) {
            $data[$field] = filled($data[$field] ?? null) ? $data[$field] : null;
        }

        foreach (['pihak_terlibat', 'ringkasan', 'hasil', 'rencana_lanjutan', 'catatan_rahasia'] as $field) {
            $data[$field] = filled($data[$field] ?? null) ? trim($data[$field]) : null;
        }

        return $data;
    }

    private function statusAwal(LaporanPembinaanSiswa $laporanPembinaanSiswa): string
    {
        return array_key_exists($laporanPembinaanSiswa->status, TindakLanjutPembinaanSiswa::DAFTAR_STATUS_LAPORAN)
            ? $laporanPembinaanSiswa->status
            : 'diproses';
    }

    private function sinkronkanStatusLaporan(LaporanPembinaanSiswa $laporanPembinaanSiswa): void
    {
        if ($laporanPembinaanSiswa->status === 'dibatalkan') {
            return;
        }

        $tindakLanjutTerakhir = $laporanPembinaanSiswa->tindakLanjutPembinaanSiswa()
            ->orderByDesc('tanggal_tindak_lanjut')
            ->orderByDesc('waktu_tindak_lanjut')
            ->orderByDesc('id')
            ->first();

        $laporanPembinaanSiswa->update([
            'status' => $tindakLanjutTerakhir?->status_laporan ?? 'baru',
        ]);
    }

    private function pilihanForm(): array
    {
        return [
            'daftarJenisTindakLanjut' => TindakLanjutPembinaanSiswa::DAFTAR_JENIS,
            'daftarStatusLaporan' => TindakLanjutPembinaanSiswa::DAFTAR_STATUS_LAPORAN,
            'daftarPegawai' => Pegawai::where('aktif', true)
                ->orderBy('nama_lengkap')
                ->get(['id', 'nama_lengkap', 'nip', 'jabatan_utama']),
        ];
    }
}
