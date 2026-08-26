<?php

namespace App\Http\Controllers;

use App\Models\GuruMataPelajaran;
use App\Services\Nilai\InputNilaiService;
use Illuminate\Http\Request;

class PublikasiNilaiController extends Controller
{
    public function __construct(private InputNilaiService $inputNilai) {}

    public function publikasikan(Request $request, GuruMataPelajaran $guruMataPelajaran, string $semester)
    {
        $this->inputNilai->publikasikan(
            $request->user(),
            $guruMataPelajaran,
            $semester,
        );

        return redirect()
            ->route('input-nilai.index', array_filter([
                'komponen_nilai_id' => $request->input('komponen_nilai_id'),
            ]))
            ->with('berhasil', 'Nilai berhasil dipublikasikan dan sekarang dapat dilihat oleh siswa.');
    }

    public function jadikanDraf(Request $request, GuruMataPelajaran $guruMataPelajaran, string $semester)
    {
        $this->inputNilai->jadikanDraf(
            $request->user(),
            $guruMataPelajaran,
            $semester,
        );

        return redirect()
            ->route('input-nilai.index', array_filter([
                'komponen_nilai_id' => $request->input('komponen_nilai_id'),
            ]))
            ->with('berhasil', 'Publikasi dibatalkan. Nilai kembali menjadi draf dan tidak terlihat oleh siswa.');
    }
}
