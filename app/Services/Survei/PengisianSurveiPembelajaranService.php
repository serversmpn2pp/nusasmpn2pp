<?php

namespace App\Services\Survei;

use App\Models\AnggotaKelas;
use App\Models\GuruMataPelajaran;
use App\Models\Pengguna;
use App\Models\PertanyaanSurveiPembelajaran;
use App\Models\PublikasiNilaiSiswa;
use App\Models\Siswa;
use App\Models\SurveiPembelajaran;
use App\Services\Notifikasi\NotifikasiPenggunaService;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class PengisianSurveiPembelajaranService
{
    public function __construct(private readonly NotifikasiPenggunaService $notifikasi) {}

    public function siapkan(
        ?Pengguna $pengguna,
        GuruMataPelajaran $guruMataPelajaran,
        string $semester,
    ): array {
        $semester = $this->semesterValid($semester);
        $siswa = $this->siswaDariPengguna($pengguna);
        $this->pastikanDapatMengisi($siswa, $guruMataPelajaran, $semester);
        $sudahDiisi = $this->surveiSudahDiisi($siswa, $guruMataPelajaran, $semester);

        $guruMataPelajaran->load([
            'tahunPelajaran:id,nama',
            'kelas:id,nama,tingkat',
            'mataPelajaran:id,kode,nama',
            'pegawai:id,nama_lengkap',
        ]);
        $daftarPertanyaan = $sudahDiisi
            ? collect()
            : PertanyaanSurveiPembelajaran::aktif()->terurut()->get();

        if (! $sudahDiisi) {
            abort_if($daftarPertanyaan->isEmpty(), 503, 'Survei pembelajaran belum memiliki pernyataan aktif.');
        }

        return compact(
            'siswa',
            'guruMataPelajaran',
            'semester',
            'daftarPertanyaan',
            'sudahDiisi',
        ) + [
            'daftarPilihan' => SurveiPembelajaran::PILIHAN,
        ];
    }

    public function aturanValidasi(Collection $daftarPertanyaan): array
    {
        $aturan = [
            'jawaban' => ['required', 'array', 'size:'.$daftarPertanyaan->count()],
            'saran' => ['nullable', 'string', 'max:1000'],
        ];

        foreach ($daftarPertanyaan as $pertanyaan) {
            $aturan['jawaban.'.$pertanyaan->kode] = [
                'required',
                'integer',
                Rule::in(array_keys(SurveiPembelajaran::PILIHAN)),
            ];
        }

        return $aturan;
    }

    public function pesanValidasi(): array
    {
        return [
            'jawaban.required' => 'Jawaban survei wajib diisi.',
            'jawaban.size' => 'Semua pernyataan survei wajib dijawab.',
            'jawaban.*.required' => 'Semua pernyataan survei wajib dijawab.',
            'jawaban.*.integer' => 'Pilihan jawaban survei tidak valid.',
            'jawaban.*.in' => 'Pilihan jawaban survei tidak valid.',
            'saran.max' => 'Saran maksimal 1.000 karakter.',
        ];
    }

    public function simpan(array $konteks, array $data): SurveiPembelajaran
    {
        /** @var GuruMataPelajaran $guruMataPelajaran */
        $guruMataPelajaran = $konteks['guruMataPelajaran'];
        /** @var Siswa $siswa */
        $siswa = $konteks['siswa'];
        /** @var Collection $daftarPertanyaan */
        $daftarPertanyaan = $konteks['daftarPertanyaan'];
        $semester = $konteks['semester'];

        $survei = SurveiPembelajaran::firstOrCreate(
            [
                'guru_mata_pelajaran_id' => $guruMataPelajaran->id,
                'siswa_id' => $siswa->id,
                'semester' => $semester,
            ],
            [
                'versi_pertanyaan' => SurveiPembelajaran::VERSI_PERTANYAAN,
                'jawaban' => $daftarPertanyaan
                    ->mapWithKeys(fn (PertanyaanSurveiPembelajaran $pertanyaan) => [
                        $pertanyaan->kode => (int) $data['jawaban'][$pertanyaan->kode],
                    ])
                    ->all(),
                'snapshot_pertanyaan' => $daftarPertanyaan
                    ->mapWithKeys(fn (PertanyaanSurveiPembelajaran $pertanyaan) => [
                        $pertanyaan->kode => [
                            'pernyataan' => $pertanyaan->pernyataan,
                            'urutan' => $pertanyaan->urutan,
                        ],
                    ])
                    ->all(),
                'saran' => filled($data['saran'] ?? null) ? trim($data['saran']) : null,
                'diisi_pada' => now(),
            ],
        );

        if ($survei->wasRecentlyCreated) {
            $this->kirimNotifikasiHasilTerbuka($guruMataPelajaran, $semester);
        }

        return $survei;
    }

    private function siswaDariPengguna(?Pengguna $pengguna): Siswa
    {
        abort_unless($pengguna?->akunSiswa() || $pengguna?->memilikiPeran('siswa'), 403);

        return $pengguna->siswa()->firstOrFail();
    }

    private function semesterValid(string $semester): string
    {
        abort_unless(in_array($semester, ['ganjil', 'genap'], true), 404);

        return $semester;
    }

    private function pastikanDapatMengisi(
        Siswa $siswa,
        GuruMataPelajaran $guruMataPelajaran,
        string $semester,
    ): void {
        $terpublikasi = PublikasiNilaiSiswa::query()
            ->where('guru_mata_pelajaran_id', $guruMataPelajaran->id)
            ->where('semester', $semester)
            ->where('dipublikasikan', true)
            ->exists();
        $anggotaKelas = AnggotaKelas::query()
            ->where('siswa_id', $siswa->id)
            ->where('tahun_pelajaran_id', $guruMataPelajaran->tahun_pelajaran_id)
            ->where('kelas_id', $guruMataPelajaran->kelas_id)
            ->exists();

        abort_unless($terpublikasi && $anggotaKelas, 404);
    }

    private function surveiSudahDiisi(
        Siswa $siswa,
        GuruMataPelajaran $guruMataPelajaran,
        string $semester,
    ): bool {
        return SurveiPembelajaran::query()
            ->where('guru_mata_pelajaran_id', $guruMataPelajaran->id)
            ->where('siswa_id', $siswa->id)
            ->where('semester', $semester)
            ->exists();
    }

    private function kirimNotifikasiHasilTerbuka(
        GuruMataPelajaran $guruMataPelajaran,
        string $semester,
    ): void {
        $jumlahPengisi = SurveiPembelajaran::query()
            ->where('guru_mata_pelajaran_id', $guruMataPelajaran->id)
            ->where('semester', $semester)
            ->count();

        if ($jumlahPengisi < RekapSurveiPembelajaranService::MINIMAL_RESPONDEN) {
            return;
        }

        $guruMataPelajaran->loadMissing([
            'mataPelajaran:id,nama',
            'kelas:id,nama',
        ]);

        if (! $guruMataPelajaran->pegawai_id) {
            return;
        }

        $namaMataPelajaran = $guruMataPelajaran->mataPelajaran?->nama ?? 'mata pelajaran';
        $namaKelas = $guruMataPelajaran->kelas?->nama ?? 'kelas';
        $tautan = route('hasil-survei-saya.index', [
            'tahun_pelajaran_id' => $guruMataPelajaran->tahun_pelajaran_id,
            'semester' => $semester,
            'guru_mata_pelajaran_id' => $guruMataPelajaran->id,
        ], false).'#rincian-survei';

        $this->notifikasi->kirimKeBanyak(
            $this->notifikasi->penggunaUntukPegawai((int) $guruMataPelajaran->pegawai_id),
            'berhasil',
            'Hasil survei sudah dapat dibuka',
            sprintf(
                'Survei %s untuk %s semester %s telah diisi %d siswa. Rincian anonim sekarang dapat dibuka.',
                $namaMataPelajaran,
                $namaKelas,
                ucfirst($semester),
                $jumlahPengisi,
            ),
            $tautan,
            "hasil-survei-terbuka:{$guruMataPelajaran->id}:{$semester}",
            [
                'guru_mata_pelajaran_id' => $guruMataPelajaran->id,
                'semester' => $semester,
                'jumlah_pengisi' => $jumlahPengisi,
            ],
        );
    }
}
