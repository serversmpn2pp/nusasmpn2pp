<?php

namespace App\Services\Mobile;

use App\Models\AbsensiPegawai;
use App\Models\Pegawai;
use App\Models\PengaturanAbsensiPegawai;
use App\Models\Pengguna;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class LaporanPresensiPegawaiMobileService
{
    private const PER_HALAMAN = 20;

    public function daftar(Pengguna $pengguna, array $filter): array
    {
        $bulan = $filter['bulan'] ?? now()->format('Y-m');
        $pribadi = $pengguna->membatasiCakupanAbsensiPegawai();
        $this->pastikanAkunPegawai($pengguna, $pribadi);
        $jenis = $pribadi ? '' : trim((string) ($filter['jenis_pegawai'] ?? ''));
        $pegawaiId = $pribadi ? (int) $pengguna->pegawai_id : ($filter['pegawai_id'] ?? null);
        $statusPegawai = $pribadi ? 'semua' : ($filter['status_pegawai'] ?? 'aktif');
        $cari = trim((string) ($filter['cari'] ?? ''));
        $halaman = max(1, (int) ($filter['halaman'] ?? 1));
        [$mulai, $selesai, $tanggalPeriode, $label] = $this->periode($bulan);
        $pegawai = $this->queryPegawai($jenis, $pegawaiId ? (int) $pegawaiId : null, $statusPegawai, $cari)->get();
        $laporan = $this->laporan($pegawai, $tanggalPeriode, $mulai, $selesai);
        $ringkasan = $this->ringkasan($laporan);
        $total = $laporan->count();
        $halamanTerakhir = max(1, (int) ceil($total / self::PER_HALAMAN));
        $items = $laporan->slice(($halaman - 1) * self::PER_HALAMAN, self::PER_HALAMAN)->values();

        return [
            'periode' => [
                'bulan' => $bulan,
                'label' => $label,
                'tanggal_mulai' => $mulai->toDateString(),
                'tanggal_selesai' => $selesai->toDateString(),
            ],
            'ringkasan' => $ringkasan,
            'items' => $items->map(fn (array $item) => $this->item($item))->values(),
            'jenis_pegawai' => $pribadi ? [] : $this->pilihanJenisPegawai(),
            'pegawai' => $pribadi ? [] : $this->pilihanPegawai($statusPegawai),
            'filter' => [
                'bulan' => $bulan,
                'jenis_pegawai' => $jenis !== '' ? $jenis : null,
                'pegawai_id' => $pegawaiId ? (int) $pegawaiId : null,
                'status_pegawai' => $statusPegawai,
                'cari' => $cari,
            ],
            'paginasi' => [
                'halaman' => $halaman,
                'halaman_terakhir' => $halamanTerakhir,
                'total' => $total,
                'ada_halaman_berikutnya' => $halaman < $halamanTerakhir,
            ],
            'hak_akses' => [
                'cakupan_pribadi' => $pribadi,
            ],
        ];
    }

    public function detail(Pengguna $pengguna, Pegawai $pegawai, string $bulan): array
    {
        $this->pastikanBolehAkses($pengguna, $pegawai);
        [$mulai, $selesai, $tanggalPeriode, $label] = $this->periode($bulan);
        $laporan = $this->laporan(collect([$pegawai]), $tanggalPeriode, $mulai, $selesai)->firstOrFail();

        return [
            'periode' => [
                'bulan' => $bulan,
                'label' => $label,
                'tanggal_mulai' => $mulai->toDateString(),
                'tanggal_selesai' => $selesai->toDateString(),
            ],
            'pegawai' => $this->identitas($pegawai),
            'ringkasan' => $this->ringkasanItem($laporan),
            'rincian' => $laporan['rincian'],
            'hak_akses' => [
                'cakupan_pribadi' => $pengguna->membatasiCakupanAbsensiPegawai(),
            ],
        ];
    }

    private function queryPegawai(string $jenis, ?int $pegawaiId, string $status, string $cari): Builder
    {
        return Pegawai::query()
            ->select(['id', 'nama_lengkap', 'nip', 'foto', 'jenis_pegawai', 'jabatan_utama', 'status_kepegawaian', 'aktif'])
            ->when($status === 'aktif', fn (Builder $query) => $query->where('aktif', true))
            ->when($status === 'nonaktif', fn (Builder $query) => $query->where('aktif', false))
            ->when($jenis !== '', fn (Builder $query) => $query->where('jenis_pegawai', $jenis))
            ->when($pegawaiId, fn (Builder $query) => $query->whereKey($pegawaiId))
            ->when($cari !== '', function (Builder $query) use ($cari) {
                $pola = '%'.mb_strtolower($cari).'%';
                $query->where(function (Builder $query) use ($pola) {
                    $query->whereRaw('LOWER(nama_lengkap) LIKE ?', [$pola])
                        ->orWhereRaw("LOWER(COALESCE(nip, '')) LIKE ?", [$pola])
                        ->orWhereRaw("LOWER(COALESCE(jabatan_utama, '')) LIKE ?", [$pola]);
                });
            })
            ->orderBy('nama_lengkap');
    }

    private function laporan(
        Collection $pegawai,
        Collection $tanggalPeriode,
        Carbon $mulai,
        Carbon $selesai,
    ): Collection {
        $jadwal = PengaturanAbsensiPegawai::query()->where('aktif', true)->get();
        $absensi = $pegawai->isEmpty()
            ? collect()
            : AbsensiPegawai::query()
                ->whereBetween('tanggal', [$mulai->toDateString(), $selesai->toDateString()])
                ->whereIn('pegawai_id', $pegawai->pluck('id'))
                ->get()
                ->groupBy('pegawai_id')
                ->map(fn (Collection $items) => $items->keyBy(
                    fn (AbsensiPegawai $item) => $item->tanggal->toDateString(),
                ));

        return $pegawai->map(function (Pegawai $item) use ($tanggalPeriode, $jadwal, $absensi) {
            $catatan = $absensi->get($item->id, collect());
            $tanggalEfektif = collect();
            foreach ($tanggalPeriode as $tanggal) {
                $jadwalHari = $this->jadwalPegawai($item, $this->hari($tanggal), $jadwal);
                if ($jadwalHari) {
                    $tanggalEfektif->put($tanggal, $jadwalHari);
                }
            }
            foreach ($catatan->keys() as $tanggal) {
                if (! $tanggalEfektif->has($tanggal)) {
                    $tanggalEfektif->put($tanggal, $this->jadwalPegawai($item, $this->hari($tanggal), $jadwal));
                }
            }
            $tanggalEfektif = $tanggalEfektif->sortKeys();
            $hitung = array_fill_keys(array_keys(AbsensiPegawai::DAFTAR_STATUS_KEHADIRAN), 0);
            $terlambat = $menitTerlambat = $pulangCepat = $menitPulangCepat = $belumPulang = $manual = 0;
            $rincian = collect();
            foreach ($tanggalEfektif as $tanggal => $jadwalHari) {
                $presensi = $catatan->get($tanggal);
                $status = $presensi?->status_kehadiran ?? 'alfa';
                if (array_key_exists($status, $hitung)) {
                    $hitung[$status]++;
                }
                if ((int) ($presensi?->menit_terlambat ?? 0) > 0) {
                    $terlambat++;
                    $menitTerlambat += (int) $presensi->menit_terlambat;
                }
                if ((int) ($presensi?->menit_pulang_cepat ?? 0) > 0) {
                    $pulangCepat++;
                    $menitPulangCepat += (int) $presensi->menit_pulang_cepat;
                }
                if ($status === 'hadir' && filled($presensi?->jam_masuk) && blank($presensi?->jam_pulang)) {
                    $belumPulang++;
                }
                if ($presensi?->sumber === 'manual') {
                    $manual++;
                }
                $rincian->push($this->rincian($tanggal, $jadwalHari, $presensi, $status));
            }
            $hariEfektif = $tanggalEfektif->count();

            return [
                'pegawai' => $item,
                'rincian' => $rincian->values(),
                'hari_efektif' => $hariEfektif,
                ...$hitung,
                'terlambat' => $terlambat,
                'menit_terlambat' => $menitTerlambat,
                'pulang_cepat' => $pulangCepat,
                'menit_pulang_cepat' => $menitPulangCepat,
                'belum_pulang' => $belumPulang,
                'manual' => $manual,
                'persentase_hadir' => $hariEfektif > 0 ? round(($hitung['hadir'] / $hariEfektif) * 100, 1) : 0,
            ];
        });
    }

    private function rincian(
        string $tanggal,
        ?PengaturanAbsensiPegawai $jadwal,
        ?AbsensiPegawai $absensi,
        string $status,
    ): array {
        $date = Carbon::parse($tanggal);

        return [
            'tanggal' => $tanggal,
            'tanggal_label' => $date->locale('id')->translatedFormat('D, d M Y'),
            'hari' => PengaturanAbsensiPegawai::DAFTAR_HARI[$this->hari($tanggal)]['label'] ?? '-',
            'jadwal' => $jadwal ? [
                'id' => (int) $jadwal->id,
                'nama' => $jadwal->nama_jadwal,
                'jam_masuk' => $this->formatJam($jadwal->jam_masuk),
                'jam_pulang' => $this->formatJam($jadwal->jam_pulang),
            ] : null,
            'status' => $status,
            'status_label' => AbsensiPegawai::DAFTAR_STATUS_KEHADIRAN[$status] ?? str($status)->headline()->toString(),
            'inferensi' => $absensi === null,
            'jam_masuk' => $this->formatJam($absensi?->jam_masuk),
            'jam_pulang' => $this->formatJam($absensi?->jam_pulang),
            'menit_terlambat' => (int) ($absensi?->menit_terlambat ?? 0),
            'menit_pulang_cepat' => (int) ($absensi?->menit_pulang_cepat ?? 0),
            'sumber' => $absensi?->sumber,
            'catatan' => $absensi?->catatan,
            'keterangan' => $this->keterangan($absensi, $status),
        ];
    }

    private function item(array $item): array
    {
        return [
            ...$this->identitas($item['pegawai']),
            'ringkasan' => $this->ringkasanItem($item),
        ];
    }

    private function identitas(Pegawai $pegawai): array
    {
        $foto = filled($pegawai->foto) && Storage::disk('public')->exists($pegawai->foto);

        return [
            'id' => (int) $pegawai->id,
            'nama' => $pegawai->nama_lengkap,
            'nip' => $pegawai->nip,
            'inisial' => $this->inisial($pegawai->nama_lengkap),
            'foto_url' => $foto ? asset('storage/'.$pegawai->foto) : null,
            'jenis_pegawai' => $pegawai->jenis_pegawai,
            'jabatan' => $pegawai->jabatan_utama,
            'status_kepegawaian' => $pegawai->status_kepegawaian,
            'aktif' => (bool) $pegawai->aktif,
        ];
    }

    private function ringkasanItem(array $item): array
    {
        return collect($item)->except(['pegawai', 'rincian'])->all();
    }

    private function ringkasan(Collection $laporan): array
    {
        $jumlah = $laporan->count();

        return [
            'pegawai' => $jumlah,
            'hari_efektif' => $laporan->sum('hari_efektif'),
            'hadir' => $laporan->sum('hadir'),
            'izin' => $laporan->sum('izin'),
            'sakit' => $laporan->sum('sakit'),
            'dinas_luar' => $laporan->sum('dinas_luar'),
            'cuti' => $laporan->sum('cuti'),
            'alfa' => $laporan->sum('alfa'),
            'terlambat' => $laporan->sum('terlambat'),
            'menit_terlambat' => $laporan->sum('menit_terlambat'),
            'pulang_cepat' => $laporan->sum('pulang_cepat'),
            'menit_pulang_cepat' => $laporan->sum('menit_pulang_cepat'),
            'belum_pulang' => $laporan->sum('belum_pulang'),
            'manual' => $laporan->sum('manual'),
            'rata_persentase_hadir' => $jumlah > 0 ? round((float) $laporan->avg('persentase_hadir'), 1) : 0,
        ];
    }

    private function jadwalPegawai(Pegawai $pegawai, string $hari, Collection $jadwal): ?PengaturanAbsensiPegawai
    {
        return $jadwal->first(fn (PengaturanAbsensiPegawai $item) => $item->hari === $hari
            && $item->cakupan === 'pegawai' && (int) $item->pegawai_id === (int) $pegawai->id)
            ?? (filled($pegawai->jenis_pegawai)
                ? $jadwal->first(fn (PengaturanAbsensiPegawai $item) => $item->hari === $hari
                    && $item->cakupan === 'jenis_pegawai' && $item->jenis_pegawai === $pegawai->jenis_pegawai)
                : null)
            ?? $jadwal->first(fn (PengaturanAbsensiPegawai $item) => $item->hari === $hari && $item->cakupan === 'semua');
    }

    private function pilihanJenisPegawai(): Collection
    {
        return Pegawai::query()->whereNotNull('jenis_pegawai')->where('jenis_pegawai', '!=', '')
            ->distinct()->orderBy('jenis_pegawai')->pluck('jenis_pegawai')->values();
    }

    private function pilihanPegawai(string $status): Collection
    {
        return Pegawai::query()
            ->when($status === 'aktif', fn (Builder $query) => $query->where('aktif', true))
            ->when($status === 'nonaktif', fn (Builder $query) => $query->where('aktif', false))
            ->orderBy('nama_lengkap')->get(['id', 'nama_lengkap', 'nip'])
            ->map(fn (Pegawai $pegawai) => [
                'id' => (int) $pegawai->id,
                'nama' => $pegawai->nama_lengkap,
                'nip' => $pegawai->nip,
            ])->values();
    }

    private function periode(string $bulan): array
    {
        $awal = Carbon::createFromFormat('Y-m', $bulan)->startOfMonth();
        $akhir = $awal->copy()->endOfMonth();
        $tanggal = collect(CarbonPeriod::create($awal->toDateString(), $akhir->toDateString()))
            ->map(fn (Carbon $item) => $item->toDateString());

        return [$awal, $akhir, $tanggal, $awal->copy()->locale('id')->translatedFormat('F Y')];
    }

    private function keterangan(?AbsensiPegawai $absensi, string $status): string
    {
        if (filled($absensi?->catatan)) {
            return $absensi->catatan;
        }
        if (! $absensi) {
            return 'Belum ada scan atau koreksi.';
        }
        if ($status === 'hadir' && filled($absensi->jam_masuk) && blank($absensi->jam_pulang)) {
            return 'Belum scan pulang.';
        }
        if ($absensi->sumber === 'manual') {
            return 'Koreksi manual.';
        }

        return $status === 'hadir' ? 'Scan tercatat.' : '-';
    }

    private function pastikanAkunPegawai(Pengguna $pengguna, bool $pribadi): void
    {
        if ($pribadi) {
            abort_unless($pengguna->pegawai_id, 403, 'Akun belum terhubung dengan data pegawai.');
        }
    }

    private function pastikanBolehAkses(Pengguna $pengguna, Pegawai $pegawai): void
    {
        abort_unless($pengguna->dapatMengaksesAbsensiPegawai($pegawai->id), 403);
    }

    private function hari(string $tanggal): string
    {
        return array_keys(PengaturanAbsensiPegawai::DAFTAR_HARI)[Carbon::parse($tanggal)->isoWeekday() - 1];
    }

    private function formatJam(?string $jam): ?string
    {
        return $jam ? substr($jam, 0, 5) : null;
    }

    private function inisial(string $nama): string
    {
        return mb_strtoupper(collect(preg_split('/\s+/', trim($nama)) ?: [])->filter()->take(2)
            ->map(fn (string $kata) => mb_substr($kata, 0, 1))->join('') ?: 'P');
    }
}
