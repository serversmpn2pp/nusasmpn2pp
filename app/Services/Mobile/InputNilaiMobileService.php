<?php

namespace App\Services\Mobile;

use App\Models\AnggotaKelas;
use App\Models\GuruMataPelajaran;
use App\Models\KomponenNilai;
use App\Models\NilaiSiswa;
use App\Models\Pengguna;
use App\Models\PublikasiNilaiSiswa;
use App\Models\TahunPelajaran;
use App\Services\Nilai\InputNilaiService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class InputNilaiMobileService
{
    public function __construct(private readonly InputNilaiService $inputNilai) {}

    public function tampilkan(Pengguna $pengguna, array $filter): array
    {
        $semester = $filter['semester'] ?? 'ganjil';
        $penugasan = $this->daftarPenugasan($pengguna);
        $penugasanId = isset($filter['guru_mata_pelajaran_id'])
            ? (int) $filter['guru_mata_pelajaran_id']
            : null;
        $penugasanDipilih = $penugasanId
            ? $penugasan->firstWhere('id', $penugasanId)
            : $penugasan->first();

        if ($penugasanId && ! $penugasanDipilih) {
            abort(404);
        }

        $komponen = $penugasanDipilih
            ? $this->daftarKomponen($pengguna, (int) $penugasanDipilih->id, $semester)
            : collect();
        $komponenId = isset($filter['komponen_nilai_id'])
            ? (int) $filter['komponen_nilai_id']
            : null;
        $komponenDipilih = $komponenId
            ? $komponen->firstWhere('id', $komponenId)
            : $komponen->first();

        if ($komponenId && ! $komponenDipilih) {
            abort(404);
        }

        $anggotaKelas = $penugasanDipilih
            ? $this->inputNilai->ambilAnggotaKelas((int) $penugasanDipilih->kelas_id)
            : collect();
        $nilaiTersimpan = $this->nilaiTersimpan($komponenDipilih, $anggotaKelas);
        $menggunakanPredikat = $penugasanDipilih
            ? ($penugasanDipilih->mataPelajaran?->menggunakanPredikat() ?? false)
            : false;

        return [
            'guru_mata_pelajaran' => $penugasan
                ->map(fn (GuruMataPelajaran $item) => $this->ringkasPenugasan($item))
                ->values(),
            'komponen_nilai' => $komponen
                ->map(fn (KomponenNilai $item) => $this->ringkasKomponen($item))
                ->values(),
            'komponen_dipilih' => $komponenDipilih
                ? $this->ringkasKomponen($komponenDipilih)
                : null,
            'siswa' => $anggotaKelas
                ->map(fn (AnggotaKelas $anggota) => $this->ringkasSiswa(
                    $anggota,
                    $nilaiTersimpan->get($anggota->siswa_id),
                ))
                ->values(),
            'ringkasan' => $this->ringkasan($anggotaKelas, $nilaiTersimpan, $menggunakanPredikat),
            'publikasi' => $this->ringkasanPublikasi(
                $penugasanDipilih,
                $semester,
                $anggotaKelas,
            ),
            'filter' => [
                'guru_mata_pelajaran_id' => $penugasanDipilih?->id,
                'semester' => $semester,
                'komponen_nilai_id' => $komponenDipilih?->id,
            ],
            'mode_penilaian' => $menggunakanPredikat ? 'predikat' : 'angka',
            'opsi_predikat' => ['SB', 'B', 'C', 'K'],
            'hak_akses' => [
                'dapat_input' => $pengguna->memilikiIzin('nilai.input'),
            ],
        ];
    }

    private function daftarPenugasan(Pengguna $pengguna): Collection
    {
        return $this->inputNilai
            ->queryGuruMataPelajaranDalamCakupan($pengguna)
            ->with([
                'tahunPelajaran:id,nama,aktif',
                'kelas:id,nama,tingkat',
                'mataPelajaran:id,kode,nama,kelompok',
                'pegawai:id,nama_lengkap,nip',
            ])
            ->where('aktif', true)
            ->orderByDesc(
                TahunPelajaran::select('aktif')
                    ->whereColumn('tahun_pelajaran.id', 'guru_mata_pelajaran.tahun_pelajaran_id')
                    ->limit(1),
            )
            ->orderByDesc('tahun_pelajaran_id')
            ->orderBy('kelas_id')
            ->orderBy('mata_pelajaran_id')
            ->get();
    }

    private function daftarKomponen(
        Pengguna $pengguna,
        int $guruMataPelajaranId,
        string $semester,
    ): Collection {
        return $this->inputNilai
            ->queryKomponenDalamCakupan($pengguna)
            ->with('guruMataPelajaran.kelas:id,nama,tingkat')
            ->where('guru_mata_pelajaran_id', $guruMataPelajaranId)
            ->where('semester', $semester)
            ->where('aktif', true)
            ->orderBy('jenis_komponen')
            ->orderBy('urutan')
            ->orderBy('nama')
            ->get();
    }

    private function nilaiTersimpan(?KomponenNilai $komponen, Collection $anggotaKelas): Collection
    {
        if (! $komponen || $anggotaKelas->isEmpty()) {
            return collect();
        }

        return NilaiSiswa::query()
            ->where('komponen_nilai_id', $komponen->id)
            ->whereIn('siswa_id', $anggotaKelas->pluck('siswa_id'))
            ->get()
            ->keyBy('siswa_id');
    }

    private function ringkasan(
        Collection $anggotaKelas,
        Collection $nilaiTersimpan,
        bool $menggunakanPredikat,
    ): array {
        $terisi = $nilaiTersimpan->filter(fn (NilaiSiswa $nilai) => $menggunakanPredikat
            ? filled($nilai->predikat)
            : $nilai->nilai !== null);
        $rataRata = $menggunakanPredikat
            ? null
            : $terisi->avg(fn (NilaiSiswa $nilai) => (float) $nilai->nilai);

        return [
            'jumlah_siswa' => $anggotaKelas->count(),
            'jumlah_terisi' => $terisi->count(),
            'jumlah_belum_terisi' => max(0, $anggotaKelas->count() - $terisi->count()),
            'rata_rata' => $rataRata === null ? null : round((float) $rataRata, 2),
        ];
    }

    private function ringkasanPublikasi(
        ?GuruMataPelajaran $penugasan,
        string $semester,
        Collection $anggotaKelas,
    ): array {
        if (! $penugasan) {
            return $this->publikasiKosong();
        }

        $komponenIds = KomponenNilai::query()
            ->where('guru_mata_pelajaran_id', $penugasan->id)
            ->where('semester', $semester)
            ->where('aktif', true)
            ->pluck('id');
        $jumlahNilai = NilaiSiswa::query()
            ->whereIn('komponen_nilai_id', $komponenIds)
            ->whereIn('siswa_id', $anggotaKelas->pluck('siswa_id'))
            ->where(function (Builder $query) {
                $query->whereNotNull('nilai')->orWhereNotNull('predikat');
            })
            ->count();
        $publikasi = PublikasiNilaiSiswa::query()
            ->where('guru_mata_pelajaran_id', $penugasan->id)
            ->where('semester', $semester)
            ->first();
        $dipublikasikan = (bool) $publikasi?->dipublikasikan;

        return [
            'status' => $dipublikasikan ? 'dipublikasikan' : 'draf',
            'dipublikasikan' => $dipublikasikan,
            'dipublikasikan_pada' => $publikasi?->dipublikasikan_pada?->toIso8601String(),
            'dipublikasikan_pada_label' => $publikasi?->dipublikasikan_pada
                ? $publikasi->dipublikasikan_pada->format('d-m-Y H:i').' WIB'
                : null,
            'jumlah_komponen' => $komponenIds->count(),
            'jumlah_nilai' => $jumlahNilai,
            'target_nilai' => $komponenIds->count() * $anggotaKelas->count(),
            'dapat_dipublikasikan' => $penugasan->aktif && $komponenIds->isNotEmpty() && $jumlahNilai > 0,
            'dapat_dijadikan_draf' => $dipublikasikan,
        ];
    }

    private function publikasiKosong(): array
    {
        return [
            'status' => 'draf',
            'dipublikasikan' => false,
            'dipublikasikan_pada' => null,
            'dipublikasikan_pada_label' => null,
            'jumlah_komponen' => 0,
            'jumlah_nilai' => 0,
            'target_nilai' => 0,
            'dapat_dipublikasikan' => false,
            'dapat_dijadikan_draf' => false,
        ];
    }

    private function ringkasPenugasan(GuruMataPelajaran $item): array
    {
        return [
            'id' => (int) $item->id,
            'tahun_pelajaran' => [
                'id' => (int) $item->tahunPelajaran?->id,
                'nama' => $item->tahunPelajaran?->nama ?? '-',
                'aktif' => (bool) $item->tahunPelajaran?->aktif,
            ],
            'kelas' => [
                'id' => (int) $item->kelas?->id,
                'nama' => $item->kelas?->nama ?? '-',
                'tingkat' => (int) $item->kelas?->tingkat,
            ],
            'mata_pelajaran' => [
                'id' => (int) $item->mataPelajaran?->id,
                'kode' => $item->mataPelajaran?->kode,
                'nama' => $item->mataPelajaran?->nama ?? '-',
                'mode_penilaian' => $item->mataPelajaran?->menggunakanPredikat() ? 'predikat' : 'angka',
            ],
            'pegawai' => [
                'id' => (int) $item->pegawai?->id,
                'nama' => $item->pegawai?->nama_lengkap ?? '-',
                'nip' => $item->pegawai?->nip,
            ],
        ];
    }

    private function ringkasKomponen(KomponenNilai $item): array
    {
        return [
            'id' => (int) $item->id,
            'guru_mata_pelajaran_id' => (int) $item->guru_mata_pelajaran_id,
            'semester' => $item->semester,
            'jenis_komponen' => $item->jenis_komponen,
            'jenis_label' => $item->labelJenis(),
            'nama' => $item->nama,
            'tanggal_penilaian' => $item->tanggal_penilaian?->toDateString(),
            'tanggal_label' => $item->tanggal_penilaian?->format('d-m-Y'),
            'urutan' => (int) $item->urutan,
        ];
    }

    private function ringkasSiswa(AnggotaKelas $anggota, ?NilaiSiswa $nilai): array
    {
        return [
            'anggota_kelas_id' => (int) $anggota->id,
            'nomor_absen' => $anggota->nomor_absen,
            'siswa' => [
                'id' => (int) $anggota->siswa?->id,
                'nama' => $anggota->siswa?->nama_lengkap ?? '-',
                'nis' => $anggota->siswa?->nis,
                'nisn' => $anggota->siswa?->nisn,
            ],
            'nilai' => $nilai?->nilai === null ? null : (float) $nilai->nilai,
            'predikat' => $nilai?->predikat,
            'catatan' => $nilai?->catatan,
        ];
    }
}
