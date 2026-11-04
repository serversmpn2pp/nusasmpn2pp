<?php

namespace App\Services\Ibadah;

use App\Models\AbsensiSiswa;
use App\Models\JadwalKegiatanIbadah;
use App\Models\KegiatanIbadah;
use App\Models\PengecualianPresensiSiswa;
use App\Models\PresensiBerhalanganIbadah;
use App\Models\PresensiKegiatanIbadah;
use App\Models\TahunPelajaran;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class EvaluasiIbadahMingguan
{
    public function hitung(TahunPelajaran $tahun, KegiatanIbadah $kegiatan, Collection $kelas, CarbonImmutable $awal, CarbonImmutable $akhir): array
    {
        $jadwal = $kegiatan->jadwal()->where('tahun_pelajaran_id', $tahun->id)->where('aktif', true)->get()->keyBy('hari');
        $pengecualian = PengecualianPresensiSiswa::query()->where('tahun_pelajaran_id', $tahun->id)->where('aktif', true)
            ->where('tanggal_mulai', '<', $akhir->addDay()->toDateString())->where('tanggal_selesai', '>=', $awal->toDateString())
            ->where(fn ($q) => $q->whereNull('kelas_id')->orWhereIn('kelas_id', $kelas->keys()))
            ->get(['kelas_id', 'tanggal_mulai', 'tanggal_selesai']);
        $sekarang = now();
        $hari = collect();
        for ($tanggal = $awal; $tanggal <= $akhir && $tanggal->toDateString() <= $sekarang->toDateString(); $tanggal = $tanggal->addDay()) {
            $tgl = $tanggal->toDateString();
            $sesi = $jadwal->get(array_keys(JadwalKegiatanIbadah::DAFTAR_HARI)[$tanggal->dayOfWeekIso - 1] ?? null);
            if (! $sesi || ($tahun->tanggal_mulai && $tgl < $tahun->tanggal_mulai->toDateString())
                || ($tahun->tanggal_selesai && $tgl > $tahun->tanggal_selesai->toDateString())
                || ($tanggal->isSameDay($sekarang) && $sekarang->format('H:i') <= $sesi->formatJam($sesi->jam_scan_selesai))) {
                continue;
            }
            $dikecualikan = $pengecualian->filter(fn ($item) => $item->tanggal_mulai->toDateString() <= $tgl && $item->tanggal_selesai->toDateString() >= $tgl);
            $kelasBerlaku = $dikecualikan->contains(fn ($item) => $item->kelas_id === null)
                ? collect() : $kelas->except($dikecualikan->pluck('kelas_id')->all());
            if ($kelasBerlaku->isNotEmpty()) {
                $hari->put($tgl, ['tanggal' => $tanggal, 'jadwal' => $sesi, 'kelas' => $kelasBerlaku]);
            }
        }
        if ($hari->isEmpty()) {
            return $this->kosong();
        }

        $tanggalPertama = $hari->keys()->first();
        $tanggalSesudah = $hari->last()['tanggal']->addDay()->toDateString();
        $catatanIbadah = PresensiKegiatanIbadah::query()->where('tahun_pelajaran_id', $tahun->id)->where('kegiatan_ibadah_id', $kegiatan->id);
        $catatanBerhalangan = PresensiBerhalanganIbadah::query()->where('tahun_pelajaran_id', $tahun->id)->where('kegiatan_ibadah_id', $kegiatan->id);
        $catatanSiswaPadaHariItu = fn ($q) => $q->selectRaw('1')
            ->whereColumn($q->getModel()->qualifyColumn('siswa_id'), 'absensi_siswa.siswa_id')
            ->whereColumn($q->getModel()->qualifyColumn('tanggal'), 'absensi_siswa.tanggal')->getQuery();
        $absensi = AbsensiSiswa::query()->where('tahun_pelajaran_id', $tahun->id)
            ->where('status_kehadiran', 'hadir')->whereIn('kelas_id', $kelas->keys())
            ->where('tanggal', '>=', $tanggalPertama)->where('tanggal', '<', $tanggalSesudah)
            ->whereHas('siswa', fn ($q) => $q->where('aktif', true)->when($kegiatan->khususLakiLaki(), fn ($q) => $q->where('jenis_kelamin', 'L')))
            ->whereNotExists($catatanSiswaPadaHariItu(clone $catatanIbadah))
            ->whereNotExists($catatanSiswaPadaHariItu($catatanBerhalangan))
            ->with(['siswa:id,nama_lengkap', 'anggotaKelas:id,nomor_absen'])
            ->orderBy('tanggal')->get(['siswa_id', 'kelas_id', 'anggota_kelas_id', 'tanggal', 'jam_masuk', 'jam_pulang'])
            ->filter(function (AbsensiSiswa $item) use ($hari) {
                $sesi = $hari->get($item->tanggal->toDateString());
                if (! $sesi || ! $sesi['kelas']->has($item->kelas_id)) {
                    return false;
                }
                $jadwal = $sesi['jadwal'];

                return (! $item->jam_masuk || substr($item->jam_masuk, 0, 5) <= $jadwal->formatJam($jadwal->jam_scan_selesai))
                    && (! $item->jam_pulang || substr($item->jam_pulang, 0, 5) >= $jadwal->formatJam($jadwal->jam_pelaksanaan));
            });

        $baris = $absensi->groupBy(fn ($item) => $item->kelas_id.'|'.$item->siswa_id)->map(function ($items) use ($kelas) {
            $pertama = $items->first();
            $tanggal = $items->pluck('tanggal')->unique(fn ($tgl) => $tgl->toDateString())->values();

            return ['siswa_id' => $pertama->siswa_id, 'nama' => $pertama->siswa->nama_lengkap,
                'kelas' => $kelas->get($pertama->kelas_id), 'absen' => $pertama->anggotaKelas?->nomor_absen, 'tanggal' => $tanggal];
        })->sortBy([['kelas.tingkat', 'asc'], ['kelas.nama', 'asc'], ['nama', 'asc']])->values();

        $tanggalTercatat = $catatanIbadah->where('tanggal', '>=', $tanggalPertama)->where('tanggal', '<', $tanggalSesudah)
            ->whereIn('kelas_id', $kelas->keys())->distinct()->get(['tanggal', 'kelas_id'])
            ->filter(fn ($item) => ($hari->get($item->tanggal->toDateString())['kelas'] ?? null)?->has($item->kelas_id) ?? false)
            ->map(fn ($item) => $item->tanggal->toDateString());

        return ['baris' => $baris, 'jumlahSiswa' => $baris->unique('siswa_id')->count(),
            'jumlahKejadian' => $baris->sum(fn ($item) => $item['tanggal']->count()), 'jumlahHari' => $hari->count(),
            'hariTanpaCatatan' => $hari->except($tanggalTercatat->all())->pluck('tanggal')->values()];
    }

    public function kosong(): array
    {
        return ['baris' => collect(), 'jumlahSiswa' => 0, 'jumlahKejadian' => 0, 'jumlahHari' => 0, 'hariTanpaCatatan' => collect()];
    }
}
