<?php

namespace App\Http\Controllers;

use App\Models\UjianOmr;
use App\Models\VersiSoalUjianOmr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class KunciJawabanUjianOmrController extends Controller
{
    public function edit(UjianOmr $ujianOmr, VersiSoalUjianOmr $versiSoalUjianOmr)
    {
        $this->pastikanVersiMilikUjian($ujianOmr, $versiSoalUjianOmr);
        $versiSoalUjianOmr->load('kunciJawaban');

        return view('ujian-omr.kunci-jawaban', compact('ujianOmr', 'versiSoalUjianOmr'));
    }

    public function update(Request $request, UjianOmr $ujianOmr, VersiSoalUjianOmr $versiSoalUjianOmr)
    {
        $this->pastikanVersiMilikUjian($ujianOmr, $versiSoalUjianOmr);
        $aturan = ['jawaban' => ['required', 'array', 'size:' . $ujianOmr->jumlah_soal]];

        foreach (range(1, $ujianOmr->jumlah_soal) as $nomorSoal) {
            $aturan['jawaban.' . $nomorSoal] = ['required', Rule::in(['A', 'B', 'C', 'D'])];
        }

        $data = $request->validate($aturan, [
            'jawaban.required' => 'Seluruh kunci jawaban perlu diisi.',
            'jawaban.size' => 'Jumlah kunci jawaban harus sesuai dengan jumlah soal.',
            'jawaban.*.required' => 'Masih ada nomor soal yang belum mempunyai kunci jawaban.',
            'jawaban.*.in' => 'Kunci jawaban hanya boleh berupa pilihan A, B, C, atau D.',
        ]);

        DB::transaction(function () use ($versiSoalUjianOmr, $ujianOmr, $data) {
            foreach (range(1, $ujianOmr->jumlah_soal) as $nomorSoal) {
                $versiSoalUjianOmr->kunciJawaban()->updateOrCreate(
                    ['nomor_soal' => $nomorSoal],
                    ['jawaban' => $data['jawaban'][$nomorSoal]],
                );
            }

            $versiSoalUjianOmr->kunciJawaban()
                ->where('nomor_soal', '>', $ujianOmr->jumlah_soal)
                ->delete();
        });

        return redirect()
            ->route('ujian-omr.show', $ujianOmr)
            ->with('berhasil', 'Kunci jawaban versi ' . $versiSoalUjianOmr->kode . ' berhasil disimpan.');
    }

    private function pastikanVersiMilikUjian(UjianOmr $ujianOmr, VersiSoalUjianOmr $versiSoalUjianOmr): void
    {
        abort_unless(
            (int) $versiSoalUjianOmr->ujian_omr_id === (int) $ujianOmr->id && $versiSoalUjianOmr->aktif,
            404,
        );
    }
}
