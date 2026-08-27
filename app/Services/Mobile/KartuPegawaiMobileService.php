<?php

namespace App\Services\Mobile;

use App\Models\Pegawai;
use App\Models\Pengguna;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;

class KartuPegawaiMobileService
{
    public function daftar(Pengguna $pengguna, array $filter): array
    {
        $status = $filter['status'] ?? 'aktif';
        $jenisPegawai = trim((string) ($filter['jenis_pegawai'] ?? ''));
        $cari = trim((string) ($filter['cari'] ?? ''));
        $pegawaiId = (int) ($filter['pegawai_id'] ?? 0);
        $daftarJenisPegawai = Pegawai::query()
            ->whereNotNull('jenis_pegawai')
            ->where('jenis_pegawai', '<>', '')
            ->distinct()
            ->orderBy('jenis_pegawai')
            ->pluck('jenis_pegawai');

        if ($jenisPegawai !== '' && ! $daftarJenisPegawai->contains($jenisPegawai)) {
            $jenisPegawai = '';
        }

        $dasar = Pegawai::query()
            ->when($status === 'aktif', fn (Builder $query) => $query->where('aktif', true))
            ->when($status === 'nonaktif', fn (Builder $query) => $query->where('aktif', false))
            ->when($jenisPegawai !== '', fn (Builder $query) => $query->where('jenis_pegawai', $jenisPegawai))
            ->when($pegawaiId > 0, fn (Builder $query) => $query->whereKey($pegawaiId));
        $ringkasan = $this->ringkasan($dasar);
        $paginator = (clone $dasar)
            ->when($cari !== '', function (Builder $query) use ($cari) {
                $pola = '%'.mb_strtolower($cari).'%';
                $query->where(function (Builder $query) use ($pola) {
                    $query->whereRaw('LOWER(nama_lengkap) LIKE ?', [$pola])
                        ->orWhereRaw("LOWER(COALESCE(nip, '')) LIKE ?", [$pola])
                        ->orWhereRaw("LOWER(COALESCE(jabatan_utama, '')) LIKE ?", [$pola]);
                });
            })
            ->orderBy('nama_lengkap')
            ->paginate(
                (int) ($filter['per_halaman'] ?? 12),
                ['*'],
                'halaman',
                (int) ($filter['halaman'] ?? 1),
            );

        return [
            'items' => collect($paginator->items())
                ->map(fn (Pegawai $pegawai) => $this->kartu($pegawai))
                ->values(),
            'ringkasan' => $ringkasan,
            'pilihan_jenis_pegawai' => $daftarJenisPegawai->values(),
            'filter' => [
                'status' => $status,
                'jenis_pegawai' => $jenisPegawai,
                'pegawai_id' => $pegawaiId ?: null,
                'cari' => $cari,
            ],
            'paginasi' => $this->paginasi($paginator),
            'ukuran_kartu' => [
                'lebar_mm' => 53.98,
                'tinggi_mm' => 85.60,
                'orientasi' => 'portrait',
            ],
            'hak_akses' => [
                'dapat_kelola_foto' => $pengguna->memilikiIzin('pegawai.kelola'),
            ],
        ];
    }

    private function kartu(Pegawai $pegawai): array
    {
        $nip = trim((string) ($pegawai->nip ?? ''));
        $fotoTersedia = filled($pegawai->foto)
            && Storage::disk('public')->exists($pegawai->foto);

        return [
            'id' => (int) $pegawai->id,
            'nama' => $pegawai->nama_lengkap,
            'nip' => $nip !== '' ? $nip : null,
            'jenis_pegawai' => $pegawai->jenis_pegawai,
            'jabatan' => $pegawai->jabatan_utama
                ?: $pegawai->jenis_pegawai
                ?: $pegawai->status_kepegawaian
                ?: 'Pegawai',
            'foto_url' => $fotoTersedia ? asset('storage/'.$pegawai->foto) : null,
            'punya_foto' => $fotoTersedia,
            'aktif' => (bool) $pegawai->aktif,
            'qr_data' => preg_match('/^[0-9]{1,41}$/', $nip) === 1 ? $nip : null,
            'qr_bisa_dibuat' => preg_match('/^[0-9]{1,41}$/', $nip) === 1,
        ];
    }

    private function ringkasan(Builder $query): array
    {
        $total = (clone $query)->count();
        $siapQr = (clone $query)
            ->whereNotNull('nip')
            ->where('nip', '<>', '')
            ->get(['nip'])
            ->filter(fn (Pegawai $pegawai) => preg_match('/^[0-9]{1,41}$/', trim((string) $pegawai->nip)) === 1)
            ->count();
        $denganFoto = (clone $query)
            ->whereNotNull('foto')
            ->where('foto', '<>', '')
            ->get(['foto'])
            ->filter(fn (Pegawai $pegawai) => Storage::disk('public')->exists($pegawai->foto))
            ->count();

        return [
            'total' => $total,
            'siap_qr' => $siapQr,
            'dengan_foto' => $denganFoto,
        ];
    }

    private function paginasi(LengthAwarePaginator $paginator): array
    {
        return [
            'halaman' => $paginator->currentPage(),
            'halaman_terakhir' => $paginator->lastPage(),
            'per_halaman' => $paginator->perPage(),
            'total' => $paginator->total(),
            'ada_halaman_berikutnya' => $paginator->hasMorePages(),
        ];
    }
}
