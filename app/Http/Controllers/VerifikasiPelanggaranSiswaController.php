<?php

namespace App\Http\Controllers;

use App\Models\LaporanPembinaanSiswa;
use App\Models\PersetujuanPelanggaran;
use App\Models\VerifikasiBkPelanggaran;
use App\Services\Notifikasi\NotifikasiPenggunaService;
use App\Services\Pembinaan\ProsesPoinSiswaService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VerifikasiPelanggaranSiswaController extends Controller
{
    public function __construct(
        private ProsesPoinSiswaService $prosesPoinSiswaService,
        private NotifikasiPenggunaService $notifikasiPenggunaService,
    ) {
    }

    public function verifikasiBk(Request $request, LaporanPembinaanSiswa $laporanPembinaanSiswa)
    {
        abort_unless($request->user()?->memilikiIzin('poin_siswa.verifikasi_bk'), 403);
        abort_unless($laporanPembinaanSiswa->jenis_laporan === 'pelanggaran', 422);
        abort_if(in_array($laporanPembinaanSiswa->status_verifikasi, ['disahkan', 'dibatalkan'], true), 422, 'Laporan ini sudah ditutup.');

        $data = $request->validate([
            'hasil' => ['required', Rule::in(array_keys(VerifikasiBkPelanggaran::DAFTAR_HASIL))],
            'catatan' => ['nullable', 'string'],
        ]);

        $laporanPembinaanSiswa->verifikasiBkPelanggaran()->create([
            'bk_pegawai_id' => $request->user()?->pegawai_id,
            'pengguna_id' => $request->user()?->id,
            'hasil' => $data['hasil'],
            'catatan' => filled($data['catatan'] ?? null) ? trim($data['catatan']) : null,
            'diverifikasi_pada' => now(),
        ]);

        $status = match ($data['hasil']) {
            'terbukti' => 'menunggu_persetujuan',
            'tidak_terbukti' => 'tidak_terbukti',
            default => 'perlu_klarifikasi',
        };
        $laporanPembinaanSiswa->update(['status_verifikasi' => $status]);

        if ($data['hasil'] === 'terbukti') {
            $this->notifikasiPemberiPersetujuan($request, $laporanPembinaanSiswa);
        }

        return back()->with('berhasil', 'Hasil pemeriksaan BK berhasil disimpan.');
    }

    public function persetujuan(Request $request, LaporanPembinaanSiswa $laporanPembinaanSiswa)
    {
        abort_unless($request->user()?->memilikiIzin(['poin_siswa.menyetujui', 'poin_siswa.putus_konflik']), 403);
        abort_unless($laporanPembinaanSiswa->jenis_laporan === 'pelanggaran', 422);
        abort_if(in_array($laporanPembinaanSiswa->status_verifikasi, ['disahkan', 'tidak_terbukti', 'dibatalkan'], true), 422, 'Laporan ini sudah diputuskan.');

        $data = $request->validate([
            'jenis_persetujuan' => ['required', Rule::in(array_keys(PersetujuanPelanggaran::DAFTAR_JENIS))],
            'keputusan' => ['required', Rule::in(array_keys(PersetujuanPelanggaran::DAFTAR_KEPUTUSAN))],
            'catatan' => ['nullable', 'string'],
        ]);

        $this->pastikanPemberiPersetujuanSesuai($request, $laporanPembinaanSiswa, $data['jenis_persetujuan']);

        $laporanPembinaanSiswa->persetujuanPelanggaran()->updateOrCreate(
            ['jenis_persetujuan' => $data['jenis_persetujuan']],
            [
                'pegawai_id' => $request->user()?->pegawai_id,
                'pengguna_id' => $request->user()?->id,
                'keputusan' => $data['keputusan'],
                'catatan' => filled($data['catatan'] ?? null) ? trim($data['catatan']) : null,
                'diputuskan_pada' => now(),
            ],
        );

        $disahkan = $this->prosesPoinSiswaService->perbaruiStatusPersetujuan($laporanPembinaanSiswa->fresh());

        return back()->with('berhasil', $disahkan
            ? 'Dua orang berbeda telah menyetujui. Poin siswa resmi ditetapkan.'
            : 'Keputusan berhasil disimpan. Laporan masih menunggu keputusan berikutnya.');
    }

    private function pastikanPemberiPersetujuanSesuai(Request $request, LaporanPembinaanSiswa $laporan, string $jenis): void
    {
        $pegawaiId = (int) ($request->user()?->pegawai_id ?? 0);

        $boleh = match ($jenis) {
            'wali_kelas' => $pegawaiId > 0 && $pegawaiId === (int) $laporan->wali_kelas_pegawai_id,
            'guru_wali' => $pegawaiId > 0 && $pegawaiId === (int) $laporan->guru_wali_pegawai_id,
            'wakil_kesiswaan' => $request->user()?->memilikiIzin('poin_siswa.putus_konflik') ?? false,
            default => false,
        };

        abort_unless($boleh || $request->user()?->administrator(), 403);
    }

    private function notifikasiPemberiPersetujuan(Request $request, LaporanPembinaanSiswa $laporan): void
    {
        $laporan->loadMissing(['siswa', 'kelas']);
        $penerima = collect();

        foreach (array_filter([$laporan->wali_kelas_pegawai_id, $laporan->guru_wali_pegawai_id]) as $pegawaiId) {
            $penerima = $penerima->merge($this->notifikasiPenggunaService->penggunaUntukPegawai((int) $pegawaiId));
        }

        if (! $laporan->wali_kelas_pegawai_id || ! $laporan->guru_wali_pegawai_id || $laporan->wali_kelas_pegawai_id === $laporan->guru_wali_pegawai_id) {
            $penerima = $penerima->merge($this->notifikasiPenggunaService->penggunaDenganPeran(['wakil_pimpinan_kesiswaan'], $request->user()?->id));
        }

        $this->notifikasiPenggunaService->kirimKeBanyak(
            $penerima->unique('id')->values(),
            'peringatan',
            'Persetujuan pelanggaran diperlukan',
            sprintf('%s dari %s menunggu persetujuan penetapan %d poin.', $laporan->siswa?->nama_lengkap ?? 'Siswa', $laporan->kelas?->nama ?? 'kelas belum ditentukan', $laporan->total_poin),
            route('laporan-pembinaan-siswa.show', $laporan, false),
            "persetujuan-pelanggaran:{$laporan->id}",
        );
    }
}
