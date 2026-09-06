<?php

namespace App\Services\Mobile;

use App\Models\JadwalUjianCbt;
use App\Models\JawabanPesertaUjianCbt;
use App\Models\KegiatanUjianCbt;
use App\Models\Pengguna;
use App\Models\PesertaUjianCbt;
use App\Models\SoalUjianCbt;
use App\Models\UjianCbt;
use App\Services\Cbt\KoreksiOtomatisCbtService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KoreksiUraianAsesmenKelasMobileService
{
    public function daftar(Pengguna $pengguna, UjianCbt $asesmen, array $filter): array
    {
        $this->pastikanDapatDiakses($pengguna, $asesmen);

        return $this->susunDaftar($asesmen, $filter, true);
    }

    public function daftarUjianTerpusat(
        Pengguna $pengguna,
        KegiatanUjianCbt $kegiatan,
        JadwalUjianCbt $jadwal,
        array $filter,
    ): array {
        $asesmen = $this->paketUjianTerpusat($kegiatan, $jadwal);
        $dapatMengelola = $asesmen->dapatDikelolaOleh($pengguna);
        $dapatMengoreksi = $dapatMengelola && ! $asesmen->hasil_difinalisasi_pada;
        abort_unless($kegiatan->dapatDiaksesOleh($pengguna) || $dapatMengelola, 403);

        return $this->susunDaftar($asesmen, $filter, $dapatMengoreksi);
    }

    public function simpanUjianTerpusat(
        Pengguna $pengguna,
        KegiatanUjianCbt $kegiatan,
        JadwalUjianCbt $jadwal,
        array $daftarSkor,
    ): int {
        $asesmen = $this->paketUjianTerpusat($kegiatan, $jadwal);
        abort_unless($asesmen->dapatDikelolaOleh($pengguna), 403);
        abort_if(
            $asesmen->hasil_difinalisasi_pada,
            422,
            'Hasil ujian sudah difinalisasi. Batalkan finalisasi untuk mengubah skor.',
        );

        return $this->simpanSkor($asesmen, $daftarSkor);
    }

    private function susunDaftar(
        UjianCbt $asesmen,
        array $filter,
        bool $dapatMengoreksi,
    ): array {
        $asesmen->loadMissing([
            'tahunPelajaran:id,nama',
            'mataPelajaran:id,nama',
            'kelasUjianCbt.kelas:id,nama',
            'kelasUjianCbt.komponenNilai:id,nama',
        ]);

        $kelasId = filled($filter['kelas_id'] ?? null) ? (int) $filter['kelas_id'] : null;
        $status = $filter['status'] ?? 'semua';
        $soalManual = $this->soalManual($asesmen);
        $peserta = $asesmen->pesertaUjianCbt()
            ->with([
                'kelasUjianCbt.kelas',
                'anggotaKelas.siswa',
                'jawabanPesertaUjianCbt' => fn ($query) => $query->whereIn(
                    'soal_ujian_cbt_id',
                    $soalManual->pluck('id'),
                ),
            ])
            ->when($kelasId, fn (Builder $query) => $query->whereHas(
                'kelasUjianCbt',
                fn (Builder $query) => $query->where('kelas_id', $kelasId),
            ))
            ->get()
            ->sortBy(fn (PesertaUjianCbt $item) => sprintf(
                '%s|%05d|%s',
                $item->kelasUjianCbt?->kelas?->nama ?? '',
                $item->anggotaKelas?->nomor_absen ?? 999,
                $item->anggotaKelas?->siswa?->nama_lengkap ?? '',
            ))
            ->values();
        $semuaBaris = $this->susunBaris($peserta, $soalManual);
        $baris = $semuaBaris
            ->when($status === 'belum_dikoreksi', fn (Collection $items) => $items->filter(
                fn (array $item) => $item['sudah_dijawab'] && ! $item['sudah_dikoreksi'],
            ))
            ->when($status === 'sudah_dikoreksi', fn (Collection $items) => $items->where(
                'sudah_dikoreksi',
                true,
            ))
            ->values();

        return [
            'dihasilkan_pada' => now()->toISOString(),
            'dapat_mengoreksi' => $dapatMengoreksi,
            'asesmen' => $this->asesmen($asesmen),
            'jumlah_soal_manual' => $soalManual->count(),
            'ringkasan' => $this->ringkasan($semuaBaris),
            'referensi' => [
                'kelas' => $asesmen->kelasUjianCbt
                    ->map(fn ($item) => [
                        'id' => (int) $item->kelas_id,
                        'label' => $item->kelas?->nama ?? '-',
                    ])
                    ->sortBy('label')
                    ->values(),
                'status' => collect([
                    'semua' => 'Semua jawaban',
                    'belum_dikoreksi' => 'Belum dikoreksi',
                    'sudah_dikoreksi' => 'Sudah dikoreksi',
                ])->map(fn ($label, $kode) => ['kode' => $kode, 'label' => $label])->values(),
            ],
            'filter' => ['kelas_id' => $kelasId, 'status' => $status],
            'items' => $baris,
        ];
    }

    public function simpan(Pengguna $pengguna, UjianCbt $asesmen, array $daftarSkor): int
    {
        $this->pastikanDapatDiakses($pengguna, $asesmen);

        return $this->simpanSkor($asesmen, $daftarSkor);
    }

    private function simpanSkor(UjianCbt $asesmen, array $daftarSkor): int
    {
        $nilaiSkor = collect($daftarSkor)->keyBy(fn (array $item) => (int) $item['jawaban_id']);
        $soalManual = $this->soalManual($asesmen)->keyBy('id');
        $jawaban = JawabanPesertaUjianCbt::query()
            ->whereIn('id', $nilaiSkor->keys())
            ->whereHas(
                'pesertaUjianCbt',
                fn (Builder $query) => $query->where('ujian_cbt_id', $asesmen->id),
            )
            ->get()
            ->keyBy('id');
        $errors = [];

        foreach ($nilaiSkor->values() as $index => $item) {
            $jawabanPeserta = $jawaban->get((int) $item['jawaban_id']);

            if (
                ! $jawabanPeserta
                || ! $soalManual->has((int) $jawabanPeserta->soal_ujian_cbt_id)
                || is_null($jawabanPeserta->jawaban)
            ) {
                $errors["skor.{$index}.jawaban_id"] = 'Jawaban tidak valid untuk koreksi uraian asesmen ini.';

                continue;
            }

            $nilai = $item['nilai'];
            $skorMaksimal = (float) $soalManual->get($jawabanPeserta->soal_ujian_cbt_id)->bobot;

            if (! is_null($nilai) && round((float) $nilai, 2) > $skorMaksimal) {
                $errors["skor.{$index}.nilai"] = "Skor tidak boleh lebih dari {$skorMaksimal}.";
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        DB::transaction(function () use ($nilaiSkor, $jawaban, $soalManual): void {
            foreach ($nilaiSkor as $jawabanId => $item) {
                $jawabanPeserta = $jawaban->get((int) $jawabanId);
                $skorMaksimal = (float) $soalManual->get($jawabanPeserta->soal_ujian_cbt_id)->bobot;
                $nilai = $item['nilai'];

                if (is_null($nilai)) {
                    $jawabanPeserta->update(['skor' => null, 'benar' => null]);

                    continue;
                }

                $skor = round((float) $nilai, 2);
                $jawabanPeserta->update([
                    'skor' => $skor,
                    'benar' => $skorMaksimal > 0 && $skor >= $skorMaksimal,
                ]);
            }
        });

        return $nilaiSkor->count();
    }

    private function soalManual(UjianCbt $asesmen): Collection
    {
        return $asesmen->soalUjianCbt()
            ->with('soalCbt')
            ->get()
            ->sortBy(fn (SoalUjianCbt $item) => sprintf(
                '%05d|%08d',
                $item->nomor_urut ?? 9999,
                $item->id,
            ))
            ->values()
            ->take($asesmen->jumlah_soal)
            ->reject(fn (SoalUjianCbt $item) => in_array(
                $item->soalCbt?->jenis_soal,
                KoreksiOtomatisCbtService::JENIS_OTOMATIS,
                true,
            ))
            ->values();
    }

    private function susunBaris(Collection $peserta, Collection $soalManual): Collection
    {
        return $peserta->flatMap(function (PesertaUjianCbt $peserta) use ($soalManual) {
            $jawaban = $peserta->jawabanPesertaUjianCbt->keyBy('soal_ujian_cbt_id');

            return $soalManual->map(function (SoalUjianCbt $relasiSoal) use ($peserta, $jawaban) {
                $jawabanPeserta = $jawaban->get($relasiSoal->id);
                $sudahDijawab = $jawabanPeserta && ! is_null($jawabanPeserta->jawaban);
                $sudahDikoreksi = $jawabanPeserta && ! is_null($jawabanPeserta->skor);
                $soal = $relasiSoal->soalCbt;

                return [
                    'id' => $jawabanPeserta?->id
                        ? "jawaban-{$jawabanPeserta->id}"
                        : "kosong-{$peserta->id}-{$relasiSoal->id}",
                    'jawaban_id' => $jawabanPeserta?->id,
                    'peserta_id' => (int) $peserta->id,
                    'siswa' => [
                        'id' => (int) ($peserta->anggotaKelas?->siswa?->id ?? 0),
                        'nama' => $peserta->anggotaKelas?->siswa?->nama_lengkap ?? '-',
                        'nis' => $peserta->anggotaKelas?->siswa?->nis,
                        'nisn' => $peserta->anggotaKelas?->siswa?->nisn,
                        'nomor_absen' => $peserta->anggotaKelas?->nomor_absen,
                    ],
                    'kelas' => $peserta->kelasUjianCbt?->kelas?->nama ?? '-',
                    'soal' => [
                        'id' => (int) $relasiSoal->id,
                        'nomor' => (int) ($relasiSoal->nomor_urut ?? 0),
                        'kode' => $soal?->kode ?? '-',
                        'jenis' => $soal?->jenis_soal ?? '-',
                        'label_jenis' => $soal?->labelJenis() ?? '-',
                        'pertanyaan' => str(strip_tags((string) $soal?->pertanyaan))->squish()->toString(),
                        'rubrik' => data_get($soal?->rubrik, 'catatan'),
                        'bobot' => round((float) $relasiSoal->bobot, 2),
                    ],
                    'jawaban' => $this->teksJawaban($jawabanPeserta?->jawaban),
                    'sudah_dijawab' => (bool) $sudahDijawab,
                    'sudah_dikoreksi' => (bool) $sudahDikoreksi,
                    'skor' => is_null($jawabanPeserta?->skor)
                        ? null
                        : round((float) $jawabanPeserta->skor, 2),
                ];
            });
        })->values();
    }

    private function ringkasan(Collection $items): array
    {
        return [
            'total' => $items->count(),
            'terjawab' => $items->where('sudah_dijawab', true)->count(),
            'belum_dijawab' => $items->where('sudah_dijawab', false)->count(),
            'belum_dikoreksi' => $items
                ->filter(fn (array $item) => $item['sudah_dijawab'] && ! $item['sudah_dikoreksi'])
                ->count(),
            'sudah_dikoreksi' => $items->where('sudah_dikoreksi', true)->count(),
        ];
    }

    private function asesmen(UjianCbt $asesmen): array
    {
        $jumlahSoalPaket = $asesmen->soalUjianCbt()->count();

        return [
            'id' => (int) $asesmen->id,
            'nama' => $asesmen->nama,
            'kode' => $asesmen->kode,
            'mata_pelajaran' => $asesmen->mataPelajaran?->nama ?? '-',
            'tahun_pelajaran' => $asesmen->tahunPelajaran?->nama,
            'semester' => $asesmen->semester,
            'tingkat' => (int) $asesmen->tingkat,
            'status' => $asesmen->status,
            'label_status' => $asesmen->labelStatus(),
            'tanggal_mulai' => $asesmen->tanggal_mulai?->toISOString(),
            'tanggal_selesai' => $asesmen->tanggal_selesai?->toISOString(),
            'durasi_menit' => (int) $asesmen->durasi_menit,
            'kkm' => $asesmen->kkm === null ? null : (int) $asesmen->kkm,
            'jumlah_soal_paket' => $jumlahSoalPaket,
            'jumlah_soal_tampil' => min((int) $asesmen->jumlah_soal, $jumlahSoalPaket),
            'kelas' => $asesmen->kelasUjianCbt->map(fn ($item) => [
                'id' => (int) $item->kelas_id,
                'nama' => $item->kelas?->nama ?? '-',
                'komponen_nilai' => $item->komponenNilai?->nama,
            ])->sortBy('nama')->values(),
        ];
    }

    private function teksJawaban(?array $jawaban): string
    {
        return collect($jawaban ?? [])
            ->flatten()
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->implode("\n");
    }

    private function pastikanDapatDiakses(Pengguna $pengguna, UjianCbt $asesmen): void
    {
        abort_unless($asesmen->asesmenKelas() && $asesmen->dapatDikelolaOleh($pengguna), 403);
    }

    private function paketUjianTerpusat(
        KegiatanUjianCbt $kegiatan,
        JadwalUjianCbt $jadwal,
    ): UjianCbt {
        abort_unless((int) $jadwal->kegiatan_ujian_cbt_id === (int) $kegiatan->id, 404);
        $jadwal->loadMissing('ujianCbt');
        abort_unless($jadwal->ujianCbt?->ujianTerpusat(), 404);

        return $jadwal->ujianCbt;
    }
}
