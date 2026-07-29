<?php

namespace App\Http\Controllers;

use App\Models\GuruMataPelajaran;
use App\Models\MataPelajaran;
use App\Models\SoalCbt;
use App\Models\TahunPelajaran;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
        ]);
    }

    public function create(Request $request)
    {
        return view('soal-cbt.create', $this->dataForm($request, [
            'kodeSaran' => $this->buatKodeSaran(),
        ]));
    }

    public function store(Request $request)
    {
        $data = $this->rapikanData($request->validate($this->aturanValidasi()));
        $this->pastikanMataPelajaranBoleh($request, (int) $data['mata_pelajaran_id']);
        $this->pastikanTingkatMataPelajaranTersedia($request, $data);
        $konten = $this->susunKontenJawaban($data);

        $soalCbt = SoalCbt::create([
            ...$this->dataSoal($data, $konten),
            'dibuat_oleh_pengguna_id' => $request->user()?->id,
        ]);

        return redirect()
            ->route('soal-cbt.show', $soalCbt)
            ->with('berhasil', 'Soal CBT berhasil ditambahkan.');
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

        return view('soal-cbt.edit', $this->dataForm($request, [
            'soalCbt' => $soalCbt,
            'kodeSaran' => null,
        ]));
    }

    public function update(Request $request, SoalCbt $soalCbt)
    {
        $this->pastikanBolehMengakses($request, $soalCbt, perluKelola: true);
        $data = $this->rapikanData($request->validate($this->aturanValidasi($soalCbt)));
        $this->pastikanMataPelajaranBoleh($request, (int) $data['mata_pelajaran_id']);
        $this->pastikanTingkatMataPelajaranTersedia($request, $data);
        $konten = $this->susunKontenJawaban($data);

        $soalCbt->update($this->dataSoal($data, $konten));

        return redirect()
            ->route('soal-cbt.show', $soalCbt)
            ->with('berhasil', 'Soal CBT berhasil diperbarui.');
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
            'daftarTahunPelajaran' => TahunPelajaran::query()
                ->orderByDesc('aktif')
                ->orderByDesc('nama')
                ->get(),
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
            'kode' => ['required', 'string', 'max:60', Rule::unique('soal_cbt', 'kode')->ignore($soalCbt)],
            'jenis_soal' => ['required', Rule::in(array_keys(SoalCbt::DAFTAR_JENIS))],
            'tingkat_kesulitan' => ['required', Rule::in(array_keys(SoalCbt::DAFTAR_KESULITAN))],
            'kategori' => ['required', Rule::in(array_keys(SoalCbt::DAFTAR_KATEGORI))],
            'topik' => ['nullable', 'string', 'max:160'],
            'materi' => ['nullable', 'string', 'max:180'],
            'tujuan_pembelajaran' => ['nullable', 'string'],
            'stimulus' => ['nullable', 'string'],
            'pertanyaan' => ['required', 'string'],
            'skor_maksimal' => ['required', 'numeric', 'min:0.25', 'max:100'],
            'pembahasan' => ['nullable', 'string'],
            'status' => ['required', Rule::in(array_keys(SoalCbt::DAFTAR_STATUS))],
            'aktif' => ['nullable', 'boolean'],
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
        ];
    }

    private function rapikanData(array $data): array
    {
        $data['tahun_pelajaran_id'] = filled($data['tahun_pelajaran_id'] ?? null) ? (int) $data['tahun_pelajaran_id'] : null;
        $data['kode'] = mb_strtoupper(trim($data['kode']));
        $data['topik'] = $this->teksAtauNull($data['topik'] ?? null);
        $data['materi'] = $this->teksAtauNull($data['materi'] ?? null);
        $data['tujuan_pembelajaran'] = $this->teksAtauNull($data['tujuan_pembelajaran'] ?? null);
        $data['stimulus'] = $this->teksAtauNull($data['stimulus'] ?? null);
        $data['pertanyaan'] = trim($data['pertanyaan']);
        $data['pembahasan'] = $this->teksAtauNull($data['pembahasan'] ?? null);
        $data['aktif'] = filter_var($data['aktif'] ?? true, FILTER_VALIDATE_BOOLEAN);

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
