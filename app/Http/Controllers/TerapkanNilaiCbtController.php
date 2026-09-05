<?php

namespace App\Http\Controllers;

use App\Models\UjianCbt;
use App\Services\Cbt\TerapkanNilaiCbtService;
use Illuminate\Http\Request;

class TerapkanNilaiCbtController extends Controller
{
    public function store(Request $request, UjianCbt $ujianCbt, TerapkanNilaiCbtService $service)
    {
        $hasil = $service->terapkan($ujianCbt, $request->user()?->id);

        return redirect()
            ->route('ujian-cbt.hasil.index', $ujianCbt)
            ->with($hasil['ringkasan']['diterapkan'] ? 'berhasil' : 'gagal', $hasil['pesan']);
    }
}
