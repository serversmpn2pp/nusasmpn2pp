<?php

namespace App\Services\Absensi;

use App\Models\AbsensiSiswa;
use App\Models\AnggotaKelas;
use App\Models\Kelas;
use App\Models\PengecualianPresensiSiswa;
use App\Models\Pengguna;
use App\Models\TahunPelajaran;
use Carbon\Carbon;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PengecualianPresensiSiswaService
{
    public function pastikanAkses(Pengguna $pengguna): void
    {
        abort_unless($pengguna->aktif && $pengguna->memilikiIzin('absensi.pengaturan_kelola')
            && ! $pengguna->akunSiswa() && ! $pengguna->akunOrangTua()
            && ! $pengguna->membatasiCakupanWaliKelas(), 403);
    }

    public function pratinjau(Pengguna $pengguna, array $data): array
    {
        $this->pastikanAkses($pengguna);
        $hasil = $this->hitung($data);
        $hasil['token'] = Crypt::encryptString(json_encode([
            'tujuan' => 'pengecualian-presensi',
            'pengguna_id' => $pengguna->id, 'data' => $hasil['data'], 'sidik' => $hasil['sidik'],
            'berlaku_sampai' => now()->addMinutes(15)->timestamp, 'kunci' => (string) Str::uuid(),
        ], JSON_THROW_ON_ERROR));

        return $hasil;
    }

    public function terapkan(Pengguna $pengguna, string $token): PengecualianPresensiSiswa
    {
        $this->pastikanAkses($pengguna);
        try {
            $isi = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException $e) {
            throw ValidationException::withMessages(['token' => 'Pratinjau tidak valid. Buat pratinjau kembali.']);
        }
        if (! is_array($isi) || ($isi['tujuan'] ?? null) !== 'pengecualian-presensi'
            || ! isset($isi['pengguna_id'], $isi['berlaku_sampai'], $isi['sidik'], $isi['kunci'], $isi['data'])
            || ! is_array($isi['data']) || ! is_string($isi['sidik']) || ! is_string($isi['kunci'])
            || ! isset($isi['data']['tahun_pelajaran_id'], $isi['data']['tanggal_mulai'], $isi['data']['tanggal_selesai'], $isi['data']['jenis'], $isi['data']['alasan'])) {
            throw ValidationException::withMessages(['token' => 'Pratinjau tidak valid. Buat pratinjau kembali.']);
        }
        if ((int) $isi['pengguna_id'] !== (int) $pengguna->id || $isi['berlaku_sampai'] < now()->timestamp) {
            throw ValidationException::withMessages(['token' => 'Pratinjau kedaluwarsa atau bukan milik akun ini. Buat pratinjau kembali.']);
        }

        return DB::transaction(function () use ($isi, $pengguna) {
            // Serialize scope changes for the same school year, including global/class overlaps.
            TahunPelajaran::lockForUpdate()->findOrFail($isi['data']['tahun_pelajaran_id']);
            if ($tersimpan = PengecualianPresensiSiswa::where('kunci_pratinjau', $isi['kunci'])->first()) {
                if (! $tersimpan->aktif) {
                    throw ValidationException::withMessages(['token' => 'Pratinjau ini sudah dipakai dan penetapannya dibatalkan. Buat pratinjau baru.']);
                }

                return $tersimpan;
            }
            $hasil = $this->hitung($isi['data']);
            if (! hash_equals($isi['sidik'], $hasil['sidik'])) {
                throw ValidationException::withMessages(['token' => 'Data presensi atau cakupan berubah. Periksa pratinjau terbaru sebelum menerapkan.']);
            }

            return PengecualianPresensiSiswa::create([
                ...$hasil['data'], 'aktif' => true, 'kunci_pratinjau' => $isi['kunci'],
                'dampak_pratinjau' => $hasil['dampak'], 'dibuat_oleh_pengguna_id' => $pengguna->id,
            ]);
        });
    }

    public function batalkan(Pengguna $pengguna, PengecualianPresensiSiswa $pengecualian, string $alasan): void
    {
        $this->pastikanAkses($pengguna);
        DB::transaction(function () use ($pengguna, $pengecualian, $alasan) {
            TahunPelajaran::lockForUpdate()->findOrFail($pengecualian->tahun_pelajaran_id);
            $p = PengecualianPresensiSiswa::lockForUpdate()->findOrFail($pengecualian->id);
            if ($p->aktif) {
                $p->update(['aktif' => false, 'dibatalkan_pada' => now(),
                    'dibatalkan_oleh_pengguna_id' => $pengguna->id, 'alasan_pembatalan' => trim($alasan)]);
            }
        });
    }

    private function hitung(array $data): array
    {
        $tahun = TahunPelajaran::findOrFail($data['tahun_pelajaran_id']);
        $kelas = filled($data['kelas_id'] ?? null) ? Kelas::where('tahun_pelajaran_id', $tahun->id)->find($data['kelas_id']) : null;
        if (filled($data['kelas_id'] ?? null) && ! $kelas) {
            throw ValidationException::withMessages(['kelas_id' => 'Kelas harus berasal dari tahun pelajaran yang dipilih.']);
        }
        $mulai = Carbon::parse($data['tanggal_mulai'])->startOfDay();
        $selesai = Carbon::parse($data['tanggal_selesai'])->startOfDay();
        if (! $tahun->tanggal_mulai || ! $tahun->tanggal_selesai || $mulai->lt($tahun->tanggal_mulai)
            || $selesai->gt($tahun->tanggal_selesai) || $mulai->gt($selesai) || $mulai->diffInDays($selesai) > 366) {
            throw ValidationException::withMessages(['tanggal_selesai' => 'Rentang harus berada dalam tahun pelajaran, paling lama 367 hari. Lengkapi tanggal tahun pelajaran terlebih dahulu.']);
        }
        $data = ['tahun_pelajaran_id' => (int) $tahun->id, 'kelas_id' => $kelas ? (int) $kelas->id : null,
            'tanggal_mulai' => $mulai->toDateString(), 'tanggal_selesai' => $selesai->toDateString(),
            'jenis' => $data['jenis'], 'alasan' => trim($data['alasan'])];
        $tumpangTindih = PengecualianPresensiSiswa::where('aktif', true)->where('tahun_pelajaran_id', $tahun->id)
            ->whereDate('tanggal_mulai', '<=', $selesai)->whereDate('tanggal_selesai', '>=', $mulai)
            ->when($kelas, fn ($q) => $q->where(fn ($q) => $q->whereNull('kelas_id')->orWhere('kelas_id', $kelas->id)))
            ->exists();
        if ($tumpangTindih) {
            throw ValidationException::withMessages(['tanggal_mulai' => 'Sudah ada pengecualian aktif yang bertumpuk pada tanggal dan cakupan ini. Batalkan aturan lama bila ingin menggantinya.']);
        }
        $anggota = AnggotaKelas::with(['siswa', 'kelas', 'tahunPelajaran'])->where('tahun_pelajaran_id', $tahun->id)
            ->where('status_keanggotaan', 'aktif')->whereHas('siswa', fn ($q) => $q->where('aktif', true))
            ->when($kelas, fn ($q) => $q->where('kelas_id', $kelas->id))->orderBy('id')->get();
        $presensi = AbsensiSiswa::where('tahun_pelajaran_id', $tahun->id)->whereIn('siswa_id', $anggota->pluck('siswa_id'))
            ->when($kelas, fn ($q) => $q->where('kelas_id', $kelas->id))
            ->whereDate('tanggal', '>=', $mulai)->whereDate('tanggal', '<=', $selesai)->orderBy('id')->get();
        $aturan = app(AturanAlfaOtomatisSiswaService::class);
        $hariAktif = $aturan->hariAktif();
        $tanggal = $aturan->tanggalEfektif($mulai, $selesai, $hariAktif);
        $rekaman = $presensi->groupBy(fn ($p) => $p->kelas_id.'|'.$p->siswa_id);
        $perSiswa = $anggota->map(function ($a) use ($rekaman, $aturan, $tanggal, $hariAktif) {
            $p = $rekaman->get($a->kelas_id.'|'.$a->siswa_id, collect());

            return ['anggota' => $a, 'alfa_dibatalkan' => $aturan->jumlah($p, $tanggal, $hariAktif, $a)];
        });
        $dampak = ['siswa_dalam_cakupan' => $anggota->count(), 'siswa_terdampak' => $perSiswa->where('alfa_dibatalkan', '>', 0)->count(),
            'alfa_otomatis_dibatalkan' => $perSiswa->sum('alfa_dibatalkan'), 'hari_aktif_terlewati' => count($tanggal),
            'catatan_dipertahankan' => $presensi->count(), 'alfa_manual' => $presensi->where('status_kehadiran', 'alfa')->count()];
        $sidik = hash('sha256', json_encode([$data, $dampak, $tanggal, $hariAktif,
            $anggota->map(fn ($a) => [$a->id, $a->kelas_id, $a->siswa_id, $a->tanggal_masuk?->toDateString(), $a->tanggal_keluar?->toDateString()])->all(),
            $presensi->map(fn ($p) => [$p->id, $p->kelas_id, $p->siswa_id, $p->tanggal->toDateString(), $p->status_kehadiran, $p->updated_at?->toISOString()])->all(),
        ], JSON_THROW_ON_ERROR));

        return compact('data', 'tahun', 'kelas', 'dampak', 'sidik', 'perSiswa');
    }
}
