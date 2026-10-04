<?php

namespace App\Http\Controllers;

use App\Models\AgendaHumas;
use App\Models\BuktiLaporanHumas;
use App\Models\DokumenHumas;
use App\Models\LaporanPelaksanaanHumas;
use App\Models\ProgramKerjaHumas;
use App\Services\Humas\KelolaProgramKerjaHumasService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LaporanPelaksanaanHumasController extends Controller
{
    public function __construct(private readonly KelolaProgramKerjaHumasService $kelola) {}

    public function create(Request $request, ProgramKerjaHumas $program)
    {
        $this->akses($request, $program);
        $this->kelola->pastikanTerbuka($program);

        return $this->form($request, $program, new LaporanPelaksanaanHumas(['tanggal_mulai' => today(), 'tanggal_selesai' => today(), 'pelaksana' => $program->penanggung_jawab, 'jumlah_peserta' => 0]));
    }

    public function store(Request $request, ProgramKerjaHumas $program)
    {
        $this->akses($request, $program);
        $data = $this->validasi($request);
        $laporan = DB::transaction(function () use ($request, $program, $data) {
            $program = $this->kelola->kunci($program);
            $laporan = LaporanPelaksanaanHumas::where('token_pembuatan', $data['token_pembuatan'])->first();
            if ($laporan) {
                $this->akses($request, $program, $laporan);
                abort_unless($laporan->dibuat_oleh_pengguna_id === $request->user()->id, 403);

                return $laporan;
            }
            $this->kelola->pastikanTerbuka($program);
            $laporan = $program->laporan()->make(Arr::only($data, ['token_pembuatan', ...LaporanPelaksanaanHumas::KOLOM]));
            $this->validasiIsi($request, $program, $laporan);
            $laporan->forceFill(['status' => 'draf', 'versi' => 0, 'dibuat_oleh_pengguna_id' => $request->user()->id, 'diubah_oleh_pengguna_id' => $request->user()->id])->save();
            $this->kelola->catat($program, $request, 'Laporan ditambahkan', null, $laporan);

            return $laporan;
        });

        return $this->kembali($program, $laporan, 'Draf laporan pelaksanaan berhasil disimpan.');
    }

    public function show(Request $request, ProgramKerjaHumas $program, LaporanPelaksanaanHumas $laporan)
    {
        $this->akses($request, $program, $laporan);
        $filter = $request->validate(['cari_dokumen' => ['nullable', 'string', 'max:120']]);
        $bolehDokumen = $this->bolehDokumen($request);
        $program->load('tahunPelajaran');
        if ($bolehDokumen) {
            $laporan->load('bukti.berkas');
        }
        $bolehAgenda = $request->user()->memilikiIzin(['agenda_humas.lihat', 'agenda_humas.kelola']);
        $isi = $laporan->status === 'final' ? $laporan->snapshot_final : $laporan->only(LaporanPelaksanaanHumas::KOLOM);
        if ($laporan->status !== 'final') {
            $isi['tanggal_mulai'] = $laporan->tanggal_mulai->format('Y-m-d');
            $isi['tanggal_selesai'] = $laporan->tanggal_selesai->format('Y-m-d');
            $isi['agenda'] = $bolehAgenda ? $laporan->agenda()->value('judul') : null;
        }

        return $this->halaman('laporan-show', ['program' => $program, 'laporan' => $laporan, 'isi' => $isi, 'bolehDokumen' => $bolehDokumen, 'bolehAgenda' => $bolehAgenda,
            'riwayat' => $laporan->riwayat()->with('pengguna:id,nama')->paginate(10)->withQueryString(), 'tokenBukti' => (string) Str::uuid(),
            'pilihanDokumen' => $bolehDokumen && $program->terbuka() && $laporan->status === 'draf' && $request->user()->memilikiIzin('program_kerja_humas.kelola')
                ? DokumenHumas::where('status', 'aktif')->whereIn('tipe_file', ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                    ->when($filter['cari_dokumen'] ?? null, fn ($q, $kata) => $q->whereLike('judul', '%'.$kata.'%'))->latest('id')->paginate(10, ['id', 'judul', 'nama_file_asli'], 'dokumen')->withQueryString() : null]);
    }

    public function edit(Request $request, ProgramKerjaHumas $program, LaporanPelaksanaanHumas $laporan)
    {
        $this->akses($request, $program, $laporan);
        $this->kelola->pastikanTerbuka($program);
        if ($laporan->status !== 'draf') {
            throw ValidationException::withMessages(['status' => 'Buka revisi sebelum mengubah laporan final atau laporan yang dibatalkan.']);
        }

        return $this->form($request, $program, $laporan);
    }

    public function update(Request $request, ProgramKerjaHumas $program, LaporanPelaksanaanHumas $laporan)
    {
        $this->akses($request, $program, $laporan);
        $data = $this->validasi($request, true);
        DB::transaction(function () use ($request, $program, $laporan, $data) {
            $program = $this->kelola->kunci($program);
            $laporan = $this->kelola->kunciLaporan($program, $laporan, $data['versi']);
            $agendaLama = $laporan->agenda_humas_id;
            $laporan->fill(Arr::only($data, LaporanPelaksanaanHumas::KOLOM));
            $this->validasiIsi($request, $program, $laporan, $agendaLama);
            if ($laporan->isDirty()) {
                $this->kelola->ubahLaporan($laporan, $request);
                $this->kelola->catat($program, $request, 'Laporan diperbarui', $data['catatan_perubahan'], $laporan);
            }
        });

        return $this->kembali($program, $laporan, 'Perubahan laporan berhasil disimpan.');
    }

    public function tindakan(Request $request, ProgramKerjaHumas $program, LaporanPelaksanaanHumas $laporan, string $aksi)
    {
        $this->akses($request, $program, $laporan);
        $data = $request->validate(['versi' => ['required', 'integer', 'min:0'], 'catatan_perubahan' => [$aksi === 'finalisasi' ? 'nullable' : 'required', 'string', 'min:5', 'max:2000']]);
        DB::transaction(function () use ($request, $program, $laporan, $data, $aksi) {
            $program = $this->kelola->kunci($program);
            $laporan = $this->kelola->kunciLaporan($program, $laporan, $data['versi'], false);
            if (($aksi === 'revisi' && $laporan->status === 'draf') || ($aksi !== 'revisi' && $laporan->status !== 'draf')) {
                throw ValidationException::withMessages(['status' => 'Tindakan tidak sesuai status laporan. Muat ulang halaman.']);
            }
            if ($aksi === 'finalisasi') {
                $this->kelola->tanggal($program, $laporan->tanggal_mulai, $laporan->tanggal_selesai);
                if ($laporan->tanggal_selesai->gt(today())) {
                    throw ValidationException::withMessages(['tanggal_selesai' => 'Kegiatan yang belum selesai tidak dapat difinalisasi.']);
                }
                foreach ($laporan->bukti()->with('berkas')->get() as $bukti) {
                    if (! Storage::disk('local')->exists($bukti->berkas->lokasi_file)) {
                        throw ValidationException::withMessages(['bukti' => 'Ada berkas bukti yang tidak ditemukan. Perbaiki atau lepas bukti tersebut sebelum finalisasi.']);
                    }
                }
                $laporan->status = 'final';
                $laporan->snapshot_final = $laporan->snapshot();
                $laporan->forceFill(['difinalisasi_pada' => now(), 'difinalisasi_oleh_pengguna_id' => $request->user()->id]);
            } else {
                $laporan->status = $aksi === 'revisi' ? 'draf' : 'dibatalkan';
                $laporan->forceFill(['snapshot_final' => null, 'difinalisasi_pada' => null, 'difinalisasi_oleh_pengguna_id' => null]);
            }
            $this->kelola->ubahLaporan($laporan, $request);
            $this->kelola->catat($program, $request, ['finalisasi' => 'Laporan difinalisasi', 'revisi' => 'Revisi dibuka', 'batalkan' => 'Laporan dibatalkan'][$aksi], $data['catatan_perubahan'] ?? null, $laporan);
        });

        return $this->kembali($program, $laporan, ['finalisasi' => 'Laporan final tersimpan dan masuk rekap realisasi program.', 'revisi' => 'Revisi dibuka. Laporan sementara dikeluarkan dari rekap realisasi.', 'batalkan' => 'Laporan dibatalkan. Riwayat dan bukti tetap tersimpan.'][$aksi]);
    }

    public function tambahBukti(Request $request, ProgramKerjaHumas $program, LaporanPelaksanaanHumas $laporan)
    {
        $this->akses($request, $program, $laporan);
        abort_unless($this->bolehDokumen($request), 403);
        $data = $request->validate(['token_pembuatan' => ['required', 'uuid'], 'versi' => ['required', 'integer', 'min:0'], 'judul' => ['required', 'string', 'max:180'],
            'berkas' => ['nullable', 'required_without:dokumen_humas_id', 'prohibits:dokumen_humas_id', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'dokumen_humas_id' => ['nullable', 'required_without:berkas', 'integer', 'exists:dokumen_humas,id']]);
        if ($request->hasFile('berkas')) {
            abort_unless($request->user()->memilikiIzin('dokumen_humas.kelola'), 403);
        }
        $lokasi = null;
        try {
            DB::transaction(function () use ($request, $program, $laporan, $data, &$lokasi) {
                $program = $this->kelola->kunci($program);
                $lama = BuktiLaporanHumas::where('token_pembuatan', $data['token_pembuatan'])->first();
                if ($lama) {
                    abort_unless($lama->laporan_pelaksanaan_humas_id === $laporan->id, 404);
                    abort_unless($lama->dibuat_oleh_pengguna_id === $request->user()->id, 403);

                    return;
                }
                $laporan = $this->kelola->kunciLaporan($program, $laporan, $data['versi']);
                if ($request->hasFile('berkas')) {
                    $file = $request->file('berkas');
                    $lokasi = $file->storeAs('dokumen-humas', Str::uuid().'.'.$file->guessExtension(), 'local');
                    if (! $lokasi) {
                        throw ValidationException::withMessages(['berkas' => 'Berkas belum dapat disimpan. Periksa ruang penyimpanan.']);
                    }
                    $meta = ['lokasi_file' => $lokasi, 'nama_file_asli' => Str::limit(basename(str_replace('\\', '/', $file->getClientOriginalName())), 240, ''),
                        'tipe_file' => $file->getMimeType(), 'ukuran_file' => $file->getSize()];
                    $dokumen = DokumenHumas::create($meta + ['kategori' => 'laporan_kegiatan', 'judul' => $data['judul'], 'status' => 'aktif',
                        'dibuat_oleh_pengguna_id' => $request->user()->id, 'diubah_oleh_pengguna_id' => $request->user()->id]);
                    $berkas = $dokumen->riwayat()->create($meta + ['versi' => 1, 'catatan' => 'Bukti laporan pelaksanaan Humas.', 'diunggah_oleh_pengguna_id' => $request->user()->id, 'diunggah_pada' => now()]);
                } else {
                    $dokumen = DokumenHumas::lockForUpdate()->findOrFail($data['dokumen_humas_id']);
                    $berkas = $dokumen->riwayat()->first();
                    if ($dokumen->status !== 'aktif' || ! $berkas || ! in_array($berkas->tipe_file, ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'], true) || ! Storage::disk('local')->exists($berkas->lokasi_file)) {
                        throw ValidationException::withMessages(['dokumen_humas_id' => 'Pilih dokumen PDF atau gambar aktif dengan berkas yang tersedia.']);
                    }
                    if ($laporan->bukti()->where('riwayat_dokumen_humas_id', $berkas->id)->exists()) {
                        throw ValidationException::withMessages(['dokumen_humas_id' => 'Versi dokumen ini sudah menjadi bukti laporan.']);
                    }
                }
                $bukti = $laporan->bukti()->make(['token_pembuatan' => $data['token_pembuatan'], 'judul' => $data['judul'], 'riwayat_dokumen_humas_id' => $berkas->id]);
                $bukti->forceFill(['dibuat_oleh_pengguna_id' => $request->user()->id])->save();
                $this->kelola->ubahLaporan($laporan, $request);
                $this->kelola->catat($program, $request, 'Bukti ditambahkan', null, $laporan);
            });
        } catch (\Throwable $e) {
            if ($lokasi) {
                Storage::disk('local')->delete($lokasi);
            }
            throw $e;
        }

        return $this->kembali($program, $laporan, 'Bukti berhasil disimpan.', $request);
    }

    public function lepasBukti(Request $request, ProgramKerjaHumas $program, LaporanPelaksanaanHumas $laporan, BuktiLaporanHumas $bukti)
    {
        $this->akses($request, $program, $laporan);
        abort_unless($this->bolehDokumen($request), 403);
        abort_unless($bukti->laporan_pelaksanaan_humas_id === $laporan->id, 404);
        $data = $request->validate(['versi' => ['required', 'integer', 'min:0'], 'catatan_perubahan' => ['required', 'string', 'min:5', 'max:2000']]);
        DB::transaction(function () use ($request, $program, $laporan, $bukti, $data) {
            $program = $this->kelola->kunci($program);
            $laporan = $this->kelola->kunciLaporan($program, $laporan, $data['versi']);
            abort_unless($laporan->bukti()->whereKey($bukti->id)->exists(), 404);
            $bukti->forceFill(['dilepas_pada' => now()])->save();
            $this->kelola->ubahLaporan($laporan, $request);
            $this->kelola->catat($program, $request, 'Bukti dilepas', $data['catatan_perubahan'], $laporan);
        });

        return $this->kembali($program, $laporan, 'Bukti dilepas dari laporan. Dokumen dan versi sebelumnya tetap tersimpan.');
    }

    public function berkas(Request $request, ProgramKerjaHumas $program, LaporanPelaksanaanHumas $laporan, BuktiLaporanHumas $bukti)
    {
        $this->akses($request, $program, $laporan);
        abort_unless($this->bolehDokumen($request), 403);
        abort_unless($bukti->laporan_pelaksanaan_humas_id === $laporan->id, 404);
        $berkas = $bukti->berkas;
        abort_unless(Storage::disk('local')->exists($berkas->lokasi_file), 404, 'Berkas bukti tidak ditemukan.');

        return Storage::disk('local')->download($berkas->lokasi_file, $berkas->nama_file_asli, ['Content-Type' => $berkas->tipe_file, 'Cache-Control' => 'private, no-store, max-age=0', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function cetak(Request $request, ProgramKerjaHumas $program, LaporanPelaksanaanHumas $laporan)
    {
        $this->akses($request, $program, $laporan);
        $isi = $laporan->status === 'final' ? $laporan->snapshot_final : $laporan->snapshot();

        return $this->halaman('cetak-laporan', ['program' => $program, 'laporan' => $laporan, 'isi' => $isi,
            'bolehDokumen' => $this->bolehDokumen($request), 'bolehAgenda' => $request->user()->memilikiIzin(['agenda_humas.lihat', 'agenda_humas.kelola'])]);
    }

    private function form(Request $request, ProgramKerjaHumas $program, LaporanPelaksanaanHumas $laporan)
    {
        $filter = $request->validate(['cari_agenda' => ['nullable', 'string', 'max:120']]);
        $bolehAgenda = $request->user()->memilikiIzin(['agenda_humas.lihat', 'agenda_humas.kelola']);
        $program->load('tahunPelajaran');

        return $this->halaman('laporan-form', ['program' => $program, 'laporan' => $laporan, 'tokenPembuatan' => (string) Str::uuid(), 'bolehAgenda' => $bolehAgenda,
            'agenda' => $bolehAgenda ? AgendaHumas::where('status', '<>', 'dibatalkan')->when($filter['cari_agenda'] ?? null, fn ($q, $kata) => $q->whereLike('judul', '%'.$kata.'%'))->latest('waktu_mulai')->limit(50)->get(['id', 'judul', 'waktu_mulai']) : collect(),
            'agendaTerpilih' => $bolehAgenda && $laporan->agenda_humas_id ? $laporan->agenda()->first(['id', 'judul', 'waktu_mulai']) : null]);
    }

    private function validasi(Request $request, bool $edit = false): array
    {
        return $request->validate(['agenda_humas_id' => ['nullable', 'integer', 'exists:agenda_humas,id'], 'judul' => ['required', 'string', 'max:180'],
            'tanggal_mulai' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'], 'tanggal_selesai' => ['required', 'date_format:Y-m-d', 'after_or_equal:tanggal_mulai', 'before_or_equal:today'],
            'tempat' => ['required', 'string', 'max:180'], 'pelaksana' => ['required', 'string', 'max:180'], 'jumlah_peserta' => ['required', 'integer', 'min:0', 'max:1000000'],
            'uraian' => ['required', 'string', 'max:10000'], 'hasil' => ['required', 'string', 'max:10000'], 'kendala' => ['nullable', 'string', 'max:10000'], 'tindak_lanjut' => ['nullable', 'string', 'max:10000'],
            'token_pembuatan' => [$edit ? 'nullable' : 'required', 'uuid'], 'versi' => [$edit ? 'required' : 'nullable', 'integer', 'min:0'],
            'catatan_perubahan' => [$edit ? 'required' : 'nullable', 'string', 'min:5', 'max:2000']]);
    }

    private function validasiIsi(Request $request, ProgramKerjaHumas $program, LaporanPelaksanaanHumas $laporan, ?int $agendaLama = null): void
    {
        $this->kelola->tanggal($program, $laporan->tanggal_mulai, $laporan->tanggal_selesai);
        if (! $request->user()->memilikiIzin(['agenda_humas.lihat', 'agenda_humas.kelola'])) {
            abort_unless(($laporan->agenda_humas_id ?? null) === $agendaLama, 403);
        } elseif ($laporan->agenda_humas_id && $laporan->agenda_humas_id !== $agendaLama && AgendaHumas::whereKey($laporan->agenda_humas_id)->where('status', 'dibatalkan')->exists()) {
            throw ValidationException::withMessages(['agenda_humas_id' => 'Agenda yang dibatalkan tidak dapat dihubungkan.']);
        }
    }

    private function akses(Request $request, ProgramKerjaHumas $program, ?LaporanPelaksanaanHumas $laporan = null): void
    {
        abort_unless($request->user()->aktif && ! $request->user()->akunOrangTua() && ! $request->user()->akunSiswa(), 403);
        if ($laporan) {
            abort_unless($laporan->program_kerja_humas_id === $program->id, 404);
        }
    }

    private function bolehDokumen(Request $request): bool
    {
        return $request->user()->memilikiIzin(['dokumen_humas.lihat', 'dokumen_humas.kelola']);
    }

    private function halaman(string $view, array $data)
    {
        return response()->view('program-kerja-humas.'.$view, $data)->header('Cache-Control', 'private, no-store, max-age=0');
    }

    private function kembali(ProgramKerjaHumas $program, LaporanPelaksanaanHumas $laporan, string $pesan, ?Request $request = null)
    {
        $url = route('program-kerja-humas.laporan.show', [$program, $laporan]);
        if ($request?->expectsJson()) {
            $request->session()->flash('berhasil', $pesan);

            return response()->json(['redirect' => $url, 'pesan' => $pesan]);
        }

        return redirect($url)->with('berhasil', $pesan);
    }
}
