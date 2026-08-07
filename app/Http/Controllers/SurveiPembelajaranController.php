<?php

namespace App\Http\Controllers;

use App\Models\AnggotaKelas;
use App\Models\GuruMataPelajaran;
use App\Models\Pengguna;
use App\Models\PertanyaanSurveiPembelajaran;
use App\Models\PublikasiNilaiSiswa;
use App\Models\Siswa;
use App\Models\SurveiPembelajaran;
use App\Services\Notifikasi\NotifikasiPenggunaService;
use App\Services\Survei\RekapSurveiPembelajaranService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SurveiPembelajaranController extends Controller
{
    public function __construct(private NotifikasiPenggunaService $notifikasi) {}

    public function create(Request $request, GuruMataPelajaran $guruMataPelajaran, string $semester)
    {
        $semester = $this->semesterValid($semester);
        $siswa = $this->siswaDariPengguna($request->user());
        $this->pastikanDapatMengisi($siswa, $guruMataPelajaran, $semester);

        if ($this->surveiSudahDiisi($siswa, $guruMataPelajaran, $semester)) {
            return $this->kembaliKeNilai($guruMataPelajaran, $semester)
                ->with('berhasil', 'Survei pembelajaran ini sudah Anda isi. Nilai sudah dapat dilihat.');
        }

        $guruMataPelajaran->load([
            'tahunPelajaran:id,nama',
            'kelas:id,nama,tingkat',
            'mataPelajaran:id,nama',
            'pegawai:id,nama_lengkap',
        ]);
        $daftarPertanyaan = PertanyaanSurveiPembelajaran::aktif()->terurut()->get();

        abort_if($daftarPertanyaan->isEmpty(), 503, 'Survei pembelajaran belum memiliki pernyataan aktif.');

        return view('survei-pembelajaran.create', [
            'siswa' => $siswa,
            'guruMataPelajaran' => $guruMataPelajaran,
            'semester' => $semester,
            'daftarPertanyaan' => $daftarPertanyaan,
            'daftarPilihan' => SurveiPembelajaran::PILIHAN,
        ]);
    }

    public function store(Request $request, GuruMataPelajaran $guruMataPelajaran, string $semester)
    {
        $semester = $this->semesterValid($semester);
        $siswa = $this->siswaDariPengguna($request->user());
        $this->pastikanDapatMengisi($siswa, $guruMataPelajaran, $semester);

        if ($this->surveiSudahDiisi($siswa, $guruMataPelajaran, $semester)) {
            return $this->kembaliKeNilai($guruMataPelajaran, $semester)
                ->with('berhasil', 'Survei pembelajaran ini sudah Anda isi. Nilai sudah dapat dilihat.');
        }

        $daftarPertanyaan = PertanyaanSurveiPembelajaran::aktif()->terurut()->get();
        abort_if($daftarPertanyaan->isEmpty(), 503, 'Survei pembelajaran belum memiliki pernyataan aktif.');

        $aturan = [
            'jawaban' => ['required', 'array', 'size:'.$daftarPertanyaan->count()],
            'saran' => ['nullable', 'string', 'max:1000'],
        ];
        foreach ($daftarPertanyaan as $pertanyaan) {
            $kode = $pertanyaan->kode;
            $aturan['jawaban.'.$kode] = [
                'required',
                'integer',
                Rule::in(array_keys(SurveiPembelajaran::PILIHAN)),
            ];
        }

        $data = $request->validate($aturan, [
            'jawaban.*.required' => 'Semua pernyataan survei wajib dijawab.',
            'jawaban.*.integer' => 'Pilihan jawaban survei tidak valid.',
            'jawaban.*.in' => 'Pilihan jawaban survei tidak valid.',
            'saran.max' => 'Saran maksimal 1.000 karakter.',
        ]);

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

        return $this->kembaliKeNilai($guruMataPelajaran, $semester)
            ->with('berhasil', 'Terima kasih. Survei berhasil dikirim dan nilai mata pelajaran sudah terbuka.');
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

    private function kembaliKeNilai(GuruMataPelajaran $guruMataPelajaran, string $semester)
    {
        return redirect()->to(route('nilai-saya.index', [
            'tahun_pelajaran_id' => $guruMataPelajaran->tahun_pelajaran_id,
            'semester' => $semester,
        ]).'#mapel-'.$guruMataPelajaran->id);
    }
}
