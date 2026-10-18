<?php

namespace App\Services;

use App\Models\AbsensiPegawai;
use App\Models\LaporanPembinaanSiswa;
use App\Models\Pegawai;
use App\Models\PengaturanAbsensiPegawai;
use App\Models\TahunPelajaran;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class StatistikDashboardAdminService
{
    public function bangun(CarbonInterface $waktu, ?TahunPelajaran $tahun): array
    {
        $hari = Carbon::instance($waktu)->copy()->locale('id');
        $awal = $hari->copy()->startOfMonth()->min($hari->copy()->startOfWeek(CarbonInterface::MONDAY));

        return [
            'pegawai' => $this->pegawai($awal, $hari),
            'laporan' => $this->laporan($awal, $hari, $tahun),
        ];
    }

    private function pegawai(Carbon $awal, Carbon $hari): array
    {
        $pegawai = Pegawai::where('aktif', true)->get(['id', 'jenis_pegawai', 'tanggal_mulai_bertugas', 'tanggal_mulai_kerja']);
        $jadwal = PengaturanAbsensiPegawai::where('aktif', true)->orderBy('id')->get()
            ->groupBy('hari');
        $catatan = AbsensiPegawai::whereIn('pegawai_id', $pegawai->modelKeys())
            ->whereDate('tanggal', '>=', $awal)->whereDate('tanggal', '<=', $hari)
            ->get(['tanggal', 'pegawai_id', 'status_kehadiran', 'status_masuk', 'menit_terlambat', 'jam_masuk'])
            ->keyBy(fn ($p) => $p->tanggal->toDateString().'|'.$p->pegawai_id);
        $perHari = [];
        $kedatangan = collect();
        foreach (CarbonPeriod::create($awal, $hari) as $tanggal) {
            $tgl = $tanggal->toDateString();
            $rekap = array_fill_keys(['present', 'late', 'permit', 'absent', 'pending', 'opportunities', 'untimed'], 0);
            $jadwalHari = $jadwal->get(array_keys(PengaturanAbsensiPegawai::DAFTAR_HARI)[$tanggal->isoWeekday() - 1], collect());
            foreach ($pegawai as $p) {
                $mulai = $p->tanggal_mulai_bertugas ?? $p->tanggal_mulai_kerja;
                if ($mulai && $mulai->toDateString() > $tgl) {
                    continue;
                }
                $absen = $catatan->get($tgl.'|'.$p->id);
                $terjadwal = $jadwalHari->contains(fn ($j) => $j->cakupan === 'semua'
                    || ($j->cakupan === 'pegawai' && $j->pegawai_id === $p->id)
                    || ($j->cakupan === 'jenis_pegawai' && filled($p->jenis_pegawai) && $j->jenis_pegawai === $p->jenis_pegawai));
                // Catatan manual tetap dihitung meskipun jadwal kemudian berubah.
                if (! $terjadwal && ! $absen) {
                    continue;
                }
                $status = $absen?->status_kehadiran ?? ($tgl === $hari->toDateString() ? 'belum_scan' : 'alfa');
                $key = match ($status) {
                    'hadir' => 'present', 'izin', 'sakit', 'dinas_luar', 'cuti' => 'permit', 'alfa' => 'absent', default => 'pending',
                };
                $rekap[$key]++;
                $rekap['opportunities']++;
                if ($key === 'present') {
                    $terlambat = $absen->menit_terlambat > 0 || $absen->status_masuk === 'terlambat';
                    $rekap['late'] += (int) $terlambat;
                    if ($tgl === $hari->toDateString()) {
                        if ($absen->jam_masuk && substr($absen->jam_masuk, 0, 5) <= $hari->format('H:i')) {
                            $kedatangan->push(['jam' => substr($absen->jam_masuk, 0, 5), 'late' => (int) $terlambat]);
                        } else {
                            $rekap['untimed']++;
                        }
                    }
                }
            }
            $perHari[$tgl] = $rekap;
        }

        $hasil = [];
        foreach ($this->periode($hari) as $key => $meta) {
            $dipilih = collect($perHari)->filter(fn ($r, $tgl) => $tgl >= $meta['start']);
            $summary = collect(array_keys(reset($perHari)))->mapWithKeys(fn ($k) => [$k => (int) $dipilih->sum($k)])->all();
            $points = $key === 'day' ? $this->kurvaPegawai($kedatangan, $hari) : $dipilih->map(fn ($r, $tgl) => [
                ...$this->labelTanggal($tgl), 'present' => $r['opportunities'] ? $r['present'] : null, 'late' => $r['opportunities'] ? $r['late'] : null,
            ])->values()->all();
            $hasil[$key] = [...$meta, 'subtitle' => $key === 'day' ? 'Akumulasi kedatangan pegawai' : 'Kehadiran pegawai per hari',
                'axis' => $key === 'day' ? 'Jam masuk (WIB)' : 'Tanggal', 'summary' => $summary, 'points' => $points,
                'empty' => ! $summary['opportunities'] ? 'Tidak ada jadwal atau catatan presensi pegawai pada periode ini.'
                    : ($key === 'day' && $points === [] ? 'Belum ada jam kedatangan pegawai tercatat hari ini.' : null),
                'note' => 'Terlambat termasuk hadir. Izin mencakup sakit, dinas, dan cuti.',
                'untimed' => $key === 'day' && $summary['untimed'] ? $summary['untimed'].' kehadiran tanpa jam masuk valid tidak digambarkan.' : null,
            ];
        }

        return ['series' => [['key' => 'present', 'label' => 'Hadir', 'color' => '#15477a'], ['key' => 'late', 'label' => 'Terlambat', 'color' => '#ba9010']],
            'metrics' => ['present' => 'Hadir', 'late' => 'Terlambat', 'permit' => 'Izin / sakit / dinas / cuti', 'pending' => 'Belum scan', 'absent' => 'Alfa'],
            'periods' => $hasil];
    }

    private function laporan(Carbon $awal, Carbon $hari, ?TahunPelajaran $tahun): array
    {
        $catatan = $tahun ? LaporanPembinaanSiswa::where('tahun_pelajaran_id', $tahun->id)
            ->whereDate('tanggal_kejadian', '>=', $tahun->tanggal_mulai ? $awal->copy()->max($tahun->tanggal_mulai) : $awal)
            ->whereDate('tanggal_kejadian', '<=', $tahun->tanggal_selesai ? $hari->copy()->min($tahun->tanggal_selesai) : $hari)
            ->where('status', '!=', 'dibatalkan')
            ->where(fn ($q) => $q->whereNull('status_verifikasi')->orWhere('status_verifikasi', '!=', 'dibatalkan'))
            ->whereIn('jenis_laporan', array_keys(LaporanPembinaanSiswa::DAFTAR_JENIS_LAPORAN))
            ->get(['tanggal_kejadian', 'waktu_kejadian', 'jenis_laporan', 'status']) : collect();
        $hasil = [];
        foreach ($this->periode($hari) as $key => $meta) {
            $dipilih = $catatan->filter(fn ($r) => $r->tanggal_kejadian->toDateString() >= $meta['start']);
            $summary = ['total' => $dipilih->count(), 'finished' => $dipilih->where('status', 'selesai')->count(),
                'pending' => $dipilih->where('status', '!=', 'selesai')->count()];
            $points = [];
            $untimed = 0;
            if ($key === 'day') {
                $valid = $dipilih->filter(fn ($r) => filled($r->waktu_kejadian) && substr($r->waktu_kejadian, 0, 5) <= $hari->format('H:i'));
                $untimed = $dipilih->count() - $valid->count();
                $jamMulai = $valid->isEmpty() ? null : (int) substr($valid->min('waktu_kejadian'), 0, 2);
                foreach ($jamMulai === null ? [] : range($jamMulai, $hari->hour) as $jam) {
                    $perJam = $valid->filter(fn ($r) => (int) substr($r->waktu_kejadian, 0, 2) === $jam);
                    $points[] = ['date' => sprintf('%02d.00', $jam), ...$this->jenisLaporan($perJam)];
                }
            } else {
                $mulai = $tahun?->tanggal_mulai ? Carbon::parse($meta['start'])->max($tahun->tanggal_mulai) : Carbon::parse($meta['start']);
                $akhir = $tahun?->tanggal_selesai ? $hari->copy()->min($tahun->tanggal_selesai) : $hari;
                if ($tahun && $mulai->lte($akhir)) {
                    foreach (CarbonPeriod::create($mulai, $akhir) as $tgl) {
                        $perTanggal = $dipilih->filter(fn ($r) => $r->tanggal_kejadian->isSameDay($tgl));
                        $points[] = [...$this->labelTanggal($tgl->toDateString()), ...$this->jenisLaporan($perTanggal)];
                    }
                }
            }
            $hasil[$key] = [...$meta, 'type' => 'bar', 'subtitle' => $key === 'day' ? 'Jumlah laporan per jam kejadian' : 'Jumlah laporan per tanggal kejadian',
                'axis' => $key === 'day' ? 'Jam kejadian (WIB)' : 'Tanggal', 'summary' => $summary, 'points' => $points,
                'empty' => ! $tahun ? 'Belum ada tahun pelajaran aktif.' : (! $summary['total'] ? 'Belum ada laporan siswa pada periode ini.'
                    : ($key === 'day' && $untimed === $summary['total'] ? 'Laporan tersedia, tetapi jam kejadian belum tercatat valid.' : null)),
                'note' => 'Jumlah laporan, bukan jumlah siswa atau poin. Laporan dibatalkan tidak dihitung.',
                'untimed' => $untimed ? $untimed.' laporan tanpa jam kejadian valid tetap dihitung dalam ringkasan.' : null,
            ];
        }

        return ['series' => [['key' => 'kejadian', 'label' => 'Kejadian', 'color' => '#15477a'], ['key' => 'pembinaan', 'label' => 'Pembinaan', 'color' => '#0f766e'], ['key' => 'pelanggaran', 'label' => 'Pelanggaran', 'color' => '#ba9010']],
            'metrics' => ['total' => 'Total laporan', 'pending' => 'Belum selesai', 'finished' => 'Selesai'], 'periods' => $hasil];
    }

    private function jenisLaporan(Collection $laporan): array
    {
        return collect(array_keys(LaporanPembinaanSiswa::DAFTAR_JENIS_LAPORAN))
            ->mapWithKeys(fn ($key) => [$key => $laporan->where('jenis_laporan', $key)->count()])->all();
    }

    private function periode(Carbon $hari): array
    {
        $hasil = [];
        foreach (['day' => $hari->copy()->startOfDay(), 'week' => $hari->copy()->startOfWeek(CarbonInterface::MONDAY), 'month' => $hari->copy()->startOfMonth()] as $key => $awal) {
            $hasil[$key] = ['start' => $awal->toDateString(), 'date' => $key === 'day' ? $hari->translatedFormat('j F Y')
                : $awal->translatedFormat('j M').' - '.$hari->translatedFormat('j M Y'), 'type' => $key === 'day' ? 'line' : 'bar'];
        }

        return $hasil;
    }

    private function labelTanggal(string $tanggal): array
    {
        $tgl = Carbon::parse($tanggal)->locale('id');

        return ['date' => $tgl->translatedFormat('j M'), 'full' => $tgl->translatedFormat('l, j F Y')];
    }

    private function kurvaPegawai(Collection $catatan, Carbon $hari): array
    {
        if ($catatan->isEmpty()) {
            return [];
        }
        $menit = fn ($jam) => (int) substr($jam, 0, 2) * 60 + (int) substr($jam, 3, 2);
        $akhir = $hari->hour * 60 + $hari->minute;
        $batas = range((int) floor($menit($catatan->min('jam')) / 30) * 30, $akhir, 30);
        if (end($batas) !== $akhir) {
            $batas[] = $akhir;
        }

        return collect($batas)->map(function ($m) use ($catatan, $menit) {
            $sudah = $catatan->filter(fn ($c) => $menit($c['jam']) <= $m);

            return ['date' => sprintf('%02d.%02d', intdiv($m, 60), $m % 60), 'present' => $sudah->count(), 'late' => $sudah->sum('late')];
        })->all();
    }
}
