<?php

namespace App\Http\Controllers;

use App\Models\GuruMataPelajaran;
use App\Services\Survei\PengisianSurveiPembelajaranService;
use Illuminate\Http\Request;

class SurveiPembelajaranController extends Controller
{
    public function __construct(private PengisianSurveiPembelajaranService $survei) {}

    public function create(Request $request, GuruMataPelajaran $guruMataPelajaran, string $semester)
    {
        $konteks = $this->survei->siapkan($request->user(), $guruMataPelajaran, $semester);

        if ($konteks['sudahDiisi']) {
            return $this->kembaliKeNilai($guruMataPelajaran, $konteks['semester'])
                ->with('berhasil', 'Survei pembelajaran ini sudah Anda isi. Nilai sudah dapat dilihat.');
        }

        return view('survei-pembelajaran.create', [
            'siswa' => $konteks['siswa'],
            'guruMataPelajaran' => $konteks['guruMataPelajaran'],
            'semester' => $konteks['semester'],
            'daftarPertanyaan' => $konteks['daftarPertanyaan'],
            'daftarPilihan' => $konteks['daftarPilihan'],
        ]);
    }

    public function store(Request $request, GuruMataPelajaran $guruMataPelajaran, string $semester)
    {
        $konteks = $this->survei->siapkan($request->user(), $guruMataPelajaran, $semester);

        if ($konteks['sudahDiisi']) {
            return $this->kembaliKeNilai($guruMataPelajaran, $konteks['semester'])
                ->with('berhasil', 'Survei pembelajaran ini sudah Anda isi. Nilai sudah dapat dilihat.');
        }

        $data = $request->validate(
            $this->survei->aturanValidasi($konteks['daftarPertanyaan']),
            $this->survei->pesanValidasi(),
        );
        $this->survei->simpan($konteks, $data);

        return $this->kembaliKeNilai($guruMataPelajaran, $konteks['semester'])
            ->with('berhasil', 'Terima kasih. Survei berhasil dikirim dan nilai mata pelajaran sudah terbuka.');
    }

    private function kembaliKeNilai(GuruMataPelajaran $guruMataPelajaran, string $semester)
    {
        return redirect()->to(route('nilai-saya.index', [
            'tahun_pelajaran_id' => $guruMataPelajaran->tahun_pelajaran_id,
            'semester' => $semester,
        ]).'#mapel-'.$guruMataPelajaran->id);
    }
}
