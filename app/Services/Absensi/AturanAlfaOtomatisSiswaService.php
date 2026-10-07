<?php

namespace App\Services\Absensi;

use App\Models\AnggotaKelas;
use App\Models\PengaturanAbsensi;
use App\Models\PengecualianPresensiSiswa;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AturanAlfaOtomatisSiswaService
{
    private ?Collection $pengecualian = null;

    public function pengecualianPada(string $tanggal, ?AnggotaKelas $anggota = null): ?PengecualianPresensiSiswa
    {
        $this->pengecualian ??= PengecualianPresensiSiswa::where('aktif', true)->orderBy('id')->get();

        return $this->pengecualian->first(fn ($p) => $p->tanggal_mulai->toDateString() <= $tanggal
            && $p->tanggal_selesai->toDateString() >= $tanggal
            && (! $anggota || (int) $p->tahun_pelajaran_id === (int) $anggota->tahun_pelajaran_id)
            && ($p->kelas_id === null || ($anggota && (int) $p->kelas_id === (int) $anggota->kelas_id)));
    }

    public function statusTanpaCatatan(string $tanggal, array $hariAktif, ?AnggotaKelas $anggota = null, string $menunggu = 'belum_scan'): string
    {
        return $this->pengecualianPada($tanggal, $anggota) ? 'pengecualian'
            : ($this->menjadiAlfa($tanggal, $hariAktif, $anggota) ? 'alfa' : $menunggu);
    }

    public function batasiPengecualian(Builder $query, string $tanggal, bool $dikecualikan): void
    {
        $query->whereExists(function ($q) use ($tanggal) {
            $q->selectRaw('1')->from('pengecualian_presensi_siswa as pengecualian')
                ->where('pengecualian.aktif', true)
                ->whereColumn('pengecualian.tahun_pelajaran_id', 'anggota_kelas.tahun_pelajaran_id')
                ->where(fn ($q) => $q->whereNull('pengecualian.kelas_id')
                    ->orWhereColumn('pengecualian.kelas_id', 'anggota_kelas.kelas_id'))
                ->whereDate('pengecualian.tanggal_mulai', '<=', $tanggal)
                ->whereDate('pengecualian.tanggal_selesai', '>=', $tanggal);
        }, 'and', ! $dikecualikan);
    }

    public function hariAktif(): array
    {
        return PengaturanAbsensi::where('aktif', true)->pluck('hari')->all();
    }

    public function tanggalEfektif(CarbonInterface $mulai, CarbonInterface $selesai, array $hariAktif): array
    {
        $akhir = Carbon::instance($selesai)->copy()->startOfDay()->min(now()->startOfDay());
        if ($mulai->copy()->startOfDay()->gt($akhir) || empty($hariAktif)) {
            return [];
        }
        $kodeHari = array_keys(PengaturanAbsensi::DAFTAR_HARI);
        $tanggal = [];
        foreach (CarbonPeriod::create($mulai->toDateString(), $akhir->toDateString()) as $hari) {
            if (in_array($kodeHari[$hari->isoWeekday() - 1], $hariAktif, true)) {
                $tanggal[] = $hari->toDateString();
            }
        }

        return $tanggal;
    }

    public function menjadiAlfa(string $tanggal, array $hariAktif, ?AnggotaKelas $anggota = null): bool
    {
        $hari = Carbon::parse($tanggal)->startOfDay();
        $kodeHari = array_keys(PengaturanAbsensi::DAFTAR_HARI)[$hari->isoWeekday() - 1];
        $tahun = $anggota?->tahunPelajaran;

        return $hari->lt(now()->startOfDay()) && in_array($kodeHari, $hariAktif, true)
            && ! $this->pengecualianPada($tanggal, $anggota)
            && (! $anggota?->tanggal_masuk || $hari->gte($anggota->tanggal_masuk))
            && (! $anggota?->tanggal_keluar || $hari->lte($anggota->tanggal_keluar))
            && (! $tahun?->tanggal_mulai || $hari->gte($tahun->tanggal_mulai))
            && (! $tahun?->tanggal_selesai || $hari->lte($tahun->tanggal_selesai));
    }

    public function hariWajibYangTelahBerakhir(string $tanggal, array $hariAktif): bool
    {
        $hari = Carbon::parse($tanggal)->startOfDay();
        $kodeHari = array_keys(PengaturanAbsensi::DAFTAR_HARI)[$hari->isoWeekday() - 1];

        return $hari->lt(now()->startOfDay()) && in_array($kodeHari, $hariAktif, true);
    }

    public function batasiKeanggotaan(Builder $query, string $tanggal): void
    {
        $query->where(fn ($q) => $q->whereNull('tanggal_masuk')->orWhereDate('tanggal_masuk', '<=', $tanggal))
            ->where(fn ($q) => $q->whereNull('tanggal_keluar')->orWhereDate('tanggal_keluar', '>=', $tanggal))
            ->whereHas('tahunPelajaran', fn ($q) => $q
                ->where(fn ($q) => $q->whereNull('tanggal_mulai')->orWhereDate('tanggal_mulai', '<=', $tanggal))
                ->where(fn ($q) => $q->whereNull('tanggal_selesai')->orWhereDate('tanggal_selesai', '>=', $tanggal)));
    }

    public function jumlah(Collection $presensi, array $tanggalEfektif, array $hariAktif, ?AnggotaKelas $anggota = null): int
    {
        $tercatat = $presensi->mapWithKeys(fn ($item) => [$item->tanggal->toDateString() => true]);

        return collect($tanggalEfektif)->filter(fn ($tanggal) => ! $tercatat->has($tanggal)
            && $this->menjadiAlfa($tanggal, $hariAktif, $anggota))->count();
    }
}
