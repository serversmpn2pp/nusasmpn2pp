<?php

namespace App\Http\Controllers;

use App\Models\AgendaHumas;
use App\Models\Kelas;
use App\Models\OrangTuaWali;
use App\Models\PesertaPertemuanHumas;
use App\Models\TahunPelajaran;
use App\Services\Humas\PresensiPertemuanHumasService;
use App\Services\Humas\UndanganOrangTuaHumasService;
use App\Support\QrCodeSvg;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PresensiPertemuanHumasController extends Controller
{
    public function undangan(Request $request, AgendaHumas $agendaHumas, UndanganOrangTuaHumasService $service)
    {
        $request->validate(['tahun_pelajaran_id' => ['nullable', 'integer', 'exists:tahun_pelajaran,id']]);
        $filter = $request->has('cakupan') ? $this->filterUndangan($request) : null;

        return view('agenda-humas.undangan', $this->dataPilihan($request, $agendaHumas) + [
            'filter' => $filter, 'pratinjau' => $filter ? $service->pratinjau($filter) : null,
        ]);
    }

    public function simpanUndangan(Request $request, AgendaHumas $agendaHumas, UndanganOrangTuaHumasService $service)
    {
        $hasil = $service->tambahkan($agendaHumas, $this->filterUndangan($request));

        return redirect()->route('agenda-humas.show', [$agendaHumas, 'tab' => 'qr'])->with('berhasil',
            $hasil['baru'].' undangan baru ditambahkan; '.$hasil['sudahAda'].' akun sudah diundang. '
            .$hasil['tanpaAkun'].' siswa belum memiliki akun orang tua aktif.');
    }

    public function ubahAkses(Request $request, AgendaHumas $agendaHumas)
    {
        app(PresensiPertemuanHumasService::class)->ubahAkses($request, $agendaHumas);

        return redirect()->route('agenda-humas.show', [$agendaHumas, 'tab' => 'qr'])
            ->with('berhasil', $request->boolean('dibuka') ? 'Presensi QR dibuka.' : 'Presensi QR ditutup.');
    }

    public function pantau(AgendaHumas $agendaHumas)
    {
        return response()->json(['rekap' => $agendaHumas->rekapPresensi(), 'dibuka' => $agendaHumas->menerimaPresensiQr(),
            'terbaru' => $agendaHumas->peserta()->where('status_kehadiran', 'hadir')->whereNotNull('hadir_pada')
                ->reorder()->latest('hadir_pada')->limit(8)->get(['nama', 'hadir_pada', 'sumber_kehadiran'])]);
    }

    public function cetak(AgendaHumas $agendaHumas)
    {
        abort_unless($agendaHumas->token_presensi, 404);

        return view('agenda-humas.cetak-qr', ['agendaHumas' => $agendaHumas,
            'qrSvg' => QrCodeSvg::svg(route('presensi-pertemuan.masuk', $agendaHumas->token_presensi), 300)]);
    }

    // Keep a narrow, server-generated destination through login and mandatory password changes.
    public function masuk(Request $request, string $token)
    {
        AgendaHumas::where('token_presensi', $token)->firstOrFail(['id']);
        $request->session()->put('humas.presensi_token', $token);

        return redirect()->route($request->user() ? 'pertemuan-saya.show' : 'login', $request->user() ? ['token' => $token] : [])
            ->withHeaders(['Cache-Control' => 'no-store, private']);
    }

    public function indexOrangTua(Request $request)
    {
        $wali = $this->wali($request);
        $tab = $request->query('tab') === 'riwayat' ? 'riwayat' : 'mendatang';
        $undangan = PesertaPertemuanHumas::where('orang_tua_wali_id', $wali->id)->with('agenda')
            ->whereHas('agenda', fn ($q) => $tab === 'mendatang'
                ? $q->where('status', 'terjadwal')->where('waktu_selesai', '>=', now())
                : $q->where(fn ($q) => $q->where('status', '<>', 'terjadwal')->orWhere('waktu_selesai', '<', now())))
            ->latest('id')->paginate(15)->withQueryString();

        return view('pertemuan-saya.index', compact('undangan', 'tab'));
    }

    public function showOrangTua(Request $request, string $token)
    {
        if (! $request->user()->aktif || ! $request->user()->akunOrangTua()) {
            return response()->view('pertemuan-saya.akses', [], 403);
        }
        $wali = $this->wali($request);
        $agendaHumas = AgendaHumas::where('token_presensi', $token)->firstOrFail();
        $peserta = $agendaHumas->peserta()->where('orang_tua_wali_id', $wali->id)->first();
        $request->session()->forget('humas.presensi_token');
        if ($peserta && ! $this->masihTerhubung($wali, $peserta)) {
            $peserta = null;
        }

        return view('pertemuan-saya.show', compact('agendaHumas', 'peserta'));
    }

    public function konfirmasi(Request $request, string $token)
    {
        app(PresensiPertemuanHumasService::class)->konfirmasi($request, $token);

        return redirect()->route('pertemuan-saya.show', $token)->with('berhasil', 'Kehadiran Anda telah tercatat. Terima kasih.');
    }

    private function wali(Request $request): OrangTuaWali
    {
        abort_unless($request->user()->aktif && $request->user()->akunOrangTua(), 403, 'Gunakan akun orang tua/wali yang aktif.');

        return $request->user()->orangTuaWali;
    }

    private function masihTerhubung(OrangTuaWali $wali, PesertaPertemuanHumas $peserta): bool
    {
        return $wali->siswa()->whereIn('siswa.id', array_column($peserta->anak_undangan ?? [], 'siswa_id'))->exists();
    }

    private function dataPilihan(Request $request, AgendaHumas $agenda): array
    {
        $tahun = TahunPelajaran::orderByDesc('aktif')->orderByDesc('tanggal_mulai')->get(['id', 'nama', 'aktif']);
        $tahunId = (int) $request->input('tahun_pelajaran_id', $tahun->first()?->id);

        return ['agendaHumas' => $agenda, 'tahunPelajaran' => $tahun, 'tahunId' => $tahunId,
            'kelas' => Kelas::where('tahun_pelajaran_id', $tahunId)->where('aktif', true)->orderBy('tingkat')->orderBy('nama')->get(['id', 'nama', 'tingkat'])];
    }

    private function filterUndangan(Request $request): array
    {
        return $request->validate([
            'tahun_pelajaran_id' => ['required', 'integer', 'exists:tahun_pelajaran,id'],
            'cakupan' => ['required', Rule::in(['kelas', 'tingkat', 'seluruh'])],
            'tingkat' => ['exclude_unless:cakupan,tingkat', 'required', 'integer', Rule::exists('kelas', 'tingkat')
                ->where('tahun_pelajaran_id', $request->input('tahun_pelajaran_id'))->where('aktif', true)],
            'kelas_ids' => ['exclude_unless:cakupan,kelas', 'required', 'array', 'min:1', 'max:100'],
            'kelas_ids.*' => ['integer', 'distinct', Rule::exists('kelas', 'id')
                ->where('tahun_pelajaran_id', $request->input('tahun_pelajaran_id'))->where('aktif', true)],
        ]);
    }
}
