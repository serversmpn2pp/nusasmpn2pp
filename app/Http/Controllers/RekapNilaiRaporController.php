<?php

namespace App\Http\Controllers;

use App\Services\Nilai\RekapNilaiRaporService;
use Illuminate\Http\Request;

class RekapNilaiRaporController extends Controller
{
    public function index(Request $request, RekapNilaiRaporService $service)
    {
        return view('rekap-nilai-rapor.index', $service->hitung(
            $request->input('guru_mata_pelajaran_id'),
            $request->input('semester', 'ganjil'),
        ));
    }
}
