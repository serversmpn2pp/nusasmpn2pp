<?php

namespace App\Http\Controllers;

use App\Models\NotifikasiPengguna;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NotifikasiPenggunaController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(['semua', 'belum_dibaca', 'sudah_dibaca'])],
        ]);
        $status = $data['status'] ?? 'semua';

        $notifikasi = $request->user()
            ->notifikasiPengguna()
            ->when($status === 'belum_dibaca', fn ($query) => $query->belumDibaca())
            ->when($status === 'sudah_dibaca', fn ($query) => $query->sudahDibaca())
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('notifikasi.index', [
            'notifikasi' => $notifikasi,
            'status' => $status,
            'jumlahBelumDibaca' => $request->user()->notifikasiPengguna()->belumDibaca()->count(),
        ]);
    }

    public function buka(Request $request, NotifikasiPengguna $notifikasiPengguna): RedirectResponse
    {
        $this->pastikanPemilik($request, $notifikasiPengguna);
        $notifikasiPengguna->tandaiDibaca();

        return redirect()->to($notifikasiPengguna->tautan ?: route('notifikasi.index'));
    }

    public function tandaiDibaca(Request $request, NotifikasiPengguna $notifikasiPengguna): RedirectResponse
    {
        $this->pastikanPemilik($request, $notifikasiPengguna);
        $notifikasiPengguna->tandaiDibaca();

        return back()->with('berhasil', 'Notifikasi ditandai sudah dibaca.');
    }

    public function tandaiSemuaDibaca(Request $request): RedirectResponse
    {
        $jumlah = $request->user()
            ->notifikasiPengguna()
            ->belumDibaca()
            ->update(['dibaca_pada' => now(), 'updated_at' => now()]);

        $pesan = $jumlah > 0
            ? "{$jumlah} notifikasi ditandai sudah dibaca."
            : 'Tidak ada notifikasi baru.';

        return back()->with('berhasil', $pesan);
    }

    public function ringkasan(Request $request): JsonResponse
    {
        return response()->json([
            'jumlah_belum_dibaca' => $request->user()->notifikasiPengguna()->belumDibaca()->count(),
        ]);
    }

    private function pastikanPemilik(Request $request, NotifikasiPengguna $notifikasiPengguna): void
    {
        abort_unless((int) $notifikasiPengguna->pengguna_id === (int) $request->user()->id, 403);
    }
}
