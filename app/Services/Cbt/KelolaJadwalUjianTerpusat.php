<?php

namespace App\Services\Cbt;

use App\Models\JadwalUjianCbt;
use App\Models\KegiatanUjianCbt;
use App\Models\KelompokPesertaKegiatanUjianCbt;
use App\Models\MataPelajaran;
use App\Models\PengawasRuangUjianTerpusat;
use App\Models\Pengguna;
use App\Models\UjianCbt;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KelolaJadwalUjianTerpusat
{
    public function __construct(
        private SinkronkanPelaksanaanUjianTerpusat $sinkronisasi,
    ) {}

    public function tambah(KegiatanUjianCbt $kegiatan, array $data): Collection
    {
        $tingkat = collect($data['tingkat'])->map(fn ($item) => (int) $item)->unique()->values();
        [$tanggal, $mataPelajaran, $kelompok] = $this->dataValid($kegiatan, $data, $tingkat);

        $this->pastikanTingkatTidakBentrok(
            $kegiatan,
            $tanggal,
            $data['waktu_mulai'],
            $data['waktu_selesai'],
            $tingkat,
            null,
        );

        $this->pastikanRuangTidakBentrok(
            $kegiatan,
            $tanggal,
            $data['waktu_mulai'],
            $data['waktu_selesai'],
            $kelompok,
            null,
        );

        return DB::transaction(function () use ($kegiatan, $data, $tanggal, $mataPelajaran, $tingkat, $kelompok) {
            $urutanAwal = (int) $kegiatan->jadwalUjianCbt()->max('urutan');

            return $tingkat->map(function (int $item, int $nomor) use ($kegiatan, $data, $tanggal, $mataPelajaran, $kelompok, $urutanAwal) {
                $kelompokTingkat = $kelompok->get($item);
                $sesi = $kelompokTingkat->sesiKegiatanUjianCbt;
                $jadwal = JadwalUjianCbt::create([
                    'kegiatan_ujian_cbt_id' => $kegiatan->id,
                    'sesi_kegiatan_ujian_cbt_id' => $sesi->id,
                    'ujian_cbt_id' => null,
                    'mata_pelajaran_id' => $mataPelajaran->id,
                    'tanggal' => $tanggal,
                    'waktu_mulai' => $data['waktu_mulai'],
                    'waktu_selesai' => $data['waktu_selesai'],
                    'label_sesi' => $sesi->nama,
                    'tingkat' => $item,
                    'urutan' => $urutanAwal + $nomor + 1,
                    'status' => 'draft',
                    'keterangan' => filled($data['keterangan'] ?? null) ? trim($data['keterangan']) : null,
                ]);
                $jadwal->kelas()->sync($kelompokTingkat->kelas->modelKeys());

                return $jadwal;
            });
        });
    }

    public function ubah(
        KegiatanUjianCbt $kegiatan,
        JadwalUjianCbt $jadwal,
        array $data,
        ?Pengguna $pengguna = null,
    ): JadwalUjianCbt {
        $this->pastikanMilikKegiatan($kegiatan, $jadwal);
        $tingkat = collect([(int) $jadwal->tingkat]);
        [$tanggal, $mataPelajaran, $kelompok] = $this->dataValid($kegiatan, $data, $tingkat);
        $kelompokTingkat = $kelompok->first();
        $paket = $jadwal->ujianCbt()->withCount('soalUjianCbt')->first();

        if ($paket && $this->pelaksanaanBerubah($jadwal, $tanggal, $data)
            && $this->paketSudahMulaiDikerjakan($paket)) {
            throw ValidationException::withMessages([
                'jadwal' => 'Tanggal atau jam tidak dapat diubah karena ujian sudah mulai dikerjakan peserta.',
            ]);
        }

        if ($paket?->soal_ujian_cbt_count > 0 && (int) $mataPelajaran->id !== (int) $jadwal->mata_pelajaran_id) {
            throw ValidationException::withMessages([
                'mata_pelajaran_id' => 'Mata pelajaran tidak dapat diganti karena paket sudah berisi soal. Kosongkan soal paket terlebih dahulu.',
            ]);
        }

        $this->pastikanTingkatTidakBentrok(
            $kegiatan,
            $tanggal,
            $data['waktu_mulai'],
            $data['waktu_selesai'],
            $tingkat,
            $jadwal,
        );

        $this->pastikanRuangTidakBentrok(
            $kegiatan,
            $tanggal,
            $data['waktu_mulai'],
            $data['waktu_selesai'],
            $kelompok,
            $jadwal,
        );

        $this->pastikanPengawasTidakBentrok(
            $jadwal,
            $tanggal,
            $data['waktu_mulai'],
            $data['waktu_selesai'],
        );

        DB::transaction(function () use ($kegiatan, $jadwal, $data, $tanggal, $mataPelajaran, $kelompokTingkat, $paket, $pengguna) {
            $sesi = $kelompokTingkat->sesiKegiatanUjianCbt;
            $jadwal->update([
                'sesi_kegiatan_ujian_cbt_id' => $sesi->id,
                'mata_pelajaran_id' => $mataPelajaran->id,
                'tanggal' => $tanggal,
                'waktu_mulai' => $data['waktu_mulai'],
                'waktu_selesai' => $data['waktu_selesai'],
                'label_sesi' => $sesi->nama,
                'keterangan' => filled($data['keterangan'] ?? null) ? trim($data['keterangan']) : null,
            ]);
            $jadwal->kelas()->sync($kelompokTingkat->kelas->modelKeys());

            if ($paket) {
                $mulai = Carbon::parse($tanggal.' '.$data['waktu_mulai']);
                $selesai = Carbon::parse($tanggal.' '.$data['waktu_selesai']);
                $mataPelajaran->load('pengaturanTingkat');
                $paket->update([
                    'mata_pelajaran_id' => $mataPelajaran->id,
                    'nama' => "{$kegiatan->nama} - {$mataPelajaran->nama} Tingkat {$jadwal->tingkat}",
                    'tanggal_mulai' => $mulai,
                    'tanggal_selesai' => $selesai,
                    'durasi_menit' => max(10, $mulai->diffInMinutes($selesai)),
                    'kkm' => $mataPelajaran->pengaturanUntuk((int) $kegiatan->tahun_pelajaran_id, (int) $jadwal->tingkat)?->kkm ?? $mataPelajaran->kkm,
                ]);

                $this->sinkronisasi->sinkronkanJadwal($jadwal->fresh(), $pengguna);
            }
        });

        return $jadwal->fresh();
    }

    public function hapus(KegiatanUjianCbt $kegiatan, JadwalUjianCbt $jadwal): void
    {
        $this->pastikanMilikKegiatan($kegiatan, $jadwal);

        if ($jadwal->ujian_cbt_id || $jadwal->terkunci()) {
            throw ValidationException::withMessages([
                'jadwal' => 'Jadwal yang sudah terhubung ke paket atau dikunci tidak dapat dihapus.',
            ]);
        }

        $jadwal->delete();
    }

    private function dataValid(
        KegiatanUjianCbt $kegiatan,
        array $data,
        Collection $tingkat,
    ): array {
        $tanggal = Carbon::parse($data['tanggal'])->startOfDay();
        if ($tanggal->lt($kegiatan->tanggal_mulai->startOfDay()) || $tanggal->gt($kegiatan->tanggal_selesai->startOfDay())) {
            throw ValidationException::withMessages([
                'tanggal' => "Tanggal harus berada dalam periode {$kegiatan->labelPeriode()}.",
            ]);
        }

        $mataPelajaran = MataPelajaran::query()->where('aktif', true)->find($data['mata_pelajaran_id']);
        if (! $mataPelajaran) {
            throw ValidationException::withMessages(['mata_pelajaran_id' => 'Mata pelajaran tidak aktif.']);
        }

        foreach ($tingkat as $item) {
            if (! $mataPelajaran->tersediaUntuk($kegiatan->tahun_pelajaran_id, $item)) {
                throw ValidationException::withMessages([
                    'mata_pelajaran_id' => "{$mataPelajaran->nama} tidak diterapkan untuk tingkat {$item} pada tahun pelajaran ini.",
                ]);
            }
        }

        $kelompok = KelompokPesertaKegiatanUjianCbt::query()
            ->where('kegiatan_ujian_cbt_id', $kegiatan->id)
            ->whereIn('tingkat', $tingkat)
            ->with(['sesiKegiatanUjianCbt', 'kelas'])
            ->withCount('penempatanPesertaUjianCbt')
            ->get()
            ->keyBy('tingkat');

        if ($kelompok->count() !== $tingkat->count()) {
            throw ValidationException::withMessages([
                'tingkat' => 'Buat pembagian peserta untuk setiap tingkat yang dipilih terlebih dahulu.',
            ]);
        }

        if ($kelompok->contains(fn ($item) => (int) $item->penempatan_peserta_ujian_cbt_count === 0)) {
            throw ValidationException::withMessages([
                'tingkat' => 'Bagi peserta otomatis pada tahap 6 sebelum membuat jadwal ujian.',
            ]);
        }

        return [$tanggal->toDateString(), $mataPelajaran, $kelompok];
    }

    private function pastikanRuangTidakBentrok(
        KegiatanUjianCbt $kegiatan,
        string $tanggal,
        string $waktuMulai,
        string $waktuSelesai,
        Collection $kelompokDipilih,
        ?JadwalUjianCbt $abaikanJadwal,
    ): void {
        $kelompokDipilih->loadMissing('ruangKegiatanUjianCbt');
        $daftarKelompok = $kelompokDipilih->values();

        for ($i = 0; $i < $daftarKelompok->count(); $i++) {
            for ($j = $i + 1; $j < $daftarKelompok->count(); $j++) {
                $ruangSama = $daftarKelompok[$i]->ruangKegiatanUjianCbt->keyBy('id')
                    ->intersectByKeys($daftarKelompok[$j]->ruangKegiatanUjianCbt->keyBy('id'));

                if ($ruangSama->isNotEmpty()) {
                    throw ValidationException::withMessages([
                        'waktu_mulai' => 'Tingkat '.$daftarKelompok[$i]->tingkat.' dan '.$daftarKelompok[$j]->tingkat
                            .' menggunakan '.($ruangSama->pluck('nama')->join(', ')).' pada waktu yang sama.',
                    ]);
                }
            }
        }

        $jadwalBentrok = JadwalUjianCbt::query()
            ->where('kegiatan_ujian_cbt_id', $kegiatan->id)
            ->whereDate('tanggal', $tanggal)
            ->where('waktu_mulai', '<', $waktuSelesai)
            ->where('waktu_selesai', '>', $waktuMulai)
            ->when($abaikanJadwal, fn ($query) => $query->whereKeyNot($abaikanJadwal->id))
            ->get();

        if ($jadwalBentrok->isEmpty()) {
            return;
        }

        $kelompokSemua = KelompokPesertaKegiatanUjianCbt::query()
            ->where('kegiatan_ujian_cbt_id', $kegiatan->id)
            ->with('ruangKegiatanUjianCbt')
            ->get()
            ->keyBy('tingkat');

        foreach ($jadwalBentrok as $jadwal) {
            $kelompokLawan = $kelompokSemua->get((int) $jadwal->tingkat);
            if (! $kelompokLawan) {
                continue;
            }

            foreach ($kelompokDipilih as $kelompok) {
                $ruangSama = $kelompok->ruangKegiatanUjianCbt->keyBy('id')
                    ->intersectByKeys($kelompokLawan->ruangKegiatanUjianCbt->keyBy('id'));

                if ($ruangSama->isNotEmpty()) {
                    throw ValidationException::withMessages([
                        'waktu_mulai' => ($ruangSama->pluck('nama')->join(', '))
                            .' sudah digunakan tingkat '.$jadwal->tingkat.' pukul '.$jadwal->labelWaktu().'.',
                    ]);
                }
            }
        }
    }

    private function pastikanTingkatTidakBentrok(
        KegiatanUjianCbt $kegiatan,
        string $tanggal,
        string $waktuMulai,
        string $waktuSelesai,
        Collection $tingkat,
        ?JadwalUjianCbt $abaikanJadwal,
    ): void {
        $bentrok = JadwalUjianCbt::query()
            ->where('kegiatan_ujian_cbt_id', $kegiatan->id)
            ->whereDate('tanggal', $tanggal)
            ->whereIn('tingkat', $tingkat)
            ->where('waktu_mulai', '<', $waktuSelesai)
            ->where('waktu_selesai', '>', $waktuMulai)
            ->when($abaikanJadwal, fn ($query) => $query->whereKeyNot($abaikanJadwal->id))
            ->with('mataPelajaran')
            ->orderBy('waktu_mulai')
            ->first();

        if (! $bentrok) {
            return;
        }

        throw ValidationException::withMessages([
            'waktu_mulai' => 'Tingkat '.$bentrok->tingkat.' sudah memiliki jadwal '
                .($bentrok->mataPelajaran?->nama ?: 'mata pelajaran lain').' pukul '
                .$bentrok->labelWaktu().'. Pilih waktu setelah jadwal tersebut selesai.',
        ]);
    }

    private function pastikanPengawasTidakBentrok(
        JadwalUjianCbt $jadwal,
        string $tanggal,
        string $waktuMulai,
        string $waktuSelesai,
    ): void {
        $pegawaiIds = PengawasRuangUjianTerpusat::query()
            ->where('jadwal_ujian_cbt_id', $jadwal->id)
            ->get(['pengawas_utama_pegawai_id', 'pengawas_pendamping_pegawai_id'])
            ->flatMap(fn ($item) => [$item->pengawas_utama_pegawai_id, $item->pengawas_pendamping_pegawai_id])
            ->filter()
            ->unique()
            ->values();

        if ($pegawaiIds->isEmpty()) {
            return;
        }

        $bentrok = PengawasRuangUjianTerpusat::query()
            ->where('jadwal_ujian_cbt_id', '!=', $jadwal->id)
            ->where(function ($query) use ($pegawaiIds) {
                $query->whereIn('pengawas_utama_pegawai_id', $pegawaiIds)
                    ->orWhereIn('pengawas_pendamping_pegawai_id', $pegawaiIds);
            })
            ->whereHas('jadwalUjianCbt', fn ($query) => $query
                ->whereDate('tanggal', $tanggal)
                ->where('waktu_mulai', '<', $waktuSelesai)
                ->where('waktu_selesai', '>', $waktuMulai))
            ->with('jadwalUjianCbt.mataPelajaran')
            ->first();

        if ($bentrok) {
            throw ValidationException::withMessages([
                'waktu_mulai' => 'Waktu baru berbenturan dengan tugas pengawas pada '
                    .($bentrok->jadwalUjianCbt?->mataPelajaran?->nama ?: 'jadwal lain').'.',
            ]);
        }
    }

    private function pelaksanaanBerubah(JadwalUjianCbt $jadwal, string $tanggal, array $data): bool
    {
        return $jadwal->tanggal?->toDateString() !== $tanggal
            || substr((string) $jadwal->waktu_mulai, 0, 5) !== $data['waktu_mulai']
            || substr((string) $jadwal->waktu_selesai, 0, 5) !== $data['waktu_selesai'];
    }

    private function paketSudahMulaiDikerjakan(UjianCbt $paket): bool
    {
        return $paket->pesertaUjianCbt()
            ->where(function ($query) {
                $query->whereIn('status', ['sedang_mengerjakan', 'selesai'])
                    ->orWhereNotNull('waktu_mulai')
                    ->orWhereHas('jawabanPesertaUjianCbt');
            })
            ->exists();
    }

    private function pastikanMilikKegiatan(KegiatanUjianCbt $kegiatan, JadwalUjianCbt $jadwal): void
    {
        abort_unless((int) $jadwal->kegiatan_ujian_cbt_id === (int) $kegiatan->id, 404);
    }
}
