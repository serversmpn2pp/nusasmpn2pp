<?php

namespace App\Http\Controllers;

use App\Models\MataPelajaran;
use App\Models\TahunPelajaran;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MataPelajaranController extends Controller
{
    public function index(Request $request)
    {
        $kataKunci = trim((string) $request->input('kata_kunci', ''));
        $status = $request->input('status', 'semua');
        $tingkat = $request->input('tingkat', 'semua');
        [$tahunPelajaran, $tahunPelajaranId] = $this->tahunPelajaranDanPilihan($request);

        if (! in_array($status, ['semua', 'aktif', 'nonaktif'], true)) {
            $status = 'semua';
        }

        if (! in_array($tingkat, ['semua', '7', '8', '9'], true)) {
            $tingkat = 'semua';
        }

        $mataPelajaran = MataPelajaran::query()
            ->with([
                'pengaturanTingkat' => fn ($query) => $query
                    ->where('tahun_pelajaran_id', $tahunPelajaranId)
                    ->orderBy('tingkat'),
            ])
            ->when($status === 'aktif', fn ($query) => $query->where('aktif', true))
            ->when($status === 'nonaktif', fn ($query) => $query->where('aktif', false))
            ->when($tingkat !== 'semua', function ($query) use ($tahunPelajaranId, $tingkat) {
                $query->whereHas('pengaturanTingkat', fn ($query) => $query
                    ->where('tahun_pelajaran_id', $tahunPelajaranId)
                    ->where('tingkat', (int) $tingkat)
                    ->where('aktif', true));
            })
            ->when($kataKunci !== '', function ($query) use ($kataKunci, $tahunPelajaranId) {
                $query->where(function ($query) use ($kataKunci, $tahunPelajaranId) {
                    $query->where('nama', 'ilike', '%'.$kataKunci.'%')
                        ->orWhere('kelompok', 'ilike', '%'.$kataKunci.'%')
                        ->orWhereHas('pengaturanTingkat', fn ($query) => $query
                            ->where('tahun_pelajaran_id', $tahunPelajaranId)
                            ->where('kode', 'ilike', '%'.$kataKunci.'%'));
                });
            })
            ->orderByDesc('aktif')
            ->orderBy('urutan')
            ->orderBy('nama')
            ->paginate(10)
            ->withQueryString();

        return view('mata-pelajaran.index', [
            'mataPelajaran' => $mataPelajaran,
            'kataKunci' => $kataKunci,
            'status' => $status,
            'tingkat' => $tingkat,
            'tahunPelajaran' => $tahunPelajaran,
            'tahunPelajaranId' => $tahunPelajaranId,
            'jumlahMataPelajaran' => MataPelajaran::count(),
            'jumlahAktif' => MataPelajaran::where('aktif', true)->count(),
            'jumlahNonaktif' => MataPelajaran::where('aktif', false)->count(),
        ]);
    }

    public function create(Request $request)
    {
        return view('mata-pelajaran.create', $this->dataForm($request));
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->aturanValidasi($request));
        $this->pastikanAdaTingkatAktif($data);

        $mataPelajaran = DB::transaction(function () use ($request, $data) {
            $mataPelajaran = MataPelajaran::create($this->dataMataPelajaran($request, $data));
            $this->sinkronkanPengaturan($mataPelajaran, $data);

            return $mataPelajaran;
        });

        return redirect()
            ->route('mata-pelajaran.show', [
                'mata_pelajaran' => $mataPelajaran,
                'tahun_pelajaran_id' => $data['tahun_pelajaran_id'],
            ])
            ->with('berhasil', 'Mata pelajaran dan pengaturan tingkat berhasil ditambahkan.');
    }

    public function show(Request $request, MataPelajaran $mataPelajaran)
    {
        [$tahunPelajaran, $tahunPelajaranId] = $this->tahunPelajaranDanPilihan($request);
        $mataPelajaran->load([
            'pengaturanTingkat' => fn ($query) => $query
                ->where('tahun_pelajaran_id', $tahunPelajaranId)
                ->orderBy('tingkat'),
        ]);

        return view('mata-pelajaran.show', compact(
            'mataPelajaran',
            'tahunPelajaran',
            'tahunPelajaranId',
        ));
    }

    public function edit(Request $request, MataPelajaran $mataPelajaran)
    {
        return view('mata-pelajaran.edit', $this->dataForm($request, $mataPelajaran));
    }

    public function update(Request $request, MataPelajaran $mataPelajaran)
    {
        $data = $request->validate($this->aturanValidasi($request, $mataPelajaran));
        $this->pastikanAdaTingkatAktif($data);

        DB::transaction(function () use ($request, $data, $mataPelajaran) {
            $mataPelajaran->update($this->dataMataPelajaran($request, $data));
            $this->sinkronkanPengaturan($mataPelajaran, $data);
        });

        return redirect()
            ->route('mata-pelajaran.show', [
                'mata_pelajaran' => $mataPelajaran,
                'tahun_pelajaran_id' => $data['tahun_pelajaran_id'],
            ])
            ->with('berhasil', 'Mata pelajaran dan pengaturan tingkat berhasil diperbarui.');
    }

    public function destroy(MataPelajaran $mataPelajaran)
    {
        $mataPelajaran->update(['aktif' => false]);

        return redirect()
            ->route('mata-pelajaran.index')
            ->with('berhasil', 'Mata pelajaran berhasil dinonaktifkan.');
    }

    private function dataForm(Request $request, ?MataPelajaran $mataPelajaran = null): array
    {
        [$tahunPelajaran, $tahunPelajaranId] = $this->tahunPelajaranDanPilihan($request);

        $mataPelajaran?->load([
            'pengaturanTingkat' => fn ($query) => $query
                ->where('tahun_pelajaran_id', $tahunPelajaranId)
                ->orderBy('tingkat'),
        ]);

        return compact(
            'mataPelajaran',
            'tahunPelajaran',
            'tahunPelajaranId',
        );
    }

    private function tahunPelajaranDanPilihan(Request $request): array
    {
        $tahunPelajaran = TahunPelajaran::query()
            ->orderByDesc('aktif')
            ->orderByDesc('nama')
            ->get();
        $tahunPelajaranId = $request->integer('tahun_pelajaran_id');

        if (! $tahunPelajaran->contains('id', $tahunPelajaranId)) {
            $tahunPelajaranId = (int) ($tahunPelajaran->firstWhere('aktif', true)?->id
                ?? $tahunPelajaran->first()?->id);
        }

        return [$tahunPelajaran, $tahunPelajaranId];
    }

    private function aturanValidasi(Request $request, ?MataPelajaran $mataPelajaran = null): array
    {
        $menggunakanPredikat = MataPelajaran::kelompokMenggunakanPredikat(
            $request->input('kelompok'),
        );
        $aturan = [
            'tahun_pelajaran_id' => ['required', 'integer', 'exists:tahun_pelajaran,id'],
            'nama' => [
                'required',
                'string',
                'max:255',
                Rule::unique('mata_pelajaran', 'nama')->ignore($mataPelajaran),
            ],
            'kelompok' => ['nullable', 'string', 'max:50'],
            'urutan' => ['nullable', 'integer', 'min:0', 'max:999'],
            'aktif' => ['nullable', 'boolean'],
            'keterangan' => ['nullable', 'string'],
            'pengaturan' => ['required', 'array'],
        ];

        foreach ([7, 8, 9] as $tingkat) {
            $pengaturan = $mataPelajaran?->pengaturanTingkat
                ->firstWhere('tingkat', $tingkat);
            $aturan["pengaturan.{$tingkat}.aktif"] = ['nullable', 'boolean'];
            $aturan["pengaturan.{$tingkat}.kode"] = [
                "required_if:pengaturan.{$tingkat}.aktif,1",
                'nullable',
                'string',
                'max:30',
                Rule::unique('pengaturan_mata_pelajaran', 'kode')
                    ->where(fn ($query) => $query->where(
                        'tahun_pelajaran_id',
                        $request->integer('tahun_pelajaran_id'),
                    ))
                    ->ignore($pengaturan),
            ];
            $aturan["pengaturan.{$tingkat}.kkm"] = [
                Rule::requiredIf(
                    ! $menggunakanPredikat
                    && $request->boolean("pengaturan.{$tingkat}.aktif")
                ),
                'nullable',
                'integer',
                'min:0',
                'max:100',
            ];
        }

        return $aturan;
    }

    private function pastikanAdaTingkatAktif(array $data): void
    {
        $adaTingkatAktif = collect($data['pengaturan'])
            ->contains(fn (array $pengaturan) => (bool) ($pengaturan['aktif'] ?? false));

        if (! $adaTingkatAktif) {
            throw ValidationException::withMessages([
                'pengaturan' => 'Aktifkan minimal satu tingkat untuk mata pelajaran ini.',
            ]);
        }
    }

    private function dataMataPelajaran(Request $request, array $data): array
    {
        return [
            'nama' => trim($data['nama']),
            'kelompok' => filled($data['kelompok'] ?? null)
                ? trim($data['kelompok'])
                : null,
            'tingkat' => null,
            'kkm' => null,
            'urutan' => (int) ($data['urutan'] ?? 0),
            'aktif' => $request->boolean('aktif'),
            'keterangan' => filled($data['keterangan'] ?? null)
                ? trim($data['keterangan'])
                : null,
        ];
    }

    private function sinkronkanPengaturan(MataPelajaran $mataPelajaran, array $data): void
    {
        $menggunakanPredikat = MataPelajaran::kelompokMenggunakanPredikat(
            $data['kelompok'] ?? null,
        );

        foreach ([7, 8, 9] as $tingkat) {
            $nilai = $data['pengaturan'][$tingkat] ?? [];
            $aktif = (bool) ($nilai['aktif'] ?? false);
            $pengaturan = $mataPelajaran->pengaturanTingkat()
                ->where('tahun_pelajaran_id', $data['tahun_pelajaran_id'])
                ->where('tingkat', $tingkat)
                ->first();

            if (! $aktif && ! $pengaturan) {
                continue;
            }

            $mataPelajaran->pengaturanTingkat()->updateOrCreate(
                [
                    'tahun_pelajaran_id' => $data['tahun_pelajaran_id'],
                    'tingkat' => $tingkat,
                ],
                [
                    'kode' => mb_strtoupper(trim((string) ($nilai['kode'] ?? $pengaturan?->kode))),
                    'kkm' => $menggunakanPredikat
                        ? null
                        : (filled($nilai['kkm'] ?? null)
                            ? (int) $nilai['kkm']
                            : $pengaturan?->kkm),
                    'aktif' => $aktif,
                ],
            );
        }
    }
}
