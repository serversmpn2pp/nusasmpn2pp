<?php

namespace App\Services\Humas;

use App\Models\DokumenHumas;
use App\Models\KontakHumas;
use App\Models\Pengguna;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DirektoriKontakHumasService
{
    public function akses(Pengguna $u, bool $kelola = false): void
    {
        abort_unless($u->aktif && ! $u->akunSiswa() && ! $u->akunOrangTua()
            && $u->memilikiIzin($kelola ? 'kontak_humas.kelola' : KontakHumas::IZIN), 403);
    }

    public function rinci(Pengguna $u): bool
    {
        return $u->memilikiIzin(['kontak_humas.kontak', 'kontak_humas.kelola']);
    }

    public function daftar(Request $r)
    {
        $this->akses($r->user());
        $filter = $r->validate(['kata_kunci' => ['nullable', 'string', 'max:120'], 'urusan' => ['nullable', Rule::in(array_keys(DokumenHumas::URUSAN))],
            'status' => ['nullable', Rule::in(array_keys(KontakHumas::STATUS))], 'per_halaman' => ['nullable', 'integer', 'min:1', 'max:50'], 'halaman' => ['nullable', 'integer', 'min:1']]);
        $q = KontakHumas::when($filter['kata_kunci'] ?? null, fn ($q, $kata) => $q->where(fn ($q) => $q->whereLike('nama', '%'.$kata.'%')
            ->orWhereLike('instansi', '%'.$kata.'%')->orWhereLike('jabatan', '%'.$kata.'%')));
        foreach (['urusan', 'status'] as $kolom) {
            $q->when($filter[$kolom] ?? null, fn ($q, $v) => $q->where($kolom, $v));
        }

        return [$q->orderBy('nama')->orderBy('id')->paginate($filter['per_halaman'] ?? 20, ['*'], 'halaman', $filter['halaman'] ?? 1)->withQueryString(), $filter];
    }

    public function data(KontakHumas $k, Pengguna $u): array
    {
        $this->akses($u);

        return $k->only(['id', 'nama', 'instansi', 'jabatan', 'urusan', 'status', 'versi']) + ['urusan_label' => DokumenHumas::URUSAN[$k->urusan],
            'kontak_terbatas' => ! $this->rinci($u), 'nomor_kontak' => $this->rinci($u) ? $k->nomor_kontak : null,
            'email' => $this->rinci($u) ? $k->email : null, 'dasar_penggunaan' => $this->rinci($u) ? $k->dasar_penggunaan : null,
            'catatan' => $this->rinci($u) ? $k->catatan : null];
    }

    public function simpan(Request $r, ?KontakHumas $kontak = null): KontakHumas
    {
        $this->akses($r->user(), true);
        $data = $r->validate(['nama' => ['required', 'string', 'max:180'], 'instansi' => ['nullable', 'string', 'max:180'], 'jabatan' => ['nullable', 'string', 'max:120'],
            'urusan' => ['required', Rule::in(array_keys(DokumenHumas::URUSAN))], 'nomor_kontak' => ['nullable', 'string', 'max:32', 'regex:/^\+?[0-9][0-9\s().-]{5,30}$/D'],
            'email' => ['nullable', 'email', 'max:180'], 'dasar_penggunaan' => ['required', 'string', 'min:5', 'max:1000'], 'catatan' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(array_keys(KontakHumas::STATUS))], 'alasan' => [$kontak ? 'required' : 'nullable', 'string', 'min:5', 'max:1000'],
            'versi' => [$kontak ? 'required' : 'prohibited', 'integer', 'min:0'], 'token_pembuatan' => [$kontak ? 'prohibited' : 'required', 'uuid']]);

        return DB::transaction(function () use ($data, $kontak, $r) {
            if (! $kontak) {
                DB::table('izin')->where('kode', 'kontak_humas.kelola')->lockForUpdate()->first();
                $lama = KontakHumas::where('token_pembuatan', $data['token_pembuatan'])->first();
                if ($lama) {
                    abort_unless($lama->dibuat_oleh_pengguna_id === $r->user()->id, 403);

                    return $lama;
                }
            }
            $k = $kontak ? KontakHumas::lockForUpdate()->findOrFail($kontak->id) : new KontakHumas;
            if ($kontak && $k->versi !== (int) $data['versi']) {
                throw ValidationException::withMessages(['versi' => 'Kontak sudah berubah. Muat ulang sebelum menyimpan.']);
            }
            $k->fill(Arr::only($data, ['token_pembuatan', ...KontakHumas::KOLOM]));
            $kolom = array_keys($k->getDirty());
            $k->forceFill(['versi' => $kontak ? $k->versi + 1 : 0, 'diubah_oleh_pengguna_id' => $r->user()->id]
                + ($kontak ? [] : ['dibuat_oleh_pengguna_id' => $r->user()->id]))->save();
            DB::table('riwayat_kontak_humas')->insert(['kontak_humas_id' => $k->id, 'aksi' => $kontak ? 'diperbarui' : 'dibuat',
                'kolom_berubah' => json_encode(array_values(array_diff($kolom, ['token_pembuatan']))), 'alasan' => $data['alasan'] ?? 'Kontak dicatat untuk keperluan Humas.',
                'pengguna_id' => $r->user()->id, 'created_at' => now()]);

            return $k;
        });
    }
}
