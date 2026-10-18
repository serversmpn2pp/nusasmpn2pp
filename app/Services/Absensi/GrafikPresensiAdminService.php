<?php

namespace App\Services\Absensi;

use App\Models\AbsensiSiswa;
use App\Models\AnggotaKelas;
use App\Models\PengaturanAbsensi;
use App\Models\TahunPelajaran;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class GrafikPresensiAdminService
{
    public function bangun(CarbonInterface $waktu, ?TahunPelajaran $tahun): array
    {
        $hari = Carbon::instance($waktu)->copy();
        $aturan = app(AturanAlfaOtomatisSiswaService::class);
        $hariAktif = $aturan->hariAktif();
        $awal = $hari->copy()->startOfMonth()->min($hari->copy()->startOfWeek(CarbonInterface::MONDAY));
        $mulai = $tahun?->tanggal_mulai ? $awal->copy()->max($tahun->tanggal_mulai) : $awal;
        $akhir = $tahun?->tanggal_selesai ? $hari->copy()->min($tahun->tanggal_selesai->copy()->endOfDay()) : $hari;
        $tanggal = $tahun ? $aturan->tanggalEfektif($mulai, $akhir, $hariAktif) : [];
        $anggota = $tahun ? AnggotaKelas::with(['tahunPelajaran', 'siswa:id,aktif'])
            ->where('tahun_pelajaran_id', $tahun->id)
            ->where(fn ($q) => $q->where('status_keanggotaan', 'aktif')->orWhereNotNull('tanggal_keluar'))
            ->where(fn ($q) => $q->whereHas('siswa', fn ($q) => $q->where('aktif', true))->orWhereNotNull('tanggal_keluar'))
            ->orderByDesc('tanggal_masuk')->orderByDesc('id')->get() : collect();
        $presensi = $tahun && $tanggal !== [] ? AbsensiSiswa::where('tahun_pelajaran_id', $tahun->id)
            ->whereDate('tanggal', '>=', reset($tanggal))->whereDate('tanggal', '<=', end($tanggal))->orderBy('id')
            ->get(['id', 'tanggal', 'siswa_id', 'kelas_id', 'status_kehadiran', 'menit_terlambat', 'status_masuk', 'jam_masuk'])
            ->keyBy(fn ($p) => $p->tanggal->toDateString().'|'.$p->siswa_id.'|'.$p->kelas_id) : collect();
        $perHari = [];
        $kedatangan = collect();
        foreach ($tanggal as $tgl) {
            $rekap = $this->ringkasanKosong();
            $terpilih = $anggota->filter(fn ($a) => (! $a->tanggal_masuk || $a->tanggal_masuk->toDateString() <= $tgl)
                && (! $a->tanggal_keluar || $a->tanggal_keluar->toDateString() >= $tgl))->unique('siswa_id');
            foreach ($terpilih as $a) {
                $p = $presensi->get($tgl.'|'.$a->siswa_id.'|'.$a->kelas_id);
                $status = $p?->status_kehadiran ?? $aturan->statusTanpaCatatan($tgl, $hariAktif, $a);
                $key = match ($status) {
                    'hadir' => 'present', 'izin' => 'permit', 'sakit' => 'sick', 'alfa' => 'absent',
                    'pengecualian' => 'excluded', default => 'pending',
                };
                $rekap[$key]++;
                if ($key !== 'present') {
                    continue;
                }
                $terlambat = $p->menit_terlambat > 0 || $p->status_masuk === 'terlambat';
                $rekap['late'] += (int) $terlambat;
                if ($tgl === $hari->toDateString()) {
                    if ($p->jam_masuk && substr($p->jam_masuk, 0, 5) <= $hari->format('H:i')) {
                        $kedatangan->push(['jam' => substr($p->jam_masuk, 0, 5), 'terlambat' => $terlambat]);
                    } else {
                        $rekap['untimed']++;
                    }
                }
            }
            $rekap['opportunities'] = $terpilih->count() - $rekap['excluded'];
            $rekap['days'] = (int) ($rekap['opportunities'] > 0);
            $perHari[$tgl] = $rekap;
        }
        $kosong = ! $tahun ? 'Belum ada tahun pelajaran aktif.'
            : ($hariAktif === [] ? 'Jadwal presensi siswa belum diatur.' : 'Tidak ada kewajiban presensi pada periode ini.');
        $hasil = [];
        foreach (['day', 'week', 'month'] as $periode) {
            $tanggalMulai = match ($periode) {
                'day' => $hari->copy()->startOfDay(), 'week' => $hari->copy()->startOfWeek(CarbonInterface::MONDAY), default => $hari->copy()->startOfMonth(),
            };
            $rekapPeriode = collect($perHari)->filter(fn ($r, $tgl) => $tgl >= $tanggalMulai->toDateString());
            $ringkasan = $this->ringkasanKosong();
            foreach ($rekapPeriode as $r) {
                foreach ($ringkasan as $key => $nilai) {
                    $ringkasan[$key] += $r[$key];
                }
            }
            $points = $periode === 'day' ? $this->kedatangan($kedatangan, $hari) : $rekapPeriode->map(function ($r, $tgl) {
                $date = Carbon::parse($tgl)->locale('id');

                return ['date' => $date->translatedFormat('j M'), 'full' => $date->translatedFormat('l, j F Y'),
                    'present' => $r['opportunities'] > 0 ? $r['present'] : null,
                    'late' => $r['opportunities'] > 0 ? $r['late'] : null];
            })->values()->all();
            $hasil[$periode] = [
                'title' => $periode === 'day' ? 'Kehadiran hari ini' : 'Rekap '.$ringkasan['days'].' hari presensi',
                'subtitle' => $periode === 'day' ? 'Akumulasi kedatangan per jam' : 'Kehadiran per hari presensi',
                'date' => $periode === 'day' ? $hari->copy()->locale('id')->translatedFormat('j F Y')
                    : $tanggalMulai->locale('id')->translatedFormat('j M').' - '.$hari->copy()->locale('id')->translatedFormat('j M Y'),
                'axis' => $periode === 'day' ? 'Jam masuk (WIB)' : 'Tanggal',
                'type' => $periode === 'week' ? 'bar' : 'line', 'points' => $points, 'summary' => $ringkasan,
                'empty' => $ringkasan['opportunities'] === 0 ? $kosong : ($periode === 'day' && $points === [] ? 'Belum ada jam kedatangan tercatat hari ini.' : null),
            ];
        }

        return $hasil;
    }

    private function ringkasanKosong(): array
    {
        return array_fill_keys(['present', 'late', 'permit', 'sick', 'absent', 'pending', 'excluded', 'untimed', 'opportunities', 'days'], 0);
    }

    private function kedatangan(Collection $catatan, Carbon $hari): array
    {
        if ($catatan->isEmpty()) {
            return [];
        }
        $menit = fn (string $jam) => (int) substr($jam, 0, 2) * 60 + (int) substr($jam, 3, 2);
        $jadwal = PengaturanAbsensi::where('aktif', true)
            ->where('hari', array_keys(PengaturanAbsensi::DAFTAR_HARI)[$hari->isoWeekday() - 1])->first();
        $awal = min($menit($catatan->min('jam')), $jadwal?->jam_scan_masuk_mulai ? $menit($jadwal->jam_scan_masuk_mulai) : 1440);
        $akhir = $hari->hour * 60 + $hari->minute;
        $batas = range((int) floor($awal / 30) * 30, $akhir, 30);
        if (end($batas) !== $akhir) {
            $batas[] = $akhir;
        }

        return collect($batas)->map(function ($batas) use ($catatan, $menit) {
            $sudahDatang = $catatan->filter(fn ($p) => $menit($p['jam']) <= $batas);

            return ['date' => sprintf('%02d.%02d', intdiv($batas, 60), $batas % 60),
                'present' => $sudahDatang->count(), 'late' => $sudahDatang->where('terlambat', true)->count()];
        })->all();
    }
}
