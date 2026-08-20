<?php

namespace App\Http\Controllers;

use App\Models\LaporanPembinaanSiswa;
use App\Models\Pengguna;
use App\Models\PersetujuanPelanggaran;
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
                'sanksi_poin' => $this->rekomendasikanSanksiPoin($laporanPembinaanSiswa, $data['jenis_pelanggaran_ids'] ?? []),
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
            'sanksi_poin' => 'Rekomendasi poin dari BK disimpan dan dikirim kepada Wakil Kesiswaan untuk disahkan.',
            'pembinaan' => 'Keputusan BK disimpan sebagai pembinaan tanpa poin.',
            'tidak_terbukti' => 'Laporan dinyatakan tidak terbukti dan tidak menambah poin.',
            default => 'Laporan dikembalikan untuk melengkapi klarifikasi.',
        });
    }

    public function pengesahanWakil(Request $request, LaporanPembinaanSiswa $laporanPembinaanSiswa)
    {
        abort_unless($request->user()?->memilikiIzin('poin_siswa.sahkan_wakil'), 403);
        abort_unless(
            in_array($laporanPembinaanSiswa->status_verifikasi, AntreanVerifikasiPelanggaranService::STATUS_WAKIL, true),
            422,
            'Laporan ini tidak sedang menunggu pengesahan Wakil Kesiswaan.',
        );

        $data = $request->validate([
            'keputusan' => ['required', Rule::in(['sahkan', 'kembalikan'])],
            'catatan' => ['nullable', 'string', 'required_if:keputusan,kembalikan'],
        ]);
        $statusSebelum = $laporanPembinaanSiswa->status_verifikasi;

        DB::transaction(function () use ($request, $laporanPembinaanSiswa, $data, $statusSebelum) {
            PersetujuanPelanggaran::updateOrCreate(
                [
                    'laporan_pembinaan_siswa_id' => $laporanPembinaanSiswa->id,
                    'jenis_persetujuan' => 'wakil_kesiswaan',
                ],
                [
                    'pegawai_id' => $request->user()?->pegawai_id,
                    'pengguna_id' => $request->user()?->id,
                    'keputusan' => $data['keputusan'] === 'sahkan' ? 'setuju' : 'tidak_setuju',
                    'catatan' => filled($data['catatan'] ?? null) ? trim($data['catatan']) : null,
                    'diputuskan_pada' => now(),
                ],
            );

            if ($data['keputusan'] === 'sahkan') {
                $this->prosesPoinSiswaService->sahkanLaporan($laporanPembinaanSiswa);
            } else {
                $laporanPembinaanSiswa->update([
                    'status_verifikasi' => 'dikembalikan_bk',
                    'poin_ditetapkan_pada' => null,
                ]);
                $this->pengaturanBatasProses->tetapkanBatas($laporanPembinaanSiswa, 'dikembalikan_bk');
            }

            $statusSesudah = $laporanPembinaanSiswa->fresh()->status_verifikasi;
            $this->riwayatPembinaan->catat(
                $laporanPembinaanSiswa,
                $data['keputusan'] === 'sahkan' ? 'poin_disahkan_wakil' : 'dikembalikan_wakil',
                $data['keputusan'] === 'sahkan'
                    ? 'Pelanggaran berpoin disahkan Wakil Kesiswaan'
                    : 'Rekomendasi dikembalikan kepada BK',
                filled($data['catatan'] ?? null)
                    ? trim($data['catatan'])
                    : 'Rekomendasi BK telah diperiksa oleh Wakil Kesiswaan.',
                $statusSebelum,
                $statusSesudah,
                $request->user()?->id,
                ['keputusan' => $data['keputusan']],
            );
        });

        $laporanPembinaanSiswa->refresh();
        $this->notifikasiPengesahanWakil($request, $laporanPembinaanSiswa, $data['keputusan']);

        return back()->with('berhasil', $data['keputusan'] === 'sahkan'
            ? 'Pelanggaran berpoin berhasil disahkan. Poin siswa sekarang resmi tercatat.'
            : 'Rekomendasi dikembalikan kepada BK untuk diperiksa kembali.');
    }

    private function rekomendasikanSanksiPoin(LaporanPembinaanSiswa $laporan, array $jenisPelanggaranIds): void
    {
        $this->prosesPoinSiswaService->siapkanSanksiPoin($laporan, $jenisPelanggaranIds);
        $laporan->refresh()->update([
            'status_verifikasi' => 'menunggu_pengesahan_wakil',
            'poin_ditetapkan_pada' => null,
        ]);
        $this->pengaturanBatasProses->tetapkanBatas($laporan, 'menunggu_pengesahan_wakil');
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
            'sanksi_poin' => ['informasi', 'Rekomendasi poin dibuat oleh BK', sprintf('%d poin direkomendasikan untuk %s dan menunggu pengesahan Wakil Kesiswaan.', $laporan->total_poin, $laporan->siswa?->nama_lengkap ?? 'siswa')],
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

        if ($hasil === 'sanksi_poin') {
            $this->notifikasiPenggunaService->kirimKeBanyak(
                $this->notifikasiPenggunaService->penggunaDenganIzin(
                    'poin_siswa.sahkan_wakil',
                    $request->user()?->id,
                ),
                'peringatan',
                'Pelanggaran berpoin menunggu pengesahan',
                sprintf(
                    '%s merekomendasikan %d poin untuk %s. Periksa dan sahkan atau kembalikan kepada BK.',
                    $laporan->nomor_laporan,
                    $laporan->total_poin,
                    $laporan->siswa?->nama_lengkap ?? 'siswa',
                ),
                route('laporan-pembinaan-siswa.show', $laporan, false),
                "pengesahan-wakil-menunggu:{$laporan->id}:{$laporan->updated_at?->timestamp}",
            );
        }

        $this->notifikasiPerkembanganKasusUntukSiswa($laporan);
    }

    private function notifikasiPengesahanWakil(
        Request $request,
        LaporanPembinaanSiswa $laporan,
        string $keputusan,
    ): void {
        $laporan->loadMissing('siswa:id,nama_lengkap');
        $penerima = $this->notifikasiPenggunaService
            ->penggunaDenganIzin('poin_siswa.verifikasi_bk', $request->user()?->id)
            ->merge($this->penerimaAsalLaporan($laporan, $request->user()?->id))
            ->unique('id')
            ->values();

        $disahkan = $keputusan === 'sahkan';
        $this->notifikasiPenggunaService->kirimKeBanyak(
            $penerima,
            $disahkan ? 'berhasil' : 'peringatan',
            $disahkan ? 'Pelanggaran berpoin telah disahkan' : 'Rekomendasi poin dikembalikan kepada BK',
            $disahkan
                ? sprintf('%d poin untuk %s telah disahkan oleh Wakil Kesiswaan.', $laporan->total_poin, $laporan->siswa?->nama_lengkap ?? 'siswa')
                : sprintf('Laporan %s perlu diperiksa kembali sesuai catatan Wakil Kesiswaan.', $laporan->nomor_laporan),
            route('laporan-pembinaan-siswa.show', $laporan, false),
            "keputusan-wakil:{$laporan->id}:{$keputusan}:{$laporan->updated_at?->timestamp}",
        );

        if ($disahkan) {
            $this->notifikasiPerkembanganKasusUntukSiswa($laporan);
        }
    }

    private function notifikasiPerkembanganKasusUntukSiswa(LaporanPembinaanSiswa $laporan): void
    {
        $laporan->loadMissing('siswa:id,nama_lengkap');
        $konfigurasi = match ($laporan->status_verifikasi) {
            'ditetapkan_pembinaan' => [
                'informasi',
                'Pembinaan telah ditetapkan',
                sprintf('BK telah menetapkan laporan %s sebagai pembinaan tanpa poin.', $laporan->nomor_laporan),
            ],
            'tidak_terbukti' => [
                'berhasil',
                'Pemeriksaan laporan selesai',
                sprintf('Pemeriksaan laporan %s telah selesai dan dinyatakan tidak terbukti.', $laporan->nomor_laporan),
            ],
            'disahkan' => [
                'peringatan',
                'Pelanggaran berpoin telah disahkan',
                sprintf('%d poin pada laporan %s telah disahkan oleh Wakil Kesiswaan.', $laporan->total_poin, $laporan->nomor_laporan),
            ],
            default => null,
        };

        if (! $konfigurasi) {
            return;
        }

        $this->notifikasiPenggunaService->kirimKeBanyak(
            $this->notifikasiPenggunaService->penggunaUntukSiswa((int) $laporan->siswa_id),
            $konfigurasi[0],
            $konfigurasi[1],
            $konfigurasi[2],
            route('progress-kasus-siswa.show', $laporan, false),
            "perkembangan-kasus-siswa:{$laporan->id}:{$laporan->status_verifikasi}",
            [
                'laporan_pembinaan_siswa_id' => $laporan->id,
                'status_verifikasi' => $laporan->status_verifikasi,
            ],
        );

        $judulOrangTua = match ($laporan->status_verifikasi) {
            'ditetapkan_pembinaan' => 'Pembinaan anak telah ditetapkan',
            'tidak_terbukti' => 'Pemeriksaan laporan anak selesai',
            'disahkan' => 'Pelanggaran berpoin anak telah disahkan',
        };
        $namaSiswa = $laporan->siswa?->nama_lengkap ?? 'Anak Anda';
        $pesanOrangTua = match ($laporan->status_verifikasi) {
            'ditetapkan_pembinaan' => sprintf('BK menetapkan laporan %s untuk %s sebagai pembinaan tanpa poin.', $laporan->nomor_laporan, $namaSiswa),
            'tidak_terbukti' => sprintf('Pemeriksaan laporan %s untuk %s telah selesai dan dinyatakan tidak terbukti.', $laporan->nomor_laporan, $namaSiswa),
            'disahkan' => sprintf('%d poin pada laporan %s untuk %s telah disahkan oleh Wakil Kesiswaan.', $laporan->total_poin, $laporan->nomor_laporan, $namaSiswa),
        };

        $this->notifikasiPenggunaService->kirimKeBanyak(
            $this->notifikasiPenggunaService->penggunaOrangTuaUntukSiswa((int) $laporan->siswa_id),
            $konfigurasi[0],
            $judulOrangTua,
            $pesanOrangTua,
            route('pembinaan-poin-anak.show', $laporan, false),
            "perkembangan-kasus-orang-tua:{$laporan->id}:{$laporan->status_verifikasi}",
            [
                'laporan_pembinaan_siswa_id' => $laporan->id,
                'status_verifikasi' => $laporan->status_verifikasi,
            ],
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
