<?php

namespace App\Services\Nilai;

use App\Models\KegiatanUjianCbt;
use App\Models\Kelas;
use App\Models\Pengguna;
use App\Models\RaporStsKelas;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PengelolaanRaporStsService
{
    public function __construct(private RaporStsService $rapor) {}

    public function pengaturan(Pengguna $pengguna, KegiatanUjianCbt $kegiatan, Kelas $kelas, array $input): void
    {
        $this->rapor->pastikanCakupan($pengguna, $kegiatan, $kelas);
        $tahun = $kegiatan->tahunPelajaran;
        $data = Validator::make($input, [
            'versi' => ['required', 'integer', 'min:0'],
            'tanggal_awal_presensi' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$tahun->tanggal_mulai->toDateString()],
            'tanggal_akhir_presensi' => ['required', 'date_format:Y-m-d', 'after_or_equal:tanggal_awal_presensi', 'before_or_equal:'.$tahun->tanggal_selesai->toDateString()],
            'tanggal_rapor' => ['required', 'date_format:Y-m-d', 'after_or_equal:tanggal_akhir_presensi'],
        ])->validate();
        DB::transaction(function () use ($pengguna, $kegiatan, $kelas, $data) {
            Kelas::whereKey($kelas->id)->lockForUpdate()->firstOrFail();
            $pengaturan = RaporStsKelas::where('kegiatan_ujian_cbt_id', $kegiatan->id)->where('kelas_id', $kelas->id)->first();
            $this->pastikanVersi($pengaturan, (int) $data['versi']);
            $pengaturan ??= new RaporStsKelas(['kegiatan_ujian_cbt_id' => $kegiatan->id, 'kelas_id' => $kelas->id]);
            $pengaturan->fill([...$data, 'versi' => $data['versi'] + 1, 'diubah_oleh_pengguna_id' => $pengguna->id])->save();
        });
    }

    public function kehadiran(Pengguna $pengguna, KegiatanUjianCbt $kegiatan, Kelas $kelas, array $input): void
    {
        $this->rapor->pastikanCakupan($pengguna, $kegiatan, $kelas);
        $data = Validator::make($input, [
            'versi' => ['required', 'integer', 'min:1'],
            'siswa' => ['required', 'array', 'min:1', 'max:500'],
            'siswa.*.sakit' => ['required', 'integer', 'min:0', 'max:366'],
            'siswa.*.izin' => ['required', 'integer', 'min:0', 'max:366'],
            'siswa.*.alfa' => ['required', 'integer', 'min:0', 'max:366'],
            'siswa.*.sidik_sumber' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'],
            'siswa.*.diperiksa' => ['required', 'boolean'],
            'siswa.*.catatan_koreksi' => ['nullable', 'string', 'max:500'],
        ])->validate();
        DB::transaction(function () use ($pengguna, $kegiatan, $kelas, $data) {
            Kelas::whereKey($kelas->id)->lockForUpdate()->firstOrFail();
            $pengaturan = RaporStsKelas::where('kegiatan_ujian_cbt_id', $kegiatan->id)->where('kelas_id', $kelas->id)->firstOrFail();
            $this->pastikanVersi($pengaturan, (int) $data['versi']);
            $laporan = $this->rapor->bangun($kegiatan, $kelas, $pengaturan);
            $baris = $laporan['baris']->keyBy('anggota.id');
            $maksimal = (int) $pengaturan->tanggal_awal_presensi->diffInDays($pengaturan->tanggal_akhir_presensi) + 1;
            foreach ($data['siswa'] as $id => $input) {
                $siswa = $baris->get($id);
                abort_unless($siswa, 404);
                if (! hash_equals($siswa['sidik_sumber'], $input['sidik_sumber'])) {
                    throw ValidationException::withMessages(['kehadiran' => 'Presensi sumber berubah. Muat ulang halaman dan periksa kembali sebelum menyimpan.']);
                }
                $angka = array_map('intval', array_intersect_key($input, array_flip(['sakit', 'izin', 'alfa'])));
                if (array_sum($angka) > $maksimal) {
                    throw ValidationException::withMessages(["siswa.$id.sakit" => 'Jumlah sakit, izin, dan alfa tidak boleh melebihi jumlah hari dalam periode.']);
                }
                $berubah = collect($angka)->contains(fn ($jumlah, $jenis) => $jumlah !== $siswa['sumber'][$jenis]);
                $catatan = trim($input['catatan_koreksi'] ?? '');
                if ($berubah && $catatan === '') {
                    throw ValidationException::withMessages(["siswa.$id.catatan_koreksi" => 'Isi alasan koreksi karena jumlah berbeda dari presensi sumber.']);
                }
                $pengaturan->kehadiran()->updateOrCreate(['anggota_kelas_id' => $id], [
                    ...$angka, 'rekap_sumber' => $siswa['sumber'], 'catatan_koreksi' => $catatan ?: null,
                    'diperiksa_pada' => $input['diperiksa'] ? now() : null,
                    'diperiksa_oleh_pengguna_id' => $pengguna->id,
                ]);
            }
            $pengaturan->update(['versi' => $pengaturan->versi + 1, 'diubah_oleh_pengguna_id' => $pengguna->id]);
        });
    }

    public function pengecualian(Pengguna $pengguna, KegiatanUjianCbt $kegiatan, Kelas $kelas, array $input): void
    {
        $this->rapor->pastikanCakupan($pengguna, $kegiatan, $kelas);
        $data = Validator::make($input, [
            'versi' => ['required', 'integer', 'min:1'], 'anggota_id' => ['required', 'integer'],
            'pengecualian' => ['required', 'array', 'min:1', 'max:100'],
            'pengecualian.*.tidak_mengikuti' => ['required', 'boolean'],
            'pengecualian.*.alasan' => ['nullable', 'string', 'max:500'],
            'pengecualian.*.sidik_kondisi' => ['nullable', 'string', 'size:64'],
        ])->validate();
        DB::transaction(function () use ($pengguna, $kegiatan, $kelas, $data) {
            Kelas::whereKey($kelas->id)->lockForUpdate()->firstOrFail();
            $pengaturan = RaporStsKelas::where('kegiatan_ujian_cbt_id', $kegiatan->id)->where('kelas_id', $kelas->id)->firstOrFail();
            $this->pastikanVersi($pengaturan, (int) $data['versi']);
            $laporan = $this->rapor->bangun($kegiatan, $kelas, $pengaturan);
            $siswa = $laporan['baris']->firstWhere('anggota.id', (int) $data['anggota_id']);
            abort_unless($siswa, 404);
            foreach ($data['pengecualian'] as $id => $input) {
                $nilai = $siswa['nilai']->firstWhere('mapel.id', $id);
                abort_unless($nilai, 404);
                if (! $input['tidak_mengikuti']) {
                    if ($nilai['pengecualian']?->aktif) {
                        $nilai['pengecualian']->update(['aktif' => false, 'dibatalkan_pada' => now(), 'dibatalkan_oleh_pengguna_id' => $pengguna->id]);
                    }

                    continue;
                }
                if (! $nilai['dapat_dikecualikan']) {
                    throw ValidationException::withMessages(["pengecualian.$id.tidak_mengikuti" => 'Keterangan hanya untuk siswa yang tercatat sakit, izin, atau alfa, belum mengerjakan ujian, dan tidak memiliki susulan berjalan. Hasil ujian harus sudah final dan jadwal berakhir.']);
                }
                if (! hash_equals($nilai['sidik_kondisi'], $input['sidik_kondisi'] ?? '')) {
                    throw ValidationException::withMessages(["pengecualian.$id.tidak_mengikuti" => 'Data ujian berubah. Muat ulang halaman dan periksa kembali keterangan siswa.']);
                }
                $alasan = trim($input['alasan'] ?? '');
                if ($alasan === '') {
                    throw ValidationException::withMessages(["pengecualian.$id.alasan" => 'Isi alasan siswa tidak mengikuti STS maupun susulan.']);
                }
                $pengaturan->pengecualian()->updateOrCreate(['anggota_kelas_id' => $data['anggota_id'], 'mata_pelajaran_id' => $id], [
                    'peserta_ujian_cbt_id' => $nilai['peserta_id'], 'aktif' => true, 'alasan' => $alasan,
                    'sidik_kondisi' => $nilai['sidik_kondisi'], 'ditetapkan_pada' => now(),
                    'ditetapkan_oleh_pengguna_id' => $pengguna->id,
                    'dibatalkan_pada' => null, 'dibatalkan_oleh_pengguna_id' => null,
                ]);
            }
            $pengaturan->update(['versi' => $pengaturan->versi + 1, 'diubah_oleh_pengguna_id' => $pengguna->id]);
        });
    }

    private function pastikanVersi(?RaporStsKelas $pengaturan, int $versi): void
    {
        if (($pengaturan?->versi ?? 0) !== $versi) {
            throw ValidationException::withMessages(['versi' => 'Rapor telah diperbarui pada halaman lain. Muat ulang halaman agar perubahan tidak saling menimpa.']);
        }
    }
}
