<?php

namespace App\Http\Controllers;

use App\Models\AgendaHumas;
use App\Models\DokumenHumas;
use App\Models\MitraHumas;
use App\Models\PesertaPertemuanHumas;
use App\Models\ProgramKomiteHumas;
use App\Models\TindakLanjutAgendaHumas;
use App\Services\Humas\KelolaAgendaHumasService;
use App\Services\Humas\KelolaKemitraanHumasService;
use App\Support\QrCodeSvg;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AgendaHumasController extends Controller
{
    public function index(Request $request)
    {
        $filter = $request->validate([
            'kata_kunci' => ['nullable', 'string', 'max:120'],
            'jenis' => ['nullable', Rule::in(array_keys(AgendaHumas::JENIS))],
            'status' => ['nullable', Rule::in(array_keys(AgendaHumas::STATUS))],
            'dari' => ['nullable', 'date_format:Y-m-d'],
            'sampai' => ['nullable', 'date_format:Y-m-d', Rule::when(filled($request->input('dari')), 'after_or_equal:dari')],
        ]);
        $agenda = AgendaHumas::query()
            ->withCount(['peserta', 'peserta as hadir_count' => fn ($q) => $q->where('status_kehadiran', 'hadir'),
                'tindakLanjut as tertunda_count' => fn ($q) => $q->where('status', '<>', 'selesai')])
            ->when($filter['kata_kunci'] ?? null, fn ($q, $kata) => $q->where(fn ($q) => $q
                ->whereLike('judul', '%'.$kata.'%')->orWhereLike('tempat', '%'.$kata.'%')->orWhereLike('sasaran', '%'.$kata.'%')))
            ->when($filter['jenis'] ?? null, fn ($q, $jenis) => $q->where('jenis', $jenis))
            ->when($filter['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filter['dari'] ?? null, fn ($q, $dari) => $q->whereDate('waktu_mulai', '>=', $dari))
            ->when($filter['sampai'] ?? null, fn ($q, $sampai) => $q->whereDate('waktu_mulai', '<=', $sampai))
            ->orderByRaw("CASE WHEN status = 'terjadwal' AND waktu_selesai >= ? THEN 0 ELSE 1 END", [now()])
            ->orderByRaw("CASE WHEN status = 'terjadwal' AND waktu_selesai >= ? THEN waktu_mulai END ASC", [now()])
            ->orderByDesc('waktu_mulai')->paginate(15)->withQueryString();

        return view('agenda-humas.index', [
            'agenda' => $agenda, 'filter' => $filter,
            'jumlahMendatang' => AgendaHumas::where('status', 'terjadwal')->where('waktu_selesai', '>=', now())->count(),
            'jumlahSelesai' => AgendaHumas::where('status', 'selesai')->count(),
            'jumlahLewat' => AgendaHumas::where('status', 'terjadwal')->where('waktu_selesai', '<', now())->count(),
            'jumlahTerlambat' => TindakLanjutAgendaHumas::where('status', '<>', 'selesai')
                ->whereDate('batas_tanggal', '<', today())->whereHas('agenda', fn ($q) => $q->where('status', '<>', 'dibatalkan'))->count(),
        ]);
    }

    public function create(Request $request)
    {
        $data = $request->validate(['mitra_humas_id' => ['nullable', 'integer', 'exists:mitra_humas,id', 'prohibits:program_komite_humas_id'],
            'program_komite_humas_id' => ['nullable', 'integer', 'exists:program_komite_humas,id']]);
        $program = $this->programUntukRapat($request, $data['program_komite_humas_id'] ?? null);
        $mitra = ! empty($data['mitra_humas_id']) ? MitraHumas::findOrFail($data['mitra_humas_id']) : null;
        if ($mitra) {
            abort_unless($request->user()->memilikiIzin('kemitraan_humas.kelola'), 403);
            app(KelolaKemitraanHumasService::class)->pastikanMitraAktif($mitra);
        }

        return view('agenda-humas.form', ['agendaHumas' => new AgendaHumas(['jenis' => $program ? 'komite' : ($mitra ? 'kemitraan' : null),
            'judul' => $program ? mb_substr('Rapat - '.$program->nama, 0, 180) : null, 'sasaran' => $program ? 'Pengurus komite sekolah' : $mitra?->nama,
            'topik' => $program?->target_hasil]), 'mitraTerkait' => $mitra, 'programTerkait' => $program]);
    }

    public function store(Request $request)
    {
        $agenda = app(KelolaAgendaHumasService::class)->store($request);

        return redirect()->route('agenda-humas.show', $agenda)->with('berhasil', 'Agenda berhasil dibuat.');
    }

    public function show(Request $request, AgendaHumas $agendaHumas)
    {
        $tab = in_array($request->query('tab'), ['peserta', 'qr', 'notulen', 'tindak-lanjut', 'dokumen'])
            ? $request->query('tab') : 'ringkasan';
        $agendaHumas->load(['tindakLanjut', 'dokumen', 'pengubah:id,nama']);
        $data = $request->validate(['cari_dokumen' => ['nullable', 'string', 'max:120']]);
        $bolehLihatDokumen = $request->user()->memilikiIzin(['dokumen_humas.lihat', 'dokumen_humas.kelola']);

        return view('agenda-humas.show', [
            'agendaHumas' => $agendaHumas, 'tab' => $tab,
            'mitraTerkait' => $request->user()->memilikiIzin(['kemitraan_humas.lihat', 'kemitraan_humas.kelola'])
                ? $agendaHumas->mitra()->get(['mitra_humas.id', 'nama']) : collect(),
            'programKomiteTerkait' => $request->user()->memilikiIzin(['komite_humas.lihat', 'komite_humas.kelola']) && ! $request->user()->akunOrangTua() && ! $request->user()->akunSiswa()
                ? $agendaHumas->programKomite()->with('periode:id,nama')->get(['program_komite_humas.id', 'nama', 'periode_komite_humas_id']) : collect(),
            'rekapPresensi' => $agendaHumas->rekapPresensi(),
            'jumlahUndanganQr' => $tab === 'qr' ? $agendaHumas->peserta()->whereNotNull('orang_tua_wali_id')->count() : 0,
            'qrSvg' => $tab === 'qr' && $agendaHumas->token_presensi
                ? QrCodeSvg::svg(route('presensi-pertemuan.masuk', $agendaHumas->token_presensi)) : null,
            'daftarPeserta' => $tab === 'peserta' ? $agendaHumas->peserta()->paginate(50, ['*'], 'halaman_peserta')->withQueryString() : null,
            'pilihanDokumen' => $tab === 'dokumen' && $bolehLihatDokumen
                ? DokumenHumas::where('status', 'aktif')->whereNotIn('id', $agendaHumas->dokumen->modelKeys())
                    ->when($data['cari_dokumen'] ?? null, fn ($q, $kata) => $q->where(fn ($q) => $q
                        ->whereLike('judul', '%'.$kata.'%')->orWhereLike('nomor_dokumen', '%'.$kata.'%')))
                    ->latest('updated_at')->limit(50)->get(['id', 'judul', 'kategori', 'nomor_dokumen']) : collect(),
        ]);
    }

    public function edit(AgendaHumas $agendaHumas)
    {
        return view('agenda-humas.form', compact('agendaHumas'));
    }

    public function update(Request $request, AgendaHumas $agendaHumas)
    {
        app(KelolaAgendaHumasService::class)->update($request, $agendaHumas);

        return $this->kembali($agendaHumas, 'ringkasan', 'Informasi agenda berhasil diperbarui.');
    }

    public function simpanNotulen(Request $request, AgendaHumas $agendaHumas)
    {
        app(KelolaAgendaHumasService::class)->simpanNotulen($request, $agendaHumas);

        return $this->kembali($agendaHumas, 'notulen', 'Notulen berhasil disimpan.');
    }

    public function ubahStatus(Request $request, AgendaHumas $agendaHumas)
    {
        app(KelolaAgendaHumasService::class)->ubahStatus($request, $agendaHumas);

        return $this->kembali($agendaHumas, 'ringkasan', 'Status agenda berhasil diperbarui.');
    }

    public function tambahPeserta(Request $request, AgendaHumas $agendaHumas)
    {
        app(KelolaAgendaHumasService::class)->tambahPeserta($request, $agendaHumas);

        return $this->kembali($agendaHumas, 'peserta', 'Peserta berhasil ditambahkan.');
    }

    public function simpanPresensi(Request $request, AgendaHumas $agendaHumas)
    {
        app(KelolaAgendaHumasService::class)->simpanPresensi($request, $agendaHumas);

        return $this->kembali($agendaHumas, 'peserta', 'Kehadiran peserta berhasil disimpan.');
    }

    public function hapusPeserta(AgendaHumas $agendaHumas, PesertaPertemuanHumas $pesertaPertemuanHumas)
    {
        app(KelolaAgendaHumasService::class)->hapusPeserta($agendaHumas, $pesertaPertemuanHumas);

        return $this->kembali($agendaHumas, 'peserta', 'Peserta dihapus dari daftar undangan.');
    }

    public function tambahTindakLanjut(Request $request, AgendaHumas $agendaHumas)
    {
        app(KelolaAgendaHumasService::class)->tambahTindakLanjut($request, $agendaHumas);

        return $this->kembali($agendaHumas, 'tindak-lanjut', 'Tindak lanjut berhasil ditambahkan.');
    }

    public function perbaruiTindakLanjut(Request $request, AgendaHumas $agendaHumas, TindakLanjutAgendaHumas $tindakLanjutAgendaHumas)
    {
        app(KelolaAgendaHumasService::class)->perbaruiTindakLanjut($request, $agendaHumas, $tindakLanjutAgendaHumas);

        return $this->kembali($agendaHumas, 'tindak-lanjut', 'Tindak lanjut berhasil diperbarui.');
    }

    public function hubungkanDokumen(Request $request, AgendaHumas $agendaHumas)
    {
        app(KelolaAgendaHumasService::class)->hubungkanDokumen($request, $agendaHumas);

        return $this->kembali($agendaHumas, 'dokumen', 'Dokumen berhasil dihubungkan dengan agenda.');
    }

    public function lepasDokumen(AgendaHumas $agendaHumas, DokumenHumas $dokumenHumas)
    {
        app(KelolaAgendaHumasService::class)->lepasDokumen($agendaHumas, $dokumenHumas);

        return $this->kembali($agendaHumas, 'dokumen', 'Hubungan dokumen dilepas. Berkas tetap tersimpan di Pusat Dokumen Humas.');
    }

    public function cetak(Request $request, AgendaHumas $agendaHumas)
    {
        $data = $request->validate(['jenis' => ['required', Rule::in(['daftar-hadir', 'notulen'])]]);
        $agendaHumas->load(['peserta', 'tindakLanjut']);

        return view('agenda-humas.cetak', ['agendaHumas' => $agendaHumas, 'jenisCetak' => $data['jenis']]);
    }

    private function programUntukRapat(Request $request, ?int $id): ?ProgramKomiteHumas
    {
        if (! $id) {
            return null;
        }
        abort_unless($request->user()->memilikiIzin('komite_humas.kelola') && $request->user()->aktif && ! $request->user()->akunOrangTua() && ! $request->user()->akunSiswa(), 403);
        $program = ProgramKomiteHumas::with('periode')->findOrFail($id);
        if ($program->periode->status === 'arsip' || $program->status === 'dibatalkan') {
            throw ValidationException::withMessages(['program_komite_humas_id' => 'Program dibatalkan atau kepengurusan diarsipkan. Rapat baru tidak dapat ditambahkan.']);
        }

        return $program;
    }

    private function kembali(AgendaHumas $agenda, string $tab, string $pesan)
    {
        return redirect()->route('agenda-humas.show', [$agenda, 'tab' => $tab])->with('berhasil', $pesan);
    }
}
