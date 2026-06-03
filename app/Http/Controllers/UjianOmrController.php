<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\KomponenNilai;
use App\Models\MataPelajaran;
use App\Models\TahunPelajaran;
use App\Models\UjianOmr;
use App\Models\VersiSoalUjianOmr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UjianOmrController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'kata_kunci' => ['nullable', 'string', 'max:100'],
            'tahun_pelajaran_id' => ['nullable', 'integer', 'exists:tahun_pelajaran,id'],
            'semester' => ['nullable', Rule::in(['semua', 'ganjil', 'genap'])],
            'status' => ['nullable', Rule::in(['semua', ...array_keys(UjianOmr::DAFTAR_STATUS)])],
        ]);
        $kataKunci = trim((string) ($data['kata_kunci'] ?? ''));
        $tahunPelajaranId = $data['tahun_pelajaran_id'] ?? null;
        $semester = $data['semester'] ?? 'semua';
        $status = $data['status'] ?? 'semua';

        $ujianOmr = UjianOmr::query()
            ->with(['tahunPelajaran', 'mataPelajaran'])
            ->withCount(['kelasUjianOmr', 'versiSoal' => fn ($query) => $query->where('aktif', true)])
            ->when($tahunPelajaranId, fn ($query, $id) => $query->where('tahun_pelajaran_id', $id))
            ->when($semester !== 'semua', fn ($query) => $query->where('semester', $semester))
            ->when($status !== 'semua', fn ($query) => $query->where('status', $status))
            ->when($kataKunci !== '', function ($query) use ($kataKunci) {
                $query->where(function ($query) use ($kataKunci) {
                    $query->where('nama', 'like', '%' . $kataKunci . '%')
                        ->orWhere('kode', 'like', '%' . $kataKunci . '%')
                        ->orWhereHas('mataPelajaran', fn ($query) => $query->where('nama', 'like', '%' . $kataKunci . '%'));
                });
            })
            ->orderByDesc('tanggal_ujian')
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        return view('ujian-omr.index', [
            'ujianOmr' => $ujianOmr,
            'kataKunci' => $kataKunci,
            'tahunPelajaranId' => $tahunPelajaranId,
            'semester' => $semester,
            'status' => $status,
            'daftarStatus' => UjianOmr::DAFTAR_STATUS,
            'daftarTahunPelajaran' => $this->daftarTahunPelajaran(),
            'jumlahUjian' => UjianOmr::count(),
            'jumlahDraft' => UjianOmr::where('status', 'draft')->count(),
            'jumlahSiap' => UjianOmr::where('status', 'siap')->count(),
        ]);
    }

    public function create()
    {
        return view('ujian-omr.create', $this->dataForm([
            'kodeSaran' => $this->buatKodeSaran(),
        ]));
    }

    public function store(Request $request)
    {
        $data = $this->rapikanData($request->validate($this->aturanValidasi()));
        $kelasPeserta = $this->pastikanKelasPesertaCocok($data);
        $kodeVersi = $this->ambilKodeVersi($data['kode_versi']);

        if ($data['status'] === 'siap') {
            throw ValidationException::withMessages([
                'status' => 'Ujian baru perlu disimpan sebagai draft sampai seluruh kunci jawaban selesai diisi.',
            ]);
        }

        $ujianOmr = DB::transaction(function () use ($data, $kelasPeserta, $kodeVersi, $request) {
            $ujianOmr = UjianOmr::create([
                ...$this->dataUjian($data),
                'dibuat_oleh_pengguna_id' => $request->user()?->id,
            ]);
            $this->sinkronkanKelasPeserta($ujianOmr, $kelasPeserta);
            $this->sinkronkanVersiSoal($ujianOmr, $kodeVersi);

            return $ujianOmr;
        });

        return redirect()
            ->route('ujian-omr.show', $ujianOmr)
            ->with('berhasil', 'Ujian OMR berhasil ditambahkan. Silakan isi kunci jawaban untuk setiap versi soal.');
    }

    public function show(UjianOmr $ujianOmr)
    {
        $ujianOmr->load([
            'tahunPelajaran',
            'mataPelajaran',
            'dibuatOleh',
            'kelasUjianOmr.kelas',
            'kelasUjianOmr.komponenNilai.guruMataPelajaran.pegawai',
            'kelasUjianOmr.lembarJawabUjianOmr',
            'versiSoal' => fn ($query) => $query->orderByDesc('aktif')->orderBy('kode'),
            'versiSoal.kunciJawaban',
        ]);
        $ujianOmr->loadCount(['lembarJawabUjianOmr', 'batchScanUjianOmr']);

        return view('ujian-omr.show', compact('ujianOmr'));
    }

    public function edit(UjianOmr $ujianOmr)
    {
        $ujianOmr->load(['kelasUjianOmr', 'versiSoal']);

        return view('ujian-omr.edit', $this->dataForm([
            'ujianOmr' => $ujianOmr,
            'kodeSaran' => null,
        ]));
    }

    public function update(Request $request, UjianOmr $ujianOmr)
    {
        $data = $this->rapikanData($request->validate($this->aturanValidasi($ujianOmr)));
        $kelasPeserta = $this->pastikanKelasPesertaCocok($data);
        $kodeVersi = $this->ambilKodeVersi($data['kode_versi']);

        DB::transaction(function () use ($ujianOmr, $data, $kelasPeserta, $kodeVersi) {
            $ujianOmr->update($this->dataUjian($data));
            $this->sinkronkanKelasPeserta($ujianOmr, $kelasPeserta);
            $this->sinkronkanVersiSoal($ujianOmr, $kodeVersi);
            $ujianOmr->versiSoal()
                ->each(fn (VersiSoalUjianOmr $versi) => $versi->kunciJawaban()
                    ->where('nomor_soal', '>', $ujianOmr->jumlah_soal)
                    ->delete());
            $this->pastikanSiapDigunakan($ujianOmr);
        });

        return redirect()
            ->route('ujian-omr.show', $ujianOmr)
            ->with('berhasil', 'Ujian OMR berhasil diperbarui.');
    }

    public function destroy(UjianOmr $ujianOmr)
    {
        $ujianOmr->update(['status' => 'nonaktif']);

        return redirect()
            ->route('ujian-omr.index')
            ->with('berhasil', 'Ujian OMR berhasil dinonaktifkan.');
    }

    private function dataForm(array $tambahan = []): array
    {
        return array_merge([
            'daftarTahunPelajaran' => $this->daftarTahunPelajaran(),
            'daftarMataPelajaran' => MataPelajaran::query()
                ->where('aktif', true)
                ->orderBy('urutan')
                ->orderBy('nama')
                ->get(),
            'daftarKelas' => Kelas::query()
                ->with('tahunPelajaran')
                ->where('aktif', true)
                ->orderBy('tingkat')
                ->orderBy('nama')
                ->get(),
            'daftarKomponenNilai' => KomponenNilai::query()
                ->with([
                    'guruMataPelajaran.tahunPelajaran',
                    'guruMataPelajaran.kelas',
                    'guruMataPelajaran.mataPelajaran',
                    'guruMataPelajaran.pegawai',
                ])
                ->where('aktif', true)
                ->whereIn('jenis_komponen', ['sts', 'sas_saj'])
                ->whereHas('guruMataPelajaran', fn ($query) => $query->where('aktif', true))
                ->orderBy('nama')
                ->get(),
            'daftarStatus' => UjianOmr::DAFTAR_STATUS,
        ], $tambahan);
    }

    private function daftarTahunPelajaran()
    {
        return TahunPelajaran::query()
            ->orderByDesc('aktif')
            ->orderByDesc('nama')
            ->get();
    }

    private function aturanValidasi(?UjianOmr $ujianOmr = null): array
    {
        return [
            'tahun_pelajaran_id' => ['required', 'integer', 'exists:tahun_pelajaran,id'],
            'mata_pelajaran_id' => ['required', 'integer', 'exists:mata_pelajaran,id'],
            'kode' => ['required', 'string', 'max:50', Rule::unique('ujian_omr', 'kode')->ignore($ujianOmr)],
            'nama' => ['required', 'string', 'max:180'],
            'semester' => ['required', Rule::in(['ganjil', 'genap'])],
            'tanggal_ujian' => ['nullable', 'date'],
            'jumlah_soal' => ['required', 'integer', 'min:1', 'max:50'],
            'jumlah_pilihan' => ['required', 'integer', Rule::in([4])],
            'status' => ['required', Rule::in(array_keys(UjianOmr::DAFTAR_STATUS))],
            'keterangan' => ['nullable', 'string'],
            'kode_versi' => ['required', 'string', 'max:120'],
            'kelas_peserta' => ['required', 'array'],
            'kelas_peserta.*' => ['nullable', 'integer', 'exists:komponen_nilai,id'],
        ];
    }

    private function rapikanData(array $data): array
    {
        $data['kode'] = mb_strtoupper(trim($data['kode']));
        $data['nama'] = trim($data['nama']);
        $data['keterangan'] = filled($data['keterangan'] ?? null) ? trim($data['keterangan']) : null;
        $data['kelas_peserta'] = collect($data['kelas_peserta'])
            ->filter(fn ($komponenNilaiId) => filled($komponenNilaiId))
            ->all();

        return $data;
    }

    private function dataUjian(array $data): array
    {
        return collect($data)->only([
            'tahun_pelajaran_id',
            'mata_pelajaran_id',
            'kode',
            'nama',
            'semester',
            'tanggal_ujian',
            'jumlah_soal',
            'jumlah_pilihan',
            'status',
            'keterangan',
        ])->all();
    }

    private function pastikanKelasPesertaCocok(array $data): array
    {
        $kelasPeserta = collect($data['kelas_peserta'])
            ->mapWithKeys(fn ($komponenNilaiId, $kelasId) => [(int) $kelasId => (int) $komponenNilaiId]);

        if ($kelasPeserta->isEmpty()) {
            throw ValidationException::withMessages([
                'kelas_peserta' => 'Pilih minimal satu kelas peserta dan komponen nilai tujuannya.',
            ]);
        }

        $kelas = Kelas::query()->whereIn('id', $kelasPeserta->keys())->get()->keyBy('id');
        $komponenNilai = KomponenNilai::query()
            ->with('guruMataPelajaran')
            ->whereIn('id', $kelasPeserta->values())
            ->get()
            ->keyBy('id');

        foreach ($kelasPeserta as $kelasId => $komponenNilaiId) {
            $kelasDipilih = $kelas[$kelasId] ?? null;
            $komponenDipilih = $komponenNilai[$komponenNilaiId] ?? null;
            $guruMapel = $komponenDipilih?->guruMataPelajaran;

            if (! $kelasDipilih || (int) $kelasDipilih->tahun_pelajaran_id !== (int) $data['tahun_pelajaran_id']) {
                throw ValidationException::withMessages([
                    'kelas_peserta' => 'Ada kelas peserta yang tidak sesuai dengan tahun pelajaran ujian.',
                ]);
            }

            if (
                ! $komponenDipilih
                || ! $komponenDipilih->aktif
                || ! in_array($komponenDipilih->jenis_komponen, ['sts', 'sas_saj'], true)
                || $komponenDipilih->semester !== $data['semester']
                || ! $guruMapel?->aktif
                || (int) $guruMapel->kelas_id !== $kelasId
                || (int) $guruMapel->tahun_pelajaran_id !== (int) $data['tahun_pelajaran_id']
                || (int) $guruMapel->mata_pelajaran_id !== (int) $data['mata_pelajaran_id']
            ) {
                throw ValidationException::withMessages([
                    'kelas_peserta' => 'Komponen nilai tujuan harus berupa STS atau SAS/SAJ yang aktif dan sesuai dengan kelas, semester, tahun pelajaran, serta mata pelajaran ujian.',
                ]);
            }
        }

        return $kelasPeserta->all();
    }

    private function ambilKodeVersi(string $nilai): array
    {
        $kodeVersi = collect(preg_split('/[\s,;]+/', mb_strtoupper(trim($nilai))))
            ->filter()
            ->unique()
            ->values();

        if ($kodeVersi->isEmpty()) {
            throw ValidationException::withMessages(['kode_versi' => 'Minimal satu versi soal perlu diisi.']);
        }

        if ($kodeVersi->contains(fn ($kode) => ! preg_match('/^[A-Z0-9-]{1,10}$/', $kode))) {
            throw ValidationException::withMessages([
                'kode_versi' => 'Kode versi hanya boleh berisi huruf, angka, atau tanda hubung dengan panjang maksimal 10 karakter.',
            ]);
        }

        return $kodeVersi->all();
    }

    private function sinkronkanKelasPeserta(UjianOmr $ujianOmr, array $kelasPeserta): void
    {
        $ujianOmr->kelasUjianOmr()->whereNotIn('kelas_id', array_keys($kelasPeserta))->delete();

        foreach ($kelasPeserta as $kelasId => $komponenNilaiId) {
            $ujianOmr->kelasUjianOmr()->updateOrCreate(
                ['kelas_id' => $kelasId],
                ['komponen_nilai_id' => $komponenNilaiId],
            );
        }
    }

    private function sinkronkanVersiSoal(UjianOmr $ujianOmr, array $kodeVersi): void
    {
        $ujianOmr->versiSoal()->whereNotIn('kode', $kodeVersi)->update(['aktif' => false]);

        foreach ($kodeVersi as $kode) {
            $ujianOmr->versiSoal()->updateOrCreate(
                ['kode' => $kode],
                ['aktif' => true],
            );
        }
    }

    private function pastikanSiapDigunakan(UjianOmr $ujianOmr): void
    {
        if ($ujianOmr->status !== 'siap') {
            return;
        }

        $versiBelumLengkap = $ujianOmr->versiSoal()
            ->where('aktif', true)
            ->withCount('kunciJawaban')
            ->get()
            ->first(fn (VersiSoalUjianOmr $versi) => $versi->kunci_jawaban_count !== $ujianOmr->jumlah_soal);

        if ($versiBelumLengkap) {
            throw ValidationException::withMessages([
                'status' => 'Ujian belum dapat ditandai siap digunakan karena kunci jawaban versi ' . $versiBelumLengkap->kode . ' belum lengkap.',
            ]);
        }
    }

    private function buatKodeSaran(): string
    {
        $prefix = 'OMR-' . now()->format('Ymd');
        $urutan = UjianOmr::where('kode', 'like', $prefix . '-%')->count() + 1;

        return sprintf('%s-%03d', $prefix, $urutan);
    }
}
