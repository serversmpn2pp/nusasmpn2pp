<?php

namespace App\Http\Controllers;

use App\Models\Pengguna;
use App\Models\RiwayatLogin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AktivitasLoginController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate([
            'tampilan' => ['nullable', Rule::in(['pengguna', 'riwayat'])],
            'kata_kunci' => ['nullable', 'string', 'max:100'],
            'jenis_akun' => ['nullable', Rule::in(['semua', 'administrator', 'pegawai', 'siswa', 'orang_tua'])],
            'status_login' => ['nullable', Rule::in(['semua', 'pernah', 'belum'])],
            'status_percobaan' => ['nullable', Rule::in(['semua', 'berhasil', 'gagal'])],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
        ]);

        $tampilan = $data['tampilan'] ?? 'pengguna';
        $kataKunci = trim((string) ($data['kata_kunci'] ?? ''));
        $jenisAkun = $data['jenis_akun'] ?? 'semua';
        $statusLogin = $data['status_login'] ?? 'semua';
        $statusPercobaan = $data['status_percobaan'] ?? 'semua';
        $tanggalMulai = $data['tanggal_mulai'] ?? null;
        $tanggalSelesai = $data['tanggal_selesai'] ?? null;

        $daftarPengguna = null;
        $daftarRiwayat = null;

        if ($tampilan === 'riwayat') {
            $daftarRiwayat = RiwayatLogin::query()
                ->with(['pengguna.daftarPeran', 'pengguna.orangTuaWali'])
                ->when($kataKunci !== '', function (Builder $query) use ($kataKunci) {
                    $query->where(function (Builder $query) use ($kataKunci) {
                        $query->whereLike('username', '%'.$kataKunci.'%', caseSensitive: false)
                            ->orWhereHas('pengguna', fn (Builder $pengguna) => $pengguna
                                ->whereLike('nama', '%'.$kataKunci.'%', caseSensitive: false));
                    });
                })
                ->when($jenisAkun !== 'semua', function (Builder $query) use ($jenisAkun) {
                    $query->whereHas('pengguna', fn (Builder $pengguna) => $this->terapkanJenisAkun($pengguna, $jenisAkun));
                })
                ->when($statusPercobaan === 'berhasil', fn (Builder $query) => $query->where('berhasil', true))
                ->when($statusPercobaan === 'gagal', fn (Builder $query) => $query->where('berhasil', false))
                ->when($tanggalMulai, fn (Builder $query) => $query->whereDate('created_at', '>=', $tanggalMulai))
                ->when($tanggalSelesai, fn (Builder $query) => $query->whereDate('created_at', '<=', $tanggalSelesai))
                ->latest('created_at')
                ->latest('id')
                ->paginate(20)
                ->withQueryString();
        } else {
            $daftarPengguna = Pengguna::query()
                ->with(['daftarPeran', 'orangTuaWali', 'loginBerhasilTerbaru'])
                ->withCount([
                    'riwayatLogin as jumlah_login_berhasil' => fn (Builder $query) => $query->where('berhasil', true),
                    'riwayatLogin as jumlah_login_gagal' => fn (Builder $query) => $query->where('berhasil', false),
                ])
                ->when($kataKunci !== '', function (Builder $query) use ($kataKunci) {
                    $query->where(function (Builder $query) use ($kataKunci) {
                        $query->whereLike('nama', '%'.$kataKunci.'%', caseSensitive: false)
                            ->orWhereLike('username', '%'.$kataKunci.'%', caseSensitive: false);
                    });
                })
                ->when($jenisAkun !== 'semua', fn (Builder $query) => $this->terapkanJenisAkun($query, $jenisAkun))
                ->when($statusLogin === 'pernah', fn (Builder $query) => $query->whereNotNull('terakhir_login_pada'))
                ->when($statusLogin === 'belum', fn (Builder $query) => $query->whereNull('terakhir_login_pada'))
                ->orderByRaw('terakhir_login_pada IS NULL')
                ->orderByDesc('terakhir_login_pada')
                ->orderBy('nama')
                ->paginate(20)
                ->withQueryString();
        }

        return view('aktivitas-login.index', [
            'tampilan' => $tampilan,
            'kataKunci' => $kataKunci,
            'jenisAkun' => $jenisAkun,
            'statusLogin' => $statusLogin,
            'statusPercobaan' => $statusPercobaan,
            'tanggalMulai' => $tanggalMulai,
            'tanggalSelesai' => $tanggalSelesai,
            'daftarPengguna' => $daftarPengguna,
            'daftarRiwayat' => $daftarRiwayat,
            'ringkasan' => [
                'jumlah_akun' => Pengguna::query()->count(),
                'login_hari_ini' => Pengguna::query()->whereDate('terakhir_login_pada', today())->count(),
                'belum_pernah_login' => Pengguna::query()->whereNull('terakhir_login_pada')->count(),
                'gagal_hari_ini' => RiwayatLogin::query()
                    ->where('berhasil', false)
                    ->whereDate('created_at', today())
                    ->count(),
            ],
            'daftarJenisAkun' => [
                'semua' => 'Semua jenis akun',
                'administrator' => 'Administrator sistem',
                'pegawai' => 'Pegawai',
                'siswa' => 'Siswa',
                'orang_tua' => 'Orang tua',
            ],
        ]);
    }

    private function terapkanJenisAkun(Builder $query, string $jenisAkun): Builder
    {
        return match ($jenisAkun) {
            'administrator' => $query->where('akun_sistem', true),
            'pegawai' => $query->whereNotNull('pegawai_id')->where('akun_sistem', false),
            'siswa' => $query->whereNotNull('siswa_id')->where('akun_sistem', false),
            'orang_tua' => $query
                ->whereNull('pegawai_id')
                ->whereNull('siswa_id')
                ->where('akun_sistem', false)
                ->whereHas('orangTuaWali'),
            default => $query,
        };
    }
}
