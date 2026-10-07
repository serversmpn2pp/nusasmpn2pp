<?php

namespace App\Services\Absensi;

use App\Models\AnggotaKelas;
use App\Models\PengaturanAbsensi;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AturanAlfaOtomatisSiswaService
{
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
            && (! $anggota?->tanggal_masuk || $hari->gte($anggota->tanggal_masuk))
            && (! $anggota?->tanggal_keluar || $hari->lte($anggota->tanggal_keluar))
            && (! $tahun?->tanggal_mulai || $hari->gte($tahun->tanggal_mulai))
            && (! $tahun?->tanggal_selesai || $hari->lte($tahun->tanggal_selesai));
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
