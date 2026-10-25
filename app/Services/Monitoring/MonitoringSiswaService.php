<?php

namespace App\Services\Monitoring;

use App\Models\AbsensiSiswa;
use App\Models\AnggotaKelas;
use App\Models\JadwalKegiatanIbadah;
use App\Models\LaporanPembinaanSiswa;
use App\Models\TahunPelajaran;
use App\Models\TransaksiPoinSiswa;
use App\Services\Absensi\AturanAlfaOtomatisSiswaService;
use App\Services\Absensi\GrafikPresensiAdminService;
use App\Services\Ibadah\RekapHarianKegiatanIbadah;
use Carbon\CarbonInterface;

class MonitoringSiswaService
{
    public function bangun(): array
    {
        $waktu = now();
        $tanggal = $waktu->toDateString();
        $tahun = TahunPelajaran::where('aktif', true)->orderByDesc('tanggal_mulai')->first();
        $aturan = app(AturanAlfaOtomatisSiswaService::class);
        $query = AnggotaKelas::with(['siswa:id,nama_lengkap', 'kelas:id,nama', 'tahunPelajaran'])
            ->where('tahun_pelajaran_id', $tahun?->id ?? 0)->where('status_keanggotaan', 'aktif')
            ->whereHas('siswa', fn ($q) => $q->where('aktif', true))
            ->whereHas('kelas', fn ($q) => $q->where('aktif', true));
        $aturan->batasiKeanggotaan($query, $tanggal);
        $anggota = $query->orderByDesc('tanggal_masuk')->orderByDesc('id')->get()->unique('siswa_id');
        $ids = $anggota->pluck('siswa_id');
        $pasangan = $anggota->keyBy(fn ($a) => $a->siswa_id.'|'.$a->kelas_id);
        $presensi = AbsensiSiswa::where('tahun_pelajaran_id', $tahun?->id ?? 0)->whereDate('tanggal', $tanggal)
            ->whereIn('siswa_id', $ids)->latest('id')
            ->get(['id', 'siswa_id', 'kelas_id', 'status_kehadiran', 'status_masuk', 'menit_terlambat', 'jam_masuk', 'updated_at'])
            ->filter(fn ($p) => $pasangan->has($p->siswa_id.'|'.$p->kelas_id))->unique('siswa_id')->keyBy('siswa_id');
        $hariAktif = $aturan->hariAktif();
        $kodeHari = ['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu', 'minggu'][$waktu->isoWeekday() - 1];
        $terjadwal = in_array($kodeHari, $hariAktif, true);
        $ringkasan = array_fill_keys(['dipantau', 'hadir', 'izin', 'sakit', 'alfa', 'belum', 'pengecualian', 'terlambat'], 0);
        $kelas = [];
        $terlambat = collect();
        foreach ($anggota as $a) {
            $p = $presensi->get($a->siswa_id);
            $status = $p?->status_kehadiran ?? ($terjadwal ? $aturan->statusTanpaCatatan($tanggal, $hariAktif, $a) : 'pengecualian');
            $key = match ($status) {
                'hadir', 'izin', 'sakit', 'alfa', 'pengecualian' => $status, default => 'belum',
            };
            $ringkasan['dipantau']++;
            $ringkasan[$key]++;
            $k = $a->kelas_id;
            $kelas[$k] ??= ['nama' => $a->kelas->nama, 'hadir' => 0, 'terlambat' => 0, 'total' => 0];
            $kelas[$k]['total']++;
            $kelas[$k]['hadir'] += (int) ($key === 'hadir');
            if ($key === 'hadir' && ($p->menit_terlambat > 0 || $p->status_masuk === 'terlambat')) {
                $ringkasan['terlambat']++;
                $kelas[$k]['terlambat']++;
                $terlambat->push(['nama' => $a->siswa->nama_lengkap, 'kelas' => $a->kelas->nama, 'menit' => $p->menit_terlambat]);
            }
        }

        // Reports are selected by submission time, not approval time or incident date.
        $laporan = LaporanPembinaanSiswa::where('tahun_pelajaran_id', $tahun?->id ?? 0)->whereIn('siswa_id', $ids)
            ->whereBetween('created_at', [$waktu->copy()->startOfMonth(), $waktu])
            ->where('status', '!=', 'dibatalkan')
            ->where(fn ($q) => $q->whereNull('status_verifikasi')->orWhereNotIn('status_verifikasi', ['dibatalkan', 'tidak_terbukti']))
            ->latest('created_at')->orderByDesc('id')
            ->get(['id', 'siswa_id', 'kelas_id', 'jenis_laporan', 'status_verifikasi', 'created_at']);
        $hariIni = $laporan->filter(fn ($l) => $l->created_at->isSameDay($waktu));
        $bySiswa = $anggota->keyBy('siswa_id');
        $laporanTerbaru = $hariIni->take(18)->map(function ($l) use ($bySiswa) {
            $a = $bySiswa->get($l->siswa_id);
            $privat = $l->jenis_laporan === 'pembinaan';
            $status = match ($l->status_verifikasi) {
                'disahkan' => 'Disahkan', 'ditetapkan_pembinaan' => 'Ditetapkan pembinaan',
                'tidak_perlu' => 'Tercatat', default => 'Belum diverifikasi',
            };

            return ['id' => 'laporan-'.$l->id, 'waktu' => $l->created_at->format('H:i:s'),
                'nama' => $privat ? 'Pembinaan siswa' : $a->siswa->nama_lengkap,
                'kelas' => $privat ? 'Identitas dibatasi' : $a->kelas->nama,
                'jenis' => LaporanPembinaanSiswa::DAFTAR_JENIS_LAPORAN[$l->jenis_laporan] ?? 'Laporan siswa',
                'status' => $status, 'resmi' => $l->status_verifikasi === 'disahkan'];
        })->values()->all();

        $transaksi = TransaksiPoinSiswa::where('tahun_pelajaran_id', $tahun?->id ?? 0)->whereIn('siswa_id', $ids)
            ->where('tercatat_pada', '<=', $waktu);
        $saldo = (clone $transaksi)->selectRaw('siswa_id, SUM(poin) as saldo')->groupBy('siswa_id')->get();
        $poinHariIni = (clone $transaksi)->where('tercatat_pada', '>=', $waktu->copy()->startOfDay())
            ->get(['poin']);
        $poin = ['saldo' => (int) $saldo->sum(fn ($r) => max(0, (int) $r->saldo)),
            'siswa' => $saldo->filter(fn ($r) => $r->saldo > 0)->count(),
            'ditambahkan' => (int) $poinHariIni->where('poin', '>', 0)->sum('poin'),
            'dikurangi' => abs((int) $poinHariIni->where('poin', '<', 0)->sum('poin'))];

        $ibadah = [];
        $daftarKelas = $anggota->pluck('kelas')->unique('id')->values();
        $jadwal = JadwalKegiatanIbadah::with('kegiatanIbadah:id,nama')
            ->where('tahun_pelajaran_id', $tahun?->id ?? 0)->where('hari', $kodeHari)->where('aktif', true)
            ->whereHas('kegiatanIbadah', fn ($q) => $q->where('aktif', true))->orderBy('jam_pelaksanaan')->get()->unique('kegiatan_ibadah_id');
        foreach ($jadwal as $j) {
            $rekap = app(RekapHarianKegiatanIbadah::class)->hitung($tahun, $daftarKelas, $j->kegiatan_ibadah_id, $waktu);
            // Keep individual medical/exemption information out of the TV payload.
            $status = $rekap['status_per_siswa']->only($ids->all());
            $sudah = $status->where('status', RekapHarianKegiatanIbadah::STATUS_SUDAH)->count();
            $belum = $status->where('status', RekapHarianKegiatanIbadah::STATUS_BELUM)->count();
            $ibadah[] = ['nama' => $j->kegiatanIbadah->nama, 'jam' => substr($j->jam_pelaksanaan, 0, 5),
                'sudah' => $sudah, 'wajib' => $sudah + $belum, 'belum' => $belum,
                'persen' => $sudah + $belum > 0 ? (int) round(100 * $sudah / ($sudah + $belum)) : 0];
        }

        $grafik = app(GrafikPresensiAdminService::class)->bangun($waktu, $tahun);
        $grafikLaporan = [];
        foreach (['week' => $waktu->copy()->startOfWeek(CarbonInterface::MONDAY), 'month' => $waktu->copy()->startOfMonth()] as $periode => $mulai) {
            // A week crossing a month boundary must include the preceding month's submissions.
            $sumber = $laporan;
            if ($mulai->lt($waktu->copy()->startOfMonth())) {
                $sumber = LaporanPembinaanSiswa::where('tahun_pelajaran_id', $tahun?->id ?? 0)->whereIn('siswa_id', $ids)
                    ->whereBetween('created_at', [$mulai, $waktu])->where('status', '!=', 'dibatalkan')
                    ->where(fn ($q) => $q->whereNull('status_verifikasi')->orWhereNotIn('status_verifikasi', ['dibatalkan', 'tidak_terbukti']))
                    ->get(['created_at']);
            }
            $grafikLaporan[$periode] = [];
            for ($tgl = $mulai->copy(); $tgl->lte($waktu); $tgl->addDay()) {
                $grafikLaporan[$periode][] = ['date' => $tgl->locale('id')->translatedFormat('j M'),
                    'total' => $sumber->filter(fn ($l) => $l->created_at->isSameDay($tgl))->count()];
            }
        }

        return [
            'diperbarui_pada' => $waktu->toIso8601String(), 'zona_waktu' => config('app.timezone'),
            'tanggal' => $waktu->locale('id')->translatedFormat('l, j F Y'), 'tahun' => $tahun?->nama ?? 'Belum ada tahun pelajaran aktif',
            'pesan' => ! $tahun ? 'Tahun pelajaran aktif belum tersedia.' : (! $terjadwal ? 'Hari ini tidak ada jadwal presensi siswa.' : null),
            'ringkasan' => [...$ringkasan, 'laporan' => $hariIni->count()],
            'grafik_presensi' => $grafik,
            'kelas' => collect($kelas)->sortBy('nama', SORT_NATURAL)->values()->all(),
            'grafik_laporan' => $grafikLaporan, 'laporan_terbaru' => $laporanTerbaru,
            'terlambat' => $terlambat->sortByDesc('menit')->values()->all(),
            'total_menit_terlambat' => (int) $terlambat->sum('menit'), 'ibadah' => $ibadah, 'poin' => $poin,
        ];
    }
}
