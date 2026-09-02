<?php

namespace App\Http\Controllers;

use App\Models\Barang;
use App\Models\KategoriBarang;
use App\Models\LokasiBarang;
use App\Models\SatuanBarang;
use App\Models\SumberPerolehanBarang;
use App\Services\Inventaris\ProsesImportPenerimaanBarang;
use App\Support\PembacaExcelPenerimaanBarang;
use App\Support\PenulisTemplateExcelPenerimaanBarang;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;

class ImportPenerimaanBarangController extends Controller
{
    private const AWALAN_SESI = 'import_penerimaan_barang.';

    public function create()
    {
        return view('penerimaan-barang.import');
    }

    public function template(PenulisTemplateExcelPenerimaanBarang $penulis)
    {
        $lokasiBerkas = $penulis->buat($this->referensiTemplate());

        return response()
            ->download(
                $lokasiBerkas,
                'template_import_barang_datang_nusa.xlsx',
                ['Content-Type' => PenulisTemplateExcelPenerimaanBarang::MIME],
            )
            ->deleteFileAfterSend();
    }

    public function unggah(
        Request $request,
        PembacaExcelPenerimaanBarang $pembaca,
        ProsesImportPenerimaanBarang $prosesImport,
    ) {
        $data = $request->validate([
            'berkas_excel' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
        ], [
            'berkas_excel.required' => 'Pilih berkas Excel barang datang terlebih dahulu.',
            'berkas_excel.mimes' => 'Berkas harus menggunakan format .xlsx.',
            'berkas_excel.max' => 'Ukuran berkas Excel maksimal 10 MB.',
        ]);

        try {
            $hasilBaca = $pembaca->baca($data['berkas_excel']->getRealPath());
            $pratinjau = $prosesImport->siapkan($hasilBaca);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['berkas_excel' => $exception->getMessage()]);
        }

        $token = Str::random(40);
        $request->session()->put(self::AWALAN_SESI.$token, $pratinjau);

        return redirect()->route('penerimaan-barang.import.pratinjau', $token);
    }

    public function pratinjau(Request $request, string $token)
    {
        $pratinjau = $this->ambilPratinjau($request, $token);

        if (! $pratinjau) {
            return redirect()
                ->route('penerimaan-barang.import.create')
                ->withErrors(['berkas_excel' => 'Pratinjau import tidak ditemukan atau sudah kedaluwarsa. Unggah kembali berkas Excel.']);
        }

        return view('penerimaan-barang.import-pratinjau', compact('pratinjau', 'token'));
    }

    public function konfirmasi(Request $request, ProsesImportPenerimaanBarang $prosesImport)
    {
        $data = $request->validate([
            'token_import' => ['required', 'string', 'size:40'],
        ]);
        $token = $data['token_import'];
        $pratinjau = $this->ambilPratinjau($request, $token);

        if (! $pratinjau) {
            return redirect()
                ->route('penerimaan-barang.import.create')
                ->withErrors(['berkas_excel' => 'Pratinjau import tidak ditemukan atau sudah kedaluwarsa. Unggah kembali berkas Excel.']);
        }

        $penerimaan = $prosesImport->simpan($pratinjau, $request->user()?->id);
        $request->session()->forget(self::AWALAN_SESI.$token);

        return redirect()
            ->route('penerimaan-barang.show', $penerimaan)
            ->with('berhasil', 'Import barang datang berhasil disimpan. Stok dan unit aset telah diperbarui.');
    }

    private function ambilPratinjau(Request $request, string $token): ?array
    {
        if (! preg_match('/^[A-Za-z0-9]{40}$/', $token)) {
            return null;
        }

        $kunci = self::AWALAN_SESI.$token;
        $pratinjau = $request->session()->get($kunci);

        if (! is_array($pratinjau)) {
            return null;
        }

        $dibuatPada = filled($pratinjau['dibuat_pada'] ?? null)
            ? Carbon::parse($pratinjau['dibuat_pada'])
            : null;

        if (! $dibuatPada || $dibuatPada->lt(now()->subHour())) {
            $request->session()->forget($kunci);

            return null;
        }

        return $pratinjau;
    }

    private function referensiTemplate(): array
    {
        return [
            'barang' => Barang::query()
                ->with(['kategoriBarang', 'satuanBarang', 'lokasiPenyimpanan'])
                ->where('aktif', true)
                ->orderBy('nama')
                ->get()
                ->map(fn (Barang $barang) => [
                    'kode' => $barang->kodeKlasifikasi(),
                    'nama' => $barang->nama,
                    'jenis' => $barang->jenis_barang,
                    'kategori' => $barang->kategoriBarang?->kode ?: '-',
                    'satuan' => $barang->satuanBarang?->kode ?: '-',
                    'lokasi' => $barang->lokasiPenyimpanan?->kode ?: '-',
                ])->all(),
            'kategori' => KategoriBarang::where('aktif', true)->orderBy('nama')->get(['kode', 'nama'])->toArray(),
            'satuan' => SatuanBarang::where('aktif', true)->orderBy('nama')->get(['kode', 'nama'])->toArray(),
            'lokasi' => LokasiBarang::where('aktif', true)->orderBy('nama')->get(['kode', 'nama'])->toArray(),
            'sumber' => SumberPerolehanBarang::where('aktif', true)->orderBy('nama')->get(['kode', 'nama'])->toArray(),
        ];
    }
}
