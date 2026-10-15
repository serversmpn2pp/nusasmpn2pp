<?php

namespace App\Services\Supervisi;

use App\Models\InstrumenSupervisi;
use App\Models\Pengguna;
use App\Models\VersiInstrumenSupervisi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InstrumenSupervisiService
{
    public function __construct(private AksesSupervisi $akses) {}

    public function validasi(array $input): array
    {
        $d = Validator::make($input, [
            'nama' => ['required', 'string', 'max:180'], 'catatan' => ['required', 'string', 'max:3000'],
            'skala' => ['required', 'array', 'list', 'min:2', 'max:11'], 'skala.*' => ['required', 'array:skor,label'],
            'skala.*.skor' => ['required', 'integer', 'between:0,10', 'distinct'], 'skala.*.label' => ['required', 'string', 'max:120'],
            'kelompok' => ['required', 'array', 'list', 'min:2', 'max:40'], 'kelompok.*' => ['required', 'array:tahap,nama,indikator'],
            'kelompok.*.tahap' => ['required', Rule::in(['perencanaan', 'pelaksanaan'])],
            'kelompok.*.nama' => ['required', 'string', 'max:180'], 'kelompok.*.indikator' => ['required', 'array', 'list', 'min:1', 'max:150'],
            'kelompok.*.indikator.*' => ['required', 'string', 'max:1000'],
            'pertanyaan_refleksi' => ['sometimes', 'required', 'array', 'list', 'min:1', 'max:30'],
            'pertanyaan_refleksi.*' => ['required', 'string', 'max:1000', 'distinct'],
        ])->validate();
        $d['nama'] = trim($d['nama']);
        $d['catatan'] = trim($d['catatan']);
        $d['skala'] = collect($d['skala'])->map(fn ($s) => ['skor' => (int) $s['skor'], 'label' => trim($s['label'])])->sortBy('skor')->values()->all();
        if (array_column($d['skala'], 'skor') !== range(0, count($d['skala']) - 1)) {
            throw ValidationException::withMessages(['skala' => 'Skala harus berurutan dari 0, dengan minimal skor 0 dan 1.']);
        }
        if (collect($d['kelompok'])->pluck('tahap')->unique()->count() !== 2 || collect($d['kelompok'])->sum(fn ($g) => count($g['indikator'])) > 500) {
            throw ValidationException::withMessages(['kelompok' => 'Isi perencanaan dan pelaksanaan, dengan maksimal 500 indikator dalam satu instrumen.']);
        }
        $d['kelompok'] = array_map(fn ($g) => ['tahap' => $g['tahap'], 'nama' => trim($g['nama']), 'indikator' => array_map('trim', $g['indikator'])], $d['kelompok']);
        if (isset($d['pertanyaan_refleksi'])) {
            $d['pertanyaan_refleksi'] = array_map('trim', $d['pertanyaan_refleksi']);
        }

        return $d;
    }

    public function simpan(Pengguna $u, array $input, ?VersiInstrumenSupervisi $versi = null): VersiInstrumenSupervisi
    {
        $this->akses->kelola($u, true);
        $d = $this->validasi($input);
        $memuatRefleksi = array_key_exists('pertanyaan_refleksi', $input);
        $d['pertanyaan_refleksi'] ??= $versi?->pertanyaan_refleksi ?? InstrumenSupervisiAwal::pertanyaanRefleksi();
        $kontrol = Validator::make($input, ['token_pembuatan' => [$versi ? 'nullable' : 'required', 'uuid'], 'versi' => [$versi ? 'required' : 'nullable', 'integer', 'min:1']])->validate();

        return DB::transaction(function () use ($u, $d, $kontrol, $versi, $memuatRefleksi) {
            $this->akses->kunci();
            if ($versi) {
                $v = VersiInstrumenSupervisi::lockForUpdate()->findOrFail($versi->id);
                $this->akses->versi($v, $kontrol['versi']);
                if ($v->status !== 'draf') {
                    throw ValidationException::withMessages(['instrumen' => 'Versi terbit tidak dapat diubah. Buat revisi instrumen.']);
                }
                $v->update([...$d, 'versi' => $v->versi + 1, 'pengubah_id' => $u->id]);
            } else {
                $sidik = $this->akses->sidik($d);
                if ($i = InstrumenSupervisi::where('token_pembuatan', $kontrol['token_pembuatan'])->first()) {
                    $sidikLama = $this->akses->sidik(collect($d)->except('pertanyaan_refleksi')->all());
                    if (! $memuatRefleksi && is_string($i->sidik_pembuatan) && hash_equals($i->sidik_pembuatan, $sidikLama)) {
                        $sidik = $sidikLama;
                    }
                    $this->akses->pengulangan($i, $u, $sidik);

                    return $i->versiInstrumen()->where('nomor', 1)->firstOrFail();
                }
                $i = InstrumenSupervisi::create(['nama' => $d['nama'], 'token_pembuatan' => $kontrol['token_pembuatan'], 'sidik_pembuatan' => $sidik, 'pembuat_id' => $u->id, 'aktif' => true]);
                $v = $i->versiInstrumen()->create([...$d, 'nomor' => 1, 'versi' => 1, 'status' => 'draf', 'pengubah_id' => $u->id]);
            }
            $this->akses->catat($u, $v, 'Draf instrumen disimpan', $d['catatan']);

            return $v->fresh('instrumen');
        });
    }

    public function tindakan(Pengguna $u, VersiInstrumenSupervisi $versi, string $aksi, array $input): VersiInstrumenSupervisi
    {
        $this->akses->kelola($u, true);
        $d = Validator::make($input, ['versi' => ['required', 'integer', 'min:1'], 'alasan' => ['required', 'string', 'min:10', 'max:1000'], 'konfirmasi' => ['required', 'accepted'], 'aktif_sekarang' => [$aksi === 'aktif' ? 'required' : 'nullable', 'boolean']])->validate();

        return DB::transaction(function () use ($u, $versi, $aksi, $d) {
            $this->akses->kunci();
            $v = VersiInstrumenSupervisi::lockForUpdate()->findOrFail($versi->id);
            $this->akses->versi($v, $d['versi']);
            if ($aksi === 'revisi') {
                if ($v->status !== 'terbit' || $v->instrumen->versiInstrumen()->where('status', 'draf')->exists()) {
                    throw ValidationException::withMessages(['instrumen' => 'Pilih versi terbit dan selesaikan draf revisi yang masih terbuka.']);
                }
                $v = $v->instrumen->versiInstrumen()->create(['nama' => $v->nama, 'nomor' => $v->instrumen->versiInstrumen()->max('nomor') + 1, 'versi' => 1, 'status' => 'draf', 'catatan' => trim($d['alasan']), 'skala' => $v->skala, 'kelompok' => $v->kelompok, 'pertanyaan_refleksi' => $v->pertanyaan_refleksi ?? InstrumenSupervisiAwal::pertanyaanRefleksi(), 'pengubah_id' => $u->id]);
            } elseif ($aksi === 'terbitkan') {
                if ($v->status !== 'draf' || ! $v->instrumen->aktif) {
                    throw ValidationException::withMessages(['instrumen' => 'Hanya draf instrumen aktif yang dapat diterbitkan.']);
                }
                $this->validasi(['nama' => $v->nama, 'catatan' => $v->catatan, 'skala' => $v->skala, 'kelompok' => $v->kelompok, 'pertanyaan_refleksi' => $v->pertanyaan_refleksi ?? InstrumenSupervisiAwal::pertanyaanRefleksi()]);
                $v->update(['status' => 'terbit', 'versi' => $v->versi + 1, 'diterbitkan_pada' => now(), 'pengubah_id' => $u->id]);
            } else {
                if ($v->instrumen->aktif !== (bool) $d['aktif_sekarang']) {
                    throw ValidationException::withMessages(['instrumen' => 'Status paket telah berubah. Muat ulang sebelum mengubah status.']);
                }
                $v->instrumen->update(['aktif' => ! $v->instrumen->aktif]);
                $v->update(['versi' => $v->versi + 1, 'pengubah_id' => $u->id]);
            }
            $this->akses->catat($u, $v, 'Instrumen: '.$aksi, trim($d['alasan']));

            return $v->fresh('instrumen');
        });
    }

    public function data(VersiInstrumenSupervisi $v): array
    {
        return ['id' => $v->id, 'instrumen_id' => $v->instrumen_supervisi_id, 'nama' => $v->nama, 'aktif' => $v->instrumen->aktif,
            'pertanyaan_refleksi' => $v->pertanyaan_refleksi ?? InstrumenSupervisiAwal::pertanyaanRefleksi(),
            'nomor' => $v->nomor, 'versi' => $v->versi, 'status' => $v->status, 'catatan' => $v->catatan,
            'skala' => $v->skala, 'kelompok' => $v->kelompok, 'ringkasan' => $v->ringkasan(), 'diterbitkan_pada' => $v->diterbitkan_pada?->toIso8601String()];
    }
}
