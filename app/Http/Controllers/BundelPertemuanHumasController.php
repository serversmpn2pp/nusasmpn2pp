<?php

namespace App\Http\Controllers;

use App\Models\AgendaHumas;
use App\Models\RiwayatBundelPertemuanHumas;
use App\Services\Humas\BundelPertemuanHumasService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BundelPertemuanHumasController extends Controller
{
    public function __construct(private readonly BundelPertemuanHumasService $kelola) {}

    public function show(Request $request, AgendaHumas $agendaHumas)
    {
        $this->akses($request);

        return response()->view('bundel-pertemuan-humas.show', $this->kelola->konteks($agendaHumas, $request->user()) + [
            'bolehUnduh' => $request->user()->memilikiIzin('agenda_humas.bundel'),
            'riwayat' => RiwayatBundelPertemuanHumas::where('agenda_humas_id', $agendaHumas->id)->with('pengguna:id,nama')->latest('id')->paginate(10),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function cetak(Request $request, AgendaHumas $agendaHumas)
    {
        $this->akses($request);
        $data = DB::transaction(function () use ($request, $agendaHumas) {
            $a = AgendaHumas::sharedLock()->findOrFail($agendaHumas->id);
            $konteks = $this->kelola->konteks($a, $request->user());

            return $this->kelola->isi($konteks, $this->kelola->pilihan($request, $konteks));
        });

        return response()->view('bundel-pertemuan-humas.cetak', $data)->header('Cache-Control', 'private, no-store');
    }

    public function export(Request $request, AgendaHumas $agendaHumas)
    {
        $this->akses($request);
        [$path, $nama] = $this->kelola->export($request, $agendaHumas);

        return response()->download($path, $nama, ['Content-Type' => 'application/zip', 'Cache-Control' => 'private, no-store'])->deleteFileAfterSend(true);
    }

    private function akses(Request $r): void
    {
        abort_unless($r->user()->aktif && ! $r->user()->akunOrangTua() && ! $r->user()->akunSiswa()
            && $r->user()->memilikiIzin(['agenda_humas.lihat', 'agenda_humas.kelola']), 403);
    }
}
