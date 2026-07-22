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
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class VerifikasiPelanggaranSiswaController extends Controller
{
    public function __construct(
        private ProsesPoinSiswaService $prosesPoinSiswaService,
        private NotifikasiPenggunaService $notifikasiPenggunaService,
        private CatatRiwayatPembinaanService $riwayatPembinaan,
        private AntreanVerifikasiPelanggaranService $antreanVerifikasi,
        private PengaturanBatasProsesPelanggaranService $pengaturanBatasProses,
    ) {}

    public function verifikasiBk(Request $request, LaporanPembinaanSiswa $laporanPembinaanSiswa)
    {
        abort_unless($request->user()?->memilikiIzin('poin_siswa.verifikasi_bk'), 403);
        abort_unless($laporanPembinaanSiswa->jenis_laporan === 'pelanggaran', 422);
        abort_unless(in_array($laporanPembinaanSiswa->status_verifikasi, AntreanVerifikasiPelanggaranService::STATUS_BK, true), 422, 'Pemeriksaan BK hanya dapat dilakukan sebelum tahap persetujuan.');

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

        $statusSebelum = $laporanPembinaanSiswa->status_verifikasi;
        $status = match ($data['hasil']) {
            'terbukti' => 'menunggu_persetujuan',
            'tidak_terbukti' => 'tidak_terbukti',
            default => 'perlu_klarifikasi',
        };
        $laporanPembinaanSiswa->update(['status_verifikasi' => $status]);
        if (! in_array($status, AntreanVerifikasiPelanggaranService::STATUS_FINAL, true)) {
            $this->pengaturanBatasProses->tetapkanBatas($laporanPembinaanSiswa, $status);
        }
        $this->riwayatPembinaan->catat(
            $laporanPembinaanSiswa,
            'pemeriksaan_bk',
            'Pemeriksaan fakta oleh BK',
            VerifikasiBkPelanggaran::DAFTAR_HASIL[$data['hasil']].(filled($data['catatan'] ?? null) ? ': '.trim($data['catatan']) : '.'),
            $statusSebelum,
            $status,
            $request->user()?->id,
            ['hasil' => $data['hasil']],
        );

        if ($data['hasil'] === 'terbukti') {
            $this->notifikasiPemberiPersetujuan($request, $laporanPembinaanSiswa);
        } else {
            $this->notifikasiHasilPemeriksaan($request, $laporanPembinaanSiswa, $data['hasil']);
        }

        return back()->with('berhasil', 'Hasil pemeriksaan BK berhasil disimpan.');
    }

    public function persetujuan(Request $request, LaporanPembinaanSiswa $laporanPembinaanSiswa)
    {
        abort_unless($request->user()?->memilikiIzin(['poin_siswa.menyetujui', 'poin_siswa.putus_konflik']), 403);
        abort_unless($laporanPembinaanSiswa->jenis_laporan === 'pelanggaran', 422);
        abort_unless(in_array($laporanPembinaanSiswa->status_verifikasi, [...AntreanVerifikasiPelanggaranService::STATUS_PERSETUJUAN, 'perlu_musyawarah'], true), 422, 'Persetujuan hanya dibuka setelah pemeriksaan BK menyatakan laporan terbukti.');
        abort_unless($laporanPembinaanSiswa->verifikasiBkPelanggaran()->latest('diverifikasi_pada')->value('hasil') === 'terbukti', 422, 'Laporan belum dinyatakan terbukti oleh BK.');

        $data = $request->validate([
            'jenis_persetujuan' => ['required', Rule::in(array_keys(PersetujuanPelanggaran::DAFTAR_JENIS))],
            'keputusan' => ['required', Rule::in(array_keys(PersetujuanPelanggaran::DAFTAR_KEPUTUSAN))],
            'catatan' => ['nullable', 'string'],
        ]);

        $this->pastikanPemberiPersetujuanSesuai($request, $laporanPembinaanSiswa, $data['jenis_persetujuan']);
        $this->pastikanPenyetujuBerbeda($request, $laporanPembinaanSiswa, $data['jenis_persetujuan']);
        $statusSebelum = $laporanPembinaanSiswa->status_verifikasi;

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
        $statusSesudah = $laporanPembinaanSiswa->fresh()->status_verifikasi;
        $this->riwayatPembinaan->catat(
            $laporanPembinaanSiswa,
            'persetujuan_'.$data['jenis_persetujuan'],
            'Keputusan '.PersetujuanPelanggaran::DAFTAR_JENIS[$data['jenis_persetujuan']],
            PersetujuanPelanggaran::DAFTAR_KEPUTUSAN[$data['keputusan']].(filled($data['catatan'] ?? null) ? ': '.trim($data['catatan']) : '.'),
            $statusSebelum,
            $statusSesudah,
            $request->user()?->id,
            ['keputusan' => $data['keputusan']],
        );
        $this->notifikasiSetelahPersetujuan($request, $laporanPembinaanSiswa->fresh(), $disahkan);

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
            'wakil_kesiswaan' => ($request->user()?->memilikiIzin('poin_siswa.putus_konflik') ?? false)
                && ($laporan->status_verifikasi === 'perlu_musyawarah' || $this->antreanVerifikasi->memerlukanPengganti($laporan)),
            default => false,
        };

        abort_unless($boleh || $request->user()?->administrator(), 403);
    }

    private function pastikanPenyetujuBerbeda(Request $request, LaporanPembinaanSiswa $laporan, string $jenis): void
    {
        if ($jenis === 'wakil_kesiswaan' || ! $request->user()?->pegawai_id) {
            return;
        }

        $sudahMenyetujuiSebagaiPihakLain = $laporan->persetujuanPelanggaran()
            ->where('jenis_persetujuan', '<>', $jenis)
            ->where('pegawai_id', $request->user()->pegawai_id)
            ->where('keputusan', 'setuju')
            ->exists();

        if ($sudahMenyetujuiSebagaiPihakLain) {
            throw ValidationException::withMessages([
                'keputusan' => 'Persetujuan kedua harus diberikan oleh pegawai yang berbeda. Laporan diteruskan kepada Wakil Kesiswaan sebagai penyetuju pengganti.',
            ]);
        }
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

    private function notifikasiHasilPemeriksaan(Request $request, LaporanPembinaanSiswa $laporan, string $hasil): void
    {
        $laporan->loadMissing(['siswa', 'kelas']);
        $penerima = $this->penerimaAsalLaporan($laporan, $request->user()?->id);
        $perluKlarifikasi = $hasil === 'perlu_klarifikasi';

        $this->notifikasiPenggunaService->kirimKeBanyak(
            $penerima,
            $perluKlarifikasi ? 'peringatan' : 'informasi',
            $perluKlarifikasi ? 'Laporan memerlukan klarifikasi' : 'Laporan dinyatakan tidak terbukti',
            sprintf('%s dari %s: %s.', $laporan->siswa?->nama_lengkap ?? 'Siswa', $laporan->kelas?->nama ?? 'kelas belum ditentukan', $laporan->labelStatusVerifikasi()),
            route('laporan-pembinaan-siswa.show', $laporan, false),
            "hasil-bk:{$laporan->id}:{$hasil}",
        );
    }

    private function notifikasiSetelahPersetujuan(Request $request, LaporanPembinaanSiswa $laporan, bool $disahkan): void
    {
        $laporan->loadMissing(['siswa', 'kelas']);

        if ($disahkan) {
            $this->notifikasiPenggunaService->kirimKeBanyak(
                $this->penerimaAsalLaporan($laporan, $request->user()?->id),
                'berhasil',
                'Poin pelanggaran telah disahkan',
                sprintf('%d poin untuk %s telah ditetapkan melalui dua persetujuan.', $laporan->total_poin, $laporan->siswa?->nama_lengkap ?? 'siswa'),
                route('laporan-pembinaan-siswa.show', $laporan, false),
                "poin-disahkan:{$laporan->id}",
            );

            return;
        }

        if ($laporan->status_verifikasi === 'perlu_musyawarah' || $this->antreanVerifikasi->memerlukanPengganti($laporan)) {
            $penerima = $this->notifikasiPenggunaService->penggunaDenganPeran('wakil_pimpinan_kesiswaan', $request->user()?->id);
            $this->notifikasiPenggunaService->kirimKeBanyak(
                $penerima,
                'penting',
                $laporan->status_verifikasi === 'perlu_musyawarah' ? 'Musyawarah pelanggaran diperlukan' : 'Penyetuju pengganti diperlukan',
                sprintf('Laporan %s untuk %s memerlukan keputusan Wakil Kesiswaan.', $laporan->nomor_laporan, $laporan->siswa?->nama_lengkap ?? 'siswa'),
                route('laporan-pembinaan-siswa.show', $laporan, false),
                "musyawarah-pelanggaran:{$laporan->id}:{$laporan->status_verifikasi}",
            );
        }
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
