<?php

namespace App\Http\Controllers;

use App\Models\JenisUjianCbt;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class JenisUjianCbtController extends Controller
{
    public function index(Request $request)
    {
        $kataKunci = trim((string) $request->input('kata_kunci', ''));
        $status = $request->input('status', 'semua');

        if (! in_array($status, ['semua', 'aktif', 'nonaktif'], true)) {
            $status = 'semua';
        }

        $jenisUjianCbt = JenisUjianCbt::query()
            ->when($status === 'aktif', fn ($query) => $query->where('aktif', true))
            ->when($status === 'nonaktif', fn ($query) => $query->where('aktif', false))
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

        return view('jenis-ujian-cbt.index', [
            'jenisUjianCbt' => $jenisUjianCbt,
            'kataKunci' => $kataKunci,
            'status' => $status,
            'jumlahJenis' => JenisUjianCbt::count(),
            'jumlahAktif' => JenisUjianCbt::where('aktif', true)->count(),
            'jumlahToken' => JenisUjianCbt::where('memerlukan_token', true)->count(),
        ]);
    }

    public function create()
    {
        return view('jenis-ujian-cbt.create');
    }

    public function store(Request $request)
    {
        $request->merge(['kode' => $this->rapikanKode($request->input('kode'))]);
        $data = $this->rapikanData($request->validate($this->aturanValidasi()));
        $data['memerlukan_token'] = $request->boolean('memerlukan_token');
        $data['dapat_diterapkan_ke_nilai'] = $request->boolean('dapat_diterapkan_ke_nilai');
        $data['tampil_di_kartu_peserta'] = $request->boolean('tampil_di_kartu_peserta');
        $data['aktif'] = $request->boolean('aktif');

        $jenisUjianCbt = JenisUjianCbt::create($data);

        return redirect()
            ->route('jenis-ujian-cbt.show', $jenisUjianCbt)
            ->with('berhasil', 'Jenis ujian CBT berhasil ditambahkan.');
    }

    public function show(JenisUjianCbt $jenisUjianCbt)
    {
        return view('jenis-ujian-cbt.show', compact('jenisUjianCbt'));
    }

    public function edit(JenisUjianCbt $jenisUjianCbt)
    {
        return view('jenis-ujian-cbt.edit', compact('jenisUjianCbt'));
    }

    public function update(Request $request, JenisUjianCbt $jenisUjianCbt)
    {
        $request->merge(['kode' => $this->rapikanKode($request->input('kode'))]);
        $data = $this->rapikanData($request->validate($this->aturanValidasi($jenisUjianCbt)));
        $data['memerlukan_token'] = $request->boolean('memerlukan_token');
        $data['dapat_diterapkan_ke_nilai'] = $request->boolean('dapat_diterapkan_ke_nilai');
        $data['tampil_di_kartu_peserta'] = $request->boolean('tampil_di_kartu_peserta');
        $data['aktif'] = $request->boolean('aktif');

        $jenisUjianCbt->update($data);

        return redirect()
            ->route('jenis-ujian-cbt.show', $jenisUjianCbt)
            ->with('berhasil', 'Jenis ujian CBT berhasil diperbarui.');
    }

    public function destroy(JenisUjianCbt $jenisUjianCbt)
    {
        $jenisUjianCbt->update(['aktif' => false]);

        return redirect()
            ->route('jenis-ujian-cbt.index')
            ->with('berhasil', 'Jenis ujian CBT berhasil dinonaktifkan.');
    }

    private function aturanValidasi(?JenisUjianCbt $jenisUjianCbt = null): array
    {
        return [
            'nama' => [
                'required',
                'string',
                'max:120',
                Rule::unique('jenis_ujian_cbt', 'nama')->ignore($jenisUjianCbt),
            ],
            'kode' => [
                'required',
                'string',
                'max:40',
                Rule::unique('jenis_ujian_cbt', 'kode')->ignore($jenisUjianCbt),
            ],
            'deskripsi' => ['nullable', 'string'],
            'memerlukan_token' => ['nullable', 'boolean'],
            'dapat_diterapkan_ke_nilai' => ['nullable', 'boolean'],
            'tampil_di_kartu_peserta' => ['nullable', 'boolean'],
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
