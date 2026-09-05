<?php

namespace App\Services\Mobile;

use App\Models\JawabanPesertaUjianCbt;
use App\Models\Pengguna;
use App\Models\PesertaUjianCbt;
use App\Models\SoalCbt;
use App\Models\SoalUjianCbt;
use App\Services\Cbt\DaftarUjianSiswaService;
use App\Services\Cbt\KoreksiOtomatisCbtService;
use App\Services\Cbt\PengacakPenyajianCbt;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class UjianSayaMobileService
{
    public function __construct(
        private readonly DaftarUjianSiswaService $daftarUjianSiswa,
        private readonly PengacakPenyajianCbt $pengacakPenyajianCbt,
        private readonly KoreksiOtomatisCbtService $koreksiOtomatisCbtService,
        private readonly KeamananUjianMobileService $keamananUjian,
    ) {}

    public function daftar(Pengguna $pengguna): array
    {
        $siswa = $this->siswa($pengguna);
        $data = $this->daftarUjianSiswa->siapkan($siswa);

        return [
            'dihasilkan_pada' => now()->toISOString(),
            'ringkasan' => [
                'aktif' => (int) $data['ringkasanUjian']['aktif'],
                'akan_datang' => (int) $data['ringkasanUjian']['akan_datang'],
                'selesai' => (int) $data['ringkasanUjian']['selesai'],
                'total' => (int) $data['ringkasanUjian']['total'],
            ],
            'items' => collect($data['daftarUjian'])
                ->map(fn (array $item) => $this->ringkasDaftar($item))
                ->values(),
            'pengerjaan_native' => true,
        ];
    }

    public function rincian(Pengguna $pengguna, PesertaUjianCbt $peserta): array
    {
        $peserta = $this->pesertaMilikSiswa($pengguna, $peserta);

        if (in_array($peserta->status, ['sedang_mengerjakan', 'terblokir'], true)
            && $this->hitungSisaDetik($peserta) <= 0) {
            $this->akhiri($peserta);

            return $this->hasil($pengguna, $peserta->fresh());
        }

        if ($peserta->status === 'selesai') {
            return $this->hasil($pengguna, $peserta);
        }

        if ($peserta->status === 'terblokir') {
            return $this->ditahan($peserta);
        }

        return [
            'mode' => 'konfirmasi',
            'waktu_server' => now()->toISOString(),
            'peserta' => $this->ringkasPeserta($peserta),
            'ujian' => $this->ringkasUjian($peserta),
            'kemajuan' => $this->ringkasKemajuan($peserta),
            'memerlukan_token' => $this->memerlukanToken($peserta),
            'dapat_dimulai' => $this->dapatDimulai($peserta),
            'keamanan' => $this->keamananUjian->ringkas($peserta),
        ];
    }

    public function mulai(
        Pengguna $pengguna,
        PesertaUjianCbt $peserta,
        ?string $token,
        string $perangkat,
        ?string $ip,
        ?string $userAgent,
    ): array {
        $peserta = $this->pesertaMilikSiswa($pengguna, $peserta);

        if ($peserta->status === 'selesai') {
            return $this->hasil($pengguna, $peserta);
        }

        if ($peserta->status === 'terblokir') {
            return $this->ditahan($peserta);
        }

        if ($this->memerlukanToken($peserta)) {
            $tokenDimasukkan = mb_strtoupper(trim((string) $token));
            $tokenUjian = mb_strtoupper(trim((string) $peserta->ujianCbt?->token));

            if ($tokenDimasukkan === '' || $tokenUjian === '' || ! hash_equals($tokenUjian, $tokenDimasukkan)) {
                throw ValidationException::withMessages([
                    'token' => 'Token ujian tidak valid. Silakan minta token yang sedang berlaku kepada pengawas.',
                ]);
            }
        }

        $this->pastikanBolehMasuk($peserta);
        $this->pastikanPerangkatSesuai($peserta, $perangkat);

        if (! $peserta->ujianCbt->soalUjianCbt()->exists()) {
            throw ValidationException::withMessages([
                'ujian' => 'Paket ujian belum memiliki soal.',
            ]);
        }

        DB::transaction(function () use ($peserta, $perangkat, $ip, $userAgent): void {
            $pesertaTerkunci = PesertaUjianCbt::query()->lockForUpdate()->findOrFail($peserta->id);

            if ($pesertaTerkunci->status === 'aktif') {
                $pesertaTerkunci->waktu_mulai = now();
                $pesertaTerkunci->menit_tersisa = $peserta->ujianCbt->durasi_menit;
                $pesertaTerkunci->status = 'sedang_mengerjakan';
            }

            $pesertaTerkunci->ip_terakhir = $ip;
            $pesertaTerkunci->perangkat_terakhir = trim($perangkat);
            $pesertaTerkunci->user_agent_terakhir = mb_substr((string) $userAgent, 0, 1000);
            $pesertaTerkunci->save();
        });

        return $this->kertasUjian($peserta->fresh());
    }

    public function pengerjaan(
        Pengguna $pengguna,
        PesertaUjianCbt $peserta,
        string $perangkat,
    ): array {
        $peserta = $this->pesertaMilikSiswa($pengguna, $peserta);

        if ($peserta->status === 'selesai') {
            return $this->hasil($pengguna, $peserta);
        }

        if ($peserta->status === 'terblokir') {
            if ($this->hitungSisaDetik($peserta) <= 0) {
                $this->akhiri($peserta);

                return $this->hasil($pengguna, $peserta->fresh());
            }

            return $this->ditahan($peserta);
        }

        if ($peserta->status !== 'sedang_mengerjakan') {
            throw ValidationException::withMessages([
                'ujian' => 'Ujian belum dimulai.',
            ]);
        }

        $this->pastikanPerangkatSesuai($peserta, $perangkat);

        if ($this->hitungSisaDetik($peserta) <= 0) {
            $this->akhiri($peserta);

            return $this->hasil($pengguna, $peserta->fresh());
        }

        return $this->kertasUjian($peserta);
    }

    public function simpanJawaban(
        Pengguna $pengguna,
        PesertaUjianCbt $peserta,
        int $soalUjianId,
        mixed $jawaban,
        bool $ragu,
        string $perangkat,
    ): array {
        $peserta = $this->pesertaMilikSiswa($pengguna, $peserta);

        if ($peserta->status !== 'sedang_mengerjakan') {
            throw ValidationException::withMessages([
                'ujian' => 'Ujian tidak sedang dikerjakan.',
            ]);
        }

        $this->pastikanPerangkatSesuai($peserta, $perangkat);

        if ($this->hitungSisaDetik($peserta) <= 0) {
            $this->akhiri($peserta);

            return $this->hasil($pengguna, $peserta->fresh());
        }

        $soalUjian = $this->soalUntukPeserta($peserta)
            ->firstWhere('id', $soalUjianId);

        abort_unless($soalUjian, 404);

        $nilaiJawaban = $this->normalisasiJawaban($jawaban);
        $tersimpan = JawabanPesertaUjianCbt::updateOrCreate(
            [
                'peserta_ujian_cbt_id' => $peserta->id,
                'soal_ujian_cbt_id' => $soalUjian->id,
            ],
            [
                'soal_cbt_id' => $soalUjian->soal_cbt_id,
                'jawaban' => $nilaiJawaban,
                'ragu' => $ragu,
                'skor' => null,
                'benar' => null,
                'waktu_dijawab' => $nilaiJawaban === null ? null : now(),
            ],
        );

        return [
            'mode' => 'tersimpan',
            'waktu_server' => now()->toISOString(),
            'soal_ujian_cbt_id' => (int) $soalUjian->id,
            'terjawab' => $tersimpan->jawaban !== null,
            'ragu' => (bool) $tersimpan->ragu,
            'tersimpan_pada' => now()->toISOString(),
            'sisa_detik' => $this->hitungSisaDetik($peserta),
        ];
    }

    public function selesai(
        Pengguna $pengguna,
        PesertaUjianCbt $peserta,
        string $perangkat,
    ): array {
        $peserta = $this->pesertaMilikSiswa($pengguna, $peserta);

        if ($peserta->status === 'selesai') {
            return $this->hasil($pengguna, $peserta);
        }

        if ($peserta->status === 'terblokir') {
            if ($this->hitungSisaDetik($peserta) <= 0) {
                $this->akhiri($peserta);

                return $this->hasil($pengguna, $peserta->fresh());
            }

            return $this->ditahan($peserta);
        }

        if ($peserta->status !== 'sedang_mengerjakan') {
            throw ValidationException::withMessages([
                'ujian' => 'Ujian belum dimulai.',
            ]);
        }

        $this->pastikanPerangkatSesuai($peserta, $perangkat);
        $this->akhiri($peserta);

        return $this->hasil($pengguna, $peserta->fresh());
    }

    public function hasil(Pengguna $pengguna, PesertaUjianCbt $peserta): array
    {
        $peserta = $this->pesertaMilikSiswa($pengguna, $peserta);
        $soal = $this->soalUntukPeserta($peserta);
        $jawaban = $peserta->jawabanPesertaUjianCbt()
            ->whereIn('soal_ujian_cbt_id', $soal->pluck('id'))
            ->get()
            ->keyBy('soal_ujian_cbt_id');
        $terjawab = $jawaban->filter(fn ($item) => $item->jawaban !== null)->count();
        $jenisManual = ['uraian', 'upload_file'];
        $menungguKoreksi = $soal->filter(function (SoalUjianCbt $relasi) use ($jawaban, $jenisManual) {
            $item = $jawaban->get($relasi->id);

            return in_array($relasi->soalCbt?->jenis_soal, $jenisManual, true)
                && $item?->jawaban !== null
                && is_null($item->skor);
        })->count();
        $bobotTotal = (float) $soal->sum(fn (SoalUjianCbt $item) => (float) $item->bobot);
        $skorTotal = (float) $jawaban->sum(fn ($item) => (float) ($item->skor ?? 0));
        $nilai = $bobotTotal > 0 ? round(($skorTotal / $bobotTotal) * 100, 2) : 0.0;
        $bolehTampil = (bool) $peserta->ujianCbt->tampilkan_hasil && $menungguKoreksi === 0;

        return [
            'mode' => 'selesai',
            'waktu_server' => now()->toISOString(),
            'peserta' => $this->ringkasPeserta($peserta),
            'ujian' => $this->ringkasUjian($peserta),
            'kemajuan' => [
                'jumlah_soal' => $soal->count(),
                'terjawab' => $terjawab,
                'belum_dijawab' => max(0, $soal->count() - $terjawab),
                'ragu' => $jawaban->where('ragu', true)->count(),
            ],
            'hasil' => [
                'ditampilkan' => $bolehTampil,
                'menunggu_koreksi' => $menungguKoreksi > 0,
                'nilai' => $bolehTampil ? $nilai : null,
                'kkm' => $peserta->ujianCbt->kkm,
                'tuntas' => $bolehTampil && ! is_null($peserta->ujianCbt->kkm)
                    ? $nilai >= (float) $peserta->ujianCbt->kkm
                    : null,
            ],
            'keamanan' => $this->keamananUjian->ringkas($peserta),
        ];
    }

    private function pesertaMilikSiswa(Pengguna $pengguna, PesertaUjianCbt $peserta): PesertaUjianCbt
    {
        $siswa = $this->siswa($pengguna);

        return PesertaUjianCbt::query()
            ->with([
                'anggotaKelas.siswa',
                'kelasUjianCbt.kelas',
                'ujianCbt.jenisUjianCbt',
                'ujianCbt.mataPelajaran',
                'ujianCbt.tahunPelajaran',
                'sesiUjianCbt',
                'ruangUjianCbt',
            ])
            ->whereKey($peserta->id)
            ->whereHas('anggotaKelas', fn ($query) => $query->where('siswa_id', $siswa->id))
            ->firstOrFail();
    }

    private function siswa(Pengguna $pengguna)
    {
        abort_unless($pengguna->akunSiswa() || $pengguna->memilikiPeran('siswa'), 403);

        return $pengguna->siswa()->firstOrFail();
    }

    private function ringkasDaftar(array $item): array
    {
        $peserta = $item['peserta'];
        $ujian = $item['ujian'];
        $jadwal = $item['jadwal'];

        return [
            'id' => (int) $peserta->id,
            'ujian_id' => (int) $ujian->id,
            'nama' => $ujian->nama,
            'kode' => $ujian->kode,
            'jenis_ujian' => $ujian->jenisUjianCbt?->nama,
            'mata_pelajaran' => $ujian->mataPelajaran?->nama ?? 'Mata pelajaran belum ditentukan',
            'kelompok' => $item['kelompok'],
            'label_status' => $item['label_status'],
            'nada_status' => $item['nada_status'],
            'waktu_mulai' => $item['waktu_mulai']?->toISOString(),
            'waktu_selesai' => $item['waktu_selesai']?->toISOString(),
            'waktu' => $jadwal?->labelWaktu(),
            'tanggal' => $jadwal?->tanggal?->toDateString(),
            'durasi_menit' => (int) $ujian->durasi_menit,
            'nomor_peserta' => $peserta->nomor_peserta,
            'memerlukan_token' => (bool) $ujian->jenisUjianCbt?->memerlukan_token
                && ! in_array($peserta->status, ['sedang_mengerjakan', 'terblokir'], true),
            'dapat_dibuka' => in_array($peserta->status, ['aktif', 'sedang_mengerjakan', 'terblokir', 'selesai'], true),
        ];
    }

    private function ringkasPeserta(PesertaUjianCbt $peserta): array
    {
        return [
            'id' => (int) $peserta->id,
            'nomor_peserta' => $peserta->nomor_peserta,
            'status' => $peserta->status,
            'label_status' => $peserta->labelStatus(),
            'nama' => $peserta->anggotaKelas?->siswa?->nama_lengkap ?? '-',
            'nisn' => $peserta->anggotaKelas?->siswa?->nisn,
            'kelas' => $peserta->kelasUjianCbt?->kelas?->nama ?? '-',
            'ruang' => $peserta->ruangUjianCbt?->nama,
            'nomor_meja' => $peserta->nomor_meja,
            'sesi' => $peserta->sesiUjianCbt?->nama,
            'waktu_mulai' => $peserta->waktu_mulai?->toISOString(),
            'waktu_selesai' => $peserta->waktu_selesai?->toISOString(),
        ];
    }

    private function ringkasUjian(PesertaUjianCbt $peserta): array
    {
        $ujian = $peserta->ujianCbt;

        return [
            'id' => (int) $ujian->id,
            'nama' => $ujian->nama,
            'kode' => $ujian->kode,
            'jenis' => $ujian->jenisUjianCbt?->nama,
            'mata_pelajaran' => $ujian->mataPelajaran?->nama ?? '-',
            'tahun_pelajaran' => $ujian->tahunPelajaran?->nama,
            'semester' => $ujian->semester,
            'durasi_menit' => (int) $ujian->durasi_menit,
            'jumlah_soal' => min((int) $ujian->jumlah_soal, $ujian->soalUjianCbt()->count()),
            'petunjuk' => $ujian->petunjuk,
            'tampilkan_hasil' => (bool) $ujian->tampilkan_hasil,
            'batasi_satu_perangkat' => (bool) $ujian->batasi_satu_perangkat,
            'alur' => $ujian->alur,
        ];
    }

    private function ringkasKemajuan(PesertaUjianCbt $peserta): array
    {
        $soal = $this->soalUntukPeserta($peserta);
        $jawaban = $peserta->jawabanPesertaUjianCbt()
            ->whereIn('soal_ujian_cbt_id', $soal->pluck('id'))
            ->get();
        $terjawab = $jawaban->whereNotNull('jawaban')->count();

        return [
            'jumlah_soal' => $soal->count(),
            'terjawab' => $terjawab,
            'belum_dijawab' => max(0, $soal->count() - $terjawab),
            'ragu' => $jawaban->where('ragu', true)->count(),
        ];
    }

    private function kertasUjian(PesertaUjianCbt $peserta): array
    {
        $soal = $this->soalUntukPeserta($peserta);
        $jawaban = $peserta->jawabanPesertaUjianCbt()
            ->whereIn('soal_ujian_cbt_id', $soal->pluck('id'))
            ->get()
            ->keyBy('soal_ujian_cbt_id');
        $sisaDetik = $this->hitungSisaDetik($peserta);

        return [
            'mode' => 'pengerjaan',
            'waktu_server' => now()->toISOString(),
            'berakhir_pada' => now()->addSeconds($sisaDetik)->toISOString(),
            'sisa_detik' => $sisaDetik,
            'peserta' => $this->ringkasPeserta($peserta),
            'ujian' => $this->ringkasUjian($peserta),
            'kemajuan' => $this->ringkasKemajuan($peserta),
            'soal' => $soal->values()->map(function (SoalUjianCbt $relasi, int $index) use ($peserta, $jawaban) {
                $soal = $relasi->soalCbt;
                $tersimpan = $jawaban->get($relasi->id);

                return [
                    'id' => (int) $relasi->id,
                    'nomor' => $index + 1,
                    'jenis' => $soal?->jenis_soal ?? 'uraian',
                    'label_jenis' => $soal?->labelJenis() ?? 'Soal',
                    'stimulus' => $soal?->stimulus,
                    'pertanyaan' => $soal?->pertanyaan ?? 'Soal tidak ditemukan.',
                    'media' => $this->media($soal),
                    'pilihan' => $soal && in_array($soal->jenis_soal, ['pilihan_ganda', 'pilihan_ganda_kompleks'], true)
                        ? $this->pengacakPenyajianCbt->pilihanJawaban($peserta->ujianCbt, $peserta, $relasi)
                            ->map(fn ($teks, $kode) => ['kode' => (string) $kode, 'teks' => (string) $teks])
                            ->values()
                        : [],
                    'pernyataan' => collect($soal?->opsi['pernyataan'] ?? [])->map(fn ($item, $index) => [
                        'nomor' => (string) ($item['nomor'] ?? $index + 1),
                        'teks' => (string) ($item['teks'] ?? '-'),
                    ])->values(),
                    'pasangan' => collect($soal?->opsi['pasangan'] ?? [])->map(fn ($item, $index) => [
                        'nomor' => (string) ($item['nomor'] ?? $index + 1),
                        'kiri' => (string) ($item['kiri'] ?? '-'),
                    ])->values(),
                    'jawaban' => $this->jawabanUntukApi($tersimpan?->jawaban),
                    'ragu' => (bool) ($tersimpan?->ragu ?? false),
                ];
            }),
            'keamanan' => $this->keamananUjian->ringkas($peserta),
        ];
    }

    private function ditahan(PesertaUjianCbt $peserta): array
    {
        $sisaDetik = $this->hitungSisaDetik($peserta);

        return [
            'mode' => 'ditahan',
            'waktu_server' => now()->toISOString(),
            'berakhir_pada' => now()->addSeconds($sisaDetik)->toISOString(),
            'sisa_detik' => $sisaDetik,
            'peserta' => $this->ringkasPeserta($peserta),
            'ujian' => $this->ringkasUjian($peserta),
            'kemajuan' => $this->ringkasKemajuan($peserta),
            'keamanan' => $this->keamananUjian->ringkas($peserta),
        ];
    }

    private function media(?SoalCbt $soal): array
    {
        $media = $soal?->media ?? [];
        $path = data_get($media, 'gambar.path');

        return [
            'gambar' => $path ? [
                'url' => url(Storage::url($path)),
                'alt' => data_get($media, 'gambar.alt'),
                'keterangan' => data_get($media, 'gambar.keterangan'),
            ] : null,
            'tabel' => data_get($media, 'tabel'),
            'rumus' => data_get($media, 'rumus'),
        ];
    }

    private function soalUntukPeserta(PesertaUjianCbt $peserta): Collection
    {
        $soal = $peserta->ujianCbt->soalUjianCbt()
            ->with('soalCbt')
            ->get();

        return $this->pengacakPenyajianCbt
            ->urutkanSoal($peserta->ujianCbt, $peserta, $soal)
            ->take($peserta->ujianCbt->jumlah_soal)
            ->values();
    }

    private function pastikanBolehMasuk(PesertaUjianCbt $peserta): void
    {
        $ujian = $peserta->ujianCbt;

        if (! in_array($peserta->status, ['aktif', 'sedang_mengerjakan'], true)) {
            throw ValidationException::withMessages([
                'ujian' => 'Status peserta tidak aktif untuk mengikuti ujian.',
            ]);
        }

        if (! in_array($ujian->status, ['terjadwal', 'berlangsung'], true)) {
            throw ValidationException::withMessages([
                'token' => 'Paket ujian belum dibuka.',
            ]);
        }

        $mulai = $peserta->sesiUjianCbt?->waktu_mulai ?: $ujian->tanggal_mulai;
        $selesai = $peserta->sesiUjianCbt?->waktu_selesai ?: $ujian->tanggal_selesai;

        if ($mulai && now()->lt($mulai)) {
            throw ValidationException::withMessages([
                'token' => 'Ujian belum masuk waktu pelaksanaan.',
            ]);
        }

        if ($selesai && now()->gt($selesai)) {
            throw ValidationException::withMessages([
                'token' => 'Waktu pelaksanaan ujian sudah berakhir.',
            ]);
        }

        if ($peserta->sesiUjianCbt?->status === 'nonaktif') {
            throw ValidationException::withMessages([
                'token' => 'Sesi peserta tidak aktif.',
            ]);
        }
    }

    private function pastikanPerangkatSesuai(PesertaUjianCbt $peserta, string $perangkat): void
    {
        $perangkat = trim($perangkat);

        if ($peserta->ujianCbt->batasi_satu_perangkat
            && $peserta->status === 'sedang_mengerjakan'
            && filled($peserta->perangkat_terakhir)
            && ! hash_equals((string) $peserta->perangkat_terakhir, $perangkat)) {
            throw ValidationException::withMessages([
                'perangkat' => 'Ujian sedang aktif di perangkat lain. Hubungi pengawas jika perangkat perlu diganti.',
            ]);
        }
    }

    private function memerlukanToken(PesertaUjianCbt $peserta): bool
    {
        return (bool) $peserta->ujianCbt?->jenisUjianCbt?->memerlukan_token
            && ! in_array($peserta->status, ['sedang_mengerjakan', 'terblokir'], true);
    }

    private function dapatDimulai(PesertaUjianCbt $peserta): bool
    {
        try {
            $this->pastikanBolehMasuk($peserta);

            return true;
        } catch (ValidationException) {
            return false;
        }
    }

    private function normalisasiJawaban(mixed $jawaban): ?array
    {
        if (! is_array($jawaban)) {
            return filled($jawaban) ? [trim((string) $jawaban)] : null;
        }

        $hasil = collect($jawaban)
            ->map(fn ($item) => is_string($item) ? trim($item) : $item)
            ->filter(fn ($item) => filled($item))
            ->all();

        if ($hasil === []) {
            return null;
        }

        return array_is_list($jawaban) ? array_values($hasil) : $hasil;
    }

    private function jawabanUntukApi(?array $jawaban): array
    {
        if ($jawaban === null) {
            return [];
        }

        return collect($jawaban)
            ->mapWithKeys(fn ($nilai, $kunci) => [(string) $kunci => (string) $nilai])
            ->all();
    }

    private function hitungSisaDetik(PesertaUjianCbt $peserta): int
    {
        if (! $peserta->waktu_mulai) {
            return $peserta->ujianCbt->durasi_menit * 60;
        }

        $selesaiPengerjaan = $peserta->waktu_mulai->copy()->addMinutes($peserta->ujianCbt->durasi_menit);
        $batasPaket = $peserta->sesiUjianCbt?->waktu_selesai ?: $peserta->ujianCbt->tanggal_selesai;

        if ($batasPaket && $batasPaket->lt($selesaiPengerjaan)) {
            $selesaiPengerjaan = $batasPaket;
        }

        return (int) max(0, now()->diffInSeconds($selesaiPengerjaan, false));
    }

    private function akhiri(PesertaUjianCbt $peserta): void
    {
        DB::transaction(function () use ($peserta): void {
            $pesertaTerkunci = PesertaUjianCbt::query()->lockForUpdate()->findOrFail($peserta->id);

            if ($pesertaTerkunci->status !== 'selesai') {
                $pesertaTerkunci->update([
                    'status' => 'selesai',
                    'waktu_selesai' => now(),
                    'menit_tersisa' => max(0, (int) ceil($this->hitungSisaDetik($peserta) / 60)),
                ]);
            }
        });

        $this->koreksiOtomatisCbtService->koreksiPeserta($peserta->fresh());
    }
}
