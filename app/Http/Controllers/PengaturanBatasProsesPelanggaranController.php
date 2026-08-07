<?php

namespace App\Http\Controllers;

use App\Models\PengaturanBatasProsesPelanggaran;
use App\Models\TahunPelajaran;
use App\Services\Pembinaan\PengaturanBatasProsesPelanggaranService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PengaturanBatasProsesPelanggaranController extends Controller
{
    public function __construct(private PengaturanBatasProsesPelanggaranService $pengaturanService) {}

    public function index()
    {
        $daftarTahunPelajaran = TahunPelajaran::query()
            ->with('pengaturanBatasProsesPelanggaran.diperbaruiOlehPengguna')
            ->orderByDesc('aktif')
            ->orderByDesc('tanggal_mulai')
            ->paginate(10);
        $daftarTahunPelajaran->getCollection()->each(function (TahunPelajaran $tahun) {
            if (! $tahun->pengaturanBatasProsesPelanggaran) {
                $tahun->setRelation('pengaturanBatasProsesPelanggaran', $this->pengaturanService->nilaiUntukTahun($tahun->id));
            }
        });

        return view('pengaturan-batas-proses-pelanggaran.index', compact('daftarTahunPelajaran'));
    }

    public function edit(TahunPelajaran $tahunPelajaran)
    {
        $pengaturan = $this->pengaturanService->nilaiUntukTahun($tahunPelajaran->id);

        return view('pengaturan-batas-proses-pelanggaran.edit', compact('tahunPelajaran', 'pengaturan'));
    }

    public function update(Request $request, TahunPelajaran $tahunPelajaran)
    {
        $data = $request->validate([
            'batas_hari_pemeriksaan_bk' => ['required', 'integer', 'min:1', 'max:30'],
            'batas_hari_persetujuan' => ['required', 'integer', 'min:1', 'max:30'],
            'pengingat_hari_sebelum_batas' => ['required', 'integer', 'min:0', 'max:29'],
            'notifikasi_pengingat_aktif' => ['nullable', 'boolean'],
            'notifikasi_terlambat_aktif' => ['nullable', 'boolean'],
        ]);

        if ((int) $data['pengingat_hari_sebelum_batas'] >= min(
            (int) $data['batas_hari_pemeriksaan_bk'],
            (int) $data['batas_hari_persetujuan'],
        )) {
            throw ValidationException::withMessages([
                'pengingat_hari_sebelum_batas' => 'Pengingat harus lebih kecil daripada batas hari terpendek.',
            ]);
        }

        PengaturanBatasProsesPelanggaran::updateOrCreate(
            ['tahun_pelajaran_id' => $tahunPelajaran->id],
            [
                'batas_hari_pemeriksaan_bk' => $data['batas_hari_pemeriksaan_bk'],
                'batas_hari_persetujuan' => $data['batas_hari_persetujuan'],
                'pengingat_hari_sebelum_batas' => $data['pengingat_hari_sebelum_batas'],
                'notifikasi_pengingat_aktif' => $request->boolean('notifikasi_pengingat_aktif'),
                'notifikasi_terlambat_aktif' => $request->boolean('notifikasi_terlambat_aktif'),
                'diperbarui_oleh_pengguna_id' => $request->user()?->id,
            ],
        );

        return redirect()->route('pengaturan-batas-proses-pelanggaran.index')
            ->with('berhasil', 'Batas proses pelanggaran tahun '.$tahunPelajaran->nama.' berhasil disimpan.');
    }
}
