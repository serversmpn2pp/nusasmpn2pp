<?php

namespace App\Services\Mobile;

use App\Models\JawabanPesertaUjianCbt;
use App\Models\OrangTuaWali;
use App\Models\Pengguna;
use App\Models\PesertaUjianCbt;
use App\Models\Siswa;
use App\Models\SoalUjianCbt;
use App\Services\Cbt\DaftarUjianSiswaService;
use App\Services\Cbt\PengacakPenyajianCbt;
use Illuminate\Support\Collection;

class UjianAnakMobileService
{
    public function __construct(
        private readonly DaftarUjianSiswaService $daftarUjianSiswa,
        private readonly PengacakPenyajianCbt $pengacakPenyajian,
    ) {}

    public function tampilkan(Pengguna $pengguna, ?int $siswaId = null): array
    {
        $orangTua = $this->orangTua($pengguna);
        $pilihan = $orangTua?->siswa ?? collect();
        $siswa = $this->pilihSiswa($orangTua, $pilihan, $siswaId);

        if (! $siswa) {
            return $this->kosong($pilihan);
        }

        $data = $this->daftarUjianSiswa->siapkan($siswa);

        return [
            'mode' => 'orang_tua',
            'hanya_pemantauan' => true,
            'siswa' => $this->ringkasSiswa($siswa),
            'siswa_id' => (int) $siswa->id,
            'pilihan_siswa' => $pilihan
                ->map(fn (Siswa $item) => $this->ringkasSiswa($item))
                ->values()
                ->all(),
            'ringkasan' => [
                'aktif' => (int) $data['ringkasanUjian']['aktif'],
                'akan_datang' => (int) $data['ringkasanUjian']['akan_datang'],
                'selesai' => (int) $data['ringkasanUjian']['selesai'],
                'total' => (int) $data['ringkasanUjian']['total'],
            ],
            'items' => collect($data['daftarUjian'])
                ->map(fn (array $item) => $this->ringkasUjian($item))
                ->values()
                ->all(),
            'catatan' => 'Orang tua hanya dapat memantau jadwal, status, dan hasil yang telah dipublikasikan. Soal, jawaban, token, dan akses pengerjaan tidak ditampilkan.',
        ];
    }

    private function orangTua(Pengguna $pengguna): ?OrangTuaWali
    {
        abort_unless($pengguna->akunOrangTua(), 403);

        return $pengguna->orangTuaWali()
            ->with(['siswa' => fn ($query) => $query->orderBy('nama_lengkap')])
            ->first();
    }

    private function pilihSiswa(
        ?OrangTuaWali $orangTua,
        Collection $pilihan,
        ?int $siswaId,
    ): ?Siswa {
        if (! $orangTua || $pilihan->isEmpty()) {
            abort_if($siswaId !== null, 403);

            return null;
        }

        $siswa = $siswaId !== null
            ? $pilihan->firstWhere('id', $siswaId)
            : ($pilihan->firstWhere('id', $orangTua->siswa_acuan_username_id) ?: $pilihan->first());
        abort_unless($siswa, 403);

        return $siswa;
    }

    private function ringkasUjian(array $item): array
    {
        /** @var PesertaUjianCbt $peserta */
        $peserta = $item['peserta'];
        $ujian = $item['ujian'];
        $jadwal = $item['jadwal'];
        $soal = $this->soalUntukPeserta($peserta);
        $jawaban = $peserta->jawabanPesertaUjianCbt()
            ->whereIn('soal_ujian_cbt_id', $soal->pluck('id'))
            ->get()
            ->keyBy('soal_ujian_cbt_id');

        return [
            'id' => (int) $peserta->id,
            'ujian_id' => (int) $ujian->id,
            'nama' => $ujian->nama,
            'kode' => $ujian->kode,
            'jenis_ujian' => $ujian->jenisUjianCbt?->nama,
            'mata_pelajaran' => $ujian->mataPelajaran?->nama ?? 'Mata pelajaran belum ditentukan',
            'kelas' => $peserta->kelasUjianCbt?->kelas?->nama,
            'kelompok' => $item['kelompok'],
            'label_status' => $item['label_status'],
            'nada_status' => $item['nada_status'],
            'status_pengerjaan' => $peserta->status,
            'waktu_mulai' => $item['waktu_mulai']?->toISOString(),
            'waktu_selesai' => $item['waktu_selesai']?->toISOString(),
            'waktu' => $jadwal?->labelWaktu(),
            'tanggal' => $jadwal?->tanggal?->toDateString(),
            'durasi_menit' => (int) $ujian->durasi_menit,
            'nomor_peserta' => $peserta->nomor_peserta,
            'kemajuan' => $this->kemajuan($soal, $jawaban),
            'hasil' => $this->hasil($peserta, $soal, $jawaban),
        ];
    }

