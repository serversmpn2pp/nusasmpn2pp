<?php

namespace App\Services\Humas;

use App\Models\DokumenHumas;
use App\Models\FaqHumas;
use App\Models\Pengguna;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FaqHumasService
{
    public function kelola(Pengguna $u): bool
    {
        return $u->aktif && ! $u->akunSiswa() && ! $u->akunOrangTua() && $u->memilikiIzin('faq_humas.kelola');
    }

    public function query(Pengguna $u)
    {
        abort_unless($u->aktif, 403);
        $q = FaqHumas::query();
        if (! $this->kelola($u)) {
            $sasaran = ['semua'];
            if ($u->akunOrangTua()) {
                $sasaran[] = 'orang_tua';
            } elseif ($u->akunSiswa()) {
                $sasaran[] = 'siswa';
            } elseif ($u->akunPegawai() || $u->administrator()) {
                $sasaran[] = 'pegawai';
            }
            $q->where('status', 'terbit')->whereNotNull('diterbitkan_pada')->whereIn('sasaran', $sasaran);
        }

        return $q;
    }

    public function data(FaqHumas $f): array
    {
        return $f->only(['id', ...FaqHumas::KOLOM, 'status', 'versi']) + ['diterbitkan_pada' => $f->diterbitkan_pada?->toIso8601String()];
    }

    public function simpan(Request $r, ?FaqHumas $faq = null): FaqHumas
    {
        abort_unless($this->kelola($r->user()), 403);
        $data = $r->validate(['pertanyaan' => ['required', 'string', 'max:240'], 'jawaban' => ['required', 'string', 'min:5', 'max:10000'],
            'urusan' => ['required', Rule::in(array_keys(DokumenHumas::URUSAN))], 'sasaran' => ['required', Rule::in(array_keys(FaqHumas::SASARAN))],
            'urutan' => ['required', 'integer', 'min:0', 'max:999'], 'token_pembuatan' => [$faq ? 'prohibited' : 'required', 'uuid'],
            'versi' => [$faq ? 'required' : 'prohibited', 'integer', 'min:0'], 'alasan' => [$faq ? 'required' : 'nullable', 'string', 'min:5', 'max:1000']]);

        return DB::transaction(function () use ($r, $data, $faq) {
            if (! $faq) {
                DB::table('izin')->where('kode', 'faq_humas.kelola')->lockForUpdate()->first();
                $lama = FaqHumas::where('token_pembuatan', $data['token_pembuatan'])->first();
                if ($lama) {
                    abort_unless($lama->dibuat_oleh_pengguna_id === $r->user()->id, 403);

                    return $lama;
                }
            }
            $f = $faq ? FaqHumas::lockForUpdate()->findOrFail($faq->id) : new FaqHumas;
            if ($faq) {
                $this->versi($f, (int) $data['versi']);
            }
            $f->fill(Arr::only($data, ['token_pembuatan', ...FaqHumas::KOLOM]));
            $f->forceFill(['status' => 'draf', 'diterbitkan_pada' => null, 'versi' => $faq ? $f->versi + 1 : 0,
                'diubah_oleh_pengguna_id' => $r->user()->id] + ($faq ? [] : ['dibuat_oleh_pengguna_id' => $r->user()->id]))->save();
            $this->catat($f, $faq ? 'draf_diperbarui' : 'draf_dibuat', $data['alasan'] ?? 'FAQ dicatat sebagai draf.', $r->user());

            return $f;
        });
    }

    public function status(Request $r, FaqHumas $faq): FaqHumas
    {
        abort_unless($this->kelola($r->user()), 403);
        $data = $r->validate(['versi' => ['required', 'integer', 'min:0'], 'status' => ['required', Rule::in(array_keys(FaqHumas::STATUS))],
            'alasan' => ['required', 'string', 'min:5', 'max:1000']]);

        return DB::transaction(function () use ($r, $faq, $data) {
            $f = FaqHumas::lockForUpdate()->findOrFail($faq->id);
            $this->versi($f, (int) $data['versi']);
            if ($f->status === $data['status']) {
                return $f;
            }
            if ($data['status'] === 'terbit' && $f->status !== 'draf') {
                throw ValidationException::withMessages(['status' => 'Kembalikan FAQ ke draf dan periksa isinya sebelum diterbitkan.']);
            }
            $f->forceFill(['status' => $data['status'], 'diterbitkan_pada' => $data['status'] === 'terbit' ? now() : null,
                'versi' => $f->versi + 1, 'diubah_oleh_pengguna_id' => $r->user()->id])->save();
            $this->catat($f, 'status_'.$data['status'], $data['alasan'], $r->user());

            return $f;
        });
    }

    private function versi(FaqHumas $f, int $versi): void
    {
        if ($f->versi !== $versi) {
            throw ValidationException::withMessages(['versi' => 'FAQ sudah berubah. Muat ulang sebelum menyimpan.']);
        }
    }

    private function catat(FaqHumas $f, string $aksi, string $alasan, Pengguna $u): void
    {
        DB::table('riwayat_faq_humas')->insert(['induk_id' => $f->id, 'aksi' => $aksi, 'versi' => $f->versi, 'alasan' => $alasan,
            'data' => json_encode($this->data($f), JSON_THROW_ON_ERROR), 'pengguna_id' => $u->id, 'created_at' => now()]);
    }
}
