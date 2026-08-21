<?php

namespace App\Http\Controllers;

use App\Services\Sistem\CadanganDatabaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CadanganDatabaseController extends Controller
{
    public function index(CadanganDatabaseService $service): View
    {
        $daftarCadangan = $service->daftarCadangan();

        return view('cadangan-database.index', [
            'statusServer' => $service->status(),
            'daftarCadangan' => $daftarCadangan,
            'daftarAktivitas' => $service->daftarAktivitas(),
            'batasUnggah' => $service->batasUnggah(),
            'cadanganTerbaru' => $daftarCadangan->first(),
            'totalUkuran' => $daftarCadangan->sum('ukuran'),
        ]);
    }

    public function store(Request $request, CadanganDatabaseService $service): RedirectResponse
    {
        try {
            $cadangan = $service->buatCadangan('manual', $request->user());

            return back()->with(
                'berhasil',
                'Cadangan database berhasil dibuat: '.$cadangan['nama_file'].' ('.$cadangan['ukuran_label'].').',
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['cadangan' => $exception->getMessage()]);
        }
    }

    public function download(string $namaFile, CadanganDatabaseService $service): BinaryFileResponse
    {
        try {
            $path = $service->pathCadangan($namaFile);
        } catch (RuntimeException) {
            abort(404);
        }

        return response()->download($path, $namaFile, [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function restore(
        Request $request,
        string $namaFile,
        CadanganDatabaseService $service,
    ): RedirectResponse {
        $this->validasiKonfirmasiPemulihan($request);

        try {
            $hasil = $service->pulihkan($namaFile, $request->user());

            return redirect()->route('cadangan-database.index')->with(
                'berhasil',
                'Database berhasil dipulihkan dari '.$hasil['cadangan']['nama_file']
                .'. Cadangan pengaman dibuat sebagai '.$hasil['cadangan_pengaman']['nama_file'].'.',
            );
        } catch (RuntimeException $exception) {
            return redirect()->route('cadangan-database.index')
                ->withErrors(['pemulihan' => $exception->getMessage()]);
        }
    }

    public function restoreUpload(
        Request $request,
        CadanganDatabaseService $service,
    ): RedirectResponse {
        $batasUnggah = $service->batasUnggah();
        $data = $request->validate([
            'berkas_cadangan' => ['required', 'file', 'max:'.$batasUnggah['kilobyte']],
        ], [
            'berkas_cadangan.required' => 'Pilih berkas cadangan PostgreSQL terlebih dahulu.',
            'berkas_cadangan.uploaded' => 'Berkas gagal diunggah. Pastikan ukurannya tidak melewati batas server.',
            'berkas_cadangan.max' => 'Ukuran cadangan maksimal '.$batasUnggah['label'].'.',
        ]);
        $this->validasiKonfirmasiPemulihan($request);

        try {
            $cadangan = $service->simpanUnggahan($data['berkas_cadangan'], $request->user());
            $hasil = $service->pulihkan($cadangan['nama_file'], $request->user());

            return redirect()->route('cadangan-database.index')->with(
                'berhasil',
                'Cadangan berhasil diunggah dan database dipulihkan. Cadangan pengaman: '
                .$hasil['cadangan_pengaman']['nama_file'].'.',
            );
        } catch (RuntimeException $exception) {
            return redirect()->route('cadangan-database.index')
                ->withErrors(['pemulihan' => $exception->getMessage()]);
        }
    }

    public function destroy(
        Request $request,
        string $namaFile,
        CadanganDatabaseService $service,
    ): RedirectResponse {
        try {
            $service->hapus($namaFile, $request->user());

            return back()->with('berhasil', 'Cadangan '.$namaFile.' berhasil dihapus.');
        } catch (RuntimeException $exception) {
            return back()->withErrors(['cadangan' => $exception->getMessage()]);
        }
    }

    private function validasiKonfirmasiPemulihan(Request $request): void
    {
        $data = $request->validate([
            'kata_sandi' => ['required', 'string'],
            'konfirmasi' => ['required', 'string', 'in:PULIHKAN'],
        ], [
            'kata_sandi.required' => 'Masukkan kata sandi akun Anda untuk melanjutkan pemulihan.',
            'konfirmasi.required' => 'Ketik PULIHKAN sebagai konfirmasi.',
            'konfirmasi.in' => 'Konfirmasi tidak sesuai. Ketik PULIHKAN dengan huruf kapital.',
        ]);

        if (! Hash::check($data['kata_sandi'], $request->user()->getAuthPassword())) {
            throw ValidationException::withMessages([
                'kata_sandi' => 'Kata sandi akun tidak sesuai.',
            ]);
        }
    }
}
