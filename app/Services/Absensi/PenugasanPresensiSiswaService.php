<?php

namespace App\Services\Absensi;

use App\Models\AbsensiSiswa;
use App\Models\AnggotaKelas;
use App\Models\LaporanPembinaanSiswa;
use App\Models\PengaturanAbsensi;
use App\Models\PengecualianPresensiSiswa;
use App\Models\RiwayatPerubahanAbsensiSiswa;
use App\Models\TahunPelajaran;
use App\Services\Notifikasi\NotifikasiPenggunaService;
use App\Services\Pembinaan\PoinPresensiOtomatisService;
use App\Services\Pembinaan\ProsesPoinKeterlambatanService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Validation\ValidationException;

class PenugasanPresensiSiswaService
{
    private const BIDANG = ['status_kehadiran', 'status_masuk', 'menit_terlambat', 'sanksi_manual_menit', 'alasan_hadir_manual', 'sumber'];

    public function hitung(array $input): array
    {
        $tahun = TahunPelajaran::findOrFail($input['tahun_pelajaran_id']);
        $mulai = Carbon::parse($input['tanggal_mulai'])->startOfDay();
        $selesai = Carbon::parse($input['tanggal_selesai'])->startOfDay();
        if (! $tahun->tanggal_mulai || ! $tahun->tanggal_selesai || $mulai->lt($tahun->tanggal_mulai)
            || $selesai->gt($tahun->tanggal_selesai) || $mulai->gt($selesai) || $mulai->diffInDays($selesai) > 30) {
            throw ValidationException::withMessages(['tanggal_selesai' => 'Penugasan harus berada dalam tahun pelajaran, maksimal 31 hari.']);
        }
        $ids = collect($input['anggota_kelas_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->sort()->values();
        if ($ids->isEmpty() || $ids->count() > 200) {
            throw ValidationException::withMessages(['anggota_kelas_ids' => 'Pilih 1–200 siswa untuk penugasan sekolah.']);
        }
        $anggota = AnggotaKelas::with(['siswa', 'kelas', 'tahunPelajaran'])->whereIn('id', $ids)
            ->where('tahun_pelajaran_id', $tahun->id)->where('status_keanggotaan', 'aktif')
            ->whereHas('siswa', fn ($q) => $q->where('aktif', true))->whereHas('kelas', fn ($q) => $q->where('aktif', true))
            ->where(fn ($q) => $q->whereNull('tanggal_masuk')->orWhereDate('tanggal_masuk', '<=', $mulai))
            ->where(fn ($q) => $q->whereNull('tanggal_keluar')->orWhereDate('tanggal_keluar', '>=', $selesai))
            ->orderBy('id')->get();
        if ($anggota->count() !== $ids->count()) {
            throw ValidationException::withMessages(['anggota_kelas_ids' => 'Semua siswa harus aktif pada tahun dan seluruh periode penugasan yang dipilih.']);
        }
        $bertumpuk = PengecualianPresensiSiswa::where('aktif', true)->where('tahun_pelajaran_id', $tahun->id)
            ->whereDate('tanggal_mulai', '<=', $selesai)->whereDate('tanggal_selesai', '>=', $mulai)
            ->where(fn ($q) => $q->where(fn ($q) => $q->whereNull('cakupan')->where(fn ($q) => $q->whereNull('kelas_id')->orWhereIn('kelas_id', $anggota->pluck('kelas_id'))))
                ->orWhereHas('anggotaKelas', fn ($q) => $q->whereIn('anggota_kelas.id', $ids)))->exists();
        if ($bertumpuk) {
            throw ValidationException::withMessages(['tanggal_mulai' => 'Siswa terpilih sudah memiliki pengecualian aktif yang bertumpuk. Periksa penetapan sebelumnya.']);
        }
        $presensi = AbsensiSiswa::whereIn('siswa_id', $anggota->pluck('siswa_id'))
            ->whereDate('tanggal', '>=', $mulai)->whereDate('tanggal', '<=', $selesai)->orderBy('id')->get();
        if ($presensi->contains(fn ($p) => (int) $p->tahun_pelajaran_id !== (int) $tahun->id || ! $ids->contains((int) $p->anggota_kelas_id))) {
            throw ValidationException::withMessages(['anggota_kelas_ids' => 'Ada catatan presensi dengan keanggotaan berbeda. Periksa kelas dan tanggal peserta.']);
        }
        if ($presensi->whereIn('status_kehadiran', ['sakit', 'izin'])->isNotEmpty()) {
            throw ValidationException::withMessages(['anggota_kelas_ids' => 'Ada catatan Sakit/Izin pada periode ini. Koreksi catatan yang keliru atau sesuaikan peserta/tanggal penugasan terlebih dahulu.']);
        }
        $tanggal = $this->tanggal($mulai, $selesai);
        if (! $tanggal) {
            throw ValidationException::withMessages(['tanggal_mulai' => 'Tidak ada hari presensi aktif pada rentang ini. Periksa pengaturan hari sekolah.']);
        }
        $data = ['tahun_pelajaran_id' => (int) $tahun->id, 'kelas_id' => null, 'cakupan' => 'siswa', 'anggota_kelas_ids' => $ids->all(),
            'tanggal_mulai' => $mulai->toDateString(), 'tanggal_selesai' => $selesai->toDateString(), 'jenis' => 'tugas_sekolah', 'alasan' => trim($input['alasan'])];
        $perSiswa = $anggota->map(fn ($a) => ['anggota' => $a, 'alfa_dibatalkan' => 0]);
        $poin = LaporanPembinaanSiswa::whereIn('anggota_kelas_id', $ids)->where('sumber_laporan', 'absensi_otomatis')
            ->whereDate('tanggal_kejadian', '>=', $data['tanggal_mulai'])->whereDate('tanggal_kejadian', '<=', $data['tanggal_selesai'])->orderBy('id')->get();
        $dampak = ['siswa_dalam_cakupan' => $anggota->count(), 'siswa_terdampak' => $anggota->count(), 'alfa_otomatis_dibatalkan' => 0,
            'hari_aktif_terlewati' => count($tanggal), 'catatan_dipertahankan' => $presensi->count(), 'alfa_manual' => $presensi->where('status_kehadiran', 'alfa')->count(),
            'hadir_penugasan' => count($tanggal) * $anggota->count(), 'poin_dibatalkan' => (int) $poin->sum('total_poin')];
        $sidik = hash('sha256', json_encode([$data, $tanggal, $anggota->toArray(), $presensi->toArray(), $poin->toArray()], JSON_THROW_ON_ERROR));

        return ['data' => $data, 'tahun' => $tahun, 'kelas' => null, 'dampak' => $dampak, 'sidik' => $sidik, 'perSiswa' => $perSiswa];
    }

    public function tanggal(Carbon $mulai, Carbon $selesai): array
    {
        $aktif = app(AturanAlfaOtomatisSiswaService::class)->hariAktif();
        $hari = array_keys(PengaturanAbsensi::DAFTAR_HARI);

        return collect(CarbonPeriod::create($mulai, $selesai))->filter(fn ($d) => in_array($hari[$d->isoWeekday() - 1], $aktif, true))
            ->map(fn ($d) => $d->toDateString())->values()->all();
    }

    public function terapkan(PengecualianPresensiSiswa $p, array $ids): void
    {
        $p->anggotaKelas()->attach($ids);
        foreach ($p->anggotaKelas()->orderBy('anggota_kelas.id')->get() as $a) {
            AnggotaKelas::whereKey($a->id)->lockForUpdate()->firstOrFail();
            foreach ($this->tanggal($p->tanggal_mulai, $p->tanggal_selesai) as $tanggal) {
                $absensi = AbsensiSiswa::where('siswa_id', $a->siswa_id)->whereDate('tanggal', $tanggal)->lockForUpdate()->first();
                $sebelum = $absensi?->only(self::BIDANG) ?? ['dibuat_penugasan' => true, 'status_kehadiran' => 'hadir', 'status_masuk' => null, 'menit_terlambat' => 0, 'sanksi_manual_menit' => 0, 'alasan_hadir_manual' => null, 'sumber' => 'penugasan_sekolah'];
                $absensi ??= new AbsensiSiswa(['siswa_id' => $a->siswa_id, 'anggota_kelas_id' => $a->id,
                    'tahun_pelajaran_id' => $a->tahun_pelajaran_id, 'kelas_id' => $a->kelas_id, 'tanggal' => $tanggal, 'sumber' => 'penugasan_sekolah']);
                $absensi->fill(['penugasan_presensi_id' => $p->id, 'sebelum_penugasan' => $sebelum, 'status_kehadiran' => 'hadir'])->save();
                if (! ($sebelum['dibuat_penugasan'] ?? false)) {
                    RiwayatPerubahanAbsensiSiswa::create(['absensi_siswa_id' => $absensi->id, 'siswa_id' => $a->siswa_id, 'tanggal' => $tanggal,
                        'status_sebelum' => $sebelum['status_kehadiran'], 'status_sesudah' => 'hadir', 'sumber' => 'penugasan_sekolah',
                        'catatan' => $p->label(), 'dibuat_oleh_pengguna_id' => $p->dibuat_oleh_pengguna_id]);
                }
                $this->sinkronkan($absensi, $p->dibuat_oleh_pengguna_id);
            }
        }
    }

    public function lindungi(AbsensiSiswa $a): void
    {
        if (! $a->penugasan_presensi_id) {
            return;
        }
        $p = PengecualianPresensiSiswa::find($a->penugasan_presensi_id);
        if (! $p?->aktif || $p->jenis !== 'tugas_sekolah') {
            return;
        }
        $awal = $a->isDirty('penugasan_presensi_id');
        if (! $awal && $a->status_kehadiran !== 'hadir') {
            throw ValidationException::withMessages(['status_kehadiran' => 'Siswa memiliki penugasan sekolah aktif. Batalkan penugasan sebelum mengubah status kehadiran.']);
        }
        if (! $awal) {
            $snapshot = $a->sebelum_penugasan ?? [];
            $ubahPresensi = $a->isDirty(['jam_masuk', 'jam_pulang', 'sumber']) && $a->status_masuk !== 'tugas_sekolah';
            foreach (self::BIDANG as $key) {
                if ($ubahPresensi || $a->isDirty($key)) {
                    $snapshot[$key] = $a->{$key};
                }
            }
            $a->sebelum_penugasan = $snapshot;
        }
        $a->forceFill(['status_kehadiran' => 'hadir', 'status_masuk' => 'tugas_sekolah', 'menit_terlambat' => 0,
            'sanksi_manual_menit' => 0, 'alasan_hadir_manual' => null]);
    }

    public function batalkan(PengecualianPresensiSiswa $p, int $penggunaId): void
    {
        foreach (AbsensiSiswa::where('penugasan_presensi_id', $p->id)->lockForUpdate()->get() as $a) {
            $snapshot = $a->sebelum_penugasan ?? [];
            $asli = $snapshot;
            unset($asli['dibuat_penugasan']);
            if (($snapshot['dibuat_penugasan'] ?? false) && ! $a->jam_masuk && ! $a->jam_pulang
                && ! $a->logScanAbsensi()->where('berhasil', true)->exists()) {
                // This row only represented the now-cancelled assignment, with no attendance evidence.
                $pivot = $p->anggotaKelas()->findOrFail($a->anggota_kelas_id)->pivot;
                $arsip = json_decode($pivot->riwayat_pembatalan ?? '[]', true);
                $arsip[$a->tanggal->toDateString()] = ['presensi' => $a->toArray(),
                    'riwayat' => RiwayatPerubahanAbsensiSiswa::where('absensi_siswa_id', $a->id)->get()->toArray()];
                $p->anggotaKelas()->updateExistingPivot($a->anggota_kelas_id, ['riwayat_pembatalan' => json_encode($arsip, JSON_THROW_ON_ERROR)]);
                $a->delete();

                continue;
            }
            $a->forceFill($asli + ['penugasan_presensi_id' => null]);
            $a->penugasan_presensi_id = null;
            $a->sebelum_penugasan = null;
            $a->save();
            RiwayatPerubahanAbsensiSiswa::create(['absensi_siswa_id' => $a->id, 'siswa_id' => $a->siswa_id, 'tanggal' => $a->tanggal,
                'status_sebelum' => 'hadir', 'status_sesudah' => $a->status_kehadiran, 'sumber' => 'pembatalan_penugasan',
                'catatan' => 'Penugasan dibatalkan: '.$p->alasan_pembatalan, 'dibuat_oleh_pengguna_id' => $penggunaId]);
            $this->sinkronkan($a, $penggunaId);
        }
    }

    private function sinkronkan(AbsensiSiswa $a, ?int $penggunaId): void
    {
        if (LaporanPembinaanSiswa::where('absensi_siswa_id', $a->id)->whereNotNull('kunci_presensi_otomatis')->exists()) {
            app(PoinPresensiOtomatisService::class)->sinkronkanAbsensi($a, $penggunaId);
        } else {
            app(ProsesPoinKeterlambatanService::class)->sinkronkanAbsensi($a, $penggunaId);
        }
    }

    public function notifikasi(PengecualianPresensiSiswa $p): void
    {
        $layanan = app(NotifikasiPenggunaService::class);
        foreach ($p->anggotaKelas as $a) {
            $identitas = $a->siswa?->nama_lengkap.' ('.($a->kelas?->nama ?: '-').')';
            $penerima = $layanan->penggunaUntukSiswa($a->siswa_id)->merge($layanan->penggunaOrangTuaUntukSiswa($a->siswa_id))->unique('id');
            foreach ($penerima as $akun) {
                $orangTua = $akun->akunOrangTua();
                $layanan->kirim($akun, 'informasi', $p->aktif ? 'Penugasan sekolah tercatat' : 'Penugasan sekolah dibatalkan',
                    ($orangTua ? 'Anak Anda, '.$identitas : 'Anda, '.$identitas).' '.($p->aktif ? 'tercatat Hadir — Tugas sekolah' : 'memiliki pembatalan penugasan sekolah')
                    .' pada '.$p->tanggal_mulai->format('d/m/Y').' s.d. '.$p->tanggal_selesai->format('d/m/Y').'. '.$p->alasan.($p->aktif ? '' : ' Alasan pembatalan: '.$p->alasan_pembatalan)
                    .($p->aktif ? '. Tidak dikenai sanksi keterlambatan atau tidak scan kartu.' : '. Presensi kembali mengikuti catatan dan aturan yang berlaku.'),
                    route($orangTua ? 'presensi-anak.index' : 'notifikasi.index', ['siswa_id' => $a->siswa_id, 'bulan' => $p->tanggal_mulai->format('Y-m')]),
                    'presensi-penugasan:'.$p->id.':'.$a->siswa_id.':'.($p->aktif ? 'aktif' : 'batal'),
                    ['siswa_id' => $a->siswa_id, 'penugasan_presensi_id' => $p->id, 'tanggal_mulai' => $p->tanggal_mulai->toDateString(),
                        'tanggal_selesai' => $p->tanggal_selesai->toDateString(), 'status_penugasan' => $p->aktif ? 'aktif' : 'batal']);
            }
        }
    }
}
