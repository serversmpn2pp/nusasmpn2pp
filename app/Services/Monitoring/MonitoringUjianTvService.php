<?php

namespace App\Services\Monitoring;

use App\Models\AktivitasKeamananUjianCbt;
use App\Models\JadwalUjianCbt;
use App\Models\KegiatanUjianCbt;
use App\Models\PesertaUjianCbt;
use App\Models\RuangUjianCbt;
use App\Models\SesiUjianCbt;
use App\Services\Cbt\BatasWaktuPesertaUjianCbtService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class MonitoringUjianTvService
{
    // Same observation window as CBT security; monitoring never records a violation.
    private const JEDA_KOMUNIKASI = 120;

    public function pilih(int $kegiatanId, ?int $jadwalId, ?int $sesiId): array
    {
        $kegiatan = KegiatanUjianCbt::whereKey($kegiatanId)->where('status', '!=', 'nonaktif')->firstOrFail(['id', 'nama']);
        $daftar = JadwalUjianCbt::with('mataPelajaran:id,nama')->where('kegiatan_ujian_cbt_id', $kegiatanId)
            ->where('status', '!=', 'dibatalkan')->whereHas('ujianCbt', fn ($q) => $q->where(fn ($q) => $q->whereNull('alur')->orWhere('alur', '!=', 'kelas')))
            ->orderBy('tanggal')->orderBy('waktu_mulai')->orderBy('id')->get();
        if ($jadwalId) {
            $jadwal = $daftar->firstWhere('id', $jadwalId);
            abort_unless($jadwal, 422, 'Jadwal tidak termasuk kegiatan yang diizinkan untuk TV ini.');
        } else {
            $hariIni = $daftar->filter(fn ($j) => $j->tanggal->isToday());
            $jadwal = $hariIni->first(fn ($j) => $j->waktu_mulai <= now()->format('H:i:s') && $j->waktu_selesai >= now()->format('H:i:s'))
                ?? $hariIni->first(fn ($j) => $j->waktu_mulai > now()->format('H:i:s')) ?? $hariIni->last();
        }
        $sesiList = $jadwal ? SesiUjianCbt::where('ujian_cbt_id', $jadwal->ujian_cbt_id)->orderBy('waktu_mulai')->get(['id', 'nama', 'waktu_mulai', 'waktu_selesai']) : collect();
        $sesi = $sesiId ? $sesiList->firstWhere('id', $sesiId) : null;
        abort_if($sesiId && ! $sesi, 422, 'Sesi tidak termasuk jadwal yang dipilih.');

        return [$kegiatan, $jadwal, $sesi, [
            'pilihan_jadwal' => $daftar->map(fn ($j) => ['id' => $j->id, 'label' => $j->tanggal->format('d/m').' | '.($j->mataPelajaran?->nama ?? 'Paket ujian').' | Kelas '.$j->tingkat.' | '.$j->labelWaktu()])->all(),
            'pilihan_sesi' => $sesiList->map(fn ($s) => ['id' => $s->id, 'label' => $s->nama])->all(),
        ]];
    }

    public function bangun(KegiatanUjianCbt $kegiatan, ?JadwalUjianCbt $jadwal, ?SesiUjianCbt $sesi): array
    {
        $now = now();
        $paket = $jadwal?->ujianCbt()->first(['id', 'nama', 'durasi_menit', 'jumlah_soal', 'tanggal_selesai']);
        $soal = $paket ? min(max(0, (int) $paket->jumlah_soal), $paket->soalUjianCbt()->count()) : 0;
        $daftarRuang = $jadwal ? RuangUjianCbt::with(['pengawasUtama:id,nama_lengkap', 'pengawasPendamping:id,nama_lengkap'])
            ->where('ujian_cbt_id', $jadwal->ujian_cbt_id)
            ->where(fn ($q) => $q->where('jadwal_ujian_cbt_id', $jadwal->id)->orWhereNull('jadwal_ujian_cbt_id'))
            ->when($sesi, fn ($q) => $q->where('sesi_ujian_cbt_id', $sesi->id))
            ->orderBy('kode')->orderBy('id')->get() : collect();
        $peserta = $jadwal ? PesertaUjianCbt::with(['sesiUjianCbt:id,nama,waktu_selesai', 'anggotaKelas.siswa:id,nama_lengkap', 'anggotaKelas.kelas:id,nama'])
            ->where('ujian_cbt_id', $jadwal->ujian_cbt_id)->when($sesi, fn ($q) => $q->where('sesi_ujian_cbt_id', $sesi->id))
            ->select(['id', 'ujian_cbt_id', 'sesi_ujian_cbt_id', 'anggota_kelas_id', 'ruang_ujian_cbt_id', 'nomor_meja', 'status', 'status_kehadiran_ujian',
                'waktu_mulai', 'waktu_selesai', 'cara_selesai', 'waktu_tambahan_sampai', 'status_susulan', 'susulan_selesai',
                'heartbeat_terakhir_pada', 'sesi_ujian_mulai_pada', 'ditahan_mode_aman_pada', 'dibuka_mode_aman_pada', 'jumlah_pindah_aplikasi'])
            ->withCount(['jawabanPesertaUjianCbt as jawaban_tersimpan' => fn ($q) => $q->whereNotNull('jawaban')])->get() : collect();
        $ruangIds = $daftarRuang->pluck('id')->all();
        $baris = $peserta->map(function ($p) use ($paket, $soal, $ruangIds, $jadwal, $now) {
            $p->setRelation('ujianCbt', $paket);
            $status = match ($p->status) {
                'sedang_mengerjakan' => 'mengerjakan', 'selesai', 'terblokir', 'nonaktif' => $p->status,
                default => match ($p->status_kehadiran_ujian) {
                    'hadir', 'terlambat' => 'hadir_belum_mulai', 'izin', 'sakit', 'alfa' => 'tidak_hadir', default => 'belum_hadir',
                },
            };
            $batas = app(BatasWaktuPesertaUjianCbtService::class)->batasAkses($p);
            $heartbeat = $p->heartbeat_terakhir_pada;
            $terakhir = $heartbeat ?? $p->sesi_ujian_mulai_pada ?? $p->waktu_mulai;
            $peringatan = [];
            if ($status === 'terblokir' || ($p->ditahan_mode_aman_pada && (! $p->dibuka_mode_aman_pada || $p->dibuka_mode_aman_pada->lt($p->ditahan_mode_aman_pada)))) {
                $peringatan[] = 'Sesi memerlukan penanganan';
            }
            if ($status === 'mengerjakan' && $terakhir && $terakhir->lt($now->copy()->subSeconds(self::JEDA_KOMUNIKASI))) {
                $peringatan[] = 'Komunikasi belum diperbarui > 2 menit';
            }
            if ($status === 'mengerjakan' && $batas && $batas->lte($now)) {
                $peringatan[] = 'Waktu habis, menunggu penutupan sistem';
            }
            if ($status === 'hadir_belum_mulai' && Carbon::parse($jadwal->tanggal->toDateString().' '.$jadwal->waktu_mulai)->lte($now)) {
                $peringatan[] = 'Hadir tetapi belum mulai';
            }
            if (in_array($status, ['mengerjakan', 'terblokir'], true) && $p->jumlah_pindah_aplikasi > 0) {
                $peringatan[] = 'Catatan perpindahan aplikasi';
            }

            return ['id' => $p->id, 'ruang_id' => in_array($p->ruang_ujian_cbt_id, $ruangIds, true) ? $p->ruang_ujian_cbt_id : 0,
                'nama' => $p->anggotaKelas?->siswa?->nama_lengkap ?? 'Peserta tidak tersedia', 'kelas' => $p->anggotaKelas?->kelas?->nama ?? '-',
                'meja' => $p->nomor_meja, 'sesi' => $p->sesiUjianCbt?->nama ?? 'Tanpa sesi', 'status' => $status,
                'presensi' => PesertaUjianCbt::DAFTAR_STATUS_KEHADIRAN[$p->status_kehadiran_ujian] ?? 'Belum hadir',
                'jawaban' => min($soal, max(0, $p->jawaban_tersimpan)), 'jumlah_soal' => $soal,
                'persen' => $soal > 0 ? (int) min(100, round(100 * $p->jawaban_tersimpan / $soal)) : 0,
                'batas_waktu' => $batas?->toIso8601String(), 'tambahan_waktu' => $p->waktu_tambahan_sampai !== null,
                'komunikasi_terakhir' => $heartbeat?->toIso8601String(), 'peringatan' => $peringatan,
                'cara_selesai' => $status === 'selesai' ? ($p->cara_selesai === 'waktu_habis' ? 'Waktu habis' : 'Selesai mengerjakan') : null];
        })->sortBy(fn ($p) => sprintf('%09d|%09d|%s', $p['ruang_id'], $p['meja'] ?? 99999, $p['nama']))->values();
        $ruang = $daftarRuang->map(fn ($r) => ['id' => $r->id, 'nama' => $r->nama, 'kode' => $r->kode,
            'pengawas' => collect([$r->pengawasUtama?->nama_lengkap, $r->pengawasPendamping?->nama_lengkap])->filter()->implode(' / ') ?: 'Belum ditugaskan',
            'sesi' => $baris->where('ruang_id', $r->id)->pluck('sesi')->unique()->implode(', ') ?: '-',
            'ringkasan' => $this->ringkas($baris->where('ruang_id', $r->id))])->values();
        if ($baris->contains('ruang_id', 0)) {
            $ruang->push(['id' => 0, 'nama' => 'Belum ditempatkan', 'kode' => '-', 'pengawas' => '-', 'sesi' => '-', 'ringkasan' => $this->ringkas($baris->where('ruang_id', 0))]);
        }
        $perhatian = $baris->filter(fn ($p) => $p['peringatan'] !== [])->values()->all();
        $aktivitas = $this->aktivitas($peserta, $baris, $ruang, $now);

        return ['diperbarui_pada' => $now->toIso8601String(), 'zona_waktu' => config('app.timezone'), 'kegiatan' => $kegiatan->nama,
            'jadwal' => $jadwal ? ['id' => $jadwal->id, 'nama' => $jadwal->mataPelajaran?->nama ?? $paket?->nama,
                'tanggal' => $jadwal->tanggal->locale('id')->translatedFormat('l, j F Y'), 'jam' => $jadwal->labelWaktu(), 'tingkat' => $jadwal->tingkat] : null,
            'sesi_id' => $sesi?->id, 'ringkasan' => $this->ringkas($baris), 'ruang' => $ruang->all(), 'peserta' => $baris->all(),
            'perhatian' => $perhatian, 'aktivitas' => $aktivitas, 'grafik_selesai' => $this->grafikSelesai($peserta, $jadwal, $now),
            'pesan' => ! $jadwal ? 'Tidak ada paket pada jadwal hari ini.' : ($peserta->isEmpty() ? 'Belum ada peserta pada jadwal dan sesi ini.' : (! $jadwal->tanggal->isToday() ? 'Menampilkan jadwal di luar hari ini.' : null))];
    }

    private function ringkas(Collection $baris): array
    {
        $result = ['total' => $baris->count()];
        foreach (['belum_hadir', 'hadir_belum_mulai', 'tidak_hadir', 'mengerjakan', 'selesai', 'terblokir', 'nonaktif'] as $status) {
            $result[$status] = $baris->where('status', $status)->count();
        }
        $result['perlu_perhatian'] = $baris->filter(fn ($p) => $p['peringatan'] !== [])->count();
        $result['persen_selesai'] = $result['total'] > 0 ? (int) round(100 * $result['selesai'] / $result['total']) : 0;

        return $result;
    }

    private function aktivitas(Collection $peserta, Collection $baris, Collection $ruang, $now): array
    {
        $namaRuang = $ruang->pluck('nama', 'id');
        $peta = $baris->keyBy('id');
        $jenis = ['keluar_aplikasi' => 'Aktivitas keluar aplikasi', 'keluar_pengerjaan' => 'Meninggalkan pengerjaan',
            'pemulihan_koneksi' => 'Rekonsiliasi koneksi', 'buka_mode_aman' => 'Sesi dibuka pengawas', 'reset_perangkat' => 'Reset perangkat oleh petugas',
            'pola_keluar_singkat' => 'Catatan perpindahan berulang'];
        $events = AktivitasKeamananUjianCbt::whereIn('peserta_ujian_cbt_id', $peserta->pluck('id'))
            ->whereIn('jenis', array_keys($jenis))->where('mulai_pada', '<=', $now)->latest('mulai_pada')->latest('id')->limit(20)
            ->get(['id', 'peserta_ujian_cbt_id', 'jenis', 'mulai_pada'])->map(function ($e) use ($peta, $namaRuang, $jenis) {
                $p = $peta->get($e->peserta_ujian_cbt_id);

                return ['id' => 'event-'.$e->id, 'waktu' => $e->mulai_pada->toIso8601String(), 'nama' => $p['nama'],
                    'peserta_id' => $p['id'], 'ruang_id' => $p['ruang_id'], 'kelas' => $p['kelas'], 'sesi' => $p['sesi'],
                    'ruang' => $namaRuang->get($p['ruang_id'], '-'), 'label' => $jenis[$e->jenis]];
            });
        foreach ($peserta as $p) {
            foreach (['waktu_mulai' => 'Mulai mengerjakan', 'waktu_selesai' => 'Selesai mengerjakan'] as $field => $label) {
                if (! $p->$field || $p->$field->gt($now)) {
                    continue;
                }
                $row = $peta->get($p->id);
                $events->push(['id' => $field.'-'.$p->id, 'waktu' => $p->$field->toIso8601String(), 'nama' => $row['nama'],
                    'peserta_id' => $row['id'], 'ruang_id' => $row['ruang_id'], 'kelas' => $row['kelas'], 'sesi' => $row['sesi'],
                    'ruang' => $namaRuang->get($row['ruang_id'], '-'), 'label' => $label]);
            }
        }

        return $events->sortByDesc(fn ($e) => $e['waktu'].'|'.$e['id'])->take(20)->values()->all();
    }

    private function grafikSelesai(Collection $peserta, ?JadwalUjianCbt $jadwal, $now): array
    {
        if (! $jadwal) {
            return [];
        }
        $awal = Carbon::parse($jadwal->tanggal->toDateString().' '.$jadwal->waktu_mulai);
        $akhir = $now->copy()->min($jadwal->tanggal->copy()->endOfDay());
        if ($awal->gt($akhir)) {
            return [];
        }
        $selesai = $peserta->where('status', 'selesai')->filter(fn ($p) => $p->waktu_selesai && $p->waktu_selesai->lte($now));
        $points = [];
        for ($t = $awal->copy(); $t->lt($akhir); $t->addMinutes(10)) {
            $points[] = ['jam' => $t->format('H:i'), 'selesai' => $selesai->filter(fn ($p) => $p->waktu_selesai->lte($t))->count()];
        }
        $points[] = ['jam' => $akhir->format('H:i'), 'selesai' => $selesai->filter(fn ($p) => $p->waktu_selesai->lte($akhir))->count()];

        return $points;
    }
}
