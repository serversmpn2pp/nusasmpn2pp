<?php

namespace App\Http\Controllers;

use App\Models\PesertaUjianCbt;
use App\Services\Cbt\KeamananUjianService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ResetSesiUjianController extends Controller
{
    public function __invoke(Request $request, PesertaUjianCbt $pesertaUjianCbt, KeamananUjianService $service): RedirectResponse
    {
        $data = $request->validate(['alasan' => ['required', 'string', 'min:10', 'max:500']]);
        $service->resetPerangkat($request->user(), $pesertaUjianCbt, $data['alasan']);

        return back()->with('berhasil', 'Ikatan sesi ujian direset. Siswa dapat membuka ujian pada perangkat yang disetujui.');
    }
}
