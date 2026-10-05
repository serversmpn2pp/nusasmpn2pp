<?php

namespace App\Services\Nilai;

use App\Models\GuruMataPelajaran;
use App\Models\JadwalUjianCbt;
use App\Models\KegiatanUjianCbt;
use App\Models\Kelas;
use App\Models\KomponenNilai;
use App\Models\MataPelajaran;
use App\Models\PengaturanMapelRaporSts;
use App\Models\Pengguna;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MapelRaporStsService
{
    public static function dapatMengatur(?Pengguna $pengguna): bool
    {
        return $pengguna && $pengguna->aktif && ! $pengguna->akunSiswa() && ! $pengguna->akunOrangTua()
            && $pengguna->memilikiIzin('nilai.rekap')
            && ($pengguna->administrator() || $pengguna->memilikiPeran('wakil_pimpinan_kurikulum'));
    }

    public function konteks(KegiatanUjianCbt $kegiatan, int $tingkat): array
    {
        $kelas = Kelas::where('tahun_pelajaran_id', $kegiatan->tahun_pelajaran_id)->where('tingkat', $tingkat)->orderBy('nama')->get();
        $penugasan = GuruMataPelajaran::where('aktif', true)->whereIn('kelas_id', $kelas->pluck('id'))
            ->where('tahun_pelajaran_id', $kegiatan->tahun_pelajaran_id)->get(['id', 'kelas_id', 'mata_pelajaran_id']);
        $jadwal = $kegiatan->jadwalUjianCbt()->where('status', '!=', 'dibatalkan')
            ->whereHas('kelas', fn ($q) => $q->whereIn('kelas.id', $kelas->pluck('id')))
            ->with(['kelas' => fn ($q) => $q->whereIn('kelas.id', $kelas->pluck('id'))])->get();
        $komponen = KomponenNilai::where('aktif', true)->where('jenis_komponen', 'sts')->where('semester', $kegiatan->semester)
            ->whereIn('guru_mata_pelajaran_id', $penugasan->pluck('id'))->pluck('guru_mata_pelajaran_id');
        $sumberCbt = $jadwal->pluck('mata_pelajaran_id')->unique();
        $sumberManual = $penugasan->whereIn('id', $komponen)->pluck('mata_pelajaran_id')->unique();
        $pengaturan = PengaturanMapelRaporSts::with('pengubah')->where('kegiatan_ujian_cbt_id', $kegiatan->id)->where('tingkat', $tingkat)->first();
        $tersimpan = collect($pengaturan?->mapel_dikecualikan ?? [])->map(fn ($id) => (int) $id);
        $mapel = MataPelajaran::whereIn('id', $penugasan->pluck('mata_pelajaran_id')->merge($sumberCbt)->unique())
            ->orderBy('urutan')->orderBy('nama')->get()->reject(fn ($m) => $m->menggunakanPredikat())
            ->map(function ($m) use ($penugasan, $jadwal, $sumberCbt, $sumberManual, $tersimpan) {
                $kelasMapel = $penugasan->where('mata_pelajaran_id', $m->id)->pluck('kelas_id')
                    ->merge($jadwal->where('mata_pelajaran_id', $m->id)->flatMap(fn ($j) => $j->kelas->pluck('id')))->unique()->sort()->values();
                $wajib = $sumberCbt->contains($m->id) || $sumberManual->contains($m->id);

                return ['mapel' => $m, 'kelas_ids' => $kelasMapel->all(), 'wajib' => $wajib,
                    'sumber' => $sumberCbt->contains($m->id) ? 'Jadwal CBT' : ($sumberManual->contains($m->id) ? 'Komponen STS manual' : 'Tidak ada sumber STS'),
                    'dipilih' => $wajib || ! $tersimpan->contains($m->id), 'dipulihkan' => $wajib && $tersimpan->contains($m->id)];
            })->values();
        $sidik = hash('sha256', json_encode([$kegiatan->id, $kegiatan->tahun_pelajaran_id, $kegiatan->semester, $tingkat,
            $kelas->pluck('id')->all(), $mapel->map(fn ($m) => [$m['mapel']->id, $m['mapel']->nama, $m['kelas_ids'], $m['wajib'], $m['sumber']])->all()], JSON_THROW_ON_ERROR));
        $riwayat = $pengaturan ? DB::table('riwayat_mapel_rapor_sts as r')->leftJoin('pengguna as p', 'p.id', '=', 'r.pengguna_id')
            ->where('pengaturan_mapel_rapor_sts_id', $pengaturan->id)->orderByDesc('r.versi')->limit(5)->get(['r.versi', 'r.alasan', 'r.created_at', 'p.nama']) : collect();

        return compact('kelas', 'mapel', 'pengaturan', 'sidik', 'riwayat');
    }

    public function dikecualikan(KegiatanUjianCbt $kegiatan, int $tingkat): Collection
    {
        $ids = collect(PengaturanMapelRaporSts::where('kegiatan_ujian_cbt_id', $kegiatan->id)->where('tingkat', $tingkat)->value('mapel_dikecualikan') ?? [])
            ->map(fn ($id) => (int) $id);
        if ($ids->isEmpty()) {
            return $ids;
        }
        $kelasIds = Kelas::where('tahun_pelajaran_id', $kegiatan->tahun_pelajaran_id)->where('tingkat', $tingkat)->select('id');
        $cbt = JadwalUjianCbt::where('kegiatan_ujian_cbt_id', $kegiatan->id)->where('status', '!=', 'dibatalkan')
            ->whereIn('mata_pelajaran_id', $ids)->whereHas('kelas', fn ($q) => $q->whereIn('kelas.id', $kelasIds))->pluck('mata_pelajaran_id');
        $manual = GuruMataPelajaran::where('aktif', true)->whereIn('kelas_id', $kelasIds)->where('tahun_pelajaran_id', $kegiatan->tahun_pelajaran_id)
            ->whereIn('mata_pelajaran_id', $ids)->whereHas('komponenNilai', fn ($q) => $q->where('aktif', true)->where('jenis_komponen', 'sts')->where('semester', $kegiatan->semester))->pluck('mata_pelajaran_id');

        // New STS sources must not remain hidden behind an earlier exclusion.
        return $ids->diff($cbt->merge($manual))->values();
    }

    public function simpan(Pengguna $pengguna, KegiatanUjianCbt $kegiatan, int $tingkat, array $data): void
    {
        abort_unless(self::dapatMengatur($pengguna), 403);
        abort_unless($kegiatan->jenisUjianCbt?->kode === 'STS', 404);
        DB::transaction(function () use ($pengguna, $kegiatan, $tingkat, $data) {
            $kegiatan = KegiatanUjianCbt::lockForUpdate()->findOrFail($kegiatan->id);
            $k = $this->konteks($kegiatan, $tingkat);
            abort_if($k['kelas']->isEmpty(), 404);
            if ((int) ($k['pengaturan']?->versi ?? 0) !== (int) $data['versi_mapel'] || ! hash_equals($k['sidik'], $data['sidik_mapel'])) {
                throw ValidationException::withMessages(['versi_mapel' => 'Pengaturan, penugasan, atau sumber STS berubah. Muat ulang dan periksa kembali.']);
            }
            $dipilih = collect($data['mapel_ids'])->map(fn ($id) => (int) $id)->unique();
            $tersedia = $k['mapel']->pluck('mapel.id');
            if ($dipilih->isEmpty() || $dipilih->diff($tersedia)->isNotEmpty()) {
                throw ValidationException::withMessages(['mapel_ids' => 'Pilih setidaknya satu mapel yang tersedia untuk tingkat ini.']);
            }
            $wajib = $k['mapel']->where('wajib', true)->pluck('mapel.id');
            if ($wajib->diff($dipilih)->isNotEmpty()) {
                throw ValidationException::withMessages(['mapel_ids' => 'Mapel dengan jadwal CBT atau komponen STS aktif harus tetap dipilih. Jika tidak diujikan, batalkan jadwal atau nonaktifkan komponen STS terlebih dahulu.']);
            }
            $dikecualikan = $tersedia->diff($dipilih)->sort()->values()->all();
            $pengaturan = $k['pengaturan'] ?? new PengaturanMapelRaporSts(['kegiatan_ujian_cbt_id' => $kegiatan->id, 'tingkat' => $tingkat]);
            $sebelum = $pengaturan->mapel_dikecualikan ?? [];
            if ($pengaturan->exists && $sebelum === $dikecualikan) {
                return;
            }
            $pengaturan->fill(['mapel_dikecualikan' => $dikecualikan, 'versi' => ($pengaturan->versi ?? 0) + 1,
                'alasan' => trim($data['alasan_mapel']), 'diubah_oleh_pengguna_id' => $pengguna->id])->save();
            DB::table('riwayat_mapel_rapor_sts')->insert(['pengaturan_mapel_rapor_sts_id' => $pengaturan->id, 'versi' => $pengaturan->versi,
                'mapel_dikecualikan_sebelum' => json_encode($sebelum, JSON_THROW_ON_ERROR), 'mapel_dikecualikan_sesudah' => json_encode($dikecualikan, JSON_THROW_ON_ERROR),
                'alasan' => $pengaturan->alasan, 'pengguna_id' => $pengguna->id, 'created_at' => now()]);
        });
    }
}
