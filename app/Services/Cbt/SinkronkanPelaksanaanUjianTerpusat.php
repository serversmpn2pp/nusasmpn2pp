<?php

namespace App\Services\Cbt;

use App\Models\JadwalUjianCbt;
use App\Models\KegiatanUjianCbt;
use App\Models\KelasUjianCbt;
use App\Models\KelompokPesertaKegiatanUjianCbt;
use App\Models\Pengguna;
use App\Models\PesertaUjianCbt;
use App\Models\RuangUjianCbt;
use App\Models\SesiUjianCbt;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SinkronkanPelaksanaanUjianTerpusat
{
    public function sinkronkanKegiatan(KegiatanUjianCbt $kegiatan, ?Pengguna $pengguna = null): void
    {
        $kegiatan->jadwalUjianCbt()
            ->whereNotNull('ujian_cbt_id')
            ->with(['ujianCbt', 'kelas'])
            ->get()
            ->each(fn (JadwalUjianCbt $jadwal) => $this->sinkronkanJadwal($jadwal, $pengguna));
    }

    public function sinkronkanJadwal(JadwalUjianCbt $jadwal, ?Pengguna $pengguna = null): void
    {
        $jadwal->loadMissing([
            'kegiatanUjianCbt',
            'sesiKegiatanUjianCbt',
            'kelas',
            'ujianCbt',
            'pengawasRuangUjianTerpusat',
        ]);

        $paket = $jadwal->ujianCbt;
        if (! $paket || ! in_array($paket->status, ['terjadwal', 'berlangsung', 'selesai'], true)) {
            return;
        }

        $kelompok = KelompokPesertaKegiatanUjianCbt::query()
            ->where('kegiatan_ujian_cbt_id', $jadwal->kegiatan_ujian_cbt_id)
            ->where('tingkat', $jadwal->tingkat)
            ->with([
                'sesiKegiatanUjianCbt',
                'penempatanPesertaUjianCbt.anggotaKelas',
                'penempatanPesertaUjianCbt.ruangKegiatanUjianCbt',
            ])
            ->first();

        if (! $kelompok || ! $kelompok->sesiKegiatanUjianCbt) {
            return;
        }

        $kelasIds = $jadwal->kelas->modelKeys();
        $penempatan = $kelompok->penempatanPesertaUjianCbt
            ->filter(fn ($item) => in_array((int) $item->anggotaKelas?->kelas_id, $kelasIds, true))
            ->values();

        if ($penempatan->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($jadwal, $paket, $kelompok, $penempatan, $pengguna) {
            $kelasPaket = KelasUjianCbt::query()
                ->where('ujian_cbt_id', $paket->id)
                ->get()
                ->keyBy('kelas_id');
            $sesiSumber = $kelompok->sesiKegiatanUjianCbt;
            $mulai = Carbon::parse($jadwal->tanggal->format('Y-m-d').' '.$jadwal->waktu_mulai);
            $selesai = Carbon::parse($jadwal->tanggal->format('Y-m-d').' '.$jadwal->waktu_selesai);
            $sesi = SesiUjianCbt::query()
                ->where('ujian_cbt_id', $paket->id)
                ->where('sesi_kegiatan_ujian_cbt_id', $sesiSumber->id)
                ->first()
                ?? SesiUjianCbt::query()
                    ->where('ujian_cbt_id', $paket->id)
                    ->where('kode', $sesiSumber->kode)
                    ->whereNull('sesi_kegiatan_ujian_cbt_id')
                    ->first()
                ?? new SesiUjianCbt(['ujian_cbt_id' => $paket->id]);

            $sesi->fill([
                'sesi_kegiatan_ujian_cbt_id' => $sesiSumber->id,
                'kode' => $sesiSumber->kode,
                'nama' => $sesiSumber->nama,
                'waktu_mulai' => $mulai,
                'waktu_selesai' => $selesai,
                'kapasitas' => $penempatan->count(),
                'status' => $paket->status === 'selesai' ? 'selesai' : 'aktif',
                'keterangan' => 'Dibuat otomatis dari sesi Ujian Terpusat.',
            ]);
            $sesi->save();

            $pengawas = $jadwal->pengawasRuangUjianTerpusat->keyBy('ruang_kegiatan_ujian_cbt_id');
            $ruangOperasional = collect();

            foreach ($penempatan->pluck('ruangKegiatanUjianCbt')->filter()->unique('id') as $ruangSumber) {
                $penugasan = $pengawas->get($ruangSumber->id);
                $ruang = RuangUjianCbt::query()
                    ->where('ujian_cbt_id', $paket->id)
                    ->where('ruang_kegiatan_ujian_cbt_id', $ruangSumber->id)
                    ->first()
                    ?? RuangUjianCbt::query()
                        ->where('ujian_cbt_id', $paket->id)
                        ->where('kode', $ruangSumber->kode)
                        ->whereNull('ruang_kegiatan_ujian_cbt_id')
                        ->first()
                    ?? new RuangUjianCbt(['ujian_cbt_id' => $paket->id]);
                $ruang->fill([
                    'ruang_kegiatan_ujian_cbt_id' => $ruangSumber->id,
                    'sesi_ujian_cbt_id' => $sesi->id,
                    'jadwal_ujian_cbt_id' => $jadwal->id,
                    'kode' => $ruangSumber->kode,
                    'nama' => $ruangSumber->nama,
                    'lokasi' => $ruangSumber->lokasi,
                    'kapasitas' => $ruangSumber->kapasitas,
                    'pengawas_utama_pegawai_id' => $penugasan?->pengawas_utama_pegawai_id,
                    'pengawas_pendamping_pegawai_id' => $penugasan?->pengawas_pendamping_pegawai_id,
                    'status' => $paket->status === 'selesai' ? 'selesai' : 'siap',
                ]);
                $ruang->save();
                $ruangOperasional->put($ruangSumber->id, $ruang);
            }

            $anggotaIds = $penempatan->pluck('anggota_kelas_id')->map(fn ($id) => (int) $id)->all();
            PesertaUjianCbt::query()
                ->where('ujian_cbt_id', $paket->id)
                ->whereNotIn('status', ['sedang_mengerjakan', 'selesai'])
                ->update(['ruang_ujian_cbt_id' => null, 'nomor_meja' => null, 'kode_meja' => null]);

            // Muat ulang setelah reset agar Eloquent melihat perubahan massal di database.
            $pesertaLama = PesertaUjianCbt::query()
                ->where('ujian_cbt_id', $paket->id)
                ->get()
                ->keyBy('anggota_kelas_id');

            foreach ($penempatan as $item) {
                $kelas = $kelasPaket->get($item->anggotaKelas?->kelas_id);
                $ruang = $ruangOperasional->get($item->ruang_kegiatan_ujian_cbt_id);
                if (! $kelas || ! $ruang) {
                    continue;
                }

                $peserta = $pesertaLama->get($item->anggota_kelas_id) ?: new PesertaUjianCbt([
                    'ujian_cbt_id' => $paket->id,
                    'anggota_kelas_id' => $item->anggota_kelas_id,
                    'status' => 'aktif',
                    'status_kehadiran_ujian' => 'belum_absen',
                    'dibuat_oleh_pengguna_id' => $pengguna?->id,
                ]);

                $peserta->fill([
                    'sesi_ujian_cbt_id' => $sesi->id,
                    'kelas_ujian_cbt_id' => $kelas->id,
                    'ruang_ujian_cbt_id' => $ruang->id,
                    'nomor_meja' => $item->nomor_meja,
                    'kode_meja' => $item->kode_meja,
                    'nomor_peserta' => $this->nomorPeserta($jadwal, (string) $item->nomor_peserta),
                ]);

                if ($peserta->exists && $peserta->status === 'nonaktif') {
                    $peserta->status = 'aktif';
                }

                $peserta->save();
            }

            PesertaUjianCbt::query()
                ->where('ujian_cbt_id', $paket->id)
                ->whereNotIn('anggota_kelas_id', $anggotaIds)
                ->whereNotIn('status', ['sedang_mengerjakan', 'selesai'])
                ->update([
                    'status' => 'nonaktif',
                    'ruang_ujian_cbt_id' => null,
                    'nomor_meja' => null,
                    'kode_meja' => null,
                ]);
        });
    }

    private function nomorPeserta(JadwalUjianCbt $jadwal, string $nomorSumber): string
    {
        return str(sprintf('UT%04d-J%05d-%s', $jadwal->kegiatan_ujian_cbt_id, $jadwal->id, $nomorSumber))
            ->limit(80, '')
            ->toString();
    }
}
