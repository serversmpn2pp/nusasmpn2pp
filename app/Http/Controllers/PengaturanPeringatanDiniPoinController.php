<?php

namespace App\Http\Controllers;

use App\Models\PengaturanPeringatanDiniPoin;
use App\Models\TahunPelajaran;
use App\Services\Pembinaan\PengaturanPeringatanDiniPoinService;
use Illuminate\Http\Request;

class PengaturanPeringatanDiniPoinController extends Controller
{
    public function __construct(private PengaturanPeringatanDiniPoinService $pengaturan) {}

    public function index()
    {
        $daftarTahunPelajaran = TahunPelajaran::query()
            ->with(['pengaturanPeringatanDiniPoin.diperbaruiOlehPengguna'])
            ->orderByDesc('aktif')
            ->orderByDesc('tanggal_mulai')
            ->paginate(10);

        return view('pengaturan-peringatan-dini-poin.index', compact('daftarTahunPelajaran'));
    }

    public function edit(TahunPelajaran $tahunPelajaran)
    {
        $pengaturan = $this->pengaturan->nilaiUntukTahun($tahunPelajaran->id);

        return view('pengaturan-peringatan-dini-poin.edit', compact('tahunPelajaran', 'pengaturan'));
    }

    public function update(Request $request, TahunPelajaran $tahunPelajaran)
    {
        $data = $request->validate([
            'aktif' => ['nullable', 'boolean'],
            'persentase_mendekati_ambang' => ['required', 'integer', 'min:50', 'max:99'],
            'jumlah_pelanggaran_berulang' => ['required', 'integer', 'min:2', 'max:20'],
            'periode_pelanggaran_hari' => ['required', 'integer', 'min:7', 'max:365'],
            'jumlah_keterlambatan_berulang' => ['required', 'integer', 'min:2', 'max:30'],
            'periode_keterlambatan_hari' => ['required', 'integer', 'min:7', 'max:365'],
            'notifikasi_aktif' => ['nullable', 'boolean'],
        ]);

        PengaturanPeringatanDiniPoin::updateOrCreate(
            ['tahun_pelajaran_id' => $tahunPelajaran->id],
            [
                'aktif' => $request->boolean('aktif'),
                'persentase_mendekati_ambang' => (int) $data['persentase_mendekati_ambang'],
                'jumlah_pelanggaran_berulang' => (int) $data['jumlah_pelanggaran_berulang'],
                'periode_pelanggaran_hari' => (int) $data['periode_pelanggaran_hari'],
                'jumlah_keterlambatan_berulang' => (int) $data['jumlah_keterlambatan_berulang'],
                'periode_keterlambatan_hari' => (int) $data['periode_keterlambatan_hari'],
                'notifikasi_aktif' => $request->boolean('notifikasi_aktif'),
                'diperbarui_oleh_pengguna_id' => $request->user()?->id,
            ],
        );

        return redirect()->route('pengaturan-peringatan-dini-poin.index')
            ->with('berhasil', 'Pengaturan peringatan dini tahun '.$tahunPelajaran->nama.' berhasil disimpan.');
    }
}
