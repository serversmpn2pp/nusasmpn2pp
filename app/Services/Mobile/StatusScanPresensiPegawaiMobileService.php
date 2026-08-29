<?php

namespace App\Services\Mobile;

use App\Models\AbsensiPegawai;
use App\Models\LogScanAbsensiPegawai;
use App\Models\Pegawai;
use App\Models\PengaturanAbsensiPegawai;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class StatusScanPresensiPegawaiMobileService
{
    private const STATUS_SUDAH_TERCATAT = [
        'duplikat_cepat',
        'sudah_scan_masuk',
        'sudah_scan_pulang',
    ];

    public function statusHariIni(array $filter): array
    {
        $tanggal = now()->toDateString();
        $jenisPegawai = filled($filter['jenis_pegawai'] ?? null)
            ? trim((string) $filter['jenis_pegawai'])
            : null;
        $pegawai = Pegawai::query()
            ->where('aktif', true)
            ->when($jenisPegawai, fn (Builder $query) => $query->where('jenis_pegawai', $jenisPegawai))
            ->get(['id', 'nama_lengkap', 'nip', 'jenis_pegawai', 'jabatan_utama', 'foto']);
        $pegawaiIds = $pegawai->pluck('id')->map(fn ($id) => (int) $id)->values();

        $absensi = AbsensiPegawai::query()
            ->whereDate('tanggal', $tanggal)
            ->when($jenisPegawai, fn (Builder $query) => $query->whereIn('pegawai_id', $pegawaiIds));
        $logDasar = LogScanAbsensiPegawai::query()
            ->whereDate('tanggal', $tanggal)
            ->when($jenisPegawai, fn (Builder $query) => $query->whereIn('pegawai_id', $pegawaiIds));
        $status = $filter['status'] ?? 'semua';
        $cari = trim((string) ($filter['cari'] ?? ''));
        $log = (clone $logDasar)->with([
            'pegawai:id,nama_lengkap,nip,jenis_pegawai,jabatan_utama,foto',
            'absensiPegawai:id,pengaturan_absensi_pegawai_id,jam_masuk,status_masuk,menit_terlambat,jam_pulang,status_pulang,menit_pulang_cepat,status_kehadiran',
            'absensiPegawai.pengaturanAbsensiPegawai:id,nama_jadwal',
        ]);
        $this->terapkanFilterStatus($log, $status);
        $this->terapkanPencarian($log, $cari);
        $aktivitas = $log
            ->latest('waktu_scan')
            ->latest('id')
            ->limit((int) ($filter['batas'] ?? 40))
            ->get();

        return [
            'tanggal' => $tanggal,
            'tanggal_label' => now()->locale('id')->translatedFormat('l, d F Y'),
            'waktu_server' => now()->toIso8601String(),
            'pembaruan_berikutnya_detik' => 15,
            'jadwal' => $this->statusJadwal($jenisPegawai),
            'ringkasan' => $this->ringkasan($pegawai, $absensi, $logDasar),
            'aktivitas' => $aktivitas
                ->map(fn (LogScanAbsensiPegawai $item) => $this->aktivitas($item))
                ->values(),
            'jenis_pegawai' => $this->pilihanJenisPegawai(),
            'filter' => [
                'jenis_pegawai' => $jenisPegawai,
                'status' => $status,
                'cari' => $cari,
            ],
        ];
    }

    private function ringkasan(Collection $pegawai, Builder $absensi, Builder $logDasar): array
    {
        $masuk = (clone $absensi)->whereNotNull('jam_masuk')->count();
        $pulang = (clone $absensi)->whereNotNull('jam_pulang')->count();

        return [
            'jumlah_pegawai' => $pegawai->count(),
            'sudah_masuk' => $masuk,
            'terlambat' => (clone $absensi)->where('status_masuk', 'terlambat')->count(),
            'sudah_pulang' => $pulang,
            'belum_scan_masuk' => max($pegawai->count() - $masuk, 0),
            'belum_scan_pulang' => max($masuk - $pulang, 0),
            'scan_berhasil' => (clone $logDasar)->where('berhasil', true)->count(),
            'sudah_tercatat' => (clone $logDasar)->whereIn('status_scan', self::STATUS_SUDAH_TERCATAT)->count(),
            'perlu_perhatian' => (clone $logDasar)
                ->where('berhasil', false)
                ->whereNotIn('status_scan', self::STATUS_SUDAH_TERCATAT)
                ->count(),
        ];
    }

    private function terapkanFilterStatus(Builder $query, string $status): void
    {
        match ($status) {
            'berhasil' => $query->where('berhasil', true),
            'sudah_tercatat' => $query->whereIn('status_scan', self::STATUS_SUDAH_TERCATAT),
            'perlu_perhatian' => $query
                ->where('berhasil', false)
                ->whereNotIn('status_scan', self::STATUS_SUDAH_TERCATAT),
            'masuk' => $query->where('jenis_scan', 'masuk'),
            'pulang' => $query->where('jenis_scan', 'pulang'),
            'terlambat' => $query
                ->where('berhasil', true)
                ->whereHas('absensiPegawai', fn (Builder $query) => $query->where('status_masuk', 'terlambat')),
            default => null,
        };
    }

    private function terapkanPencarian(Builder $query, string $cari): void
    {
        if ($cari === '') {
            return;
        }

        $pola = '%'.mb_strtolower($cari).'%';
        $query->where(function (Builder $query) use ($pola) {
            $query->whereRaw("LOWER(COALESCE(nip, '')) LIKE ?", [$pola])
                ->orWhereRaw("LOWER(COALESCE(scanner_id, '')) LIKE ?", [$pola])
                ->orWhereRaw("LOWER(COALESCE(status_scan, '')) LIKE ?", [$pola])
                ->orWhereRaw("LOWER(COALESCE(pesan, '')) LIKE ?", [$pola])
                ->orWhereHas('pegawai', function (Builder $query) use ($pola) {
                    $query->whereRaw('LOWER(nama_lengkap) LIKE ?', [$pola])
                        ->orWhereRaw("LOWER(COALESCE(nip, '')) LIKE ?", [$pola])
                        ->orWhereRaw("LOWER(COALESCE(jenis_pegawai, '')) LIKE ?", [$pola]);
                });
        });
    }

    private function aktivitas(LogScanAbsensiPegawai $item): array
    {
        $pegawai = $item->pegawai;
        $fotoTersedia = $pegawai && filled($pegawai->foto)
            && Storage::disk('public')->exists($pegawai->foto);

        return [
            'id' => (int) $item->id,
            'berhasil' => (bool) $item->berhasil,
            'status' => $item->status_scan,
            'status_label' => $this->labelStatus($item->status_scan),
            'pesan' => $item->pesan,
            'jenis_scan' => $item->jenis_scan,
            'jenis_scan_label' => match ($item->jenis_scan) {
                'masuk' => 'Masuk',
                'pulang' => 'Pulang',
                default => 'Tidak ditentukan',
            },
            'scanner_id' => $item->scanner_id,
            'waktu_scan' => $item->waktu_scan?->toIso8601String(),
            'jam_scan' => $item->waktu_scan?->format('H:i:s'),
            'pegawai' => $pegawai ? [
                'id' => (int) $pegawai->id,
                'nama' => $pegawai->nama_lengkap,
                'nip' => $pegawai->nip,
                'jenis_pegawai' => $pegawai->jenis_pegawai,
                'jabatan' => $pegawai->jabatan_utama,
                'foto_url' => $fotoTersedia ? asset('storage/'.$pegawai->foto) : null,
                'inisial' => $this->inisial($pegawai->nama_lengkap),
            ] : null,
            'presensi' => $item->absensiPegawai ? [
                'jam_masuk' => $this->formatJam($item->absensiPegawai->jam_masuk),
                'status_masuk' => $item->absensiPegawai->status_masuk,
                'menit_terlambat' => (int) $item->absensiPegawai->menit_terlambat,
                'jam_pulang' => $this->formatJam($item->absensiPegawai->jam_pulang),
                'status_pulang' => $item->absensiPegawai->status_pulang,
                'menit_pulang_cepat' => (int) $item->absensiPegawai->menit_pulang_cepat,
                'status_kehadiran' => $item->absensiPegawai->status_kehadiran,
                'nama_jadwal' => $item->absensiPegawai->pengaturanAbsensiPegawai?->nama_jadwal,
            ] : null,
        ];
    }

    private function statusJadwal(?string $jenisPegawai): array
    {
        $hari = array_keys(PengaturanAbsensiPegawai::DAFTAR_HARI)[now()->isoWeekday() - 1];
        $jadwal = PengaturanAbsensiPegawai::query()
            ->with('pegawai:id,nama_lengkap,jenis_pegawai')
            ->where('hari', $hari)
            ->where('aktif', true)
            ->when($jenisPegawai, function (Builder $query) use ($jenisPegawai) {
                $query->where(function (Builder $query) use ($jenisPegawai) {
                    $query->where('cakupan', 'semua')
                        ->orWhere(function (Builder $query) use ($jenisPegawai) {
                            $query->where('cakupan', 'jenis_pegawai')
                                ->where('jenis_pegawai', $jenisPegawai);
                        })
                        ->orWhere(function (Builder $query) use ($jenisPegawai) {
                            $query->where('cakupan', 'pegawai')
                                ->whereHas('pegawai', fn (Builder $query) => $query->where('jenis_pegawai', $jenisPegawai));
                        });
                });
            })
            ->orderBy('jam_scan_masuk_mulai')
            ->orderBy('cakupan')
            ->orderBy('nama_jadwal')
            ->get();

        if ($jadwal->isEmpty()) {
            return [
                'tersedia' => false,
                'jumlah' => 0,
                'hari' => $hari,
                'hari_label' => PengaturanAbsensiPegawai::DAFTAR_HARI[$hari]['label'],
                'fase' => 'tidak_tersedia',
                'fase_label' => 'Jadwal belum tersedia',
                'items' => [],
            ];
        }

        $sekarang = now()->hour * 60 + now()->minute;
        $mulaiMasuk = $jadwal->min(fn (PengaturanAbsensiPegawai $item) => $this->menit($item->jam_scan_masuk_mulai));
        $selesaiMasuk = $jadwal->max(fn (PengaturanAbsensiPegawai $item) => $this->menit($item->jam_scan_masuk_selesai));
        $mulaiPulang = $jadwal->min(fn (PengaturanAbsensiPegawai $item) => $this->menit($item->jam_scan_pulang_mulai));
        $selesaiPulang = $jadwal->max(fn (PengaturanAbsensiPegawai $item) => $this->menit($item->jam_scan_pulang_selesai));
        $scanMasukAktif = $jadwal->contains(fn (PengaturanAbsensiPegawai $item) => $sekarang >= $this->menit($item->jam_scan_masuk_mulai)
            && $sekarang <= $this->menit($item->jam_scan_masuk_selesai));
        $scanPulangAktif = $jadwal->contains(fn (PengaturanAbsensiPegawai $item) => $sekarang >= $this->menit($item->jam_scan_pulang_mulai)
            && $sekarang <= $this->menit($item->jam_scan_pulang_selesai));
        [$fase, $label] = match (true) {
            $scanMasukAktif => ['scan_masuk', 'Scan masuk berlangsung'],
            $scanPulangAktif => ['scan_pulang', 'Scan pulang berlangsung'],
            $sekarang < $mulaiMasuk => ['belum_dibuka', 'Menunggu scan masuk'],
            $sekarang < $mulaiPulang => ['menunggu_pulang', 'Menunggu scan pulang'],
            $sekarang <= $selesaiPulang => ['di_antara_jadwal', 'Menunggu jadwal scan berikutnya'],
            default => ['selesai', 'Jadwal scan selesai'],
        };

        return [
            'tersedia' => true,
            'jumlah' => $jadwal->count(),
            'hari' => $hari,
            'hari_label' => PengaturanAbsensiPegawai::DAFTAR_HARI[$hari]['label'],
            'fase' => $fase,
            'fase_label' => $label,
            'jam_scan_masuk_mulai' => $this->jamDariMenit($mulaiMasuk),
            'jam_scan_masuk_selesai' => $this->jamDariMenit($selesaiMasuk),
            'jam_scan_pulang_mulai' => $this->jamDariMenit($mulaiPulang),
            'jam_scan_pulang_selesai' => $this->jamDariMenit($selesaiPulang),
            'items' => $jadwal->map(fn (PengaturanAbsensiPegawai $item) => [
                'id' => (int) $item->id,
                'nama' => $item->nama_jadwal,
                'cakupan' => $item->labelCakupan(),
                'sasaran' => $item->labelSasaran(),
                'jam_masuk' => $item->formatJam($item->jam_masuk),
                'jam_pulang' => $item->formatJam($item->jam_pulang),
            ])->values(),
        ];
    }

    private function pilihanJenisPegawai(): Collection
    {
        return Pegawai::query()
            ->where('aktif', true)
            ->whereNotNull('jenis_pegawai')
            ->where('jenis_pegawai', '!=', '')
            ->distinct()
            ->orderBy('jenis_pegawai')
            ->pluck('jenis_pegawai')
            ->values();
    }

    private function labelStatus(string $status): string
    {
        return match ($status) {
            'berhasil_masuk' => 'Presensi masuk tersimpan',
            'berhasil_pulang' => 'Presensi pulang tersimpan',
            'duplikat_cepat', 'sudah_scan_masuk', 'sudah_scan_pulang' => 'Presensi sudah tercatat',
            'format_tidak_valid' => 'Kartu tidak terbaca',
            'pegawai_tidak_ditemukan' => 'Pegawai tidak ditemukan',
            'pegawai_nonaktif' => 'Pegawai nonaktif',
            'jadwal_absensi_tidak_ada' => 'Jadwal belum tersedia',
            'di_luar_jadwal', 'di_luar_jadwal_masuk', 'di_luar_jadwal_pulang' => 'Di luar jadwal',
            default => str($status)->replace('_', ' ')->title()->toString(),
        };
    }

    private function formatJam(?string $jam): ?string
    {
        return $jam ? substr($jam, 0, 8) : null;
    }

    private function menit(string $jam): int
    {
        [$hour, $minute] = array_map('intval', explode(':', substr($jam, 0, 5)));

        return ($hour * 60) + $minute;
    }

    private function jamDariMenit(int $menit): string
    {
        return str_pad((string) intdiv($menit, 60), 2, '0', STR_PAD_LEFT)
            .':'
            .str_pad((string) ($menit % 60), 2, '0', STR_PAD_LEFT);
    }

    private function inisial(string $nama): string
    {
        $inisial = collect(preg_split('/\s+/', trim($nama)) ?: [])
            ->take(2)
            ->map(fn (string $kata) => mb_substr($kata, 0, 1))
            ->join('');

        return mb_strtoupper($inisial ?: 'P');
    }
}
