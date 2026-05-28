<?php

namespace App\Http\Controllers;

use App\Models\Izin;
use App\Models\Peran;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PeranController extends Controller
{
    public function index(Request $request)
    {
        $kataKunci = $request->kata_kunci;
        $status = $request->input('status', 'semua');

        if (! in_array($status, ['semua', 'aktif', 'nonaktif'], true)) {
            $status = 'semua';
        }

        $peran = Peran::query()
            ->withCount([
                'pengguna',
                'izin' => fn ($query) => $query->where('izin.aktif', true),
            ])
            ->when($kataKunci, function ($query, $kataKunci) {
                $query->where(function ($query) use ($kataKunci) {
                    $query->where('nama', 'ilike', '%' . $kataKunci . '%')
                        ->orWhere('kode', 'ilike', '%' . $kataKunci . '%')
                        ->orWhere('deskripsi', 'ilike', '%' . $kataKunci . '%');
                });
            })
            ->when($status === 'aktif', fn ($query) => $query->where('aktif', true))
            ->when($status === 'nonaktif', fn ($query) => $query->where('aktif', false))
            ->orderByDesc('sistem')
            ->orderBy('nama')
            ->paginate(12)
            ->withQueryString();

        $ringkasan = [
            'total' => Peran::count(),
            'aktif' => Peran::where('aktif', true)->count(),
            'sistem' => Peran::where('sistem', true)->count(),
            'tambahan' => Peran::where('sistem', false)->count(),
            'izin_aktif' => Izin::where('aktif', true)->count(),
            'pengguna_terhubung' => DB::table('pengguna_peran')
                ->distinct('pengguna_id')
                ->count('pengguna_id'),
        ];

        return view('peran.index', compact('peran', 'kataKunci', 'status', 'ringkasan'));
    }

    public function create()
    {
        return view('peran.create', [
            'peran' => new Peran([
                'aktif' => true,
            ]),
            'daftarIzin' => $this->daftarIzinForm(),
            'izinDipilih' => collect(old('izin_ids', []))->map(fn ($id) => (int) $id)->all(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:120'],
            'kode' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9_\\-]+$/'],
            'deskripsi' => ['nullable', 'string'],
            'aktif' => ['nullable', 'boolean'],
            'izin_ids' => ['nullable', 'array'],
            'izin_ids.*' => ['integer', Rule::exists('izin', 'id')->where('aktif', true)],
        ], [
            'kode.regex' => 'Kode hanya boleh berisi huruf kecil, angka, garis bawah, atau tanda hubung.',
        ]);

        $kode = $this->buatKode($data['kode'] ?? null, $data['nama']);

        if (Peran::where('kode', $kode)->exists()) {
            throw ValidationException::withMessages([
                'kode' => 'Kode peran sudah digunakan.',
            ]);
        }

        $peran = Peran::create([
            'nama' => $data['nama'],
            'kode' => $kode,
            'deskripsi' => $data['deskripsi'] ?? null,
            'sistem' => false,
            'aktif' => $request->boolean('aktif'),
        ]);
        $peran->izin()->sync($data['izin_ids'] ?? []);

        return redirect()
            ->route('peran.index')
            ->with('berhasil', 'Peran baru berhasil ditambahkan.');
    }

    public function edit(Peran $peran)
    {
        $peran->load('izin');

        return view('peran.edit', [
            'peran' => $peran,
            'daftarIzin' => $this->daftarIzinForm(),
            'izinDipilih' => collect(old('izin_ids', $peran->izin->pluck('id')->all()))->map(fn ($id) => (int) $id)->all(),
        ]);
    }

    public function update(Request $request, Peran $peran)
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:120'],
            'kode' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9_\\-]+$/'],
            'deskripsi' => ['nullable', 'string'],
            'aktif' => ['nullable', 'boolean'],
            'izin_ids' => ['nullable', 'array'],
            'izin_ids.*' => ['integer', Rule::exists('izin', 'id')->where('aktif', true)],
        ], [
            'kode.regex' => 'Kode hanya boleh berisi huruf kecil, angka, garis bawah, atau tanda hubung.',
        ]);

        $kode = $peran->sistem
            ? $peran->kode
            : $this->buatKode($data['kode'] ?? null, $data['nama']);

        if (Peran::where('kode', $kode)->whereKeyNot($peran->id)->exists()) {
            throw ValidationException::withMessages([
                'kode' => 'Kode peran sudah digunakan.',
            ]);
        }

        $peran->update([
            'nama' => $data['nama'],
            'kode' => $kode,
            'deskripsi' => $data['deskripsi'] ?? null,
            'aktif' => $peran->sistem ? true : $request->boolean('aktif'),
        ]);
        $peran->izin()->sync($this->izinUntukDisimpan($peran, $data['izin_ids'] ?? []));

        return redirect()
            ->route('peran.index')
            ->with('berhasil', 'Peran berhasil diperbarui.');
    }

    public function destroy(Peran $peran)
    {
        if ($peran->sistem) {
            return back()->with('gagal', 'Peran sistem tidak dapat dinonaktifkan.');
        }

        $peran->update([
            'aktif' => false,
        ]);

        return back()->with('berhasil', 'Peran berhasil dinonaktifkan.');
    }

    private function buatKode(?string $kode, string $nama): string
    {
        $sumber = filled($kode) ? $kode : $nama;

        return str($sumber)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->toString();
    }

    private function daftarIzinForm()
    {
        $urutanKelompok = [
            'Umum',
            'Akun',
            'Pegawai',
            'Siswa',
            'Akademik',
            'Nilai',
            'Absensi',
            'Laporan',
            'BK',
            'Kurikulum',
            'Sarpras',
            'Keamanan',
            'Kebersihan',
        ];
        $urutanKelompokMap = array_flip($urutanKelompok);

        return Izin::query()
            ->where('aktif', true)
            ->orderBy('nama')
            ->get()
            ->groupBy('kelompok')
            ->sortBy(fn ($izin, $kelompok) => $urutanKelompokMap[$kelompok] ?? 999);
    }

    private function izinUntukDisimpan(Peran $peran, array $izinIds): array
    {
        if ($peran->kode === 'administrator') {
            return Izin::where('aktif', true)->pluck('id')->all();
        }

        return $izinIds;
    }
}
