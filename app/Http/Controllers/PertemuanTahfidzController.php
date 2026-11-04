<?php

namespace App\Http\Controllers;

use App\Models\KelompokTahfidz;
use App\Models\PertemuanTahfidz;
use App\Models\SurahTahfidz;
use App\Services\Tahfidz\AksesTahfidz;
use App\Services\Tahfidz\SetoranTahfidzService;
use Illuminate\Http\Request;

class PertemuanTahfidzController extends Controller
{
    public function __construct(private readonly AksesTahfidz $akses, private readonly SetoranTahfidzService $layanan) {}

    public function buka(Request $request, int $kelompok)
    {
        $kelompok = $this->kelompok($request, $kelompok);
        abort_unless($this->akses->catat($request->user(), $kelompok), 403);
        $data = $request->validate([
            'tanggal' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.today()->toDateString()],
        ], ['tanggal.before_or_equal' => 'Pertemuan tidak dapat dibuka untuk tanggal mendatang.']);
        $pertemuan = $this->layanan->buka($request->user(), $kelompok, $data['tanggal']);

        return redirect()->route('tahfidz.pertemuan.show', [$kelompok, $pertemuan]);
    }

    public function show(Request $request, int $kelompok, int $pertemuan)
    {
        $kelompok = $this->kelompok($request, $kelompok);
        $pertemuan = $kelompok->pertemuan()->findOrFail($pertemuan);
        $bolehCatat = ! $pertemuan->selesai_pada && $this->akses->catat($request->user(), $kelompok);
        $peserta = $bolehCatat ? $this->layanan->peserta($kelompok)->with('siswa:id,nama_lengkap')->get()
            ->sortBy(fn ($anggota) => $anggota->siswa->nama_lengkap, SORT_NATURAL | SORT_FLAG_CASE) : collect();
        $surah = $bolehCatat ? SurahTahfidz::orderBy('nomor')->get() : collect();
        $setoran = $pertemuan->setoran()->with(['anggota.siswa:id,nama_lengkap', 'surah', 'penilai:id,nama'])
            ->latest('id')->paginate(20);

        return view('tahfidz.pertemuan', compact('kelompok', 'pertemuan', 'bolehCatat', 'peserta', 'surah', 'setoran'));
    }

    public function selesaikan(Request $request, int $kelompok, int $pertemuan)
    {
        $kelompok = $this->kelompok($request, $kelompok);
        $this->layanan->selesaikan($request->user(), $kelompok, $pertemuan);

        return redirect()->route('tahfidz.pertemuan.show', [$kelompok, $pertemuan])->with('berhasil', 'Pertemuan selesai. Rangkuman dikirim ke akun orang tua yang terhubung.');
    }

    public function simpan(Request $request, int $kelompok, int $pertemuan)
    {
        $kelompok = $this->kelompok($request, $kelompok);
        abort_unless($this->akses->catat($request->user(), $kelompok), 403);
        if (is_string($request->input('nilai'))) {
            $request->merge(['nilai' => str_replace(',', '.', $request->input('nilai'))]);
        }
        $data = $request->validate([
            'anggota_tahfidz_id' => ['required', 'integer', 'min:1'],
            'nomor_surah' => ['required', 'integer', 'exists:surah_tahfidz,nomor'],
            'nilai' => ['required', 'numeric', 'regex:/^\d{1,3}(?:\.\d{1,2})?$/D', 'between:0,100'],
            'catatan' => ['nullable', 'string', 'max:500'],
            'token_setoran' => ['required', 'uuid'],
        ], ['nilai.regex' => 'Masukkan nilai 0-100 dengan maksimal dua angka desimal.']);
        $setoran = $this->layanan->simpan($request->user(), $kelompok, $pertemuan, $data);

        return redirect()->route('tahfidz.pertemuan.show', [$kelompok, $pertemuan])
            ->with('berhasil', 'Setoran berhasil disimpan.')->with('tahfidz_anggota', $setoran->anggota_tahfidz_id);
    }

    public function riwayat(Request $request, int $kelompok, int $anggota)
    {
        $kelompok = $this->kelompok($request, $kelompok);
        $anggota = $kelompok->anggota()->with('siswa:id,nama_lengkap')->findOrFail($anggota);
        $setoran = $anggota->setoran()->whereHas('pertemuan', fn ($q) => $q->where('kelompok_tahfidz_id', $kelompok->id))
            ->with(['pertemuan', 'surah', 'penilai:id,nama'])
            ->orderByDesc(PertemuanTahfidz::select('tanggal')->whereColumn('pertemuan_tahfidz.id', 'setoran_tahfidz.pertemuan_tahfidz_id'))
            ->latest('id')->paginate(20);

        return view('tahfidz.riwayat', compact('kelompok', 'anggota', 'setoran'));
    }

    private function kelompok(Request $request, int $id): KelompokTahfidz
    {
        return $this->akses->cakupan($request->user())->with('tahunPelajaran')->findOrFail($id);
    }
}
