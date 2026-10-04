<?php

namespace App\Http\Controllers\Api\V1\Humas;

use App\Models\AgendaHumas;
use App\Models\PesertaPertemuanHumas;
use App\Models\TindakLanjutAgendaHumas;
use App\Services\Humas\KelolaAgendaHumasService;
use App\Services\Humas\PresensiPertemuanHumasService;
use App\Services\Humas\UndanganOrangTuaHumasService;
use App\Services\Mobile\HumasMobileService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AgendaController extends HumasController
{
    public function __construct(private readonly HumasMobileService $mobile, private readonly KelolaAgendaHumasService $kelola) {}

    public function index(Request $r)
    {
        $this->staff($r, ['agenda_humas.lihat', 'agenda_humas.kelola']);
        $filter = $r->validate(['kata_kunci' => ['nullable', 'string', 'max:120'], 'jenis' => ['nullable', Rule::in(array_keys(AgendaHumas::JENIS))],
            'status' => ['nullable', Rule::in(array_keys(AgendaHumas::STATUS))], 'dari' => ['nullable', 'date_format:Y-m-d'],
            'sampai' => ['nullable', 'date_format:Y-m-d', Rule::when($r->filled('dari'), 'after_or_equal:dari')]]) + $this->halaman($r);
        $q = AgendaHumas::withCount(['peserta', 'peserta as hadir_count' => fn ($q) => $q->where('status_kehadiran', 'hadir'),
            'tindakLanjut as tertunda_count' => fn ($q) => $q->where('status', '!=', 'selesai')]);
        foreach (['jenis', 'status'] as $key) {
            $q->when($filter[$key] ?? null, fn ($q, $value) => $q->where($key, $value));
        }
        $q->when($filter['kata_kunci'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q->whereLike('judul', '%'.$s.'%')->orWhereLike('tempat', '%'.$s.'%')))
            ->when($filter['dari'] ?? null, fn ($q, $d) => $q->whereDate('waktu_mulai', '>=', $d))
            ->when($filter['sampai'] ?? null, fn ($q, $d) => $q->whereDate('waktu_mulai', '<=', $d));

        return $this->json($this->mobile->paginasi($q->orderByDesc('waktu_mulai')->orderByDesc('id')->paginate($filter['per_halaman'] ?? 15, ['*'], 'halaman', $filter['halaman'] ?? 1), fn ($a) => $this->mobile->agenda($a))
            + ['filter' => $filter, 'pilihan' => ['jenis' => AgendaHumas::JENIS, 'status' => AgendaHumas::STATUS], 'hak_akses' => ['dapat_kelola' => $r->user()->memilikiIzin('agenda_humas.kelola')]]);
    }

    public function show(Request $r, AgendaHumas $agendaHumas)
    {
        $this->staff($r, ['agenda_humas.lihat', 'agenda_humas.kelola']);
        $data = $this->mobile->agenda($agendaHumas, true);
        $data['hak_akses'] = ['dapat_kelola' => $r->user()->memilikiIzin('agenda_humas.kelola')];
        if ($data['hak_akses']['dapat_kelola']) {
            $data['qr'] = ['token' => $agendaHumas->token_presensi, 'url' => $agendaHumas->token_presensi ? route('presensi-pertemuan.masuk', $agendaHumas->token_presensi) : null];
        }

        return $this->json($data);
    }

    public function peserta(Request $r, AgendaHumas $agendaHumas)
    {
        $this->staff($r, ['agenda_humas.lihat', 'agenda_humas.kelola']);
        $filter = $r->validate(['kata_kunci' => ['nullable', 'string', 'max:120'], 'status_kehadiran' => ['nullable', Rule::in(array_keys(PesertaPertemuanHumas::KEHADIRAN))]]) + $this->halaman($r);
        $q = $agendaHumas->peserta()->when($filter['kata_kunci'] ?? null, fn ($q, $s) => $q->whereLike('nama', '%'.$s.'%'))
            ->when($filter['status_kehadiran'] ?? null, fn ($q, $s) => $q->where('status_kehadiran', $s));

        return $this->json($this->mobile->paginasi($q->paginate($filter['per_halaman'] ?? 50, ['*'], 'halaman', $filter['halaman'] ?? 1), fn ($p) => $this->mobile->peserta($p)) + ['rekap_presensi' => $agendaHumas->rekapPresensi()]);
    }

    public function tindakLanjut(Request $r, AgendaHumas $agendaHumas)
    {
        $this->staff($r, ['agenda_humas.lihat', 'agenda_humas.kelola']);
        $p = $this->halaman($r);

        return $this->json($this->mobile->paginasi($agendaHumas->tindakLanjut()->paginate($p['per_halaman'] ?? 50, ['*'], 'halaman', $p['halaman'] ?? 1), fn ($t) => $t->only(['id', 'uraian', 'penanggung_jawab', 'batas_tanggal', 'status', 'catatan', 'selesai_pada'])));
    }

    public function store(Request $r)
    {
        $this->staff($r, 'agenda_humas.kelola');

        return $this->json($this->mobile->agenda($this->kelola->store($r), true), 'Agenda berhasil dibuat.', 201);
    }

    public function update(Request $r, AgendaHumas $agendaHumas)
    {
        $this->staff($r, 'agenda_humas.kelola');

        return $this->json($this->mobile->agenda($this->kelola->update($r, $agendaHumas), true), 'Agenda diperbarui.');
    }

    public function notulen(Request $r, AgendaHumas $agendaHumas)
    {
        $this->staff($r, 'agenda_humas.kelola');

        return $this->json($this->mobile->agenda($this->kelola->simpanNotulen($r, $agendaHumas), true), 'Notulen tersimpan.');
    }

    public function status(Request $r, AgendaHumas $agendaHumas)
    {
        $this->staff($r, 'agenda_humas.kelola');

        return $this->json($this->mobile->agenda($this->kelola->ubahStatus($r, $agendaHumas), true), 'Status agenda diperbarui.');
    }

    public function tambahPeserta(Request $r, AgendaHumas $agendaHumas)
    {
        $this->staff($r, 'agenda_humas.kelola');
        $this->kelola->tambahPeserta($r, $agendaHumas);

        return $this->json(['rekap_presensi' => $agendaHumas->rekapPresensi()], 'Peserta ditambahkan.', 201);
    }

    public function presensi(Request $r, AgendaHumas $agendaHumas)
    {
        $this->staff($r, 'agenda_humas.kelola');
        $this->kelola->simpanPresensi($r, $agendaHumas);

        return $this->json(['rekap_presensi' => $agendaHumas->rekapPresensi()], 'Presensi tersimpan.');
    }

    public function hapusPeserta(Request $r, AgendaHumas $agendaHumas, PesertaPertemuanHumas $pesertaPertemuanHumas)
    {
        $this->staff($r, 'agenda_humas.kelola');
        $this->kelola->hapusPeserta($agendaHumas, $pesertaPertemuanHumas);

        return $this->json(['rekap_presensi' => $agendaHumas->rekapPresensi()], 'Peserta dihapus.');
    }

    public function tambahTindak(Request $r, AgendaHumas $agendaHumas)
    {
        $this->staff($r, 'agenda_humas.kelola');
        $this->kelola->tambahTindakLanjut($r, $agendaHumas);

        return $this->json(['id' => $agendaHumas->id], 'Tindak lanjut ditambahkan.', 201);
    }

    public function ubahTindak(Request $r, AgendaHumas $agendaHumas, TindakLanjutAgendaHumas $tindakLanjutAgendaHumas)
    {
        $this->staff($r, 'agenda_humas.kelola');
        $this->kelola->perbaruiTindakLanjut($r, $agendaHumas, $tindakLanjutAgendaHumas);

        return $this->json(['id' => $agendaHumas->id], 'Tindak lanjut diperbarui.');
    }

    public function undangan(Request $r, AgendaHumas $agendaHumas, UndanganOrangTuaHumasService $service)
    {
        $this->staff($r, 'agenda_humas.kelola');
        $data = $r->validate(['tahun_pelajaran_id' => ['required', 'integer', 'exists:tahun_pelajaran,id'], 'cakupan' => ['required', Rule::in(['kelas', 'tingkat', 'seluruh'])],
            'tingkat' => ['exclude_unless:cakupan,tingkat', 'required', 'integer', Rule::exists('kelas', 'tingkat')->where('tahun_pelajaran_id', $r->input('tahun_pelajaran_id'))->where('aktif', true)],
            'kelas_ids' => ['exclude_unless:cakupan,kelas', 'required', 'array', 'min:1', 'max:100'],
            'kelas_ids.*' => ['integer', 'distinct', Rule::exists('kelas', 'id')->where('tahun_pelajaran_id', $r->input('tahun_pelajaran_id'))->where('aktif', true)]]);

        return $this->json($service->tambahkan($agendaHumas, $data), 'Undangan ditambahkan.');
    }

    public function aksesPresensi(Request $r, AgendaHumas $agendaHumas, PresensiPertemuanHumasService $service)
    {
        $this->staff($r, 'agenda_humas.kelola');

        return $this->json($this->mobile->agenda($service->ubahAkses($r, $agendaHumas), true), 'Akses presensi diperbarui.');
    }
}
