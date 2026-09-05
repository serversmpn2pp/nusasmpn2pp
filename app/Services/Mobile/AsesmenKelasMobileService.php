<?php

namespace App\Services\Mobile;

use App\Models\GuruMataPelajaran;
use App\Models\JenisUjianCbt;
use App\Models\KomponenNilai;
use App\Models\PengaturanMataPelajaran;
use App\Models\Pengguna;
use App\Models\SoalCbt;
use App\Models\TahunPelajaran;
use App\Models\UjianCbt;
use App\Services\Cbt\SinkronkanPesertaAsesmenKelas;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AsesmenKelasMobileService
{
    public function __construct(private readonly SinkronkanPesertaAsesmenKelas $sinkronkanPeserta) {}

    public function daftar(Pengguna $pengguna, array $filter): array
    {
        $kataKunci = trim((string) ($filter['kata_kunci'] ?? ''));
        $status = $filter['status'] ?? 'semua';
        $halaman = (int) ($filter['halaman'] ?? 1);
        $perHalaman = (int) ($filter['per_halaman'] ?? 12);
        $cakupan = $this->queryDalamCakupan($pengguna);

        $query = (clone $cakupan)
            ->with(['mataPelajaran:id,nama', 'tahunPelajaran:id,nama', 'kelasUjianCbt.kelas:id,nama'])
            ->withCount(['soalUjianCbt', 'pesertaUjianCbt'])
            ->when($status !== 'semua', fn (Builder $query) => $query->where('status', $status))
            ->when($kataKunci !== '', function (Builder $query) use ($kataKunci) {
                $pola = '%'.mb_strtolower($kataKunci).'%';
                $query->where(function (Builder $query) use ($pola) {
                    $query->whereRaw('LOWER(nama) LIKE ?', [$pola])
                        ->orWhereHas('mataPelajaran', fn (Builder $query) => $query->whereRaw('LOWER(nama) LIKE ?', [$pola]))
                        ->orWhereHas('kelasUjianCbt.kelas', fn (Builder $query) => $query->whereRaw('LOWER(nama) LIKE ?', [$pola]));
                });
            });
        $paginator = $query->latest('tanggal_mulai')->latest('id')
            ->paginate($perHalaman, ['*'], 'halaman', $halaman);

        return [
            'ringkasan' => [
                'total' => (clone $cakupan)->count(),
                'draft' => (clone $cakupan)->where('status', 'draft')->count(),
                'terjadwal' => (clone $cakupan)->where('status', 'terjadwal')->count(),
                'berlangsung' => (clone $cakupan)->where('status', 'berlangsung')->count(),
            ],
            'items' => collect($paginator->items())->map(fn (UjianCbt $asesmen) => $this->ringkas($asesmen))->values(),
            'referensi' => $this->referensi($pengguna),
            'filter' => ['kata_kunci' => $kataKunci, 'status' => $status],
            'paginasi' => [
                'halaman' => $paginator->currentPage(),
                'halaman_terakhir' => $paginator->lastPage(),
                'total' => $paginator->total(),
                'ada_halaman_berikutnya' => $paginator->hasMorePages(),
            ],
        ];
    }

    public function rincian(Pengguna $pengguna, UjianCbt $asesmen): array
    {
        $this->pastikanBolehMengelola($pengguna, $asesmen);
        $asesmen->load([
            'tahunPelajaran:id,nama', 'mataPelajaran:id,nama', 'dibuatOleh:id,nama',
            'kelasUjianCbt.kelas:id,nama,tingkat',
            'kelasUjianCbt.komponenNilai:id,guru_mata_pelajaran_id,nama,semester',
            'kelasUjianCbt.komponenNilai.guruMataPelajaran:id,pegawai_id,mata_pelajaran_id,kelas_id',
            'kelasUjianCbt.komponenNilai.guruMataPelajaran.kelas:id,tingkat',
        ])->loadCount(['soalUjianCbt', 'pesertaUjianCbt']);

        $ringkas = $this->ringkas($asesmen);

        return $ringkas + [
            'kode' => $asesmen->kode,
            'kkm' => (int) $asesmen->kkm,
            'acak_soal' => (bool) $asesmen->acak_soal,
            'acak_jawaban' => (bool) $asesmen->acak_jawaban,
            'batasi_satu_perangkat' => (bool) $asesmen->batasi_satu_perangkat,
            'deteksi_pindah_tab' => (bool) $asesmen->deteksi_pindah_tab,
            'wajib_fullscreen' => (bool) $asesmen->wajib_fullscreen,
            'blokir_tangkapan_layar' => (bool) $asesmen->blokir_tangkapan_layar,
            'toleransi_pindah_aplikasi_detik' => (int) $asesmen->toleransi_pindah_aplikasi_detik,
            'batas_pindah_aplikasi' => (int) $asesmen->batas_pindah_aplikasi,
            'tindakan_pindah_aplikasi' => $asesmen->tindakan_pindah_aplikasi,
            'tampilkan_hasil' => (bool) $asesmen->tampilkan_hasil,
            'petunjuk' => $asesmen->petunjuk,
            'dibuat_oleh' => $asesmen->dibuatOleh?->nama,
            'kelas_tujuan' => $asesmen->kelasUjianCbt->sortBy(fn ($item) => $item->kelas?->nama)->map(fn ($item) => [
                'kelas_id' => (int) $item->kelas_id,
                'nama' => $item->kelas?->nama ?? '-',
                'komponen_nilai_id' => $item->komponen_nilai_id ? (int) $item->komponen_nilai_id : null,
                'komponen_nilai' => $item->komponenNilai?->nama,
                'jumlah_peserta' => $item->pesertaUjianCbt()->count(),
            ])->values(),
            'kelompok_pengajaran' => $this->kelompokTersimpan($pengguna, $asesmen),
            'referensi' => $this->referensi($pengguna),
            'hak_akses' => [
                'dapat_kelola' => true,
                'dapat_nonaktifkan' => $asesmen->status !== 'nonaktif',
            ],
        ];
    }

    public function tambah(Pengguna $pengguna, array $data): UjianCbt
    {
        [$data, $tahun, $kelompok, $kelasPeserta] = $this->siapkanData($pengguna, $data);

        return DB::transaction(function () use ($pengguna, $data, $tahun, $kelompok, $kelasPeserta) {
            $asesmen = UjianCbt::create([
                ...$this->dataUjian($data, $tahun, $kelompok),
                'dibuat_oleh_pengguna_id' => $pengguna->id,
            ]);
            $this->sinkronkanKelasDanKomponen($asesmen, $data, $kelompok, $kelasPeserta);
            $this->sinkronkanPeserta->jalankan($asesmen, $pengguna->id);

            return $asesmen;
        });
    }

    public function ubah(Pengguna $pengguna, UjianCbt $asesmen, array $data): void
    {
        $this->pastikanBolehMengelola($pengguna, $asesmen);
        [$data, $tahun, $kelompok, $kelasPeserta] = $this->siapkanData($pengguna, $data);

        DB::transaction(function () use ($pengguna, $asesmen, $data, $tahun, $kelompok, $kelasPeserta) {
            $asesmen->update($this->dataUjian($data, $tahun, $kelompok, $asesmen));
            $this->sinkronkanKelasDanKomponen($asesmen, $data, $kelompok, $kelasPeserta);
            $this->sinkronkanPeserta->jalankan($asesmen, $pengguna->id);
        });
    }

    public function nonaktifkan(Pengguna $pengguna, UjianCbt $asesmen): void
    {
        $this->pastikanBolehMengelola($pengguna, $asesmen);
        $asesmen->update(['status' => 'nonaktif']);
    }

    public function daftarSoal(Pengguna $pengguna, UjianCbt $asesmen): array
    {
        $this->pastikanBolehMengelola($pengguna, $asesmen);
        $asesmen->load(['mataPelajaran:id,nama', 'kelasUjianCbt.kelas:id,nama', 'soalUjianCbt']);
        $dipilih = $asesmen->soalUjianCbt->keyBy('soal_cbt_id');
        $soal = SoalCbt::query()
            ->where('mata_pelajaran_id', $asesmen->mata_pelajaran_id)
            ->where('tingkat', $asesmen->tingkat)
            ->where(function (Builder $query) use ($dipilih) {
                $query->where(fn (Builder $query) => $query->where('aktif', true)->where('status', 'siap'))
                    ->when($dipilih->isNotEmpty(), fn (Builder $query) => $query->orWhereIn('id', $dipilih->keys()));
            })
            ->orderBy('jenis_soal')->orderBy('tingkat_kesulitan')->orderBy('kode')->get();

        return [
            'asesmen' => $this->ringkas($asesmen),
            'soal' => $soal->map(fn (SoalCbt $item) => $this->ringkasSoal($item, $dipilih->get($item->id)))->values(),
            'referensi' => [
                'jenis_soal' => $this->pilihan(SoalCbt::DAFTAR_JENIS),
                'tingkat_kesulitan' => $this->pilihan(SoalCbt::DAFTAR_KESULITAN),
            ],
            'hak_akses' => ['dapat_ubah' => $asesmen->status !== 'nonaktif'],
        ];
    }

    public function simpanSoal(Pengguna $pengguna, UjianCbt $asesmen, array $soal): void
    {
        $this->pastikanBolehMengelola($pengguna, $asesmen);
        abort_if($asesmen->status === 'nonaktif', 422, 'Asesmen nonaktif tidak dapat diubah.');
        $terpilih = collect($soal)->mapWithKeys(fn (array $item) => [(int) $item['id'] => (float) $item['bobot']]);
        if ($terpilih->isNotEmpty()) {
            $jumlahValid = SoalCbt::query()->whereIn('id', $terpilih->keys())
                ->where('mata_pelajaran_id', $asesmen->mata_pelajaran_id)
                ->where('tingkat', $asesmen->tingkat)->where('aktif', true)->where('status', 'siap')->count();
            if ($jumlahValid !== $terpilih->count()) {
                throw ValidationException::withMessages(['soal' => 'Ada soal yang tidak cocok dengan mata pelajaran, tingkat, atau belum siap digunakan.']);
            }
        }

        DB::transaction(function () use ($asesmen, $terpilih) {
            $asesmen->soalUjianCbt()->whereNotIn('soal_cbt_id', $terpilih->keys())->delete();
            foreach ($terpilih as $soalId => $bobot) {
                $asesmen->soalUjianCbt()->updateOrCreate(
                    ['soal_cbt_id' => $soalId],
                    ['nomor_urut' => $terpilih->keys()->search($soalId) + 1, 'bobot' => $bobot],
                );
            }
        });
    }

    private function queryDalamCakupan(Pengguna $pengguna): Builder
    {
        return UjianCbt::query()->where('alur', 'kelas')
            ->when(! $pengguna->memilikiIzin('cbt.kelola'), fn (Builder $query) => $query->where('dibuat_oleh_pengguna_id', $pengguna->id));
    }

    private function referensi(Pengguna $pengguna): array
    {
        $tahun = TahunPelajaran::query()->where('aktif', true)->firstOrFail();

        return [
            'tahun_pelajaran' => ['id' => (int) $tahun->id, 'nama' => $tahun->nama],
            'kelompok_pengajaran' => $this->kelompokPengajaran($pengguna, $tahun->id)->values(),
            'status' => collect(UjianCbt::DAFTAR_STATUS)->only(['draft', 'terjadwal', 'berlangsung', 'selesai'])
                ->map(fn ($label, $kode) => ['kode' => $kode, 'label' => $label])->values(),
        ];
    }

    private function kelompokPengajaran(Pengguna $pengguna, int $tahunId): Collection
    {
        $query = GuruMataPelajaran::query()->with([
            'kelas:id,nama,tingkat', 'mataPelajaran:id,nama,kkm', 'pegawai:id,nama_lengkap',
            'komponenNilai' => fn ($query) => $query->where('aktif', true)->where('jenis_komponen', 'sumatif')
                ->orderBy('semester')->orderBy('urutan'),
        ])->where('tahun_pelajaran_id', $tahunId)->where('aktif', true);
        if (! $pengguna->memilikiIzin('cbt.kelola')) {
            $query->where('pegawai_id', $pengguna->pegawai_id ?: 0);
        }

        return $query->get()->filter(fn ($item) => $item->kelas && $item->mataPelajaran)
            ->groupBy(fn ($item) => implode('-', [$item->pegawai_id, $item->mata_pelajaran_id, $item->kelas->tingkat]))
            ->map(function (Collection $items, string $key) use ($pengguna, $tahunId) {
                $acuan = $items->first();
                $kkm = PengaturanMataPelajaran::query()->where('tahun_pelajaran_id', $tahunId)
                    ->where('mata_pelajaran_id', $acuan->mata_pelajaran_id)->where('tingkat', $acuan->kelas->tingkat)
                    ->value('kkm') ?? $acuan->mataPelajaran->kkm;

                return [
                    'key' => $key,
                    'mata_pelajaran_id' => (int) $acuan->mata_pelajaran_id,
                    'mata_pelajaran' => $acuan->mataPelajaran->nama,
                    'pegawai' => $acuan->pegawai?->nama_lengkap,
                    'tingkat' => (int) $acuan->kelas->tingkat,
                    'kkm' => (int) $kkm,
                    'label' => $acuan->mataPelajaran->nama.' - Kelas '.$acuan->kelas->tingkat
                        .($pengguna->memilikiIzin('cbt.kelola') ? ' - '.($acuan->pegawai?->nama_lengkap ?: 'Tanpa guru') : ''),
                    'kelas' => $items->sortBy('kelas.nama')->map(fn ($item) => [
                        'kelas_id' => (int) $item->kelas_id,
                        'nama' => $item->kelas->nama,
                        'guru_mata_pelajaran_id' => (int) $item->id,
                        'komponen' => $item->komponenNilai->map(fn ($komponen) => [
                            'id' => (int) $komponen->id,
                            'nama' => $komponen->nama,
                            'semester' => $komponen->semester,
                        ])->values(),
                    ])->values(),
                ];
            })->sortBy('label');
    }

    private function siapkanData(Pengguna $pengguna, array $data): array
    {
        $tahun = TahunPelajaran::query()->where('aktif', true)->firstOrFail();
        $kelompok = $this->kelompokPengajaran($pengguna, $tahun->id)->get($data['kelompok_pengajaran']);
        if (! $kelompok) {
            throw ValidationException::withMessages(['kelompok_pengajaran' => 'Penugasan mengajar tidak tersedia untuk akun ini.']);
        }
        $kelasTersedia = collect($kelompok['kelas'])->keyBy('kelas_id');
        $kelasPeserta = collect($data['kelas_peserta'])->keyBy(fn ($item) => (int) $item['kelas_id']);
        foreach ($kelasPeserta as $kelasId => $item) {
            $kelas = $kelasTersedia->get((int) $kelasId);
            if (! $kelas) {
                throw ValidationException::withMessages(['kelas_peserta' => 'Ada kelas yang tidak termasuk dalam penugasan guru.']);
            }
            $komponenId = $item['komponen_nilai_id'];
            if ($komponenId !== 'baru') {
                $valid = collect($kelas['komponen'])->contains(fn ($komponen) => (string) $komponen['id'] === (string) $komponenId
                    && $komponen['semester'] === $data['semester']);
                if (! $valid) {
                    throw ValidationException::withMessages(['kelas_peserta' => 'Pilih tujuan nilai yang sesuai untuk setiap kelas.']);
                }
            }
        }
        $data['nama'] = trim($data['nama']);
        $data['petunjuk'] = filled($data['petunjuk'] ?? null) ? trim($data['petunjuk']) : null;

        return [$data, $tahun, $kelompok, $kelasPeserta];
    }

    private function dataUjian(array $data, TahunPelajaran $tahun, array $kelompok, ?UjianCbt $asesmen = null): array
    {
        return [
            'alur' => 'kelas',
            'jenis_ujian_cbt_id' => JenisUjianCbt::query()->where('kode', 'ASESMEN_KELAS')->firstOrFail()->id,
            'tahun_pelajaran_id' => $tahun->id,
            'mata_pelajaran_id' => $kelompok['mata_pelajaran_id'],
            'kode' => $asesmen?->kode ?: $this->buatKodeSaran(),
            'nama' => $data['nama'], 'semester' => $data['semester'], 'tingkat' => $kelompok['tingkat'],
            'tanggal_mulai' => $data['tanggal_mulai'], 'tanggal_selesai' => $data['tanggal_selesai'],
            'durasi_menit' => $data['durasi_menit'], 'jumlah_soal' => $data['jumlah_soal'], 'kkm' => $kelompok['kkm'],
            'token' => null, 'acak_soal' => $data['acak_soal'], 'acak_jawaban' => $data['acak_jawaban'],
            'batasi_satu_perangkat' => $data['batasi_satu_perangkat'], 'deteksi_pindah_tab' => $data['deteksi_pindah_tab'],
            'wajib_fullscreen' => $data['wajib_fullscreen'] ?? $asesmen?->wajib_fullscreen ?? false,
            'blokir_tangkapan_layar' => $data['blokir_tangkapan_layar'] ?? $asesmen?->blokir_tangkapan_layar ?? true,
            'toleransi_pindah_aplikasi_detik' => $data['toleransi_pindah_aplikasi_detik'] ?? $asesmen?->toleransi_pindah_aplikasi_detik ?? 3,
            'batas_pindah_aplikasi' => $data['batas_pindah_aplikasi'] ?? $asesmen?->batas_pindah_aplikasi ?? 3,
            'tindakan_pindah_aplikasi' => $data['tindakan_pindah_aplikasi'] ?? $asesmen?->tindakan_pindah_aplikasi ?? 'catat',
            'tampilkan_hasil' => $data['tampilkan_hasil'], 'status' => $data['status'],
            'petunjuk' => $data['petunjuk'], 'keterangan' => null,
        ];
    }

    private function sinkronkanKelasDanKomponen(UjianCbt $asesmen, array $data, array $kelompok, Collection $kelasPeserta): void
    {
        $kelasKelompok = collect($kelompok['kelas'])->keyBy('kelas_id');
        $asesmen->kelasUjianCbt()->whereNotIn('kelas_id', $kelasPeserta->keys())->delete();
        foreach ($kelasPeserta as $kelasId => $item) {
            $kelas = $kelasKelompok->get((int) $kelasId);
            $komponenId = $item['komponen_nilai_id'];
            if ($komponenId === 'baru') {
                $urutan = KomponenNilai::query()->where('guru_mata_pelajaran_id', $kelas['guru_mata_pelajaran_id'])
                    ->where('semester', $data['semester'])->max('urutan') ?? 0;
                $komponenId = KomponenNilai::create([
                    'guru_mata_pelajaran_id' => $kelas['guru_mata_pelajaran_id'], 'semester' => $data['semester'],
                    'jenis_komponen' => 'sumatif', 'nama' => $data['nama'],
                    'tanggal_penilaian' => substr($data['tanggal_mulai'], 0, 10), 'urutan' => $urutan + 1,
                    'aktif' => true, 'keterangan' => 'Dibuat otomatis dari Asesmen Kelas CBT.',
                ])->id;
            }
            $asesmen->kelasUjianCbt()->updateOrCreate(['kelas_id' => $kelasId], ['komponen_nilai_id' => $komponenId]);
        }
    }

    private function ringkas(UjianCbt $asesmen): array
    {
        return [
            'id' => (int) $asesmen->id,
            'nama' => $asesmen->nama,
            'mata_pelajaran' => $asesmen->mataPelajaran?->nama ?? '-',
            'tahun_pelajaran' => $asesmen->tahunPelajaran?->nama,
            'semester' => $asesmen->semester,
            'tingkat' => (int) $asesmen->tingkat,
            'tanggal_mulai' => $asesmen->tanggal_mulai?->toISOString(),
            'tanggal_selesai' => $asesmen->tanggal_selesai?->toISOString(),
            'durasi_menit' => (int) $asesmen->durasi_menit,
            'target_soal' => (int) $asesmen->jumlah_soal,
            'jumlah_soal' => (int) ($asesmen->soal_ujian_cbt_count ?? $asesmen->soalUjianCbt->count()),
            'jumlah_peserta' => (int) ($asesmen->peserta_ujian_cbt_count ?? $asesmen->pesertaUjianCbt->count()),
            'kelas' => $asesmen->kelasUjianCbt->pluck('kelas.nama')->filter()->values(),
            'status' => $asesmen->status,
            'label_status' => $asesmen->labelStatus(),
            'siap_soal' => ($asesmen->soal_ujian_cbt_count ?? $asesmen->soalUjianCbt->count()) >= $asesmen->jumlah_soal,
        ];
    }

    private function ringkasSoal(SoalCbt $soal, mixed $relasi): array
    {
        $path = data_get($soal->media, 'gambar.path');

        return [
            'id' => (int) $soal->id, 'kode' => $soal->kode,
            'jenis_soal' => $soal->jenis_soal, 'label_jenis_soal' => $soal->labelJenis(),
            'tingkat_kesulitan' => $soal->tingkat_kesulitan, 'label_tingkat_kesulitan' => $soal->labelKesulitan(),
            'topik' => $soal->topik, 'materi' => $soal->materi, 'pertanyaan' => $soal->pertanyaan,
            'skor_maksimal' => (float) $soal->skor_maksimal, 'dipilih' => (bool) $relasi,
            'bobot' => (float) ($relasi?->bobot ?? $soal->skor_maksimal),
            'nomor_urut' => $relasi ? (int) $relasi->nomor_urut : null,
            'dapat_dipilih' => (bool) ($soal->aktif && $soal->status === 'siap'),
            'gambar_url' => $path ? url(Storage::url($path)) : null,
        ];
    }

    private function kelompokTersimpan(Pengguna $pengguna, UjianCbt $asesmen): ?string
    {
        $komponen = $asesmen->kelasUjianCbt->first()?->komponenNilai;
        if ($komponen?->guruMataPelajaran) {
            $guru = $komponen->guruMataPelajaran;

            return implode('-', [$guru->pegawai_id, $guru->mata_pelajaran_id, $guru->kelas?->tingkat]);
        }

        return $this->kelompokPengajaran($pengguna, $asesmen->tahun_pelajaran_id)
            ->first(fn ($item) => (int) $item['mata_pelajaran_id'] === (int) $asesmen->mata_pelajaran_id
                && (int) $item['tingkat'] === (int) $asesmen->tingkat)['key'] ?? null;
    }

    private function pilihan(array $items): Collection
    {
        return collect($items)->map(fn ($label, $kode) => ['kode' => $kode, 'label' => $label])->values();
    }

    private function pastikanBolehMengelola(Pengguna $pengguna, UjianCbt $asesmen): void
    {
        abort_unless($asesmen->asesmenKelas() && $asesmen->dapatDikelolaOleh($pengguna), 403);
    }

    private function buatKodeSaran(): string
    {
        do {
            $kode = 'AK-'.now()->format('Ymd-His').'-'.random_int(10, 99);
        } while (UjianCbt::query()->where('kode', $kode)->exists());

        return $kode;
    }
}
