<?php

namespace App\Http\Controllers;

use App\Models\UjianCbt;
use App\Services\Cbt\KoreksiOtomatisCbtService;

class KoreksiOtomatisUjianCbtController extends Controller
{
    public function store(UjianCbt $ujianCbt, KoreksiOtomatisCbtService $koreksiOtomatisCbtService)
    {
        $hasil = $koreksiOtomatisCbtService->koreksiUjian($ujianCbt);

        return back()->with(
            'berhasil',
            "Koreksi otomatis selesai. {$hasil['jawaban_dikoreksi']} jawaban objektif dikoreksi dari {$hasil['peserta']} peserta. Benar {$hasil['benar']}, salah/kosong {$hasil['salah']}."
        );
    }
}
