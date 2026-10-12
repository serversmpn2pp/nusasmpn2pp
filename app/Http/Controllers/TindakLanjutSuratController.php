<?php

namespace App\Http\Controllers;

use App\Models\BerkasSuratTu;
use App\Models\BuktiTindakLanjutSuratTu;
use App\Models\SuratTu;
use App\Models\TindakLanjutSuratTu;
use App\Services\Persuratan\TindakLanjutSuratService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TindakLanjutSuratController extends Controller
{
    public function __construct(private TindakLanjutSuratService $service) {}

    public function index(Request $request)
    {
        return $this->daftar($request, false);
    }

    public function monitoring(Request $request)
    {
        return $this->daftar($request, true);
    }

    private function daftar(Request $request, bool $monitoring)
    {
        $filter = $request->validate(['kata_kunci' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(array_keys(TindakLanjutSuratTu::STATUS))],
            'jenis' => ['nullable', Rule::in(['disposisi', 'tugas'])], 'terlambat' => ['nullable', 'boolean'],
            'pemeriksaan_saya' => ['nullable', 'boolean']]);
        $query = $this->service->daftar($request->user(), $monitoring)->with('surat', 'pemeriksa:id,nama');
        $query->when($filter['kata_kunci'] ?? null, fn ($q, $kata) => $q->where(fn ($q) => $q
            ->whereLike('nama_pegawai', '%'.$kata.'%')->orWhereHas('surat', fn ($q) => $q
            ->whereLike('nomor_surat', '%'.$kata.'%')->orWhereLike('perihal', '%'.$kata.'%'))))
            ->when($filter['jenis'] ?? null, fn ($q, $jenis) => $q->where('jenis', $jenis))
            ->when($filter['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filter['terlambat'] ?? false, fn ($q) => $q->whereDate('batas_tanggal', '<', today())
                ->whereNotIn('status', ['selesai', 'dibatalkan', 'menunggu_pemeriksaan'])->whereHas('surat', fn ($q) => $q->where('status', '<>', 'dibatalkan')))
            ->when($filter['pemeriksaan_saya'] ?? false, fn ($q) => $q->where('pemeriksa_pengguna_id', $request->user()->id)->where('status', 'menunggu_pemeriksaan'));
        $jumlah = (clone $query)->selectRaw('status, COUNT(*) AS jumlah')->groupBy('status')->pluck('jumlah', 'status');

        return response()->view('persuratan.tindak-lanjut.index', ['tugas' => $query->orderBy('batas_tanggal')->orderBy('id')->paginate(20)->withQueryString(),
            'filter' => $filter, 'monitoring' => $monitoring, 'jumlah' => $jumlah], 200, ['Cache-Control' => 'private, no-store']);
    }

    public function disposisi(Request $request, SuratTu $surat)
    {
        $this->service->disposisi($request, $surat);

        return redirect()->route('persuratan.show', $surat)->with('berhasil', 'Disposisi disimpan dan penerima diberi notifikasi.');
    }

    public function show(Request $request, TindakLanjutSuratTu $tindakLanjut)
    {
        $arsip = $this->service->pastikanAkses($request->user(), $tindakLanjut);
        $tindakLanjut->load(['surat.berkas' => fn ($q) => $q->where('aktif', true), 'pemeriksa:id,nama',
            'laporan.bukti', 'riwayat.pengguna:id,nama']);

        return response()->view('persuratan.tindak-lanjut.show', ['tugas' => $tindakLanjut, 'arsip' => $arsip,
            'pemeriksa' => $this->service->dapatMemeriksa($request->user(), $tindakLanjut),
            'pengatur' => $this->service->dapatMengatur($request->user(), $tindakLanjut),
            'calonPemeriksa' => $arsip ? $this->service->calonPemeriksa($tindakLanjut) : collect()],
            200, ['Cache-Control' => 'private, no-store']);
    }

    public function laporan(Request $request, TindakLanjutSuratTu $tindakLanjut)
    {
        $this->service->laporan($request, $tindakLanjut);

        return redirect()->route('tindak-lanjut-surat.show', $tindakLanjut)->with('berhasil', $request->aksi === 'ajukan' ? 'Laporan diajukan kepada pemeriksa.' : 'Progres disimpan.');
    }

    public function tindakan(Request $request, TindakLanjutSuratTu $tindakLanjut, string $aksi)
    {
        $this->service->tindakan($request, $tindakLanjut, $aksi);

        return redirect()->route('tindak-lanjut-surat.show', $tindakLanjut)->with('berhasil', 'Tindak lanjut diperbarui.');
    }

    public function bukti(Request $request, TindakLanjutSuratTu $tindakLanjut, BuktiTindakLanjutSuratTu $bukti)
    {
        return $this->service->unduhBukti($request->user(), $tindakLanjut, $bukti);
    }

    public function berkas(Request $request, TindakLanjutSuratTu $tindakLanjut, BerkasSuratTu $berkas)
    {
        return $this->service->unduhSurat($request->user(), $tindakLanjut, $berkas);
    }
}
