<?php

namespace App\Http\Controllers;

use App\Models\KegiatanIbadah;
use App\Models\Kelas;
use App\Models\TahunPelajaran;
use App\Services\Ibadah\AksesScanKegiatanIbadah;
use App\Services\Ibadah\EvaluasiIbadahMingguan;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;

class EvaluasiIbadahMingguanController extends Controller
{
    public function __invoke(Request $request, AksesScanKegiatanIbadah $akses, EvaluasiIbadahMingguan $evaluasi)
    {
        $tahun = TahunPelajaran::query()->where('aktif', true)->latest('tanggal_mulai')->first();
        $cakupan = $akses->cakupanKelasEvaluasiMingguan($request->user(), $tahun);
        abort_if($cakupan === [], 403);

        $data = $request->validate([
            'tanggal' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'kegiatan_ibadah_id' => ['nullable', 'integer', 'min:1'],
            'kelas_id' => ['nullable', 'integer', 'min:1'],
        ]);
        $tanggal = $data['tanggal'] ?? now()->toDateString();
        $awal = CarbonImmutable::parse($tanggal)->startOfWeek(CarbonInterface::MONDAY);
        $akhir = $awal->addDays(6);
        $periode = $awal->locale('id')->translatedFormat('d M').' s.d. '.$akhir->locale('id')->translatedFormat('d M Y');
        $daftarKegiatan = KegiatanIbadah::query()->where('aktif', true)->orderBy('nama')->get(['id', 'kode', 'nama']);
        $kegiatan = isset($data['kegiatan_ibadah_id']) ? $daftarKegiatan->find($data['kegiatan_ibadah_id'])
            : ($daftarKegiatan->firstWhere('kode', KegiatanIbadah::KODE_SHOLAT_DUHUR) ?? $daftarKegiatan->first());
        abort_if(isset($data['kegiatan_ibadah_id']) && ! $kegiatan, 404);
        $daftarKelas = $tahun ? Kelas::query()->where('tahun_pelajaran_id', $tahun->id)->where('aktif', true)
            ->when($cakupan !== null, fn ($q) => $q->whereIn('id', $cakupan))
            ->orderBy('tingkat')->orderBy('nama')->get(['id', 'nama', 'tingkat'])->toBase()->keyBy('id') : collect();
        $kelasId = isset($data['kelas_id']) ? (int) $data['kelas_id'] : null;
        abort_if($kelasId && $cakupan !== null && ! in_array($kelasId, $cakupan, true), 403);
        $kelas = $daftarKelas->get($kelasId);
        abort_if($kelasId && ! $kelas, 404);

        $hasil = $tahun && $kegiatan ? $evaluasi->hitung($tahun, $kegiatan, $kelas ? $daftarKelas->only($kelasId) : $daftarKelas, $awal, $akhir)
            : $evaluasi->kosong();
        $parameter = array_filter(['tanggal' => $tanggal, 'kegiatan_ibadah_id' => $kegiatan?->id, 'kelas_id' => $kelasId]);
        $cetak = $request->routeIs('rekap-kegiatan-ibadah.mingguan.cetak');

        return response()->view($cetak ? 'rekap-kegiatan-ibadah.mingguan-cetak' : 'rekap-kegiatan-ibadah.mingguan', [
            ...compact('tahun', 'kegiatan', 'kelas', 'daftarKegiatan', 'daftarKelas', 'tanggal', 'awal', 'akhir', 'periode', 'parameter', 'cetak'),
            ...$hasil,
        ])->header('Cache-Control', 'private, no-store');
    }
}
