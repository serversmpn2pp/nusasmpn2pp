<?php

namespace App\Http\Controllers;

use App\Models\JenisPerangkatAjar;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class JenisPerangkatAjarController extends Controller
{
    public function index(Request $request)
    {
        $kataKunci = trim((string) $request->input('kata_kunci', ''));
        $status = $request->input('status', 'semua');
        $kewajiban = $request->input('kewajiban', 'semua');

        if (! in_array($status, ['semua', 'aktif', 'nonaktif'], true)) {
            $status = 'semua';
        }

        if (! in_array($kewajiban, ['semua', 'wajib', 'opsional'], true)) {
            $kewajiban = 'semua';
        }

        $jenisPerangkatAjar = JenisPerangkatAjar::query()
            ->when($status === 'aktif', fn ($query) => $query->where('aktif', true))
            ->when($status === 'nonaktif', fn ($query) => $query->where('aktif', false))
            ->when($kewajiban === 'wajib', fn ($query) => $query->where('wajib', true))
            ->when($kewajiban === 'opsional', fn ($query) => $query->where('wajib', false))
            ->when($kataKunci !== '', function ($query) use ($kataKunci) {
                $query->where(function ($query) use ($kataKunci) {
                    $query->where('nama', 'ilike', '%' . $kataKunci . '%')
                        ->orWhere('kode', 'ilike', '%' . $kataKunci . '%')
                        ->orWhere('deskripsi', 'ilike', '%' . $kataKunci . '%');
                });
            })
            ->orderByDesc('aktif')
            ->orderBy('urutan')
            ->orderBy('nama')
            ->paginate(10)
            ->withQueryString();

        return view('jenis-perangkat-ajar.index', [
            'jenisPerangkatAjar' => $jenisPerangkatAjar,
            'kataKunci' => $kataKunci,
            'status' => $status,
            'kewajiban' => $kewajiban,
            'jumlahJenis' => JenisPerangkatAjar::count(),
            'jumlahWajib' => JenisPerangkatAjar::where('wajib', true)->count(),
            'jumlahAktif' => JenisPerangkatAjar::where('aktif', true)->count(),
        ]);
    }

    public function create()
    {
        return view('jenis-perangkat-ajar.create');
    }

    public function store(Request $request)
    {
        $request->merge(['kode' => $this->rapikanKode($request->input('kode'))]);
        $data = $this->rapikanData($request->validate($this->aturanValidasi()));
        $data['wajib'] = $request->boolean('wajib');
        $data['aktif'] = $request->boolean('aktif');

        $jenisPerangkatAjar = JenisPerangkatAjar::create($data);

        return redirect()
            ->route('jenis-perangkat-ajar.show', $jenisPerangkatAjar)
            ->with('berhasil', 'Jenis perangkat ajar berhasil ditambahkan.');
    }

    public function show(JenisPerangkatAjar $jenisPerangkatAjar)
    {
        return view('jenis-perangkat-ajar.show', compact('jenisPerangkatAjar'));
    }

    public function edit(JenisPerangkatAjar $jenisPerangkatAjar)
    {
        return view('jenis-perangkat-ajar.edit', compact('jenisPerangkatAjar'));
    }

    public function update(Request $request, JenisPerangkatAjar $jenisPerangkatAjar)
    {
        $request->merge(['kode' => $this->rapikanKode($request->input('kode'))]);
        $data = $this->rapikanData($request->validate($this->aturanValidasi($jenisPerangkatAjar)));
        $data['wajib'] = $request->boolean('wajib');
        $data['aktif'] = $request->boolean('aktif');

        $jenisPerangkatAjar->update($data);

        return redirect()
            ->route('jenis-perangkat-ajar.show', $jenisPerangkatAjar)
            ->with('berhasil', 'Jenis perangkat ajar berhasil diperbarui.');
    }

    public function destroy(JenisPerangkatAjar $jenisPerangkatAjar)
    {
        $jenisPerangkatAjar->update(['aktif' => false]);

        return redirect()
            ->route('jenis-perangkat-ajar.index')
            ->with('berhasil', 'Jenis perangkat ajar berhasil dinonaktifkan.');
    }

    private function aturanValidasi(?JenisPerangkatAjar $jenisPerangkatAjar = null): array
    {
        return [
            'nama' => [
                'required',
                'string',
                'max:120',
                Rule::unique('jenis_perangkat_ajar', 'nama')->ignore($jenisPerangkatAjar),
            ],
            'kode' => [
                'required',
                'string',
                'max:40',
                Rule::unique('jenis_perangkat_ajar', 'kode')->ignore($jenisPerangkatAjar),
            ],
            'deskripsi' => ['nullable', 'string'],
            'wajib' => ['nullable', 'boolean'],
            'urutan' => ['nullable', 'integer', 'min:0', 'max:999'],
            'aktif' => ['nullable', 'boolean'],
        ];
    }

    private function rapikanData(array $data): array
    {
        $data['nama'] = trim($data['nama']);
        $data['kode'] = $this->rapikanKode($data['kode']);
        $data['deskripsi'] = filled($data['deskripsi'] ?? null)
            ? trim($data['deskripsi'])
            : null;
        $data['urutan'] = (int) ($data['urutan'] ?? 0);

        return $data;
    }

    private function rapikanKode(mixed $kode): string
    {
        return str((string) $kode)
            ->trim()
            ->upper()
            ->replaceMatches('/[^A-Z0-9]+/', '_')
            ->trim('_')
            ->toString();
    }
}
