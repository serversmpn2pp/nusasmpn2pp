<?php

namespace App\Http\Controllers;

use App\Models\PenguranganPoinSiswa;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Services\Pembinaan\ProsesPoinSiswaService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PenguranganPoinSiswaController extends Controller
{
    public function __construct(private ProsesPoinSiswaService $prosesPoinSiswaService)
    {
    }

    public function index(Request $request)
    {
        $status = (string) $request->input('status', 'semua');
        $tahunPelajaranId = $request->integer('tahun_pelajaran_id') ?: TahunPelajaran::where('aktif', true)->value('id');

        $pengurangan = PenguranganPoinSiswa::query()
            ->with(['siswa:id,nama_lengkap,nisn', 'tahunPelajaran:id,nama', 'diajukanOlehPengguna:id,nama', 'disetujuiOlehPegawai:id,nama_lengkap'])
            ->when($status !== 'semua', fn ($query) => $query->where('status', $status))
            ->when($tahunPelajaranId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId))
            ->latest('tanggal_kegiatan')
            ->paginate(15)
            ->withQueryString();

        $daftarSiswa = Siswa::where('aktif', true)->orderBy('nama_lengkap')->get(['id', 'nama_lengkap', 'nisn']);
        $daftarTahunPelajaran = TahunPelajaran::orderByDesc('aktif')->orderByDesc('tanggal_mulai')->get();

        return view('pengurangan-poin-siswa.index', compact('pengurangan', 'daftarSiswa', 'daftarTahunPelajaran', 'status', 'tahunPelajaranId'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'siswa_id' => ['required', 'integer', Rule::exists('siswa', 'id')->where('aktif', true)],
            'tahun_pelajaran_id' => ['required', 'integer', Rule::exists('tahun_pelajaran', 'id')],
            'tanggal_kegiatan' => ['required', 'date'],
            'jenis_kegiatan' => ['required', 'string', 'max:160'],
            'deskripsi' => ['nullable', 'string'],
            'poin_pengurangan' => ['required', 'integer', Rule::in([10, 15, 20, 30])],
            'bukti' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
        ]);

        if ($request->hasFile('bukti')) {
            $data['bukti'] = $request->file('bukti')->store('bukti-pengurangan-poin', 'public');
        }
        $data['deskripsi'] = filled($data['deskripsi'] ?? null) ? trim($data['deskripsi']) : null;
        $data['status'] = 'diajukan';
        $data['diajukan_oleh_pengguna_id'] = auth()->id();

        PenguranganPoinSiswa::create($data);

        return back()->with('berhasil', 'Pengurangan poin diajukan untuk persetujuan Wakil Kesiswaan.');
    }

    public function putuskan(Request $request, PenguranganPoinSiswa $penguranganPoinSiswa)
    {
        abort_unless($request->user()?->administrator() || $request->user()?->memilikiIzin('poin_siswa.putus_konflik'), 403);
        abort_if($penguranganPoinSiswa->status !== 'diajukan', 422, 'Pengajuan ini sudah diputuskan.');

        $data = $request->validate([
            'keputusan' => ['required', Rule::in(['disetujui', 'ditolak'])],
            'catatan_keputusan' => ['nullable', 'string'],
        ]);

        if ($data['keputusan'] === 'disetujui') {
            $diterapkan = $this->prosesPoinSiswaService->setujuiPengurangan(
                $penguranganPoinSiswa,
                $request->user()?->pegawai_id,
                $data['catatan_keputusan'] ?? null,
            );

            return back()->with('berhasil', "Pengurangan disetujui. {$diterapkan} poin diterapkan tanpa membuat saldo negatif.");
        }

        $penguranganPoinSiswa->update([
            'status' => 'ditolak',
            'disetujui_oleh_pegawai_id' => $request->user()?->pegawai_id,
            'diputuskan_pada' => now(),
            'catatan_keputusan' => filled($data['catatan_keputusan'] ?? null) ? trim($data['catatan_keputusan']) : null,
        ]);

        return back()->with('berhasil', 'Pengajuan pengurangan poin ditolak.');
    }
}
