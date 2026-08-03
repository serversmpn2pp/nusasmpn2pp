<?php

namespace App\Http\Controllers;

use App\Models\LaporanPembinaanSiswa;
use App\Models\Pengguna;
use App\Models\VerifikasiBkPelanggaran;
use App\Services\Notifikasi\NotifikasiPenggunaService;
use App\Services\Pembinaan\AntreanVerifikasiPelanggaranService;
use App\Services\Pembinaan\CatatRiwayatPembinaanService;
use App\Services\Pembinaan\PengaturanBatasProsesPelanggaranService;
use App\Services\Pembinaan\ProsesPoinSiswaService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class VerifikasiPelanggaranSiswaController extends Controller
{
    public function __construct(
        private ProsesPoinSiswaService $prosesPoinSiswaService,
        private NotifikasiPenggunaService $notifikasiPenggunaService,
        private CatatRiwayatPembinaanService $riwayatPembinaan,
        private PengaturanBatasProsesPelanggaranService $pengaturanBatasProses,
    ) {}

    public function verifikasiBk(Request $request, LaporanPembinaanSiswa $laporanPembinaanSiswa)
    {
        abort_unless($request->user()?->memilikiIzin('poin_siswa.verifikasi_bk'), 403);
        abort_unless(in_array($laporanPembinaanSiswa->jenis_laporan, ['kejadian', 'pelanggaran'], true), 422);
        abort_unless(
            in_array($laporanPembinaanSiswa->status_verifikasi, AntreanVerifikasiPelanggaranService::STATUS_BK, true),
            422,
            'Keputusan BK hanya dapat diberikan pada laporan yang masih menunggu pemeriksaan.',
        );

        $data = $request->validate([
            'hasil' => ['required', Rule::in(array_keys(VerifikasiBkPelanggaran::DAFTAR_HASIL))],
            'jenis_pelanggaran_ids' => ['nullable', 'array', 'min:1'],
            'jenis_pelanggaran_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('jenis_pelanggaran_siswa', 'id')->where('aktif', true),
            ],
            'catatan' => ['nullable', 'string'],
        ]);
        if ($data['hasil'] === 'sanksi_poin' && empty($data['jenis_pelanggaran_ids'])) {
            $data['jenis_pelanggaran_ids'] = $laporanPembinaanSiswa->butirPelanggaranLaporan()
                ->pluck('jenis_pelanggaran_siswa_id')->all();
        }
        $statusSebelum = $laporanPembinaanSiswa->status_verifikasi;

        DB::transaction(function () use ($request, $laporanPembinaanSiswa, $data, $statusSebelum) {
            $laporanPembinaanSiswa->verifikasiBkPelanggaran()->create([
                'bk_pegawai_id' => $request->user()?->pegawai_id,
                'pengguna_id' => $request->user()?->id,
                'hasil' => $data['hasil'],
                'catatan' => filled($data['catatan'] ?? null) ? trim($data['catatan']) : null,
                'diverifikasi_pada' => now(),
            ]);

            match ($data['hasil']) {
                'sanksi_poin' => $this->tetapkanSanksiPoin($laporanPembinaanSiswa, $data['jenis_pelanggaran_ids'] ?? []),
                'pembinaan' => $this->prosesPoinSiswaService->tetapkanPembinaan($laporanPembinaanSiswa),
                'tidak_terbukti' => $this->tutupTanpaPoin($laporanPembinaanSiswa),
                default => $laporanPembinaanSiswa->update(['status_verifikasi' => 'perlu_klarifikasi']),
            };

            if ($data['hasil'] === 'perlu_klarifikasi') {
                $this->pengaturanBatasProses->tetapkanBatas($laporanPembinaanSiswa, 'perlu_klarifikasi');
            }

            $statusSesudah = $laporanPembinaanSiswa->fresh()->status_verifikasi;
            $this->riwayatPembinaan->catat(
                $laporanPembinaanSiswa,
                'keputusan_bk',
                'Keputusan penanganan oleh BK',
                VerifikasiBkPelanggaran::DAFTAR_HASIL[$data['hasil']].(filled($data['catatan'] ?? null) ? ': '.trim($data['catatan']) : '.'),
                $statusSebelum,
                $statusSesudah,
                $request->user()?->id,
                ['hasil' => $data['hasil']],
            );
        });

        $laporanPembinaanSiswa->refresh();
        $this->notifikasiHasilPemeriksaan($request, $laporanPembinaanSiswa, $data['hasil']);

        return back()->with('berhasil', match ($data['hasil']) {
            'sanksi_poin' => 'Keputusan BK disimpan. Poin siswa resmi ditetapkan.',
            'pembinaan' => 'Keputusan BK disimpan sebagai pembinaan tanpa poin.',
            'tidak_terbukti' => 'Laporan dinyatakan tidak terbukti dan tidak menambah poin.',
            default => 'Laporan dikembalikan untuk melengkapi klarifikasi.',
        });
    }

    private function tetapkanSanksiPoin(LaporanPembinaanSiswa $laporan, array $jenisPelanggaranIds): void
    {
        $this->prosesPoinSiswaService->siapkanSanksiPoin($laporan, $jenisPelanggaranIds);
        $this->prosesPoinSiswaService->sahkanLaporan($laporan->fresh());
    }

    private function tutupTanpaPoin(LaporanPembinaanSiswa $laporan): void
    {
        $laporan->butirPelanggaranLaporan()->delete();
        $laporan->update([
            'jenis_laporan' => 'kejadian',
            'kategori_pembinaan_siswa_id' => null,
            'tingkat' => 'ringan',
            'status_verifikasi' => 'tidak_terbukti',
            'total_poin' => 0,
            'tahap_batas_proses' => null,
            'batas_proses_pada' => null,
        ]);
    }

    private function notifikasiHasilPemeriksaan(Request $request, LaporanPembinaanSiswa $laporan, string $hasil): void
    {
        $laporan->loadMissing(['siswa', 'kelas']);
        $konfigurasi = match ($hasil) {
            'sanksi_poin' => ['berhasil', 'Sanksi poin ditetapkan oleh BK', sprintf('%d poin ditetapkan untuk %s.', $laporan->total_poin, $laporan->siswa?->nama_lengkap ?? 'siswa')],
            'pembinaan' => ['informasi', 'Pembinaan ditetapkan oleh BK', sprintf('%s akan ditangani melalui pembinaan tanpa poin.', $laporan->siswa?->nama_lengkap ?? 'Siswa')],
            'perlu_klarifikasi' => ['peringatan', 'Laporan memerlukan klarifikasi', sprintf('Laporan %s perlu dilengkapi sebelum BK memberikan keputusan.', $laporan->nomor_laporan)],
            default => ['informasi', 'Laporan dinyatakan tidak terbukti', sprintf('Laporan %s ditutup tanpa penambahan poin.', $laporan->nomor_laporan)],
        };

        $this->notifikasiPenggunaService->kirimKeBanyak(
            $this->penerimaAsalLaporan($laporan, $request->user()?->id),
            $konfigurasi[0],
            $konfigurasi[1],
            $konfigurasi[2],
            route('laporan-pembinaan-siswa.show', $laporan, false),
            "keputusan-bk:{$laporan->id}:{$hasil}",
        );
    }

    private function penerimaAsalLaporan(LaporanPembinaanSiswa $laporan, ?int $kecualiPenggunaId): Collection
    {
        $penerima = collect();
        if ($laporan->dibuat_oleh_pengguna_id) {
            $penerima->push($laporan->dibuat_oleh_pengguna_id);
        }
        if ($laporan->pelapor_pegawai_id) {
            $penerima = $penerima->merge($this->notifikasiPenggunaService->penggunaUntukPegawai((int) $laporan->pelapor_pegawai_id));
        }

        return $penerima
            ->map(fn ($pengguna) => is_numeric($pengguna) ? Pengguna::find((int) $pengguna) : $pengguna)
            ->filter(fn ($pengguna) => $pengguna && (int) $pengguna->id !== (int) $kecualiPenggunaId)
            ->unique('id')
            ->values();
    }
}
