<?php

namespace App\Services\Humas;

use App\Models\SasaranUmpanBalikHumas;
use App\Models\UmpanBalikHumas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class KirimUmpanBalikOrangTuaHumasService
{
    public function __construct(private readonly UmpanBalikHumasService $kelola) {}

    public function store(Request $request, UmpanBalikHumas $formulir)
    {
        $wali = $this->kelola->wali($request);
        $token = $request->validate(['token_pengiriman' => ['required', 'uuid']])['token_pengiriman'];
        DB::transaction(function () use ($request, $formulir, $wali, $token) {
            $f = UmpanBalikHumas::lockForUpdate()->findOrFail($formulir->id);
            $s = $this->kelola->sasaran($f, $wali);
            $s = SasaranUmpanBalikHumas::lockForUpdate()->findOrFail($s->id);
            if ($s->dikirim_pada) {
                if ($s->token_pengiriman === $token) {
                    return;
                }
                $this->kelola->gagal('Jawaban sudah terkirim dan tidak dapat diubah. Terima kasih atas masukan Anda.');
            }
            if (! $f->menerimaJawaban()) {
                $this->kelola->gagal('Formulir belum dibuka atau periode pengisian sudah berakhir.');
            }
            abort_if(SasaranUmpanBalikHumas::where('token_pengiriman', $token)->exists(), 403);
            $f->load('pertanyaan');
            $rules = ['jawaban' => ['required', 'array:'.$f->pertanyaan->pluck('id')->join(',')]];
            foreach ($f->pertanyaan as $p) {
                $rules['jawaban.'.$p->id] = [$p->wajib ? 'required' : 'nullable', ...($p->jenis === 'skala' ? ['integer', Rule::in([0, 1, 2, 3, 4])] : ['string', 'max:2000'])];
            }
            $data = $request->validate($rules, ['jawaban.*.required' => 'Lengkapi jawaban yang wajib diisi.']);
            $terisi = 0;
            foreach ($f->pertanyaan as $p) {
                $isi = $data['jawaban'][$p->id] ?? null;
                if ($isi === null || $isi === '') {
                    continue;
                }
                $s->jawaban()->create(['pertanyaan_umpan_balik_humas_id' => $p->id, 'nilai' => $p->jenis === 'skala' ? (int) $isi : null, 'teks' => $p->jenis === 'teks' ? $isi : null]);
                $terisi++;
            }
            if (! $terisi) {
                $this->kelola->gagal('Isi minimal satu jawaban sebelum mengirim.');
            }
            $s->forceFill(['token_pengiriman' => $token, 'dikirim_pada' => now()])->save();
        });

        return $this->kelola->sasaran($formulir->fresh(), $wali)->fresh();
    }
}
