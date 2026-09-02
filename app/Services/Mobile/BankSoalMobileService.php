<?php

namespace App\Services\Mobile;

use App\Models\GuruMataPelajaran;
use App\Models\MataPelajaran;
use App\Models\Pengguna;
use App\Models\SoalCbt;
use App\Models\TahunPelajaran;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class BankSoalMobileService
{
    public function daftar(Pengguna $pengguna, array $filter): array
    {
        $bisaLihatSemua = $this->bisaLihatSemua($pengguna);
        $mapelIds = $this->mataPelajaranCakupan($pengguna)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $kataKunci = trim((string) ($filter['kata_kunci'] ?? ''));
        $mataPelajaranId = isset($filter['mata_pelajaran_id']) ? (int) $filter['mata_pelajaran_id'] : null;
        $tingkat = $filter['tingkat'] ?? 'semua';
        $jenisSoal = $filter['jenis_soal'] ?? 'semua';
        $status = $filter['status'] ?? 'semua';
        $halaman = (int) ($filter['halaman'] ?? 1);
        $perHalaman = (int) ($filter['per_halaman'] ?? 12);

        if ($mataPelajaranId && ! $bisaLihatSemua) {
            abort_unless(in_array($mataPelajaranId, $mapelIds, true), 403);
        }

        $cakupan = SoalCbt::query()
            ->when(! $bisaLihatSemua, fn ($query) => $query->whereIn('mata_pelajaran_id', $mapelIds));
        $paginator = (clone $cakupan)
            ->with(['tahunPelajaran:id,nama', 'mataPelajaran:id,nama,kode', 'dibuatOleh:id,nama'])
            ->withCount('soalUjianCbt')
            ->when($mataPelajaranId, fn ($query, $id) => $query->where('mata_pelajaran_id', $id))
            ->when($tingkat !== 'semua', fn ($query) => $query->where('tingkat', (int) $tingkat))
            ->when($jenisSoal !== 'semua', fn ($query) => $query->where('jenis_soal', $jenisSoal))
            ->when($status !== 'semua', fn ($query) => $query->where('status', $status))
            ->when($kataKunci !== '', function ($query) use ($kataKunci) {
                $pola = '%'.mb_strtolower($kataKunci).'%';
                $query->where(function ($query) use ($pola) {
                    $query->whereRaw('LOWER(kode) LIKE ?', [$pola])
                        ->orWhereRaw('LOWER(topik) LIKE ?', [$pola])
                        ->orWhereRaw('LOWER(materi) LIKE ?', [$pola])
                        ->orWhereRaw('LOWER(pertanyaan) LIKE ?', [$pola])
                        ->orWhereHas('mataPelajaran', fn ($query) => $query->whereRaw('LOWER(nama) LIKE ?', [$pola]));
                });
            })
            ->latest('updated_at')
            ->latest('id')
            ->paginate($perHalaman, ['*'], 'halaman', $halaman);

        return [
            'ringkasan' => [
                'total' => (clone $cakupan)->count(),
                'siap' => (clone $cakupan)->where('status', 'siap')->count(),
                'draft' => (clone $cakupan)->where('status', 'draft')->count(),
                'arsip' => (clone $cakupan)->where('status', 'arsip')->count(),
            ],
            'items' => collect($paginator->items())->map(fn (SoalCbt $soal) => $this->ringkas($soal))->values(),
            'referensi' => $this->referensi($pengguna),
            'filter' => [
                'kata_kunci' => $kataKunci,
                'mata_pelajaran_id' => $mataPelajaranId,
                'tingkat' => (string) $tingkat,
                'jenis_soal' => $jenisSoal,
                'status' => $status,
            ],
            'paginasi' => [
                'halaman' => $paginator->currentPage(),
                'halaman_terakhir' => $paginator->lastPage(),
                'per_halaman' => $paginator->perPage(),
                'total' => $paginator->total(),
                'ada_halaman_berikutnya' => $paginator->hasMorePages(),
            ],
            'hak_akses' => ['dapat_kelola' => $this->dapatKelola($pengguna)],
        ];
    }

    public function rincian(Pengguna $pengguna, SoalCbt $soal): array
    {
        $this->pastikanBolehMengakses($pengguna, $soal);
        $soal->load(['tahunPelajaran:id,nama', 'mataPelajaran:id,nama,kode', 'dibuatOleh:id,nama'])
            ->loadCount('soalUjianCbt');

        return $this->ringkas($soal) + [
            'tahun_pelajaran' => $soal->tahunPelajaran ? [
                'id' => (int) $soal->tahunPelajaran->id,
                'nama' => $soal->tahunPelajaran->nama,
            ] : null,
            'tujuan_pembelajaran' => $soal->tujuan_pembelajaran,
            'stimulus' => $soal->stimulus,
            'pembahasan' => $soal->pembahasan,
            'jawaban' => $this->ringkasJawaban($soal),
            'media' => $this->ringkasMedia($soal),
            'dibuat_oleh' => $soal->dibuatOleh?->nama,
            'hak_akses' => [
                'dapat_kelola' => $this->dapatKelola($pengguna),
                'dapat_arsipkan' => $this->dapatKelola($pengguna) && $soal->aktif,
            ],
        ];
    }

    public function tambah(Pengguna $pengguna, array $data, ?UploadedFile $gambar): SoalCbt
    {
        abort_unless($this->dapatKelola($pengguna), 403);
        $data = $this->rapikanData($data);
        $this->pastikanKonteksTersedia($pengguna, $data);
        $jawaban = $this->susunJawaban($data);
        $pathBaru = $this->simpanGambar($gambar);

        try {
            return SoalCbt::create([
                ...$this->dataSoal($data),
                ...$jawaban,
                'kode' => $this->buatKodeSaran(),
                'media' => $this->susunMedia($data, null, $pathBaru),
                'dibuat_oleh_pengguna_id' => $pengguna->id,
            ]);
        } catch (Throwable $exception) {
            $this->hapusGambar($pathBaru);
            throw $exception;
        }
    }

    public function ubah(Pengguna $pengguna, SoalCbt $soal, array $data, ?UploadedFile $gambar): void
    {
        $this->pastikanBolehMengakses($pengguna, $soal, perluKelola: true);
        $data = $this->rapikanData($data);
        $data['tahun_pelajaran_id'] ??= $soal->tahun_pelajaran_id;
        $this->pastikanKonteksTersedia($pengguna, $data);
        $jawaban = $this->susunJawaban($data);
        $pathLama = data_get($soal->media, 'gambar.path');
        $pathBaru = $this->simpanGambar($gambar);
        $media = $this->susunMedia($data, $soal, $pathBaru);

        try {
            $soal->update([...$this->dataSoal($data), ...$jawaban, 'media' => $media]);
        } catch (Throwable $exception) {
            $this->hapusGambar($pathBaru);
            throw $exception;
        }

        $pathSekarang = data_get($media, 'gambar.path');
        if ($pathLama && $pathLama !== $pathSekarang) {
            $this->hapusGambar($pathLama);
        }
    }

    public function arsipkan(Pengguna $pengguna, SoalCbt $soal): void
    {
        $this->pastikanBolehMengakses($pengguna, $soal, perluKelola: true);
        $soal->update(['status' => 'arsip', 'aktif' => false]);
    }

    private function referensi(Pengguna $pengguna): array
    {
        return [
            'konteks' => $this->konteksBankSoal($pengguna),
            'jenis_soal' => $this->pilihan(SoalCbt::DAFTAR_JENIS),
            'tingkat_kesulitan' => $this->pilihan(SoalCbt::DAFTAR_KESULITAN),
            'kategori' => $this->pilihan(SoalCbt::DAFTAR_KATEGORI),
            'status' => $this->pilihan(SoalCbt::DAFTAR_STATUS),
        ];
    }

    private function pilihan(array $daftar): Collection
    {
        return collect($daftar)->map(fn ($label, $kode) => ['kode' => $kode, 'label' => $label])->values();
    }

    private function ringkas(SoalCbt $soal): array
    {
        return [
            'id' => (int) $soal->id,
            'kode' => $soal->kode,
            'mata_pelajaran' => $soal->mataPelajaran ? [
                'id' => (int) $soal->mataPelajaran->id,
                'kode' => $soal->mataPelajaran->kode,
                'nama' => $soal->mataPelajaran->nama,
            ] : null,
            'tingkat' => (int) $soal->tingkat,
            'jenis_soal' => $soal->jenis_soal,
            'label_jenis_soal' => $soal->labelJenis(),
            'tingkat_kesulitan' => $soal->tingkat_kesulitan,
            'label_tingkat_kesulitan' => $soal->labelKesulitan(),
            'kategori' => $soal->kategori,
            'label_kategori' => $soal->labelKategori(),
            'topik' => $soal->topik,
            'materi' => $soal->materi,
            'pertanyaan' => $soal->pertanyaan,
            'skor_maksimal' => (float) $soal->skor_maksimal,
            'status' => $soal->status,
            'label_status' => $soal->labelStatus(),
            'aktif' => (bool) $soal->aktif,
            'jumlah_pemakaian' => (int) ($soal->soal_ujian_cbt_count ?? 0),
            'diperbarui_pada' => $soal->updated_at?->toISOString(),
        ];
    }

    private function ringkasJawaban(SoalCbt $soal): array
    {
        $jawaban = data_get($soal->kunci_jawaban, 'jawaban');

        return match ($soal->jenis_soal) {
            'pilihan_ganda', 'pilihan_ganda_kompleks' => [
                'opsi' => collect(data_get($soal->opsi, 'pilihan', []))->map(fn ($teks, $kode) => [
                    'kode' => (string) $kode,
                    'teks' => $teks,
                    'benar' => is_array($jawaban) ? in_array($kode, $jawaban, true) : $jawaban === $kode,
                ])->values(),
                'kunci_teks' => null,
                'rubrik' => null,
            ],
            'benar_salah' => [
                'pernyataan' => collect(data_get($soal->opsi, 'pernyataan', []))->map(fn ($item) => [
                    'nomor' => (int) data_get($item, 'nomor'),
                    'teks' => data_get($item, 'teks'),
                    'jawaban' => (bool) data_get($soal->kunci_jawaban, 'jawaban.'.data_get($item, 'nomor')),
                ])->values(),
                'kunci_teks' => null,
                'rubrik' => null,
            ],
            'menjodohkan' => [
                'pasangan' => collect(data_get($soal->opsi, 'pasangan', []))->map(fn ($item) => [
                    'nomor' => (int) data_get($item, 'nomor'),
                    'kiri' => data_get($item, 'kiri'),
                    'kanan' => data_get($item, 'kanan'),
                ])->values(),
                'kunci_teks' => null,
                'rubrik' => null,
            ],
            default => [
                'kunci_teks' => is_scalar($jawaban) ? (string) $jawaban : null,
                'rubrik' => data_get($soal->rubrik, 'catatan'),
            ],
        };
    }

    private function ringkasMedia(SoalCbt $soal): array
    {
        $path = data_get($soal->media, 'gambar.path');

        return [
            'gambar' => $path ? [
                'url' => url(Storage::url($path)),
                'alt' => data_get($soal->media, 'gambar.alt'),
                'keterangan' => data_get($soal->media, 'gambar.keterangan'),
            ] : null,
            'tabel' => data_get($soal->media, 'tabel'),
            'rumus' => data_get($soal->media, 'rumus'),
        ];
    }

    private function rapikanData(array $data): array
    {
        return [
            ...$data,
            'tahun_pelajaran_id' => filled($data['tahun_pelajaran_id'] ?? null) ? (int) $data['tahun_pelajaran_id'] : null,
            'tingkat_kesulitan' => $data['tingkat_kesulitan'] ?? 'sedang',
            'kategori' => $data['kategori'] ?? 'umum',
            'skor_maksimal' => $data['skor_maksimal'] ?? 1,
            'status' => ($data['aksi'] ?? null) === 'simpan_siap' ? 'siap' : 'draft',
            'aktif' => true,
            'topik' => $this->teksAtauNull($data['topik'] ?? null),
            'materi' => $this->teksAtauNull($data['materi'] ?? null),
            'tujuan_pembelajaran' => $this->teksAtauNull($data['tujuan_pembelajaran'] ?? null),
            'stimulus' => $this->teksAtauNull($data['stimulus'] ?? null),
            'pertanyaan' => trim((string) $data['pertanyaan']),
            'pembahasan' => $this->teksAtauNull($data['pembahasan'] ?? null),
        ];
    }

    private function dataSoal(array $data): array
    {
        return collect($data)->only([
            'tahun_pelajaran_id',
            'mata_pelajaran_id',
            'tingkat',
            'jenis_soal',
            'tingkat_kesulitan',
            'kategori',
            'topik',
            'materi',
            'tujuan_pembelajaran',
            'stimulus',
            'pertanyaan',
            'skor_maksimal',
            'pembahasan',
            'status',
            'aktif',
        ])->all();
    }

    private function susunJawaban(array $data): array
    {
        return match ($data['jenis_soal']) {
            'pilihan_ganda' => $this->pilihanGanda($data, kompleks: false),
            'pilihan_ganda_kompleks' => $this->pilihanGanda($data, kompleks: true),
            'benar_salah' => $this->benarSalah($data),
            'menjodohkan' => $this->menjodohkan($data),
            'isian_singkat', 'numerik' => $this->jawabanTeks($data, wajib: true),
            'uraian', 'upload_file' => $this->jawabanTeks($data, wajib: false),
            default => ['opsi' => null, 'kunci_jawaban' => null, 'rubrik' => null],
        };
    }

    private function pilihanGanda(array $data, bool $kompleks): array
    {
        $opsi = collect($data['opsi'] ?? [])->mapWithKeys(fn ($teks, $kode) => [
            mb_strtoupper(trim((string) $kode)) => $this->teksAtauNull($teks),
        ])->filter()->all();
        if (count($opsi) < 2) {
            throw ValidationException::withMessages(['opsi' => 'Isi minimal dua opsi jawaban.']);
        }

        if (! $kompleks) {
            $kunci = mb_strtoupper(trim((string) ($data['kunci_pg'] ?? '')));
            if (! array_key_exists($kunci, $opsi)) {
                throw ValidationException::withMessages(['kunci_pg' => 'Pilih satu kunci jawaban yang sesuai dengan opsi.']);
            }

            return ['opsi' => ['pilihan' => $opsi], 'kunci_jawaban' => ['jawaban' => $kunci], 'rubrik' => null];
        }

        $kunci = collect($data['kunci_pgk'] ?? [])->map(fn ($kode) => mb_strtoupper(trim((string) $kode)))
            ->filter(fn ($kode) => array_key_exists($kode, $opsi))->unique()->values()->all();
        if ($kunci === []) {
            throw ValidationException::withMessages(['kunci_pgk' => 'Pilih minimal satu jawaban benar.']);
        }

        return ['opsi' => ['pilihan' => $opsi], 'kunci_jawaban' => ['jawaban' => $kunci], 'rubrik' => null];
    }

    private function benarSalah(array $data): array
    {
        $items = collect($data['pernyataan'] ?? [])->map(fn ($item, $index) => [
            'nomor' => $index + 1,
            'teks' => $this->teksAtauNull(data_get($item, 'teks')),
            'jawaban' => filter_var(data_get($item, 'jawaban', false), FILTER_VALIDATE_BOOLEAN),
        ])->filter(fn ($item) => filled($item['teks']))->values();
        if ($items->isEmpty()) {
            throw ValidationException::withMessages(['pernyataan' => 'Isi minimal satu pernyataan benar-salah.']);
        }

        return [
            'opsi' => ['pernyataan' => $items->map(fn ($item) => collect($item)->only(['nomor', 'teks'])->all())->all()],
            'kunci_jawaban' => ['jawaban' => $items->pluck('jawaban', 'nomor')->all()],
            'rubrik' => null,
        ];
    }

    private function menjodohkan(array $data): array
    {
        $items = collect($data['pasangan'] ?? [])->map(fn ($item, $index) => [
            'nomor' => $index + 1,
            'kiri' => $this->teksAtauNull(data_get($item, 'kiri')),
            'kanan' => $this->teksAtauNull(data_get($item, 'kanan')),
        ])->filter(fn ($item) => filled($item['kiri']) && filled($item['kanan']))->values();
        if ($items->isEmpty()) {
            throw ValidationException::withMessages(['pasangan' => 'Isi minimal satu pasangan lengkap.']);
        }

        return [
            'opsi' => ['pasangan' => $items->all()],
            'kunci_jawaban' => ['jawaban' => $items->pluck('kanan', 'nomor')->all()],
            'rubrik' => null,
        ];
    }

    private function jawabanTeks(array $data, bool $wajib): array
    {
        $kunci = $this->teksAtauNull($data['kunci_teks'] ?? null);
        $rubrik = $this->teksAtauNull($data['rubrik_teks'] ?? null);
        if ($wajib && blank($kunci)) {
            throw ValidationException::withMessages(['kunci_teks' => 'Isi kunci jawaban untuk jenis soal ini.']);
        }

        return [
            'opsi' => null,
            'kunci_jawaban' => $kunci ? ['jawaban' => $kunci] : null,
            'rubrik' => $rubrik ? ['catatan' => $rubrik] : null,
        ];
    }

    private function susunMedia(array $data, ?SoalCbt $soal, ?string $pathBaru): ?array
    {
        $lama = $soal?->media ?? [];
        $media = [];
        $hapus = filter_var($data['hapus_gambar_soal'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $path = $pathBaru ?: ($hapus ? null : data_get($lama, 'gambar.path'));
        if ($path) {
            $media['gambar'] = [
                'path' => $path,
                'alt' => $this->teksAtauNull($data['gambar_alt'] ?? null)
                    ?: data_get($lama, 'gambar.alt', 'Gambar pendukung soal'),
                'keterangan' => $this->teksAtauNull($data['gambar_keterangan'] ?? null)
                    ?? data_get($lama, 'gambar.keterangan'),
            ];
        }

        $tabel = array_key_exists('tabel', $data)
            ? $this->rapikanTabel($data['tabel'] ?? [])
            : data_get($lama, 'tabel.baris', []);
        if ($tabel !== []) {
            $media['tabel'] = [
                'judul' => $this->teksAtauNull($data['tabel_judul'] ?? null) ?? data_get($lama, 'tabel.judul'),
                'baris' => $tabel,
            ];
        }

        $rumus = array_key_exists('rumus_latex', $data)
            ? $this->teksAtauNull($data['rumus_latex'])
            : data_get($lama, 'rumus.latex');
        if ($rumus) {
            $media['rumus'] = [
                'latex' => $rumus,
                'keterangan' => $this->teksAtauNull($data['rumus_keterangan'] ?? null) ?? data_get($lama, 'rumus.keterangan'),
            ];
        }

        return $media === [] ? null : $media;
    }

    private function rapikanTabel(array $baris): array
    {
        $baris = collect($baris)->take(10)->map(fn ($row) => collect(is_array($row) ? $row : [])->take(8)
            ->map(fn ($cell) => str((string) $cell)->trim()->limit(500, '')->toString())->values()->all())
            ->filter(fn ($row) => collect($row)->contains(fn ($cell) => filled($cell)))->values();
        if ($baris->isEmpty()) {
            return [];
        }
        $kolom = $baris->max(fn ($row) => count($row));

        return $baris->map(fn ($row) => array_pad($row, $kolom, ''))->all();
    }

    private function konteksBankSoal(Pengguna $pengguna): Collection
    {
        $tahunAktif = TahunPelajaran::query()->where('aktif', true)->first();
        if (! $this->bisaLihatSemua($pengguna)) {
            return GuruMataPelajaran::query()->with(['mataPelajaran', 'kelas'])
                ->where('pegawai_id', $pengguna->pegawai_id)->where('aktif', true)
                ->when($tahunAktif, fn ($query, $tahun) => $query->where('tahun_pelajaran_id', $tahun->id))
                ->get()->filter(fn (GuruMataPelajaran $tugas) => $tugas->mataPelajaran?->aktif
                    && in_array((int) $tugas->kelas?->tingkat, [7, 8, 9], true))
                ->map(fn (GuruMataPelajaran $tugas) => $this->susunKonteks($tugas->mataPelajaran, (int) $tugas->kelas->tingkat))
                ->unique('kunci')->sortBy('label')->values();
        }

        return MataPelajaran::query()->where('aktif', true)->with('pengaturanTingkat')->orderBy('urutan')->orderBy('nama')->get()
            ->flatMap(function (MataPelajaran $mapel) use ($tahunAktif) {
                if ($mapel->pengaturanTingkat->isNotEmpty() && $tahunAktif) {
                    $tingkat = $mapel->pengaturanTingkat->where('tahun_pelajaran_id', $tahunAktif->id)
                        ->where('aktif', true)->pluck('tingkat');
                } elseif (in_array((int) $mapel->tingkat, [7, 8, 9], true)) {
                    $tingkat = collect([(int) $mapel->tingkat]);
                } else {
                    $tingkat = collect([7, 8, 9]);
                }

                return $tingkat->unique()->sort()->map(fn ($nilai) => $this->susunKonteks($mapel, (int) $nilai));
            })->sortBy('label')->values();
    }

    private function susunKonteks(MataPelajaran $mapel, int $tingkat): array
    {
        return [
            'kunci' => $mapel->id.'-'.$tingkat,
            'mata_pelajaran_id' => (int) $mapel->id,
            'tingkat' => $tingkat,
            'nama_mata_pelajaran' => $mapel->nama,
            'label' => $mapel->nama.' · Kelas '.$tingkat,
        ];
    }

    private function mataPelajaranCakupan(Pengguna $pengguna): Collection
    {
        if (! $pengguna->pegawai_id) {
            return collect();
        }
        $ids = GuruMataPelajaran::query()->where('pegawai_id', $pengguna->pegawai_id)
            ->where('aktif', true)->pluck('mata_pelajaran_id')->unique();

        return MataPelajaran::query()->whereIn('id', $ids)->where('aktif', true)->orderBy('urutan')->orderBy('nama')->get();
    }

    private function pastikanBolehMengakses(Pengguna $pengguna, SoalCbt $soal, bool $perluKelola = false): void
    {
        if ($perluKelola) {
            abort_unless($this->dapatKelola($pengguna), 403);
        }
        if ($this->bisaLihatSemua($pengguna)) {
            return;
        }
        abort_unless($this->mataPelajaranCakupan($pengguna)->contains('id', $soal->mata_pelajaran_id), 403);
    }

    private function pastikanKonteksTersedia(Pengguna $pengguna, array $data): void
    {
        $mapel = MataPelajaran::find($data['mata_pelajaran_id']);
        if ($data['tahun_pelajaran_id'] && ! $mapel?->tersediaUntuk((int) $data['tahun_pelajaran_id'], (int) $data['tingkat'])) {
            throw ValidationException::withMessages(['mata_pelajaran_id' => 'Mata pelajaran belum aktif pada tingkat tersebut.']);
        }
        if ($this->bisaLihatSemua($pengguna)) {
            return;
        }
        $ditugaskan = GuruMataPelajaran::query()->where('pegawai_id', $pengguna->pegawai_id)
            ->where('mata_pelajaran_id', $data['mata_pelajaran_id'])->where('aktif', true)
            ->when($data['tahun_pelajaran_id'], fn ($query, $tahun) => $query->where('tahun_pelajaran_id', $tahun))
            ->whereHas('kelas', fn ($query) => $query->where('tingkat', $data['tingkat']))->exists();
        abort_unless($ditugaskan, 403);
    }

    private function bisaLihatSemua(Pengguna $pengguna): bool
    {
        return $pengguna->memilikiIzin('cbt.kelola')
            || $pengguna->memilikiPeran(['pimpinan', 'wakil_pimpinan_kurikulum']);
    }

    private function dapatKelola(Pengguna $pengguna): bool
    {
        return $pengguna->memilikiIzin(['cbt.kelola', 'cbt.soal_kelola']);
    }

    private function buatKodeSaran(): string
    {
        $prefix = 'SOAL-CBT-'.now()->format('Ymd');
        $urutan = SoalCbt::query()->where('kode', 'like', $prefix.'-%')->count() + 1;

        return sprintf('%s-%03d', $prefix, $urutan);
    }

    private function simpanGambar(?UploadedFile $gambar): ?string
    {
        return $gambar?->store('soal-cbt/'.now()->format('Y'), 'public');
    }

    private function hapusGambar(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }

    private function teksAtauNull(mixed $nilai): ?string
    {
        $nilai = trim((string) $nilai);

        return $nilai === '' ? null : $nilai;
    }
}
