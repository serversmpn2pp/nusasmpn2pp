<?php

namespace App\Services\Mobile;

use App\Models\GuruMataPelajaran;
use App\Models\JadwalPelajaran;
use App\Models\JamPelajaran;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\Pengguna;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JadwalKelasMobileService
{
    /**
     * @return array{items: array<int, array<string, mixed>>, jumlah: int}
     */
    public function pilihan(Pengguna $pengguna, Kelas $kelas): array
    {
        $this->pastikanDapatKelola($pengguna, $kelas);

        $penugasan = GuruMataPelajaran::query()
            ->with(['mataPelajaran', 'pegawai'])
            ->where('tahun_pelajaran_id', $kelas->tahun_pelajaran_id)
            ->where('kelas_id', $kelas->id)
            ->where('jenis_penugasan', 'pengampu')
            ->where('aktif', true)
            ->orderBy(
                MataPelajaran::select('nama')
                    ->whereColumn('mata_pelajaran.id', 'guru_mata_pelajaran.mata_pelajaran_id')
                    ->limit(1)
            )
            ->orderBy(
                Pegawai::select('nama_lengkap')
                    ->whereColumn('pegawai.id', 'guru_mata_pelajaran.pegawai_id')
                    ->limit(1)
            )
            ->get()
            ->map(fn (GuruMataPelajaran $item) => [
                'nilai' => 'guru:'.$item->id,
                'jenis' => 'guru',
                'judul' => $item->mataPelajaran?->nama ?? 'Mata pelajaran',
                'subjudul' => $item->pegawai?->nama_lengkap ?? 'Guru belum ditentukan',
                'mata_pelajaran_id' => (int) $item->mata_pelajaran_id,
                'pegawai_id' => (int) $item->pegawai_id,
            ]);

        $kegiatan = $this->ambilKegiatan($kelas)
            ->map(fn (MataPelajaran $item) => [
                'nilai' => 'kegiatan:'.$item->id,
                'jenis' => 'kegiatan',
                'judul' => $item->nama,
                'subjudul' => $item->kelompok,
                'mata_pelajaran_id' => (int) $item->id,
                'pegawai_id' => null,
            ]);
        $items = $penugasan->concat($kegiatan)->values();

        return [
            'items' => $items->all(),
            'jumlah' => $items->count(),
        ];
    }

    public function simpanSlot(
        Pengguna $pengguna,
        Kelas $kelas,
        JamPelajaran $jamPelajaran,
        ?string $nilai,
        ?string $keterangan,
    ): void {
        $this->pastikanDapatKelola($pengguna, $kelas);

        if (! $jamPelajaran->aktif || $jamPelajaran->jenis !== 'pelajaran') {
            throw ValidationException::withMessages([
                'jam_pelajaran_id' => 'Pilih slot dengan jenis Pelajaran. Slot istirahat/upacara tidak dapat diubah.',
            ]);
        }

        $pilihan = $this->uraikanPilihan($nilai);

        if (filled($nilai) && ! $pilihan) {
            throw ValidationException::withMessages([
                'pilihan_jadwal' => 'Pilihan jadwal tidak valid.',
            ]);
        }

        $jadwal = JadwalPelajaran::query()
            ->where('tahun_pelajaran_id', $kelas->tahun_pelajaran_id)
            ->where('kelas_id', $kelas->id)
            ->where('jam_pelajaran_id', $jamPelajaran->id)
            ->first();

        if (! $pilihan) {
            $jadwal?->update([
                'aktif' => false,
                'keterangan' => $keterangan,
            ]);

            return;
        }

        $penugasan = $this->pastikanPilihanTersedia($kelas, $pilihan);
        $this->pastikanGuruTidakBentrok($kelas, $jamPelajaran, $penugasan, $jadwal);

        DB::transaction(function () use ($kelas, $jamPelajaran, $pilihan, $keterangan, $jadwal) {
            $atribut = [
                'hari' => $jamPelajaran->hari,
                'guru_mata_pelajaran_id' => $pilihan['guru_mata_pelajaran_id'],
                'mata_pelajaran_id' => $pilihan['mata_pelajaran_id'],
                'keterangan' => $keterangan,
                'aktif' => true,
            ];

            if ($jadwal) {
                $jadwal->update($atribut);

                return;
            }

            JadwalPelajaran::create([
                'tahun_pelajaran_id' => $kelas->tahun_pelajaran_id,
                'kelas_id' => $kelas->id,
                'jam_pelajaran_id' => $jamPelajaran->id,
                ...$atribut,
            ]);
        });
    }

    private function pastikanDapatKelola(Pengguna $pengguna, Kelas $kelas): void
    {
        abort_unless($pengguna->memilikiIzin('jadwal.kelola'), 403);
        abort_unless($pengguna->dapatMengaksesKelasSebagaiWali($kelas->id), 403);

        if (! $kelas->aktif) {
            throw ValidationException::withMessages([
                'kelas_id' => 'Jadwal kelas yang sudah tidak aktif tidak dapat diubah.',
            ]);
        }
    }

    /**
     * @param  array{guru_mata_pelajaran_id: ?int, mata_pelajaran_id: ?int}  $pilihan
     */
    private function pastikanPilihanTersedia(Kelas $kelas, array $pilihan): ?GuruMataPelajaran
    {
        if ($pilihan['guru_mata_pelajaran_id']) {
            $penugasan = GuruMataPelajaran::query()
                ->with(['mataPelajaran', 'pegawai'])
                ->whereKey($pilihan['guru_mata_pelajaran_id'])
                ->where('tahun_pelajaran_id', $kelas->tahun_pelajaran_id)
                ->where('kelas_id', $kelas->id)
                ->where('jenis_penugasan', 'pengampu')
                ->where('aktif', true)
                ->first();

            if (! $penugasan) {
                throw ValidationException::withMessages([
                    'pilihan_jadwal' => 'Guru mata pelajaran harus sesuai dengan tahun pelajaran dan kelas.',
                ]);
            }

            return $penugasan;
        }

        if (! $this->ambilKegiatan($kelas)->contains('id', $pilihan['mata_pelajaran_id'])) {
            throw ValidationException::withMessages([
                'pilihan_jadwal' => 'Kokurikuler atau ekstrakurikuler tidak tersedia untuk tingkat kelas ini.',
            ]);
        }

        return null;
    }

    private function pastikanGuruTidakBentrok(
        Kelas $kelas,
        JamPelajaran $jamPelajaran,
        ?GuruMataPelajaran $penugasan,
        ?JadwalPelajaran $jadwal,
    ): void {
        if (! $penugasan) {
            return;
        }

        $bentrok = JadwalPelajaran::query()
            ->with('kelas')
            ->where('tahun_pelajaran_id', $kelas->tahun_pelajaran_id)
            ->where('hari', $jamPelajaran->hari)
            ->where('jam_pelajaran_id', $jamPelajaran->id)
            ->where('aktif', true)
            ->whereHas(
                'guruMataPelajaran',
                fn ($query) => $query->where('pegawai_id', $penugasan->pegawai_id),
            )
            ->when($jadwal, fn ($query) => $query->whereKeyNot($jadwal->id))
            ->first();

        if ($bentrok) {
            $namaGuru = $penugasan->pegawai?->nama_lengkap ?? 'Guru';
            $kelasBentrok = $bentrok->kelas?->nama ?? 'kelas lain';

            throw ValidationException::withMessages([
                'pilihan_jadwal' => "{$namaGuru} sudah mengajar di {$kelasBentrok} pada "
                    .$jamPelajaran->labelHari().' '.$jamPelajaran->labelJam().'.',
            ]);
        }
    }

    private function ambilKegiatan(Kelas $kelas)
    {
        return MataPelajaran::query()
            ->with('pengaturanTingkat')
            ->where('aktif', true)
            ->whereIn('kelompok', ['Kokurikuler', 'Ekstrakurikuler'])
            ->orderBy('kelompok')
            ->orderBy('urutan')
            ->orderBy('nama')
            ->get()
            ->filter(function (MataPelajaran $mataPelajaran) use ($kelas) {
                $pengaturan = $mataPelajaran->pengaturanTingkat;

                if ($pengaturan->isNotEmpty()) {
                    return $pengaturan->contains(fn ($item) => (
                        (int) $item->tahun_pelajaran_id === (int) $kelas->tahun_pelajaran_id
                        && (int) $item->tingkat === (int) $kelas->tingkat
                        && $item->aktif
                    ));
                }

                return ! $mataPelajaran->tingkat
                    || (int) $mataPelajaran->tingkat === (int) $kelas->tingkat;
            })
            ->values();
    }

    /**
     * @return array{guru_mata_pelajaran_id: ?int, mata_pelajaran_id: ?int}|null
     */
    private function uraikanPilihan(?string $nilai): ?array
    {
        if (! filled($nilai)) {
            return null;
        }

        if (! preg_match('/^(guru|kegiatan):(\d+)$/', trim($nilai), $bagian)) {
            return null;
        }

        return [
            'guru_mata_pelajaran_id' => $bagian[1] === 'guru' ? (int) $bagian[2] : null,
            'mata_pelajaran_id' => $bagian[1] === 'kegiatan' ? (int) $bagian[2] : null,
        ];
    }
}
