<?php

namespace App\Services\Cbt;

use App\Models\JadwalUjianCbt;
use App\Models\KegiatanUjianCbt;
use App\Models\KelompokPesertaKegiatanUjianCbt;
use App\Models\MataPelajaran;
use App\Models\Pengguna;
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

        if (JadwalUjianCbt::query()
            ->where('kegiatan_ujian_cbt_id', $kegiatan->id)
            ->whereDate('tanggal', $tanggal)
            ->whereIn('tingkat', $tingkat)
            ->exists()) {
            throw ValidationException::withMessages([
                'tingkat' => 'Salah satu tingkat sudah memiliki jadwal pada tanggal tersebut.',
            ]);
        }

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
                    'waktu_mulai' => $sesi->waktu_mulai,
                    'waktu_selesai' => $sesi->waktu_selesai,
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

        if ($paket?->soal_ujian_cbt_count > 0 && (int) $mataPelajaran->id !== (int) $jadwal->mata_pelajaran_id) {
            throw ValidationException::withMessages([
                'mata_pelajaran_id' => 'Mata pelajaran tidak dapat diganti karena paket sudah berisi soal. Kosongkan soal paket terlebih dahulu.',
            ]);
        }

        if (JadwalUjianCbt::query()
            ->where('kegiatan_ujian_cbt_id', $kegiatan->id)
            ->whereDate('tanggal', $tanggal)
            ->where('tingkat', $jadwal->tingkat)
            ->where('id', '!=', $jadwal->id)
            ->exists()) {
            throw ValidationException::withMessages([
                'tanggal' => 'Tingkat ini sudah memiliki jadwal pada tanggal tersebut.',
            ]);
        }

        DB::transaction(function () use ($kegiatan, $jadwal, $data, $tanggal, $mataPelajaran, $kelompokTingkat, $paket, $pengguna) {
            $sesi = $kelompokTingkat->sesiKegiatanUjianCbt;
            $jadwal->update([
                'sesi_kegiatan_ujian_cbt_id' => $sesi->id,
                'mata_pelajaran_id' => $mataPelajaran->id,
                'tanggal' => $tanggal,
                'waktu_mulai' => $sesi->waktu_mulai,
                'waktu_selesai' => $sesi->waktu_selesai,
                'label_sesi' => $sesi->nama,
                'keterangan' => filled($data['keterangan'] ?? null) ? trim($data['keterangan']) : null,
            ]);
            $jadwal->kelas()->sync($kelompokTingkat->kelas->modelKeys());

            if ($paket) {
                $mulai = Carbon::parse($tanggal.' '.$sesi->waktu_mulai);
                $selesai = Carbon::parse($tanggal.' '.$sesi->waktu_selesai);
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

    private function dataValid(KegiatanUjianCbt $kegiatan, array $data, Collection $tingkat): array
    {
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

    private function pastikanMilikKegiatan(KegiatanUjianCbt $kegiatan, JadwalUjianCbt $jadwal): void
    {
        abort_unless((int) $jadwal->kegiatan_ujian_cbt_id === (int) $kegiatan->id, 404);
    }
}
