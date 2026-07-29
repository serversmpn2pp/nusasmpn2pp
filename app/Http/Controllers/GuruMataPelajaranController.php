<?php

namespace App\Http\Controllers;

use App\Models\GuruMataPelajaran;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\TahunPelajaran;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GuruMataPelajaranController extends Controller
{
    public function index(Request $request)
    {
        $kata_kunci = $request->kata_kunci;
        $status = $request->input('status', 'semua');
        $tahunPelajaranId = $request->input('tahun_pelajaran_id');

        if (! in_array($status, ['semua', 'aktif', 'nonaktif'], true)) {
            $status = 'semua';
        }

        $tahunPelajaran = TahunPelajaran::query()
            ->orderByDesc('aktif')
            ->orderByDesc('nama')
            ->get();

        $guruMataPelajaran = GuruMataPelajaran::query()
            ->with(['tahunPelajaran', 'kelas', 'mataPelajaran.pengaturanTingkat', 'pegawai'])
            ->when($tahunPelajaranId, function ($query, $tahunPelajaranId) {
                $query->where('tahun_pelajaran_id', $tahunPelajaranId);
            })
            ->when($status === 'aktif', function ($query) {
                $query->where('aktif', true);
            })
            ->when($status === 'nonaktif', function ($query) {
                $query->where('aktif', false);
            })
            ->when($kata_kunci, function ($query, $kata_kunci) {
                $query->where(function ($query) use ($kata_kunci) {
                    $query->whereHas('pegawai', function ($query) use ($kata_kunci) {
                        $query->where('nama_lengkap', 'ilike', '%'.$kata_kunci.'%')
                            ->orWhere('nip', 'ilike', '%'.$kata_kunci.'%');
                    })
                        ->orWhereHas('mataPelajaran', function ($query) use ($kata_kunci) {
                            $query->where('nama', 'ilike', '%'.$kata_kunci.'%')
                                ->orWhere('kode', 'ilike', '%'.$kata_kunci.'%')
                                ->orWhereHas('pengaturanTingkat', fn ($query) => $query
                                    ->where('kode', 'ilike', '%'.$kata_kunci.'%'));
                        })
                        ->orWhereHas('kelas', function ($query) use ($kata_kunci) {
                            $query->where('nama', 'ilike', '%'.$kata_kunci.'%');
                        });
                });
            })
            ->orderByDesc(
                TahunPelajaran::select('aktif')
                    ->whereColumn('tahun_pelajaran.id', 'guru_mata_pelajaran.tahun_pelajaran_id')
                    ->limit(1)
            )
            ->orderByDesc(
                TahunPelajaran::select('nama')
                    ->whereColumn('tahun_pelajaran.id', 'guru_mata_pelajaran.tahun_pelajaran_id')
                    ->limit(1)
            )
            ->orderBy(
                Kelas::select('nama')
                    ->whereColumn('kelas.id', 'guru_mata_pelajaran.kelas_id')
                    ->limit(1)
            )
            ->paginate(10)
            ->withQueryString();

        $jumlahGuruMataPelajaran = GuruMataPelajaran::count();
        $jumlahAktif = GuruMataPelajaran::where('aktif', true)->count();
        $jumlahNonaktif = GuruMataPelajaran::where('aktif', false)->count();

        return view('guru-mata-pelajaran.index', compact(
            'guruMataPelajaran',
            'tahunPelajaran',
            'tahunPelajaranId',
            'kata_kunci',
            'status',
            'jumlahGuruMataPelajaran',
            'jumlahAktif',
            'jumlahNonaktif',
        ));
    }

    public function create(Request $request)
    {
        return view('guru-mata-pelajaran.create', $this->dataForm([
            'tahunPelajaranDipilih' => $request->input('tahun_pelajaran_id'),
        ]));
    }

    public function store(Request $request)
    {
        $data = $this->rapikanData($request->validate($this->aturanValidasiMassal()));
        $data['aktif'] = $request->boolean('aktif');
        $kelasIds = collect($data['kelas_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        $this->pastikanRelasiMassalCocok($data, $kelasIds->all());

        [$jumlahBaru, $jumlahDiperbarui] = DB::transaction(function () use ($data, $kelasIds) {
            $jumlahBaru = 0;
            $jumlahDiperbarui = 0;
            $dataPenugasan = collect($data)
                ->except('kelas_ids')
                ->all();

            foreach ($kelasIds as $kelasId) {
                $kunci = [
                    'tahun_pelajaran_id' => $data['tahun_pelajaran_id'],
                    'kelas_id' => $kelasId,
                    'mata_pelajaran_id' => $data['mata_pelajaran_id'],
                    'pegawai_id' => $data['pegawai_id'],
                ];
                $penugasan = GuruMataPelajaran::query()->where($kunci)->first();

                if ($penugasan) {
                    $penugasan->update([
                        'jenis_penugasan' => $data['jenis_penugasan'],
                        'aktif' => $data['aktif'],
                        'keterangan' => $data['keterangan'] ?? null,
                    ]);
                    $jumlahDiperbarui++;

                    continue;
                }

                GuruMataPelajaran::create([
                    ...$dataPenugasan,
                    'kelas_id' => $kelasId,
                ]);
                $jumlahBaru++;
            }

            return [$jumlahBaru, $jumlahDiperbarui];
        });

        $rincian = collect([
            $jumlahBaru > 0 ? "{$jumlahBaru} baru" : null,
            $jumlahDiperbarui > 0 ? "{$jumlahDiperbarui} diperbarui" : null,
        ])->filter()->implode(', ');

        return redirect()
            ->route('guru-mata-pelajaran.index', [
                'tahun_pelajaran_id' => $data['tahun_pelajaran_id'],
                'status' => $data['aktif'] ? 'aktif' : 'semua',
            ])
            ->with(
                'berhasil',
                "Penugasan guru mata pelajaran berhasil disimpan untuk {$kelasIds->count()} kelas ({$rincian}).",
            );
    }

    public function show(GuruMataPelajaran $guruMataPelajaran)
    {
        $guruMataPelajaran->load([
            'tahunPelajaran',
            'kelas',
            'mataPelajaran.pengaturanTingkat',
            'pegawai',
            'riwayatPergantian.pegawaiLama',
            'riwayatPergantian.pegawaiBaru',
            'riwayatPergantian.digantiOleh',
        ]);

        return view('guru-mata-pelajaran.show', compact('guruMataPelajaran'));
    }

    public function edit(GuruMataPelajaran $guruMataPelajaran)
    {
        return view('guru-mata-pelajaran.edit', $this->dataForm([
            'guruMataPelajaran' => $guruMataPelajaran,
            'tahunPelajaranDipilih' => null,
        ]));
    }

    public function update(Request $request, GuruMataPelajaran $guruMataPelajaran)
    {
        $data = $this->rapikanData($request->validate($this->aturanValidasi($guruMataPelajaran)));
        $data['aktif'] = $request->boolean('aktif');
        $this->pastikanRelasiCocok($data);

        $guruMataPelajaran->update($data);

        return redirect()
            ->route('guru-mata-pelajaran.show', $guruMataPelajaran)
            ->with('berhasil', 'Guru mata pelajaran berhasil diperbarui.');
    }

    public function destroy(GuruMataPelajaran $guruMataPelajaran)
    {
        $guruMataPelajaran->update(['aktif' => false]);

        return redirect()
            ->route('guru-mata-pelajaran.index')
            ->with('berhasil', 'Guru mata pelajaran berhasil dinonaktifkan.');
    }

    private function dataForm(array $tambahan = []): array
    {
        return array_merge([
            'tahunPelajaran' => TahunPelajaran::query()
                ->orderByDesc('aktif')
                ->orderByDesc('nama')
                ->get(),
            'kelas' => Kelas::query()
                ->with('tahunPelajaran')
                ->orderByDesc(
                    TahunPelajaran::select('aktif')
                        ->whereColumn('tahun_pelajaran.id', 'kelas.tahun_pelajaran_id')
                        ->limit(1)
                )
                ->orderByDesc(
                    TahunPelajaran::select('nama')
                        ->whereColumn('tahun_pelajaran.id', 'kelas.tahun_pelajaran_id')
                        ->limit(1)
                )
                ->orderBy('tingkat')
                ->orderBy('nama')
                ->get(),
            'mataPelajaran' => MataPelajaran::query()
                ->with(['pengaturanTingkat' => fn ($query) => $query
                    ->where('aktif', true)
                    ->orderBy('tingkat')])
                ->where('aktif', true)
                ->orderBy('urutan')
                ->orderBy('nama')
                ->get(),
            'pegawai' => Pegawai::query()
                ->where('aktif', true)
                ->orderBy('nama_lengkap')
                ->get(['id', 'nama_lengkap', 'nip', 'jabatan_utama', 'jenis_pegawai']),
        ], $tambahan);
    }

    private function aturanValidasi(?GuruMataPelajaran $guruMataPelajaran = null): array
    {
        return [
            'tahun_pelajaran_id' => 'required|exists:tahun_pelajaran,id',
            'kelas_id' => 'required|exists:kelas,id',
            'mata_pelajaran_id' => 'required|exists:mata_pelajaran,id',
            'pegawai_id' => [
                'required',
                'exists:pegawai,id',
                Rule::unique('guru_mata_pelajaran', 'pegawai_id')
                    ->where('tahun_pelajaran_id', request('tahun_pelajaran_id'))
                    ->where('kelas_id', request('kelas_id'))
                    ->where('mata_pelajaran_id', request('mata_pelajaran_id'))
                    ->ignore($guruMataPelajaran),
            ],
            'jenis_penugasan' => 'required|in:pengampu,pendamping,koordinator',
            'aktif' => 'nullable|boolean',
            'keterangan' => 'nullable|string',
        ];
    }

    private function aturanValidasiMassal(): array
    {
        return [
            'tahun_pelajaran_id' => ['required', 'integer', 'exists:tahun_pelajaran,id'],
            'kelas_ids' => ['required', 'array', 'min:1'],
            'kelas_ids.*' => ['required', 'integer', 'distinct', 'exists:kelas,id'],
            'mata_pelajaran_id' => ['required', 'integer', 'exists:mata_pelajaran,id'],
            'pegawai_id' => ['required', 'integer', 'exists:pegawai,id'],
            'jenis_penugasan' => ['required', Rule::in(['pengampu', 'pendamping', 'koordinator'])],
            'aktif' => ['nullable', 'boolean'],
            'keterangan' => ['nullable', 'string'],
        ];
    }

    private function rapikanData(array $data): array
    {
        $data['jenis_penugasan'] = $data['jenis_penugasan'] ?: 'pengampu';

        return $data;
    }

    private function pastikanRelasiCocok(array $data): void
    {
        $kelas = Kelas::find($data['kelas_id']);
        $mataPelajaran = MataPelajaran::find($data['mata_pelajaran_id']);

        if (! $kelas || (int) $kelas->tahun_pelajaran_id !== (int) $data['tahun_pelajaran_id']) {
            throw ValidationException::withMessages([
                'kelas_id' => 'Kelas yang dipilih tidak berada pada tahun pelajaran tersebut.',
            ]);
        }

        if (
            ! $mataPelajaran
            || ! $mataPelajaran->tersediaUntuk(
                (int) $data['tahun_pelajaran_id'],
                (int) $kelas->tingkat,
            )
        ) {
            throw ValidationException::withMessages([
                'mata_pelajaran_id' => 'Mata pelajaran belum diaktifkan untuk tingkat kelas dan tahun pelajaran yang dipilih.',
            ]);
        }
    }

    private function pastikanRelasiMassalCocok(array $data, array $kelasIds): void
    {
        $kelas = Kelas::query()
            ->whereIn('id', $kelasIds)
            ->get();
        $mataPelajaran = MataPelajaran::find($data['mata_pelajaran_id']);

        if ($kelas->count() !== count($kelasIds)) {
            throw ValidationException::withMessages([
                'kelas_ids' => 'Ada kelas yang tidak ditemukan.',
            ]);
        }

        $kelasTidakCocok = $kelas->first(fn (Kelas $item) => (
            ! $item->aktif
            || (int) $item->tahun_pelajaran_id !== (int) $data['tahun_pelajaran_id']
        ));

        if ($kelasTidakCocok) {
            throw ValidationException::withMessages([
                'kelas_ids' => 'Semua kelas harus aktif dan berada pada tahun pelajaran yang dipilih.',
            ]);
        }

        $tingkatTidakTersedia = $kelas
            ->pluck('tingkat')
            ->unique()
            ->first(fn ($tingkat) => ! $mataPelajaran?->tersediaUntuk(
                (int) $data['tahun_pelajaran_id'],
                (int) $tingkat,
            ));

        if ($tingkatTidakTersedia) {
            throw ValidationException::withMessages([
                'mata_pelajaran_id' => "Mata pelajaran belum diaktifkan untuk tingkat {$tingkatTidakTersedia} pada tahun pelajaran yang dipilih.",
            ]);
        }
    }
}
