<?php

namespace App\Http\Controllers;

use App\Models\UjianCbt;
use App\Services\Cbt\KoreksiOtomatisCbtService;

class KoreksiOtomatisUjianCbtController extends Controller
{
    public function store(UjianCbt $ujianCbt, KoreksiOtomatisCbtService $koreksiOtomatisCbtService)
    {
        abort_if(
            $ujianCbt->ujianTerpusat() && $ujianCbt->hasil_difinalisasi_pada,
            422,
            'Hasil ujian sudah difinalisasi. Batalkan finalisasi untuk menjalankan koreksi ulang.',
        );

        $hasil = $koreksiOtomatisCbtService->koreksiUjian($ujianCbt);

        return back()->with(
            'berhasil',
            "Koreksi otomatis selesai. {$hasil['jawaban_dikoreksi']} jawaban objektif dikoreksi dari {$hasil['peserta']} peserta. Benar {$hasil['benar']}, salah/kosong {$hasil['salah']}."
        );
    }
}
