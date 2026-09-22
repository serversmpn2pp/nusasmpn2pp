<?php

namespace App\Services\Cbt;

use App\Models\JadwalUjianCbt;
use App\Models\PengawasRuangUjianTerpusat;
use App\Models\PesertaUjianCbt;
use App\Models\RuangKegiatanUjianCbt;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PemeriksaBentrokUjianSusulan
{
    public function pastikanTidakBentrok(
        RuangKegiatanUjianCbt $ruang,
        int $pengawasPegawaiId,
        CarbonInterface $mulai,
        CarbonInterface $selesai,
    ): void {
        $this->pastikanRuangTidakDipakaiUjianUtama($ruang, $mulai, $selesai);
        $this->pastikanRuangTidakDipakaiSusulan($ruang, $mulai, $selesai);
        $this->pastikanPengawasTidakBertugasDiUjianUtama($pengawasPegawaiId, $mulai, $selesai);
        $this->pastikanPengawasTidakBertugasDiSusulan($pengawasPegawaiId, $mulai, $selesai);
    }

    private function pastikanRuangTidakDipakaiUjianUtama(
        RuangKegiatanUjianCbt $ruang,
        CarbonInterface $mulai,
        CarbonInterface $selesai,
    ): void {
        $jadwalId = DB::table('jadwal_ujian_cbt as jadwal')
            ->join('kelompok_peserta_kegiatan_ujian_cbt as kelompok', function ($join) {
                $join->on('kelompok.kegiatan_ujian_cbt_id', '=', 'jadwal.kegiatan_ujian_cbt_id')
                    ->on('kelompok.tingkat', '=', 'jadwal.tingkat');
            })
            ->join('kelompok_peserta_kegiatan_ujian_cbt_ruang as kelompok_ruang', 'kelompok_ruang.kelompok_peserta_kegiatan_ujian_cbt_id', '=', 'kelompok.id')
            ->where('kelompok_ruang.ruang_kegiatan_ujian_cbt_id', $ruang->id)
            ->whereDate('jadwal.tanggal', $mulai->toDateString())
            ->where('jadwal.waktu_mulai', '<', $selesai->format('H:i:s'))
            ->where('jadwal.waktu_selesai', '>', $mulai->format('H:i:s'))
            ->where('jadwal.status', '!=', 'dibatalkan')
            ->orderBy('jadwal.waktu_mulai')
            ->value('jadwal.id');

        if (! $jadwalId) {
            return;
        }

        $jadwal = JadwalUjianCbt::query()->with('mataPelajaran')->find($jadwalId);

        throw ValidationException::withMessages([
            'ruang_susulan_kegiatan_ujian_cbt_id' => $ruang->nama.' sedang digunakan untuk '
                .($jadwal?->mataPelajaran?->nama ?: 'ujian utama')
                .' tingkat '.($jadwal?->tingkat ?: '-')
                .' pukul '.($jadwal?->labelWaktu() ?: '-').'.',
        ]);
    }

    private function pastikanRuangTidakDipakaiSusulan(
        RuangKegiatanUjianCbt $ruang,
        CarbonInterface $mulai,
        CarbonInterface $selesai,
    ): void {
        $bentrok = PesertaUjianCbt::query()
            ->where('status_susulan', 'dijadwalkan')
            ->where('susulan_mulai', '<', $selesai)
            ->where('susulan_selesai', '>', $mulai)
            ->where(function ($query) use ($ruang) {
                $query->where('ruang_susulan_kegiatan_ujian_cbt_id', $ruang->id)
                    ->orWhere(function ($query) use ($ruang) {
                        $query->whereNull('ruang_susulan_kegiatan_ujian_cbt_id')
                            ->whereIn('ruang_susulan', array_filter([$ruang->nama, $ruang->kode]));
                    });
            })
            ->with('ujianCbt.mataPelajaran')
            ->orderBy('susulan_mulai')
            ->first();

        if (! $bentrok) {
            return;
        }

        throw ValidationException::withMessages([
            'ruang_susulan_kegiatan_ujian_cbt_id' => $ruang->nama.' sudah digunakan untuk ujian susulan '
                .($bentrok->ujianCbt?->mataPelajaran?->nama ?: 'lainnya')
                .' pukul '.$bentrok->susulan_mulai?->format('H:i').'-'.$bentrok->susulan_selesai?->format('H:i').'.',
        ]);
    }

    private function pastikanPengawasTidakBertugasDiUjianUtama(
        int $pengawasPegawaiId,
        CarbonInterface $mulai,
        CarbonInterface $selesai,
    ): void {
        $bentrok = PengawasRuangUjianTerpusat::query()
            ->where(function ($query) use ($pengawasPegawaiId) {
                $query->where('pengawas_utama_pegawai_id', $pengawasPegawaiId)
                    ->orWhere('pengawas_pendamping_pegawai_id', $pengawasPegawaiId);
            })
            ->whereHas('jadwalUjianCbt', fn ($query) => $query
                ->whereDate('tanggal', $mulai->toDateString())
                ->where('waktu_mulai', '<', $selesai->format('H:i:s'))
                ->where('waktu_selesai', '>', $mulai->format('H:i:s'))
                ->where('status', '!=', 'dibatalkan'))
            ->with(['jadwalUjianCbt.mataPelajaran', 'ruangKegiatanUjianCbt'])
            ->first();

        if (! $bentrok) {
            return;
        }

        throw ValidationException::withMessages([
            'pengawas_susulan_pegawai_id' => 'Pengawas sudah bertugas pada '
                .($bentrok->jadwalUjianCbt?->mataPelajaran?->nama ?: 'ujian utama lain')
                .' di '.($bentrok->ruangKegiatanUjianCbt?->nama ?: 'ruang lain')
                .' pukul '.($bentrok->jadwalUjianCbt?->labelWaktu() ?: '-').'.',
        ]);
    }

    private function pastikanPengawasTidakBertugasDiSusulan(
        int $pengawasPegawaiId,
        CarbonInterface $mulai,
        CarbonInterface $selesai,
    ): void {
        $bentrok = PesertaUjianCbt::query()
            ->where('status_susulan', 'dijadwalkan')
            ->where('pengawas_susulan_pegawai_id', $pengawasPegawaiId)
            ->where('susulan_mulai', '<', $selesai)
            ->where('susulan_selesai', '>', $mulai)
            ->with('ujianCbt.mataPelajaran')
            ->orderBy('susulan_mulai')
            ->first();

        if (! $bentrok) {
            return;
        }

        throw ValidationException::withMessages([
            'pengawas_susulan_pegawai_id' => 'Pengawas sudah bertugas pada ujian susulan '
                .($bentrok->ujianCbt?->mataPelajaran?->nama ?: 'lainnya')
                .' di '.($bentrok->ruang_susulan ?: 'ruang lain')
                .' pukul '.$bentrok->susulan_mulai?->format('H:i').'-'.$bentrok->susulan_selesai?->format('H:i').'.',
        ]);
    }
}