    private function kemajuan(Collection $soal, Collection $jawaban): array
    {
        $terjawab = $jawaban->whereNotNull('jawaban')->count();

        return [
            'jumlah_soal' => $soal->count(),
            'terjawab' => $terjawab,
            'belum_dijawab' => max(0, $soal->count() - $terjawab),
        ];
    }

    private function hasil(PesertaUjianCbt $peserta, Collection $soal, Collection $jawaban): array
    {
        $ujian = $peserta->ujianCbt;
        $kkm = $ujian?->kkm;

        if ($peserta->status !== 'selesai') {
            return [
                'ditampilkan' => false,
                'menunggu_koreksi' => false,
                'nilai' => null,
                'kkm' => $kkm,
                'tuntas' => null,
            ];
        }

        $jenisManual = ['uraian', 'upload_file'];
        $menungguKoreksi = $soal->contains(function (SoalUjianCbt $relasi) use ($jawaban, $jenisManual) {
            $item = $jawaban->get($relasi->id);

            return in_array($relasi->soalCbt?->jenis_soal, $jenisManual, true)
                && $item?->jawaban !== null
                && is_null($item->skor);
        });
        $bobotTotal = (float) $soal->sum(fn (SoalUjianCbt $item) => (float) $item->bobot);
        $skorTotal = (float) $jawaban->sum(fn (JawabanPesertaUjianCbt $item) => (float) ($item->skor ?? 0));
        $nilai = $bobotTotal > 0 ? round(($skorTotal / $bobotTotal) * 100, 2) : 0.0;
        $ditampilkan = (bool) $ujian?->tampilkan_hasil && ! $menungguKoreksi;

        return [
            'ditampilkan' => $ditampilkan,
            'menunggu_koreksi' => $menungguKoreksi,
            'nilai' => $ditampilkan ? $nilai : null,
            'kkm' => $kkm,
            'tuntas' => $ditampilkan && ! is_null($kkm)
                ? $nilai >= (float) $kkm
                : null,
        ];
    }

    private function soalUntukPeserta(PesertaUjianCbt $peserta): Collection
    {
        $soal = $peserta->ujianCbt->soalUjianCbt()
            ->with('soalCbt')
            ->get();

        return $this->pengacakPenyajian
            ->urutkanSoal($peserta->ujianCbt, $peserta, $soal)
            ->take($peserta->ujianCbt->jumlah_soal)
            ->values();
    }

    private function ringkasSiswa(Siswa $siswa): array
    {
        return [
            'id' => (int) $siswa->id,
            'nama' => $siswa->nama_lengkap,
            'nis' => $siswa->nis,
            'nisn' => $siswa->nisn,
        ];
    }

    private function kosong(Collection $pilihan): array
    {
        return [
            'mode' => 'orang_tua',
            'hanya_pemantauan' => true,
            'siswa' => null,
            'siswa_id' => null,
            'pilihan_siswa' => $pilihan->values()->all(),
            'ringkasan' => ['aktif' => 0, 'akan_datang' => 0, 'selesai' => 0, 'total' => 0],
            'items' => [],
            'catatan' => 'Hubungi administrator sekolah agar akun orang tua dihubungkan dengan data anak yang benar.',
        ];
    }
}
