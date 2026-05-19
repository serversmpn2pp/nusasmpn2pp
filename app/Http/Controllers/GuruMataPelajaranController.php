<?php

namespace App\Http\Controllers;

use App\Models\GuruMataPelajaran;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\TahunPelajaran;
use Illuminate\Http\Request;
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
            ->with(['tahunPelajaran', 'kelas', 'mataPelajaran', 'pegawai'])
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
                        $query->where('nama_lengkap', 'ilike', '%' . $kata_kunci . '%')
                            ->orWhere('nip', 'ilike', '%' . $kata_kunci . '%');
                    })
                        ->orWhereHas('mataPelajaran', function ($query) use ($kata_kunci) {
                            $query->where('nama', 'ilike', '%' . $kata_kunci . '%')
                                ->orWhere('kode', 'ilike', '%' . $kata_kunci . '%');
                        })
                        ->orWhereHas('kelas', function ($query) use ($kata_kunci) {
                            $query->where('nama', 'ilike', '%' . $kata_kunci . '%');
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
        $data = $this->rapikanData($request->validate($this->aturanValidasi()));
        $data['aktif'] = $request->boolean('aktif');
        $this->pastikanRelasiCocok($data);

        $guruMataPelajaran = GuruMataPelajaran::create($data);

        return redirect()
            ->route('guru-mata-pelajaran.show', $guruMataPelajaran)
            ->with('berhasil', 'Guru mata pelajaran berhasil ditambahkan.');
    }

    public function show(GuruMataPelajaran $guruMataPelajaran)
    {
        $guruMataPelajaran->load(['tahunPelajaran', 'kelas', 'mataPelajaran', 'pegawai']);

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

        if ($mataPelajaran?->tingkat && $kelas->tingkat && (int) $mataPelajaran->tingkat !== (int) $kelas->tingkat) {
            throw ValidationException::withMessages([
                'mata_pelajaran_id' => 'Mata pelajaran ini khusus kelas ' . $mataPelajaran->tingkat . ', sedangkan kelas tujuan adalah kelas ' . $kelas->tingkat . '.',
            ]);
        }
    }
}
