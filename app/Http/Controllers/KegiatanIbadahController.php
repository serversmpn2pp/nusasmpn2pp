<?php

namespace App\Http\Controllers;

use App\Models\KegiatanIbadah;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class KegiatanIbadahController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(['semua', 'aktif', 'nonaktif'])],
            'cari' => ['nullable', 'string', 'max:100'],
        ]);
        $status = $data['status'] ?? 'semua';
        $cari = trim((string) ($data['cari'] ?? ''));
        $kegiatanIbadah = KegiatanIbadah::query()
            ->withCount([
                'jadwal',
                'jadwal as jumlah_jadwal_aktif' => fn ($query) => $query->where('aktif', true),
            ])
            ->when($status === 'aktif', fn ($query) => $query->where('aktif', true))
            ->when($status === 'nonaktif', fn ($query) => $query->where('aktif', false))
            ->when($cari !== '', function ($query) use ($cari) {
                $kataKunci = '%'.mb_strtolower($cari).'%';
                $query->where(function ($query) use ($kataKunci) {
                    $query->whereRaw('LOWER(nama) LIKE ?', [$kataKunci])
                        ->orWhereRaw('LOWER(kode) LIKE ?', [$kataKunci]);
                });
            })
            ->orderByDesc('aktif')
            ->orderBy('nama')
            ->paginate(15)
            ->withQueryString();

        return view('kegiatan-ibadah.index', [
            'kegiatanIbadah' => $kegiatanIbadah,
            'status' => $status,
            'cari' => $cari,
            'jumlahKegiatan' => KegiatanIbadah::count(),
            'jumlahAktif' => KegiatanIbadah::where('aktif', true)->count(),
            'jumlahNonaktif' => KegiatanIbadah::where('aktif', false)->count(),
        ]);
    }

    public function create()
    {
        return view('kegiatan-ibadah.create');
    }

    public function store(Request $request)
    {
        $data = $this->dataTervalidasi($request);
        $kegiatanIbadah = KegiatanIbadah::create($data);

        return redirect()
            ->route('kegiatan-ibadah.show', $kegiatanIbadah)
            ->with('berhasil', 'Kegiatan ibadah berhasil ditambahkan.');
    }

    public function show(KegiatanIbadah $kegiatanIbadah)
    {
        $kegiatanIbadah->load(['jadwal' => fn ($query) => $query
            ->with('tahunPelajaran:id,nama,aktif')
            ->orderByDesc('tahun_pelajaran_id')
            ->orderBy('urutan_hari')]);

        return view('kegiatan-ibadah.show', compact('kegiatanIbadah'));
    }

    public function edit(KegiatanIbadah $kegiatanIbadah)
    {
        return view('kegiatan-ibadah.edit', compact('kegiatanIbadah'));
    }

    public function update(Request $request, KegiatanIbadah $kegiatanIbadah)
    {
        $kegiatanIbadah->update($this->dataTervalidasi($request, $kegiatanIbadah));

        return redirect()
            ->route('kegiatan-ibadah.show', $kegiatanIbadah)
            ->with('berhasil', 'Kegiatan ibadah berhasil diperbarui.');
    }

    public function destroy(KegiatanIbadah $kegiatanIbadah)
    {
        $kegiatanIbadah->update(['aktif' => false]);
        $kegiatanIbadah->jadwal()->update(['aktif' => false]);

        return redirect()
            ->route('kegiatan-ibadah.index')
            ->with('berhasil', 'Kegiatan dan seluruh jadwalnya berhasil dinonaktifkan.');
    }

    private function dataTervalidasi(Request $request, ?KegiatanIbadah $kegiatanIbadah = null): array
    {
        $request->merge([
            'kode' => Str::of((string) $request->input('kode'))->trim()->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->value(),
        ]);
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/', Rule::unique('kegiatan_ibadah', 'kode')->ignore($kegiatanIbadah)],
            'nama' => ['required', 'string', 'max:150'],
            'aktif' => ['nullable', 'boolean'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ]);
        $data['nama'] = trim($data['nama']);
        $data['aktif'] = $request->boolean('aktif');
        $data['keterangan'] = filled($data['keterangan'] ?? null) ? trim($data['keterangan']) : null;

        return $data;
    }
}
