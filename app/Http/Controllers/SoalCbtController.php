<?php

namespace App\Http\Controllers;

use App\Models\GuruMataPelajaran;
use App\Models\MataPelajaran;
use App\Models\SoalCbt;
use App\Models\TahunPelajaran;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class SoalCbtController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'kata_kunci' => ['nullable', 'string', 'max:100'],
            'mata_pelajaran_id' => ['nullable', 'integer', 'exists:mata_pelajaran,id'],
            'tingkat' => ['nullable', Rule::in(['semua', 7, 8, 9, '7', '8', '9'])],
            'jenis_soal' => ['nullable', Rule::in(['semua', ...array_keys(SoalCbt::DAFTAR_JENIS)])],
            'status' => ['nullable', Rule::in(['semua', ...array_keys(SoalCbt::DAFTAR_STATUS)])],
        ]);

        $pengguna = $request->user();
        $bisaLihatSemua = $this->bisaLihatSemua($request);
        $daftarKonteks = $this->konteksBankSoal($request);
        $mapelCakupanIds = $this->mataPelajaranCakupan($request)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $kataKunci = trim((string) ($data['kata_kunci'] ?? ''));
        $mataPelajaranId = $data['mata_pelajaran_id'] ?? null;
        $tingkat = $data['tingkat'] ?? 'semua';
        $jenisSoal = $data['jenis_soal'] ?? 'semua';
        $status = $data['status'] ?? 'semua';

        $soalCbt = SoalCbt::query()
            ->with(['tahunPelajaran', 'mataPelajaran', 'dibuatOleh'])
            ->when(! $bisaLihatSemua, fn ($query) => $query->whereIn('mata_pelajaran_id', $mapelCakupanIds))
            ->when($mataPelajaranId, fn ($query, $id) => $query->where('mata_pelajaran_id', $id))
            ->when($tingkat !== 'semua', fn ($query) => $query->where('tingkat', (int) $tingkat))
            ->when($jenisSoal !== 'semua', fn ($query) => $query->where('jenis_soal', $jenisSoal))
            ->when($status !== 'semua', fn ($query) => $query->where('status', $status))
            ->when($kataKunci !== '', function ($query) use ($kataKunci) {
                $query->where(function ($query) use ($kataKunci) {
                    $query->where('kode', 'like', '%'.$kataKunci.'%')
                        ->orWhere('topik', 'like', '%'.$kataKunci.'%')
                        ->orWhere('materi', 'like', '%'.$kataKunci.'%')
                        ->orWhere('pertanyaan', 'like', '%'.$kataKunci.'%')
                        ->orWhereHas('mataPelajaran', fn ($query) => $query->where('nama', 'like', '%'.$kataKunci.'%'));
                });
            })
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        return view('soal-cbt.index', [
            'soalCbt' => $soalCbt,
            'kataKunci' => $kataKunci,
            'mataPelajaranId' => $mataPelajaranId,
            'tingkat' => $tingkat,
            'jenisSoal' => $jenisSoal,
            'status' => $status,
            'daftarMataPelajaran' => $bisaLihatSemua ? $this->semuaMataPelajaran() : $this->mataPelajaranCakupan($request),
            'daftarJenisSoal' => SoalCbt::DAFTAR_JENIS,
            'daftarStatus' => SoalCbt::DAFTAR_STATUS,
            'jumlahSoal' => SoalCbt::when(! $bisaLihatSemua, fn ($query) => $query->whereIn('mata_pelajaran_id', $mapelCakupanIds))->count(),
            'jumlahSiap' => SoalCbt::when(! $bisaLihatSemua, fn ($query) => $query->whereIn('mata_pelajaran_id', $mapelCakupanIds))->where('status', 'siap')->count(),
            'jumlahDraft' => SoalCbt::when(! $bisaLihatSemua, fn ($query) => $query->whereIn('mata_pelajaran_id', $mapelCakupanIds))->where('status', 'draft')->count(),
            'bisaKelolaSoal' => $pengguna?->memilikiIzin(['cbt.kelola', 'cbt.soal_kelola']) ?? false,
            'daftarKonteks' => $daftarKonteks,
        ]);
    }

    public function create(Request $request)
    {
        $daftarKonteks = $this->konteksBankSoal($request);
        $konteksTerpilih = $this->pilihKonteksDariRequest($request, $daftarKonteks)
            ?? ($daftarKonteks->count() === 1 ? $daftarKonteks->first() : null);

        return view('soal-cbt.create', $this->dataForm($request, [
            'daftarKonteks' => $daftarKonteks,
            'konteksTerpilih' => $konteksTerpilih,
            'nilaiAwal' => $this->nilaiAwalDariRequest($request),
        ]));
    }

    public function store(Request $request)
    {
        $data = $this->rapikanData($request->validate($this->aturanValidasi()));
        $data['kode'] = $data['kode'] ?: $this->buatKodeSaran();
        $this->pastikanMataPelajaranBoleh($request, (int) $data['mata_pelajaran_id']);
        $this->pastikanTingkatMataPelajaranTersedia($request, $data);
        $konten = $this->susunKontenJawaban($data);
        $gambarBaru = $this->simpanGambarSoal($request);

        try {
            $soalCbt = SoalCbt::create([
                ...$this->dataSoal($data, $konten),
                'media' => $this->susunMediaSoal($data, null, $gambarBaru),
                'dibuat_oleh_pengguna_id' => $request->user()?->id,
            ]);
        } catch (Throwable $exception) {
            $this->hapusGambarSoal($gambarBaru);

            throw $exception;
        }

        if (($data['aksi'] ?? null) === 'simpan_lanjut') {
            return redirect()
                ->route('soal-cbt.create', $this->parameterSoalBerikutnya($soalCbt))
                ->with('berhasil', 'Soal siap digunakan. Silakan lanjutkan ke soal berikutnya.');
        }

        return redirect()
            ->route('soal-cbt.show', $soalCbt)
            ->with('berhasil', $soalCbt->status === 'siap' ? 'Soal berhasil disimpan dan siap digunakan.' : 'Soal berhasil disimpan sebagai draf.');
    }

    public function show(Request $request, SoalCbt $soalCbt)
    {
        $this->pastikanBolehMengakses($request, $soalCbt);
        $soalCbt->load(['tahunPelajaran', 'mataPelajaran', 'dibuatOleh']);

        return view('soal-cbt.show', compact('soalCbt'));
    }

    public function edit(Request $request, SoalCbt $soalCbt)
    {
        $this->pastikanBolehMengakses($request, $soalCbt, perluKelola: true);
        $daftarKonteks = $this->konteksBankSoal($request);

        return view('soal-cbt.edit', $this->dataForm($request, [
            'soalCbt' => $soalCbt,
            'daftarKonteks' => $daftarKonteks,
            'konteksTerpilih' => $daftarKonteks->first(fn (array $item) => (
                (int) $item['mata_pelajaran_id'] === (int) $soalCbt->mata_pelajaran_id
                && (int) $item['tingkat'] === (int) $soalCbt->tingkat
            )),
        ]));
    }

    public function update(Request $request, SoalCbt $soalCbt)
    {
        $this->pastikanBolehMengakses($request, $soalCbt, perluKelola: true);
        $data = $this->rapikanData($request->validate($this->aturanValidasi($soalCbt)));
        $data['kode'] = $data['kode'] ?: $soalCbt->kode;
        $data['tahun_pelajaran_id'] = $data['tahun_pelajaran_id'] ?? $soalCbt->tahun_pelajaran_id;
        $this->pastikanMataPelajaranBoleh($request, (int) $data['mata_pelajaran_id']);
        $this->pastikanTingkatMataPelajaranTersedia($request, $data);
        $konten = $this->susunKontenJawaban($data);
        $gambarLama = data_get($soalCbt->media, 'gambar.path');
        $gambarBaru = $this->simpanGambarSoal($request);
        $media = $this->susunMediaSoal($data, $soalCbt, $gambarBaru);

        try {
            $soalCbt->update([
                ...$this->dataSoal($data, $konten),
                'media' => $media,
            ]);
        } catch (Throwable $exception) {
            $this->hapusGambarSoal($gambarBaru);

            throw $exception;
        }

        $gambarSekarang = data_get($media, 'gambar.path');
        if ($gambarLama && $gambarLama !== $gambarSekarang) {
            $this->hapusGambarSoal($gambarLama);
        }

        return redirect()
            ->route('soal-cbt.show', $soalCbt)
            ->with('berhasil', $soalCbt->status === 'siap' ? 'Soal diperbarui dan siap digunakan.' : 'Perubahan soal disimpan sebagai draf.');
    }

    public function destroy(Request $request, SoalCbt $soalCbt)
    {
        $this->pastikanBolehMengakses($request, $soalCbt, perluKelola: true);
        $soalCbt->update([
            'status' => 'arsip',
            'aktif' => false,
        ]);

        return redirect()
            ->route('soal-cbt.index')
            ->with('berhasil', 'Soal CBT berhasil diarsipkan.');
    }

    private function dataForm(Request $request, array $tambahan = []): array
    {
        return array_merge([
            'daftarMataPelajaran' => $this->bisaLihatSemua($request)
                ? $this->semuaMataPelajaran()
                : $this->mataPelajaranCakupan($request),
            'daftarJenisSoal' => SoalCbt::DAFTAR_JENIS,
            'daftarKesulitan' => SoalCbt::DAFTAR_KESULITAN,
            'daftarKategori' => SoalCbt::DAFTAR_KATEGORI,
            'daftarStatus' => SoalCbt::DAFTAR_STATUS,
        ], $tambahan);
    }

    private function aturanValidasi(?SoalCbt $soalCbt = null): array
    {
        return [
            'tahun_pelajaran_id' => ['nullable', 'integer', 'exists:tahun_pelajaran,id'],
            'mata_pelajaran_id' => ['required', 'integer', 'exists:mata_pelajaran,id'],
            'tingkat' => ['required', 'integer', Rule::in([7, 8, 9])],
            'kode' => ['nullable', 'string', 'max:60', Rule::unique('soal_cbt', 'kode')->ignore($soalCbt)],
            'jenis_soal' => ['required', Rule::in(array_keys(SoalCbt::DAFTAR_JENIS))],
            'tingkat_kesulitan' => ['nullable', Rule::in(array_keys(SoalCbt::DAFTAR_KESULITAN))],
            'kategori' => ['nullable', Rule::in(array_keys(SoalCbt::DAFTAR_KATEGORI))],
            'topik' => ['nullable', 'string', 'max:160'],
            'materi' => ['nullable', 'string', 'max:180'],
            'tujuan_pembelajaran' => ['nullable', 'string'],
            'stimulus' => ['nullable', 'string'],
            'pertanyaan' => ['required', 'string'],
            'skor_maksimal' => ['nullable', 'numeric', 'min:0.25', 'max:100'],
            'pembahasan' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in(array_keys(SoalCbt::DAFTAR_STATUS))],
            'aktif' => ['nullable', 'boolean'],
            'aksi' => ['nullable', Rule::in(['simpan_draf', 'simpan_siap', 'simpan_lanjut'])],
            'opsi' => ['nullable', 'array'],
            'opsi.*' => ['nullable', 'string', 'max:800'],
            'kunci_pg' => ['nullable', 'string', 'max:5'],
            'kunci_pgk' => ['nullable', 'array'],
            'kunci_pgk.*' => ['nullable', 'string', 'max:5'],
            'pernyataan' => ['nullable', 'array'],
            'pernyataan.*' => ['nullable', 'string', 'max:800'],
            'jawaban_bs' => ['nullable', 'array'],
            'jawaban_bs.*' => ['nullable', Rule::in(['benar', 'salah'])],
            'pasangan_kiri' => ['nullable', 'array'],
            'pasangan_kiri.*' => ['nullable', 'string', 'max:800'],
            'pasangan_kanan' => ['nullable', 'array'],
            'pasangan_kanan.*' => ['nullable', 'string', 'max:800'],
            'kunci_teks' => ['nullable', 'string'],
            'rubrik_teks' => ['nullable', 'string'],
            'gambar_soal' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'hapus_gambar_soal' => ['nullable', 'boolean'],
            'gambar_alt' => ['nullable', 'string', 'max:160'],
            'gambar_keterangan' => ['nullable', 'string', 'max:220'],
            'media_tabel' => ['nullable', 'json', 'max:24000'],
            'tabel_judul' => ['nullable', 'string', 'max:160'],
            'rumus_latex' => [
                'nullable',
                'string',
                'max:1500',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (str_contains((string) $value, '\\placeholder')) {
                        $fail('Lengkapi bagian rumus yang masih kosong.');
                    }
                },
            ],
            'rumus_keterangan' => ['nullable', 'string', 'max:220'],
        ];
    }

    private function rapikanData(array $data): array
    {
        $data['tahun_pelajaran_id'] = filled($data['tahun_pelajaran_id'] ?? null) ? (int) $data['tahun_pelajaran_id'] : null;
        $data['kode'] = mb_strtoupper(trim((string) ($data['kode'] ?? '')));
        $data['tingkat_kesulitan'] = $data['tingkat_kesulitan'] ?? 'sedang';
        $data['kategori'] = $data['kategori'] ?? 'umum';
        $data['skor_maksimal'] = $data['skor_maksimal'] ?? 1;
        $data['status'] = match ($data['aksi'] ?? null) {
            'simpan_siap', 'simpan_lanjut' => 'siap',
            'simpan_draf' => 'draft',
            default => $data['status'] ?? 'draft',
        };
        $data['topik'] = $this->teksAtauNull($data['topik'] ?? null);
        $data['materi'] = $this->teksAtauNull($data['materi'] ?? null);
        $data['tujuan_pembelajaran'] = $this->teksAtauNull($data['tujuan_pembelajaran'] ?? null);
        $data['stimulus'] = $this->teksAtauNull($data['stimulus'] ?? null);
        $data['pertanyaan'] = trim($data['pertanyaan']);
        $data['pembahasan'] = $this->teksAtauNull($data['pembahasan'] ?? null);
        $data['aktif'] = $data['status'] !== 'arsip'
            && filter_var($data['aktif'] ?? true, FILTER_VALIDATE_BOOLEAN);

        return $data;
    }

    private function dataSoal(array $data, array $konten): array
    {
        return collect($data)->only([
            'tahun_pelajaran_id',
            'mata_pelajaran_id',
            'tingkat',
            'kode',
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
        ])->merge($konten)->all();
    }

    private function susunKontenJawaban(array $data): array
    {
        return match ($data['jenis_soal']) {
            'pilihan_ganda' => $this->kontenPilihanGanda($data),
            'pilihan_ganda_kompleks' => $this->kontenPilihanGandaKompleks($data),
            'benar_salah' => $this->kontenBenarSalah($data),
            'menjodohkan' => $this->kontenMenjodohkan($data),
            'isian_singkat', 'numerik' => $this->kontenJawabanTeks($data, wajibKunci: true),
            'uraian', 'upload_file' => $this->kontenJawabanTeks($data, wajibKunci: false),
            default => ['opsi' => null, 'kunci_jawaban' => null, 'rubrik' => null],
        };
    }

    private function kontenPilihanGanda(array $data): array
    {
        $opsi = $this->ambilOpsiPilihan($data);
        $kunci = mb_strtoupper((string) ($data['kunci_pg'] ?? ''));

        if (! array_key_exists($kunci, $opsi)) {
            throw ValidationException::withMessages([
                'kunci_pg' => 'Pilih satu kunci jawaban yang sesuai dengan opsi.',
            ]);
        }

        return [
            'opsi' => ['pilihan' => $opsi],
            'kunci_jawaban' => ['jawaban' => $kunci],
            'rubrik' => null,
        ];
    }

    private function kontenPilihanGandaKompleks(array $data): array
    {
        $opsi = $this->ambilOpsiPilihan($data);
        $jawaban = collect($data['kunci_pgk'] ?? [])
            ->map(fn ($value) => mb_strtoupper(trim((string) $value)))
            ->filter(fn ($value) => array_key_exists($value, $opsi))
            ->unique()
            ->values()
            ->all();

        if ($jawaban === []) {
            throw ValidationException::withMessages([
                'kunci_pgk' => 'Pilih minimal satu jawaban benar untuk pilihan ganda kompleks.',
            ]);
        }

        return [
            'opsi' => ['pilihan' => $opsi],
            'kunci_jawaban' => ['jawaban' => $jawaban],
            'rubrik' => null,
        ];
    }

    private function kontenBenarSalah(array $data): array
    {
        $pernyataan = collect($data['pernyataan'] ?? [])
            ->map(fn ($value, $key) => [
                'nomor' => (int) $key + 1,
                'teks' => $this->teksAtauNull($value),
                'jawaban' => ($data['jawaban_bs'][$key] ?? null) === 'benar',
            ])
            ->filter(fn ($item) => filled($item['teks']))
            ->values();

        if ($pernyataan->isEmpty()) {
            throw ValidationException::withMessages([
                'pernyataan' => 'Isi minimal satu pernyataan benar-salah.',
            ]);
        }

        return [
            'opsi' => ['pernyataan' => $pernyataan->map(fn ($item) => collect($item)->only(['nomor', 'teks'])->all())->all()],
            'kunci_jawaban' => ['jawaban' => $pernyataan->pluck('jawaban', 'nomor')->all()],
            'rubrik' => null,
        ];
    }

    private function kontenMenjodohkan(array $data): array
    {
        $pasangan = collect($data['pasangan_kiri'] ?? [])
            ->map(fn ($value, $key) => [
                'nomor' => (int) $key + 1,
                'kiri' => $this->teksAtauNull($value),
                'kanan' => $this->teksAtauNull($data['pasangan_kanan'][$key] ?? null),
            ])
            ->filter(fn ($item) => filled($item['kiri']) && filled($item['kanan']))
            ->values();

        if ($pasangan->isEmpty()) {
            throw ValidationException::withMessages([
                'pasangan_kiri' => 'Isi minimal satu pasangan untuk soal menjodohkan.',
            ]);
        }

        return [
            'opsi' => ['pasangan' => $pasangan->all()],
            'kunci_jawaban' => ['jawaban' => $pasangan->mapWithKeys(fn ($item) => [$item['nomor'] => $item['kanan']])->all()],
            'rubrik' => null,
        ];
    }

    private function kontenJawabanTeks(array $data, bool $wajibKunci): array
    {
        $kunci = $this->teksAtauNull($data['kunci_teks'] ?? null);
        $rubrik = $this->teksAtauNull($data['rubrik_teks'] ?? null);

        if ($wajibKunci && blank($kunci)) {
            throw ValidationException::withMessages([
                'kunci_teks' => 'Isi kunci jawaban untuk jenis soal ini.',
            ]);
        }

        return [
            'opsi' => null,
            'kunci_jawaban' => $kunci ? ['jawaban' => $kunci] : null,
            'rubrik' => $rubrik ? ['catatan' => $rubrik] : null,
        ];
    }

    private function ambilOpsiPilihan(array $data): array
    {
        $opsi = collect($data['opsi'] ?? [])
            ->mapWithKeys(fn ($value, $key) => [mb_strtoupper((string) $key) => $this->teksAtauNull($value)])
            ->filter()
            ->all();

        if (count($opsi) < 2) {
            throw ValidationException::withMessages([
                'opsi' => 'Isi minimal dua opsi jawaban.',
            ]);
        }

        return $opsi;
    }

    private function simpanGambarSoal(Request $request): ?string
    {
        if (! $request->hasFile('gambar_soal')) {
            return null;
        }

        return $request->file('gambar_soal')->store('soal-cbt/'.now()->format('Y'), 'public');
    }

    private function susunMediaSoal(array $data, ?SoalCbt $soalCbt, ?string $gambarBaru): ?array
    {
        $mediaLama = $soalCbt?->media ?? [];
        $media = [];
        $hapusGambar = filter_var($data['hapus_gambar_soal'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $pathGambar = $gambarBaru ?: ($hapusGambar ? null : data_get($mediaLama, 'gambar.path'));

        if ($pathGambar) {
            $media['gambar'] = [
                'path' => $pathGambar,
                'alt' => $this->teksAtauNull($data['gambar_alt'] ?? null) ?: 'Gambar pendukung soal',
                'keterangan' => $this->teksAtauNull($data['gambar_keterangan'] ?? null),
            ];
        }

        $tabel = $this->rapikanTabelSoal($data['media_tabel'] ?? null);
        if ($tabel !== []) {
            $media['tabel'] = [
                'judul' => $this->teksAtauNull($data['tabel_judul'] ?? null),
                'baris' => $tabel,
            ];
        }

        $rumus = $this->teksAtauNull($data['rumus_latex'] ?? null);
        if ($rumus) {
            $media['rumus'] = [
                'latex' => $rumus,
                'keterangan' => $this->teksAtauNull($data['rumus_keterangan'] ?? null),
            ];
        }

        return $media === [] ? null : $media;
    }

    private function rapikanTabelSoal(?string $json): array
    {
        if (blank($json)) {
            return [];
        }

        $baris = json_decode($json, true);
        if (! is_array($baris)) {
            throw ValidationException::withMessages(['media_tabel' => 'Isi tabel tidak dapat dibaca. Buat kembali tabel soal.']);
        }

        $baris = collect($baris)
            ->take(10)
            ->map(fn ($row) => collect(is_array($row) ? $row : [])
                ->take(8)
                ->map(fn ($cell) => str((string) $cell)->trim()->limit(500, '')->toString())
                ->values()
                ->all())
            ->filter(fn ($row) => collect($row)->contains(fn ($cell) => filled($cell)))
            ->values();

        if ($baris->isEmpty()) {
            return [];
        }

        $jumlahKolom = $baris->max(fn ($row) => count($row));

        return $baris
            ->map(fn ($row) => array_pad(array_slice($row, 0, $jumlahKolom), $jumlahKolom, ''))
            ->all();
    }

    private function hapusGambarSoal(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }

    private function pastikanMataPelajaranBoleh(Request $request, int $mataPelajaranId): void
    {
        if ($this->bisaLihatSemua($request)) {
            return;
        }

        abort_unless(
            in_array($mataPelajaranId, $this->mataPelajaranCakupan($request)->pluck('id')->map(fn ($id) => (int) $id)->all(), true),
            403,
        );
    }

    private function pastikanBolehMengakses(Request $request, SoalCbt $soalCbt, bool $perluKelola = false): void
    {
        if ($perluKelola) {
            abort_unless($request->user()?->memilikiIzin(['cbt.kelola', 'cbt.soal_kelola']) ?? false, 403);
        }

        $this->pastikanMataPelajaranBoleh($request, (int) $soalCbt->mata_pelajaran_id);
    }

    private function bisaLihatSemua(Request $request): bool
    {
        $pengguna = $request->user();

        return ($pengguna?->memilikiIzin('cbt.kelola') ?? false)
            || ($pengguna?->memilikiPeran(['pimpinan', 'wakil_pimpinan_kurikulum']) ?? false);
    }

    private function mataPelajaranCakupan(Request $request): Collection
    {
        $pegawaiId = $request->user()?->pegawai_id;

        if (! $pegawaiId) {
            return collect();
        }

        $ids = GuruMataPelajaran::query()
            ->where('pegawai_id', $pegawaiId)
            ->where('aktif', true)
            ->pluck('mata_pelajaran_id')
            ->unique()
            ->all();

        return MataPelajaran::query()
            ->whereIn('id', $ids)
            ->where('aktif', true)
            ->orderBy('urutan')
            ->orderBy('nama')
            ->get();
    }

    private function konteksBankSoal(Request $request): Collection
    {
        $tahunAktif = TahunPelajaran::query()->where('aktif', true)->first();

        if (! $this->bisaLihatSemua($request)) {
            return GuruMataPelajaran::query()
                ->with(['mataPelajaran', 'kelas'])
                ->where('pegawai_id', $request->user()?->pegawai_id)
                ->where('aktif', true)
                ->when($tahunAktif, fn ($query, $tahun) => $query->where('tahun_pelajaran_id', $tahun->id))
                ->get()
                ->filter(fn (GuruMataPelajaran $penugasan) => (
                    $penugasan->mataPelajaran?->aktif
                    && in_array((int) $penugasan->kelas?->tingkat, [7, 8, 9], true)
                ))
                ->map(fn (GuruMataPelajaran $penugasan) => $this->susunKonteks(
                    $penugasan->mataPelajaran,
                    (int) $penugasan->kelas->tingkat,
                ))
                ->unique('kunci')
                ->sortBy(fn (array $item) => $item['nama_mata_pelajaran'].'-'.$item['tingkat'])
                ->values();
        }

        return $this->semuaMataPelajaran()
            ->load('pengaturanTingkat')
            ->flatMap(function (MataPelajaran $mataPelajaran) use ($tahunAktif) {
                $pengaturan = $mataPelajaran->pengaturanTingkat;

                if ($pengaturan->isNotEmpty() && $tahunAktif) {
                    $tingkatTersedia = $pengaturan
                        ->where('tahun_pelajaran_id', $tahunAktif->id)
                        ->where('aktif', true)
                        ->pluck('tingkat');
                } elseif (in_array((int) $mataPelajaran->tingkat, [7, 8, 9], true)) {
                    $tingkatTersedia = collect([(int) $mataPelajaran->tingkat]);
                } else {
                    $tingkatTersedia = collect([7, 8, 9]);
                }

                return $tingkatTersedia
                    ->unique()
                    ->sort()
                    ->map(fn ($tingkat) => $this->susunKonteks($mataPelajaran, (int) $tingkat));
            })
            ->sortBy(fn (array $item) => $item['nama_mata_pelajaran'].'-'.$item['tingkat'])
            ->values();
    }

    private function susunKonteks(MataPelajaran $mataPelajaran, int $tingkat): array
    {
        return [
            'kunci' => $mataPelajaran->id.'-'.$tingkat,
            'mata_pelajaran_id' => (int) $mataPelajaran->id,
            'tingkat' => $tingkat,
            'nama_mata_pelajaran' => $mataPelajaran->nama,
            'label' => $mataPelajaran->nama.' · Kelas '.$tingkat,
        ];
    }

    private function pilihKonteksDariRequest(Request $request, Collection $daftarKonteks): ?array
    {
        $mataPelajaranId = $request->integer('mata_pelajaran_id');
        $tingkat = $request->integer('tingkat');

        return $daftarKonteks->first(fn (array $item) => (
            $item['mata_pelajaran_id'] === $mataPelajaranId
            && $item['tingkat'] === $tingkat
        ));
    }

    private function nilaiAwalDariRequest(Request $request): array
    {
        $jenisSoal = (string) $request->query('jenis_soal', 'pilihan_ganda');
        $kesulitan = (string) $request->query('tingkat_kesulitan', 'sedang');
        $kategori = (string) $request->query('kategori', 'umum');

        return [
            'jenis_soal' => array_key_exists($jenisSoal, SoalCbt::DAFTAR_JENIS) ? $jenisSoal : 'pilihan_ganda',
            'tingkat_kesulitan' => array_key_exists($kesulitan, SoalCbt::DAFTAR_KESULITAN) ? $kesulitan : 'sedang',
            'kategori' => array_key_exists($kategori, SoalCbt::DAFTAR_KATEGORI) ? $kategori : 'umum',
            'topik' => str($request->query('topik', ''))->limit(160, '')->toString(),
            'materi' => str($request->query('materi', ''))->limit(180, '')->toString(),
        ];
    }

    private function parameterSoalBerikutnya(SoalCbt $soalCbt): array
    {
        return [
            'mata_pelajaran_id' => $soalCbt->mata_pelajaran_id,
            'tingkat' => $soalCbt->tingkat,
            'jenis_soal' => $soalCbt->jenis_soal,
            'tingkat_kesulitan' => $soalCbt->tingkat_kesulitan,
            'kategori' => $soalCbt->kategori,
            'topik' => $soalCbt->topik,
            'materi' => $soalCbt->materi,
        ];
    }

    private function semuaMataPelajaran(): Collection
    {
        return MataPelajaran::query()
            ->where('aktif', true)
            ->orderBy('urutan')
            ->orderBy('nama')
            ->get();
    }

    private function buatKodeSaran(): string
    {
        $prefix = 'SOAL-CBT-'.now()->format('Ymd');
        $urutan = SoalCbt::where('kode', 'like', $prefix.'-%')->count() + 1;

        return sprintf('%s-%03d', $prefix, $urutan);
    }

    private function pastikanTingkatMataPelajaranTersedia(Request $request, array $data): void
    {
        $mataPelajaran = MataPelajaran::find($data['mata_pelajaran_id']);

        if (
            $data['tahun_pelajaran_id']
            && ! $mataPelajaran?->tersediaUntuk(
                (int) $data['tahun_pelajaran_id'],
                (int) $data['tingkat'],
            )
        ) {
            throw ValidationException::withMessages([
                'mata_pelajaran_id' => 'Mata pelajaran belum diaktifkan untuk tingkat dan tahun pelajaran soal.',
            ]);
        }

        if ($this->bisaLihatSemua($request)) {
            return;
        }

        $ditugaskan = GuruMataPelajaran::query()
            ->where('pegawai_id', $request->user()?->pegawai_id)
            ->where('mata_pelajaran_id', $data['mata_pelajaran_id'])
            ->where('aktif', true)
            ->when(
                $data['tahun_pelajaran_id'],
                fn ($query, $tahunPelajaranId) => $query->where('tahun_pelajaran_id', $tahunPelajaranId),
            )
            ->whereHas('kelas', fn ($query) => $query->where('tingkat', $data['tingkat']))
            ->exists();

        if (! $ditugaskan) {
            throw ValidationException::withMessages([
                'tingkat' => 'Pilih tingkat yang memang diajar oleh akun guru untuk mata pelajaran ini.',
            ]);
        }
    }

    private function teksAtauNull(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
