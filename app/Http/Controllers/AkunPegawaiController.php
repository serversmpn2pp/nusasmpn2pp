<?php

namespace App\Http\Controllers;

use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AkunPegawaiController extends Controller
{
    public function index(Request $request)
    {
        $kataKunci = $request->kata_kunci;
        $statusAkun = $request->input('status_akun', 'semua');

        if (! in_array($statusAkun, ['semua', 'sudah', 'belum', 'tanpa_nip'], true)) {
            $statusAkun = 'semua';
        }

        $pegawai = Pegawai::query()
            ->with('pengguna.daftarPeran')
            ->when($kataKunci, function ($query, $kataKunci) {
                $query->where(function ($query) use ($kataKunci) {
                    $query->where('nama_lengkap', 'ilike', '%' . $kataKunci . '%')
                        ->orWhere('nip', 'ilike', '%' . $kataKunci . '%')
                        ->orWhere('jabatan_utama', 'ilike', '%' . $kataKunci . '%');
                });
            })
            ->when($statusAkun === 'sudah', function ($query) {
                $query->whereHas('pengguna');
            })
            ->when($statusAkun === 'belum', function ($query) {
                $query->whereDoesntHave('pengguna')
                    ->whereNotNull('nip')
                    ->where('nip', '<>', '');
            })
            ->when($statusAkun === 'tanpa_nip', function ($query) {
                $query->where(function ($query) {
                    $query->whereNull('nip')->orWhere('nip', '');
                });
            })
            ->orderBy('nama_lengkap')
            ->paginate(12)
            ->withQueryString();

        $ringkasan = [
            'pegawai_aktif' => Pegawai::where('aktif', true)->count(),
            'punya_nip' => Pegawai::whereNotNull('nip')->where('nip', '<>', '')->count(),
            'akun_pegawai' => Pengguna::whereNotNull('pegawai_id')->count(),
            'belum_akun' => Pegawai::where('aktif', true)
                ->whereNotNull('nip')
                ->where('nip', '<>', '')
                ->whereDoesntHave('pengguna')
                ->count(),
        ];
        $daftarPeran = Peran::query()
            ->where('aktif', true)
            ->orderByDesc('sistem')
            ->orderBy('nama')
            ->get();

        return view('akun-pegawai.index', compact('pegawai', 'kataKunci', 'statusAkun', 'ringkasan', 'daftarPeran'));
    }

    public function store(Pegawai $pegawai)
    {
        if ($pegawai->pengguna) {
            return back()->with('berhasil', 'Pegawai ini sudah memiliki akun.');
        }

        $username = $this->usernameDariNip($pegawai->nip);

        if (! $username) {
            return back()->with('gagal', 'Akun belum bisa dibuat karena NIP pegawai masih kosong.');
        }

        if (Pengguna::where('username', $username)->exists()) {
            return back()->with('gagal', 'Username ' . $username . ' sudah dipakai akun lain.');
        }

        $pengguna = Pengguna::create([
            'pegawai_id' => $pegawai->id,
            'nama' => $pegawai->nama_lengkap,
            'username' => $username,
            'kata_sandi' => Hash::make(config('nusa.kata_sandi_default_pegawai')),
            'peran' => 'pegawai',
            'aktif' => $pegawai->aktif,
            'akun_sistem' => false,
        ]);
        $this->pasangPeranPegawai($pengguna);

        return back()->with('berhasil', 'Akun pegawai berhasil dibuat. Username: ' . $username . '.');
    }

    public function storeMassal()
    {
        $ringkasan = [
            'dibuat' => 0,
            'dilewati' => 0,
            'catatan' => [],
        ];

        Pegawai::query()
            ->where('aktif', true)
            ->whereNotNull('nip')
            ->where('nip', '<>', '')
            ->whereDoesntHave('pengguna')
            ->orderBy('nama_lengkap')
            ->get()
            ->each(function (Pegawai $pegawai) use (&$ringkasan) {
                $username = $this->usernameDariNip($pegawai->nip);

                if (! $username) {
                    $ringkasan['dilewati']++;
                    $ringkasan['catatan'][] = $pegawai->nama_lengkap . ' dilewati karena NIP kosong.';

                    return;
                }

                if (Pengguna::where('username', $username)->exists()) {
                    $ringkasan['dilewati']++;
                    $ringkasan['catatan'][] = $pegawai->nama_lengkap . ' dilewati karena username ' . $username . ' sudah dipakai.';

                    return;
                }

                $pengguna = Pengguna::create([
                    'pegawai_id' => $pegawai->id,
                    'nama' => $pegawai->nama_lengkap,
                    'username' => $username,
                    'kata_sandi' => Hash::make(config('nusa.kata_sandi_default_pegawai')),
                    'peran' => 'pegawai',
                    'aktif' => true,
                    'akun_sistem' => false,
                ]);
                $this->pasangPeranPegawai($pengguna);

                $ringkasan['dibuat']++;
            });

        return back()
            ->with('berhasil', 'Pembuatan akun pegawai selesai.')
            ->with('ringkasan_akun_pegawai', $ringkasan);
    }

    public function resetPassword(Pengguna $pengguna)
    {
        abort_if($pengguna->akun_sistem, 403);

        $pengguna->forceFill([
            'kata_sandi' => Hash::make(config('nusa.kata_sandi_default_pegawai')),
        ])->save();

        return back()->with('berhasil', 'Kata sandi akun ' . $pengguna->nama . ' berhasil direset ke default.');
    }

    public function ubahStatus(Pengguna $pengguna)
    {
        abort_if($pengguna->akun_sistem, 403);

        $pengguna->update([
            'aktif' => ! $pengguna->aktif,
        ]);

        return back()->with('berhasil', 'Status akun ' . $pengguna->nama . ' berhasil diperbarui.');
    }

    public function updatePeran(Request $request, Pengguna $pengguna)
    {
        abort_if($pengguna->akun_sistem, 403);

        $data = $request->validate([
            'peran_ids' => ['nullable', 'array'],
            'peran_ids.*' => ['integer', Rule::exists('peran', 'id')->where('aktif', true)],
        ]);

        $peranIds = collect($data['peran_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter();
        $peranPegawai = Peran::where('kode', 'pegawai')->first();

        if ($peranPegawai) {
            $peranIds->push($peranPegawai->id);
        }

        $pengguna->daftarPeran()->sync($peranIds->unique()->values()->all());

        return back()->with('berhasil', 'Role akun ' . $pengguna->nama . ' berhasil diperbarui.');
    }

    private function usernameDariNip(?string $nip): ?string
    {
        $username = preg_replace('/\s+/', '', trim((string) $nip));

        return $username === '' ? null : $username;
    }

    private function pasangPeranPegawai(Pengguna $pengguna): void
    {
        $peranPegawai = Peran::where('kode', 'pegawai')->first();

        if ($peranPegawai) {
            $pengguna->daftarPeran()->syncWithoutDetaching([$peranPegawai->id]);
        }
    }
}
