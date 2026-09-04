<?php

namespace App\Services\Mobile;

use App\Models\AnggotaKelas;
use App\Models\JadwalKegiatanIbadah;
use App\Models\KegiatanIbadah;
use App\Models\Kelas;
use App\Models\Pengguna;
use App\Models\PresensiKegiatanIbadah;
use App\Models\RiwayatKoreksiKegiatanIbadah;
use App\Models\TahunPelajaran;
use App\Services\Ibadah\AksesScanKegiatanIbadah;
use App\Services\Ibadah\RekapHarianKegiatanIbadah;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class RekapKegiatanIbadahMobileService
{
    public function __construct(
        private AksesScanKegiatanIbadah $akses,
        private RekapHarianKegiatanIbadah $rekapHarian,
    ) {}

    public function daftar(Pengguna $pengguna, array $filter): array
    {
        $tanggal = Carbon::parse($filter['tanggal'] ?? now())->startOfDay();
        $tahunPelajaran = $this->tahunPelajaranAktif(false);
        abort_unless(
            $this->akses->dapatMelihatRekap($pengguna, $tahunPelajaran, $tanggal),
            403,
            'Rekap hanya dapat dibuka oleh wali kelas untuk kelasnya, guru PAI, guru piket pada hari terkait, atau pengelola kesiswaan.',
        );
        $cakupanKelasIds = $this->akses->cakupanKelasRekap($pengguna, $tahunPelajaran, $tanggal);

        $daftarKegiatan = KegiatanIbadah::query()
            ->orderByDesc('aktif')
            ->orderBy('nama')
            ->get();
        $kegiatanId = filled($filter['kegiatan_ibadah_id'] ?? null)
            && $daftarKegiatan->contains('id', (int) $filter['kegiatan_ibadah_id'])
                ? (int) $filter['kegiatan_ibadah_id']
                : $daftarKegiatan->firstWhere('aktif', true)?->id;
        $kegiatanDipilih = $daftarKegiatan->firstWhere('id', $kegiatanId);
        $daftarKelas = $tahunPelajaran ? $this->daftarKelas($tahunPelajaran, $cakupanKelasIds) : collect();
        $kelasDimintaId = filled($filter['kelas_id'] ?? null) ? (int) $filter['kelas_id'] : null;
        abort_if(
            $kelasDimintaId && $cakupanKelasIds !== null && ! in_array($kelasDimintaId, $cakupanKelasIds, true),
            403,
            'Wali kelas hanya dapat membuka rekap kelas yang diampunya.',
        );
        $kelasId = filled($filter['kelas_id'] ?? null)
            && $daftarKelas->contains('id', (int) $filter['kelas_id'])
                ? (int) $filter['kelas_id']
                : null;
        $status = $filter['status'] ?? 'semua';
        $cari = trim((string) ($filter['cari'] ?? ''));
        $halaman = (int) ($filter['halaman'] ?? 1);
        $tanggalString = $tanggal->toDateString();

        $hasilHarian = ($tahunPelajaran && $kegiatanId)
            ? $this->rekapHarian->hitung($tahunPelajaran, $daftarKelas, $kegiatanId, $tanggal)
            : ['status_per_siswa' => collect(), 'ringkasan_per_kelas' => collect()];
        $statusPerSiswa = $hasilHarian['status_per_siswa'];
        $ringkasanKelas = $daftarKelas->map(function (Kelas $kelas) use ($hasilHarian) {
            $ringkasan = $hasilHarian['ringkasan_per_kelas']->get((int) $kelas->id)
                ?? RekapHarianKegiatanIbadah::ringkasanKosong();

            return [
                'kelas' => [
                    'id' => (int) $kelas->id,
                    'nama' => $kelas->nama,
                    'tingkat' => (int) $kelas->tingkat,
                ],
                ...$ringkasan,
            ];
        });
        $ringkasan = $kelasId
            ? $ringkasanKelas->first(fn (array $item) => (int) $item['kelas']['id'] === $kelasId)
            : $this->jumlahkanRingkasan($ringkasanKelas);

        $paginator = null;
        $presensiPerSiswa = collect();
        if ($tahunPelajaran && $kegiatanId && $kelasId) {
            $paginator = AnggotaKelas::query()
                ->with(['siswa:id,nama_lengkap,nis,nisn,foto', 'kelas:id,nama'])
                ->where('tahun_pelajaran_id', $tahunPelajaran->id)
                ->where('kelas_id', $kelasId)
                ->where('status_keanggotaan', 'aktif')
                ->whereHas('siswa', function (Builder $query) use ($cari) {
                    $query->where('aktif', true)
                        ->when($cari !== '', function (Builder $query) use ($cari) {
                            $pola = '%'.mb_strtolower($cari).'%';
                            $query->where(function (Builder $query) use ($pola) {
                                $query->whereRaw('LOWER(nama_lengkap) LIKE ?', [$pola])
                                    ->orWhereRaw('LOWER(nis) LIKE ?', [$pola])
                                    ->orWhereRaw('LOWER(nisn) LIKE ?', [$pola]);
                            });
                        });
                })
                ->when($status !== 'semua', fn (Builder $query) => $query->whereIn(
                    'siswa_id',
                    $statusPerSiswa->where('status', $status)->keys(),
                ))
                ->orderByRaw('nomor_absen IS NULL')
                ->orderBy('nomor_absen')
                ->orderBy('id')
                ->paginate(40, ['*'], 'halaman', $halaman);
            $presensiPerSiswa = PresensiKegiatanIbadah::query()
                ->with(['dipindaiOleh:id,nama', 'dikoreksiOleh:id,nama'])
                ->where('tahun_pelajaran_id', $tahunPelajaran->id)
                ->where('kegiatan_ibadah_id', $kegiatanId)
                ->whereDate('tanggal', $tanggalString)
                ->whereIn('siswa_id', collect($paginator->items())->pluck('siswa_id'))
                ->get()
                ->keyBy('siswa_id');
        }

        $jadwal = $this->jadwalPada($tahunPelajaran, $kegiatanId, $tanggal);

        return [
            'tersedia' => (bool) ($tahunPelajaran && $kegiatanDipilih),
            'tanggal' => $tanggalString,
            'tanggal_label' => $tanggal->locale('id')->translatedFormat('l, d F Y'),
            'tahun_pelajaran' => $tahunPelajaran ? [
                'id' => (int) $tahunPelajaran->id,
                'nama' => $tahunPelajaran->nama,
            ] : null,
            'kegiatan_dipilih' => $kegiatanDipilih ? [
                'id' => (int) $kegiatanDipilih->id,
                'nama' => $kegiatanDipilih->nama,
                'kode' => $kegiatanDipilih->kode,
                'aktif' => (bool) $kegiatanDipilih->aktif,
                'khusus_laki_laki' => $kegiatanDipilih->khususLakiLaki(),
                'cakupan_peserta' => $kegiatanDipilih->labelCakupanPeserta(),
            ] : null,
            'kelas_dipilih_id' => $kelasId,
            'filter' => [
                'status' => $status,
                'cari' => $cari,
            ],
            'referensi' => [
                'kegiatan' => $daftarKegiatan->map(fn (KegiatanIbadah $item) => [
                    'id' => (int) $item->id,
                    'nama' => $item->nama,
                    'kode' => $item->kode,
                    'aktif' => (bool) $item->aktif,
                ])->values(),
                'kelas' => $daftarKelas->map(fn (Kelas $item) => [
                    'id' => (int) $item->id,
                    'nama' => $item->nama,
                    'tingkat' => (int) $item->tingkat,
                    'jumlah_siswa' => (int) $item->jumlah_siswa,
                ])->values(),
            ],
            'jadwal' => $this->dataJadwal($jadwal),
            'ringkasan' => [
                'total' => (int) ($ringkasan['total'] ?? 0),
                'hadir' => (int) ($ringkasan['hadir'] ?? 0),
                'tidak_hadir' => (int) ($ringkasan['tidak_hadir'] ?? 0),
                'berhalangan' => (int) ($ringkasan['berhalangan'] ?? 0),
                'tidak_wajib' => (int) ($ringkasan['tidak_wajib'] ?? 0),
                'wajib' => (int) ($ringkasan['wajib'] ?? 0),
                'sudah' => (int) ($ringkasan['sudah'] ?? 0),
                'belum' => (int) ($ringkasan['belum'] ?? 0),
                'persentase' => (int) ($ringkasan['persentase'] ?? 0),
            ],
            'ringkasan_kelas' => $ringkasanKelas->values(),
            'items' => $paginator
                ? collect($paginator->items())->map(fn (AnggotaKelas $anggota) => $this->dataAnggota(
                    $anggota,
                    $presensiPerSiswa->get($anggota->siswa_id),
                    $statusPerSiswa->get($anggota->siswa_id),
                ))->values()
                : [],
            'paginasi' => [
                'halaman' => $paginator?->currentPage() ?? 1,
                'halaman_terakhir' => $paginator?->lastPage() ?? 1,
                'per_halaman' => $paginator?->perPage() ?? 40,
                'total' => $paginator?->total() ?? 0,
                'ada_halaman_berikutnya' => $paginator?->hasMorePages() ?? false,
            ],
            'hak_akses' => [
                'dapat_koreksi' => $this->akses->dapatMengoreksi($pengguna, $tahunPelajaran, $tanggal),
                'cakupan_wali_kelas' => $cakupanKelasIds !== null,
                'dapat_scan_sekarang' => $tanggal->isToday()
                    && $this->akses->dapatMemindai($pengguna, $tahunPelajaran, now()),
            ],
            'pesan_privasi' => 'Rekap umum hanya menampilkan status berhalangan. Catatan privat dan rincian konfirmasi tetap hanya tersedia bagi pendamping yang berwenang.',
        ];
    }

    public function detailKoreksi(
        Pengguna $pengguna,
        AnggotaKelas $anggotaKelas,
        int $kegiatanId,
        Carbon $tanggal,
    ): array {
        [$tahunPelajaran, $kegiatan, $jadwal] = $this->pastikanDapatDikoreksi(
            $pengguna,
            $anggotaKelas,
            $kegiatanId,
            $tanggal,
        );
        $anggotaKelas->load(['kelas:id,nama', 'siswa:id,nama_lengkap,nis,nisn,foto']);
        $presensi = $this->ambilPresensi($anggotaKelas, $kegiatan, $tanggal);
        $riwayat = RiwayatKoreksiKegiatanIbadah::query()
            ->with('diubahOleh:id,nama')
            ->where('kegiatan_ibadah_id', $kegiatan->id)
            ->where('siswa_id', $anggotaKelas->siswa_id)
            ->whereDate('tanggal', $tanggal->toDateString())
            ->latest('id')
            ->limit(5)
            ->get();

        return [
            'tanggal' => $tanggal->toDateString(),
            'tanggal_label' => $tanggal->locale('id')->translatedFormat('l, d F Y'),
            'tahun_pelajaran' => [
                'id' => (int) $tahunPelajaran->id,
                'nama' => $tahunPelajaran->nama,
            ],
            'kegiatan' => [
                'id' => (int) $kegiatan->id,
                'nama' => $kegiatan->nama,
            ],
            'jadwal' => $this->dataJadwal($jadwal),
            'dapat_input_baru' => (bool) $jadwal,
            'anggota_kelas' => [
                'id' => (int) $anggotaKelas->id,
                'nomor_absen' => $anggotaKelas->nomor_absen ? (int) $anggotaKelas->nomor_absen : null,
                'kelas' => [
                    'id' => (int) $anggotaKelas->kelas_id,
                    'nama' => $anggotaKelas->kelas?->nama,
                ],
                'siswa' => [
                    'id' => (int) $anggotaKelas->siswa_id,
                    'nama' => $anggotaKelas->siswa?->nama_lengkap,
                    'nis' => $anggotaKelas->siswa?->nis,
                    'nisn' => $anggotaKelas->siswa?->nisn,
                    'foto_url' => $this->fotoUrl($anggotaKelas->siswa?->foto),
                ],
            ],
            'presensi' => $presensi ? $this->dataPresensi($presensi, sertakanCatatan: true) : null,
            'nilai_awal' => [
                'status' => $presensi ? 'sudah' : 'belum',
                'waktu' => $presensi
                    ? substr((string) $presensi->waktu_scan, 0, 5)
                    : ($jadwal?->formatJam($jadwal->jam_pelaksanaan) ?? ''),
            ],
            'riwayat' => $riwayat->map(fn (RiwayatKoreksiKegiatanIbadah $item) => [
                'id' => (int) $item->id,
                'tindakan' => $item->tindakan,
                'tindakan_label' => $this->labelTindakan($item->tindakan),
                'waktu_sebelum' => $item->waktu_sebelum ? substr((string) $item->waktu_sebelum, 0, 5) : null,
                'waktu_sesudah' => $item->waktu_sesudah ? substr((string) $item->waktu_sesudah, 0, 5) : null,
                'alasan' => $item->alasan,
                'diubah_oleh' => $item->diubahOleh?->nama,
                'dibuat_pada' => $item->created_at?->toIso8601String(),
                'dibuat_pada_label' => $item->created_at?->locale('id')->translatedFormat('d M Y, H:i'),
            ])->values(),
        ];
    }

    public function simpanKoreksi(
        Pengguna $pengguna,
        AnggotaKelas $anggotaKelas,
        int $kegiatanId,
        Carbon $tanggal,
        string $status,
        ?string $waktu,
        string $alasan,
        ?string $ipAddress,
        ?string $userAgent,
    ): string {
        [$tahunPelajaran, $kegiatan, $jadwal] = $this->pastikanDapatDikoreksi(
            $pengguna,
            $anggotaKelas,
            $kegiatanId,
            $tanggal,
        );

        DB::transaction(function () use ($pengguna, $anggotaKelas, $kegiatan, $tahunPelajaran, $jadwal, $tanggal, $status, $waktu, $alasan, $ipAddress, $userAgent) {
            AnggotaKelas::query()->whereKey($anggotaKelas->id)->lockForUpdate()->firstOrFail();
            $presensi = $this->ambilPresensi($anggotaKelas, $kegiatan, $tanggal, kunci: true);
            $hadirSebelum = (bool) $presensi;
            $waktuSebelum = $presensi?->waktu_scan;
            $sumberSebelum = $presensi?->sumber;

            if ($status === 'belum') {
                if (! $presensi) {
                    throw ValidationException::withMessages([
                        'status_presensi' => 'Siswa ini memang belum memiliki catatan presensi.',
                    ]);
                }
                $this->buatRiwayat($anggotaKelas, $kegiatan, $tahunPelajaran, $tanggal, $pengguna, $alasan, [
                    'presensi_kegiatan_ibadah_id' => $presensi->id,
                    'tindakan' => 'hapus',
                    'hadir_sebelum' => true,
                    'hadir_sesudah' => false,
                    'waktu_sebelum' => $waktuSebelum,
                    'waktu_sesudah' => null,
                    'sumber_sebelum' => $sumberSebelum,
                    'sumber_sesudah' => null,
                ]);
                $presensi->delete();

                return;
            }

            if (! $presensi && ! $jadwal) {
                throw ValidationException::withMessages([
                    'status_presensi' => 'Input manual tidak dapat dibuat karena kegiatan ini tidak memiliki jadwal pada tanggal tersebut.',
                ]);
            }

            $waktuSesudah = $waktu.':00';
            if (! $presensi) {
                $presensi = new PresensiKegiatanIbadah([
                    'jadwal_kegiatan_ibadah_id' => $jadwal->id,
                    'kegiatan_ibadah_id' => $kegiatan->id,
                    'tahun_pelajaran_id' => $tahunPelajaran->id,
                    'kelas_id' => $anggotaKelas->kelas_id,
                    'anggota_kelas_id' => $anggotaKelas->id,
                    'siswa_id' => $anggotaKelas->siswa_id,
                    'dipindai_oleh_pengguna_id' => $pengguna->id,
                    'tanggal' => $tanggal->toDateString(),
                    'sumber' => 'manual',
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent,
                ]);
            }
            $presensi->fill([
                'waktu_scan' => $waktuSesudah,
                'dikoreksi_oleh_pengguna_id' => $pengguna->id,
                'dikoreksi_pada' => now(),
                'catatan_koreksi' => trim($alasan),
            ])->save();

            $this->buatRiwayat($anggotaKelas, $kegiatan, $tahunPelajaran, $tanggal, $pengguna, $alasan, [
                'presensi_kegiatan_ibadah_id' => $presensi->id,
                'tindakan' => $hadirSebelum ? 'ubah' : 'tambah',
                'hadir_sebelum' => $hadirSebelum,
                'hadir_sesudah' => true,
                'waktu_sebelum' => $waktuSebelum,
                'waktu_sesudah' => $waktuSesudah,
                'sumber_sebelum' => $sumberSebelum,
                'sumber_sesudah' => $presensi->sumber,
            ]);
        });

        return $status === 'sudah'
            ? 'Presensi manual/koreksi berhasil disimpan.'
            : 'Catatan presensi berhasil dibatalkan dan riwayat perubahan tetap tersimpan.';
    }

    private function daftarKelas(TahunPelajaran $tahunPelajaran, ?array $cakupanKelasIds = null)
    {
        return Kelas::query()
            ->where('tahun_pelajaran_id', $tahunPelajaran->id)
            ->where('aktif', true)
            ->when($cakupanKelasIds !== null, fn (Builder $query) => $query->whereIn('id', $cakupanKelasIds))
            ->withCount([
                'anggotaKelas as jumlah_siswa' => fn (Builder $query) => $query
                    ->where('status_keanggotaan', 'aktif')
                    ->whereHas('siswa', fn (Builder $query) => $query->where('aktif', true)),
            ])
            ->orderBy('tingkat')
            ->orderBy('nama')
            ->get();
    }

    private function jumlahkanRingkasan($items): array
    {
        $ringkasan = RekapHarianKegiatanIbadah::ringkasanKosong();

        foreach (array_keys($ringkasan) as $kunci) {
            if ($kunci !== 'persentase') {
                $ringkasan[$kunci] = (int) $items->sum($kunci);
            }
        }

        $ringkasan['persentase'] = $ringkasan['wajib'] > 0
            ? (int) round(($ringkasan['sudah'] / $ringkasan['wajib']) * 100)
            : 0;

        return $ringkasan;
    }

    private function dataAnggota(AnggotaKelas $anggota, ?PresensiKegiatanIbadah $presensi, ?array $statusHarian): array
    {
        return [
            'anggota_kelas_id' => (int) $anggota->id,
            'nomor_absen' => $anggota->nomor_absen ? (int) $anggota->nomor_absen : null,
            'siswa' => [
                'id' => (int) $anggota->siswa_id,
                'nama' => $anggota->siswa?->nama_lengkap,
                'nis' => $anggota->siswa?->nis,
                'nisn' => $anggota->siswa?->nisn,
                'foto_url' => $this->fotoUrl($anggota->siswa?->foto),
            ],
            'kelas' => [
                'id' => (int) $anggota->kelas_id,
                'nama' => $anggota->kelas?->nama,
            ],
            'status' => $statusHarian['status'] ?? 'tidak_hadir',
            'status_label' => $statusHarian['status_label'] ?? 'Tidak hadir sekolah',
            'status_kehadiran' => $statusHarian['status_kehadiran'] ?? 'alfa',
            'status_kehadiran_label' => $statusHarian['status_kehadiran_label'] ?? 'Belum tercatat di presensi sekolah',
            'presensi' => $presensi ? $this->dataPresensi($presensi) : null,
        ];
    }

    private function dataPresensi(PresensiKegiatanIbadah $presensi, bool $sertakanCatatan = false): array
    {
        return [
            'id' => (int) $presensi->id,
            'waktu' => substr((string) $presensi->waktu_scan, 0, 5),
            'sumber' => $presensi->sumber,
            'sumber_label' => $this->labelSumber($presensi->sumber),
            'dicatat_oleh' => $presensi->dipindaiOleh?->nama,
            'dikoreksi_oleh' => $presensi->dikoreksiOleh?->nama,
            'dikoreksi_pada' => $presensi->dikoreksi_pada?->toIso8601String(),
            'catatan_koreksi' => $sertakanCatatan ? $presensi->catatan_koreksi : null,
        ];
    }

    private function dataJadwal(?JadwalKegiatanIbadah $jadwal): ?array
    {
        return $jadwal ? [
            'id' => (int) $jadwal->id,
            'aktif' => (bool) $jadwal->aktif,
            'jam_pelaksanaan' => $jadwal->formatJam($jadwal->jam_pelaksanaan),
            'jam_scan_mulai' => $jadwal->formatJam($jadwal->jam_scan_mulai),
            'jam_scan_selesai' => $jadwal->formatJam($jadwal->jam_scan_selesai),
            'rentang_scan' => $jadwal->rentangScan(),
            'keterangan' => $jadwal->keterangan,
        ] : null;
    }

    private function jadwalPada(?TahunPelajaran $tahun, ?int $kegiatanId, Carbon $tanggal): ?JadwalKegiatanIbadah
    {
        $hari = array_keys(JadwalKegiatanIbadah::DAFTAR_HARI)[$tanggal->dayOfWeekIso - 1] ?? 'minggu';
        if (! $tahun || ! $kegiatanId || $hari === 'minggu') {
            return null;
        }

        return JadwalKegiatanIbadah::query()
            ->where('tahun_pelajaran_id', $tahun->id)
            ->where('kegiatan_ibadah_id', $kegiatanId)
            ->where('hari', $hari)
            ->first();
    }

    private function pastikanDapatDikoreksi(Pengguna $pengguna, AnggotaKelas $anggota, int $kegiatanId, Carbon $tanggal): array
    {
        $tahun = $this->tahunPelajaranAktif();
        abort_unless((int) $anggota->tahun_pelajaran_id === (int) $tahun->id && $anggota->status_keanggotaan === 'aktif', 404);
        abort_unless($this->akses->dapatMengoreksi($pengguna, $tahun, $tanggal), 403);
        $cakupanKelasIds = $this->akses->cakupanKelasRekap($pengguna, $tahun, $tanggal);
        abort_if(
            $cakupanKelasIds !== null && ! in_array((int) $anggota->kelas_id, $cakupanKelasIds, true),
            403,
            'Wali kelas hanya dapat mengoreksi presensi ibadah siswa di kelas yang diampunya.',
        );
        $kegiatan = KegiatanIbadah::query()->findOrFail($kegiatanId);
        if ($kegiatan->khususLakiLaki()
            && $anggota->siswa()->where('jenis_kelamin', 'P')->exists()) {
            throw ValidationException::withMessages([
                'anggota_kelas_id' => 'Siswi tidak wajib mengikuti Sholat Jumat dan tidak memerlukan koreksi presensi ibadah.',
            ]);
        }

        return [$tahun, $kegiatan, $this->jadwalPada($tahun, $kegiatanId, $tanggal)];
    }

    private function ambilPresensi(AnggotaKelas $anggota, KegiatanIbadah $kegiatan, Carbon $tanggal, bool $kunci = false): ?PresensiKegiatanIbadah
    {
        return PresensiKegiatanIbadah::query()
            ->with(['dipindaiOleh:id,nama', 'dikoreksiOleh:id,nama'])
            ->where('kegiatan_ibadah_id', $kegiatan->id)
            ->where('siswa_id', $anggota->siswa_id)
            ->whereDate('tanggal', $tanggal->toDateString())
            ->when($kunci, fn (Builder $query) => $query->lockForUpdate())
            ->first();
    }

    private function buatRiwayat(AnggotaKelas $anggota, KegiatanIbadah $kegiatan, TahunPelajaran $tahun, Carbon $tanggal, Pengguna $pengguna, string $alasan, array $perubahan): void
    {
        RiwayatKoreksiKegiatanIbadah::create([
            'kegiatan_ibadah_id' => $kegiatan->id,
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $anggota->kelas_id,
            'anggota_kelas_id' => $anggota->id,
            'siswa_id' => $anggota->siswa_id,
            'diubah_oleh_pengguna_id' => $pengguna->id,
            'tanggal' => $tanggal->toDateString(),
            'alasan' => trim($alasan),
            ...$perubahan,
        ]);
    }

    private function tahunPelajaranAktif(bool $wajib = true): ?TahunPelajaran
    {
        $tahun = TahunPelajaran::query()->where('aktif', true)->orderByDesc('tanggal_mulai')->first();
        if (! $tahun && $wajib) {
            abort(404, 'Tahun pelajaran aktif belum tersedia.');
        }

        return $tahun;
    }

    private function labelSumber(?string $sumber): string
    {
        return match ($sumber) {
            'kamera' => 'Kamera HP',
            'manual' => 'Input manual',
            'scanner_kartu', 'scan' => 'Scanner kartu',
            default => filled($sumber) ? str($sumber)->replace('_', ' ')->title()->toString() : '-',
        };
    }

    private function labelTindakan(string $tindakan): string
    {
        return match ($tindakan) {
            'tambah' => 'Input manual',
            'ubah' => 'Dikoreksi',
            'hapus' => 'Dibatalkan',
            default => str($tindakan)->title()->toString(),
        };
    }

    private function fotoUrl(?string $foto): ?string
    {
        return $foto && Storage::disk('public')->exists($foto) ? asset('storage/'.$foto) : null;
    }
}
