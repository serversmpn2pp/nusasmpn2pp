<?php

namespace App\Http\Controllers;

use App\Models\GuruMataPelajaran;
use App\Models\JadwalPelajaran;
use App\Models\JamPelajaran;
use App\Models\Kelas;
use App\Models\TahunPelajaran;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class JadwalPelajaranController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'tahun_pelajaran_id' => ['nullable', 'integer', 'exists:tahun_pelajaran,id'],
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
            'hari' => ['nullable', Rule::in(array_merge(['semua'], array_keys(JamPelajaran::DAFTAR_HARI)))],
            'status' => ['nullable', Rule::in(['semua', 'aktif', 'nonaktif'])],
        ]);

        $tahunPelajaran = TahunPelajaran::query()
            ->orderByDesc('aktif')
            ->orderByDesc('tanggal_mulai')
            ->get();
        $tahunPelajaranId = $this->ambilTahunPelajaranId($data['tahun_pelajaran_id'] ?? null, $tahunPelajaran);
        $kelas = $tahunPelajaranId
            ? Kelas::query()
                ->where('tahun_pelajaran_id', $tahunPelajaranId)
                ->where('aktif', true)
                ->orderBy('tingkat')
                ->orderBy('nama')
                ->get()
            : collect();
        $kelasId = $data['kelas_id'] ?? null;
        $hari = $data['hari'] ?? 'semua';
        $status = $data['status'] ?? 'aktif';

        if ($kelasId && ! $kelas->contains('id', (int) $kelasId)) {
            $kelasId = null;
        }

        $jadwalPelajaran = JadwalPelajaran::query()
            ->with([
                'tahunPelajaran',
                'kelas',
                'jamPelajaran',
                'guruMataPelajaran.mataPelajaran',
                'guruMataPelajaran.pegawai',
            ])
            ->when($tahunPelajaranId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId))
            ->when($kelasId, fn ($query) => $query->where('kelas_id', $kelasId))
            ->when($hari !== 'semua', fn ($query) => $query->where('hari', $hari))
            ->when($status === 'aktif', fn ($query) => $query->where('aktif', true))
            ->when($status === 'nonaktif', fn ($query) => $query->where('aktif', false))
            ->orderByRaw("case hari when 'senin' then 1 when 'selasa' then 2 when 'rabu' then 3 when 'kamis' then 4 when 'jumat' then 5 when 'sabtu' then 6 else 7 end")
            ->orderBy(
                JamPelajaran::select('nomor_jam')
                    ->whereColumn('jam_pelajaran.id', 'jadwal_pelajaran.jam_pelajaran_id')
                    ->limit(1)
            )
            ->paginate(15)
            ->withQueryString();

        return view('jadwal-pelajaran.index', [
            'jadwalPelajaran' => $jadwalPelajaran,
            'tahunPelajaran' => $tahunPelajaran,
            'kelas' => $kelas,
            'tahunPelajaranId' => $tahunPelajaranId,
            'kelasId' => $kelasId,
            'hari' => $hari,
            'status' => $status,
            'daftarHari' => JamPelajaran::DAFTAR_HARI,
            'jumlahJadwal' => JadwalPelajaran::count(),
            'jumlahAktif' => JadwalPelajaran::where('aktif', true)->count(),
            'jumlahNonaktif' => JadwalPelajaran::where('aktif', false)->count(),
        ]);
    }

    public function create(Request $request)
    {
        return view('jadwal-pelajaran.create', $this->dataForm([
            'tahunPelajaranDipilih' => $request->input('tahun_pelajaran_id'),
            'kelasDipilih' => $request->input('kelas_id'),
        ]));
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->aturanValidasi());
        $data['aktif'] = $request->boolean('aktif');
        $this->pastikanRelasiCocok($data);
        $this->pastikanGuruTidakBentrok($data);

        $jadwalPelajaran = JadwalPelajaran::create($data);

        return redirect()
            ->route('jadwal-pelajaran.show', $jadwalPelajaran)
            ->with('berhasil', 'Jadwal pelajaran berhasil ditambahkan.');
    }

    public function show(JadwalPelajaran $jadwalPelajaran)
    {
        $jadwalPelajaran->load([
            'tahunPelajaran',
            'kelas',
            'jamPelajaran',
            'guruMataPelajaran.mataPelajaran',
            'guruMataPelajaran.pegawai',
        ]);

        return view('jadwal-pelajaran.show', compact('jadwalPelajaran'));
    }

    public function edit(JadwalPelajaran $jadwalPelajaran)
    {
        return view('jadwal-pelajaran.edit', $this->dataForm([
            'jadwalPelajaran' => $jadwalPelajaran,
            'tahunPelajaranDipilih' => null,
            'kelasDipilih' => null,
        ]));
    }

    public function update(Request $request, JadwalPelajaran $jadwalPelajaran)
    {
        $data = $request->validate($this->aturanValidasi($jadwalPelajaran));
        $data['aktif'] = $request->boolean('aktif');
        $this->pastikanRelasiCocok($data);
        $this->pastikanGuruTidakBentrok($data, $jadwalPelajaran);

        $jadwalPelajaran->update($data);

        return redirect()
            ->route('jadwal-pelajaran.show', $jadwalPelajaran)
            ->with('berhasil', 'Jadwal pelajaran berhasil diperbarui.');
    }

    public function destroy(JadwalPelajaran $jadwalPelajaran)
    {
        $jadwalPelajaran->update(['aktif' => false]);

        return redirect()
            ->route('jadwal-pelajaran.index')
            ->with('berhasil', 'Jadwal pelajaran berhasil dinonaktifkan.');
    }

    private function dataForm(array $tambahan = []): array
    {
        return array_merge([
            'tahunPelajaran' => TahunPelajaran::query()
                ->orderByDesc('aktif')
                ->orderByDesc('tanggal_mulai')
                ->get(),
            'kelas' => Kelas::query()
                ->with('tahunPelajaran')
                ->where('aktif', true)
                ->orderByDesc(
                    TahunPelajaran::select('aktif')
                        ->whereColumn('tahun_pelajaran.id', 'kelas.tahun_pelajaran_id')
                        ->limit(1)
                )
                ->orderBy('tingkat')
                ->orderBy('nama')
                ->get(),
            'jamPelajaran' => JamPelajaran::query()
                ->where('aktif', true)
                ->orderByRaw("case hari when 'senin' then 1 when 'selasa' then 2 when 'rabu' then 3 when 'kamis' then 4 when 'jumat' then 5 when 'sabtu' then 6 else 7 end")
                ->orderBy('nomor_jam')
                ->get(),
            'guruMataPelajaran' => GuruMataPelajaran::query()
                ->with(['tahunPelajaran', 'kelas', 'mataPelajaran', 'pegawai'])
                ->where('aktif', true)
                ->orderBy(
                    Kelas::select('nama')
                        ->whereColumn('kelas.id', 'guru_mata_pelajaran.kelas_id')
                        ->limit(1)
                )
                ->get(),
            'daftarHari' => JamPelajaran::DAFTAR_HARI,
        ], $tambahan);
    }

    private function aturanValidasi(?JadwalPelajaran $jadwalPelajaran = null): array
    {
        return [
            'tahun_pelajaran_id' => ['required', 'exists:tahun_pelajaran,id'],
            'kelas_id' => ['required', 'exists:kelas,id'],
            'hari' => ['required', Rule::in(array_keys(JamPelajaran::DAFTAR_HARI))],
            'jam_pelajaran_id' => [
                'required',
                'exists:jam_pelajaran,id',
                Rule::unique('jadwal_pelajaran', 'jam_pelajaran_id')
                    ->where('tahun_pelajaran_id', request('tahun_pelajaran_id'))
                    ->where('kelas_id', request('kelas_id'))
                    ->where('hari', request('hari'))
                    ->ignore($jadwalPelajaran),
            ],
            'guru_mata_pelajaran_id' => ['required', 'exists:guru_mata_pelajaran,id'],
            'aktif' => ['nullable', 'boolean'],
            'keterangan' => ['nullable', 'string'],
        ];
    }

    private function pastikanRelasiCocok(array $data): void
    {
        $kelas = Kelas::find($data['kelas_id']);
        $jamPelajaran = JamPelajaran::find($data['jam_pelajaran_id']);
        $guruMataPelajaran = GuruMataPelajaran::with(['mataPelajaran', 'pegawai'])->find($data['guru_mata_pelajaran_id']);

        if (! $kelas || (int) $kelas->tahun_pelajaran_id !== (int) $data['tahun_pelajaran_id']) {
            throw ValidationException::withMessages([
                'kelas_id' => 'Kelas yang dipilih tidak berada pada tahun pelajaran tersebut.',
            ]);
        }

        if (! $jamPelajaran || $jamPelajaran->hari !== $data['hari']) {
            throw ValidationException::withMessages([
                'jam_pelajaran_id' => 'Jam pelajaran yang dipilih tidak sesuai dengan hari.',
            ]);
        }

        if ($jamPelajaran->jenis !== 'pelajaran') {
            throw ValidationException::withMessages([
                'jam_pelajaran_id' => 'Pilih slot dengan jenis Pelajaran. Slot istirahat/upacara tidak diisi guru mapel.',
            ]);
        }

        if (
            ! $guruMataPelajaran
            || (int) $guruMataPelajaran->tahun_pelajaran_id !== (int) $data['tahun_pelajaran_id']
            || (int) $guruMataPelajaran->kelas_id !== (int) $data['kelas_id']
        ) {
            throw ValidationException::withMessages([
                'guru_mata_pelajaran_id' => 'Guru mata pelajaran harus sesuai dengan tahun pelajaran dan kelas.',
            ]);
        }
    }

    private function pastikanGuruTidakBentrok(array $data, ?JadwalPelajaran $jadwalPelajaran = null): void
    {
        $guruMataPelajaran = GuruMataPelajaran::find($data['guru_mata_pelajaran_id']);

        if (! $guruMataPelajaran) {
            return;
        }

        $bentrok = JadwalPelajaran::query()
            ->where('tahun_pelajaran_id', $data['tahun_pelajaran_id'])
            ->where('hari', $data['hari'])
            ->where('jam_pelajaran_id', $data['jam_pelajaran_id'])
            ->where('aktif', true)
            ->whereHas('guruMataPelajaran', fn ($query) => $query->where('pegawai_id', $guruMataPelajaran->pegawai_id))
            ->when($jadwalPelajaran, fn ($query) => $query->whereKeyNot($jadwalPelajaran->id))
            ->exists();

        if ($bentrok) {
            throw ValidationException::withMessages([
                'guru_mata_pelajaran_id' => 'Guru ini sudah memiliki jadwal pada hari dan jam yang sama.',
            ]);
        }
    }

    private function ambilTahunPelajaranId(?int $tahunPelajaranId, $tahunPelajaran): ?int
    {
        if ($tahunPelajaranId && $tahunPelajaran->contains('id', $tahunPelajaranId)) {
            return $tahunPelajaranId;
        }

        return $tahunPelajaran->firstWhere('aktif', true)?->id ?? $tahunPelajaran->first()?->id;
    }
}
