<?php

namespace App\Http\Controllers;

use App\Models\BuktiRuangUjianCbt;
use App\Models\PengawasRuangUjianTerpusat;
use App\Models\Pengguna;
use App\Models\RuangUjianCbt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TugasPengawasUjianController extends Controller
{
    public function index(Request $request)
    {
        $pengguna = $request->user();
        abort_unless($pengguna?->pegawai_id, 403);

        $tugas = PengawasRuangUjianTerpusat::query()
            ->where(function ($query) use ($pengguna) {
                $query->where('pengawas_utama_pegawai_id', $pengguna->pegawai_id)
                    ->orWhere('pengawas_pendamping_pegawai_id', $pengguna->pegawai_id);
            })
            ->with([
                'jadwalUjianCbt.kegiatanUjianCbt.jenisUjianCbt',
                'jadwalUjianCbt.kegiatanUjianCbt.tahunPelajaran',
                'jadwalUjianCbt.mataPelajaran',
                'ruangKegiatanUjianCbt',
                'pengawasUtama',
                'pengawasPendamping',
            ])
            ->get();

        $ruangOperasional = RuangUjianCbt::query()
            ->whereIn('jadwal_ujian_cbt_id', $tugas->pluck('jadwal_ujian_cbt_id')->filter())
            ->whereIn('ruang_kegiatan_ujian_cbt_id', $tugas->pluck('ruang_kegiatan_ujian_cbt_id')->filter())
            ->withCount([
                'pesertaUjianCbt',
                'buktiRuangUjianCbt as bukti_daftar_hadir_count' => fn ($query) => $query->where('jenis', BuktiRuangUjianCbt::JENIS_DAFTAR_HADIR),
                'buktiRuangUjianCbt as bukti_berita_acara_count' => fn ($query) => $query->where('jenis', BuktiRuangUjianCbt::JENIS_BERITA_ACARA),
            ])
            ->get()
            ->keyBy(fn (RuangUjianCbt $ruang) => $ruang->jadwal_ujian_cbt_id.'-'.$ruang->ruang_kegiatan_ujian_cbt_id);

        $tugas->each(function (PengawasRuangUjianTerpusat $penugasan) use ($ruangOperasional) {
            $penugasan->setRelation(
                'ruangOperasional',
                $ruangOperasional->get($penugasan->jadwal_ujian_cbt_id.'-'.$penugasan->ruang_kegiatan_ujian_cbt_id),
            );
        });

        $tugas = $tugas->sortBy(fn (PengawasRuangUjianTerpusat $penugasan) => sprintf(
            '%s %s %03d',
            $penugasan->jadwalUjianCbt?->tanggal?->format('Y-m-d') ?? '9999-12-31',
            substr((string) $penugasan->jadwalUjianCbt?->waktu_mulai, 0, 5),
            $penugasan->ruangKegiatanUjianCbt?->urutan ?? 999,
        ))->values();

        return view('tugas-pengawas-ujian.index', [
            'tugas' => $tugas,
            'ringkasan' => [
                'jumlah' => $tugas->count(),
                'hari_ini' => $tugas->filter(fn ($item) => $item->jadwalUjianCbt?->tanggal?->isToday())->count(),
                'perlu_bukti' => $tugas->filter(fn ($item) => in_array(
                    $item->ruangOperasional?->status_bukti,
                    ['belum_diunggah', 'sebagian', 'siap_dikirim', 'perlu_diulang'],
                    true,
                ))->count(),
            ],
        ]);
    }

    public function show(Request $request, RuangUjianCbt $ruangUjianCbt)
    {
        $this->pastikanBolehMelihat($request->user(), $ruangUjianCbt);

        $ruangUjianCbt->load([
            'ujianCbt.jenisUjianCbt',
            'ujianCbt.tahunPelajaran',
            'jadwalUjianCbt.kegiatanUjianCbt.jenisUjianCbt',
            'jadwalUjianCbt.mataPelajaran',
            'ruangKegiatanUjianCbt',
            'pengawasUtama',
            'pengawasPendamping',
            'buktiRuangUjianCbt' => fn ($query) => $query->with('diunggahOleh')->orderBy('jenis')->orderBy('diunggah_pada'),
            'buktiDiajukanOleh',
            'buktiDiperiksaOleh',
            'pesertaUjianCbt.anggotaKelas.siswa',
            'pesertaUjianCbt.kelasUjianCbt.kelas',
        ]);

        $pengguna = $request->user();

        return view('tugas-pengawas-ujian.show', [
            'ruang' => $ruangUjianCbt,
            'bolehUnggah' => $this->bolehMengunggah($pengguna, $ruangUjianCbt),
            'bolehMemeriksa' => $this->bolehMemeriksa($pengguna, $ruangUjianCbt),
            'sebagaiPengawasUtama' => (int) $pengguna?->pegawai_id === (int) $ruangUjianCbt->pengawas_utama_pegawai_id,
        ]);
    }

    public function storeBukti(Request $request, RuangUjianCbt $ruangUjianCbt)
    {
        $this->pastikanBolehMengunggah($request->user(), $ruangUjianCbt);
        $this->pastikanBuktiDapatDiubah($ruangUjianCbt);

        $data = $request->validate([
            'jenis' => ['required', Rule::in([
                BuktiRuangUjianCbt::JENIS_DAFTAR_HADIR,
                BuktiRuangUjianCbt::JENIS_BERITA_ACARA,
            ])],
            'berkas' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ]);
        $file = $request->file('berkas');
        $lokasiFile = $file->store("cbt/{$ruangUjianCbt->ujian_cbt_id}/ruang/{$ruangUjianCbt->id}/bukti-pengawas", 'local');

        try {
            DB::transaction(function () use ($data, $file, $lokasiFile, $request, $ruangUjianCbt) {
                $ruangUjianCbt->buktiRuangUjianCbt()->create([
                    'jenis' => $data['jenis'],
                    'lokasi_file' => $lokasiFile,
                    'nama_file_asli' => $file->getClientOriginalName(),
                    'tipe_file' => $file->getMimeType(),
                    'ukuran_file' => $file->getSize(),
                    'diunggah_oleh_pengguna_id' => $request->user()?->id,
                    'diunggah_pada' => now(),
                ]);

                $this->segarkanStatusBukti($ruangUjianCbt);
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($lokasiFile);
            throw $exception;
        }

        return back()->with('berhasil', 'Bukti berhasil ditambahkan. Periksa pratinjau sebelum dikirim ke panitia.');
    }

    public function lihatBukti(Request $request, RuangUjianCbt $ruangUjianCbt, BuktiRuangUjianCbt $buktiRuangUjianCbt)
    {
        $this->pastikanBuktiMilikRuang($ruangUjianCbt, $buktiRuangUjianCbt);
        $this->pastikanBolehMelihat($request->user(), $ruangUjianCbt);
        abort_unless(Storage::disk('local')->exists($buktiRuangUjianCbt->lokasi_file), 404);

        return Storage::disk('local')->response(
            $buktiRuangUjianCbt->lokasi_file,
            $buktiRuangUjianCbt->nama_file_asli,
            ['Content-Type' => $buktiRuangUjianCbt->tipe_file ?: 'application/octet-stream'],
        );
    }

    public function destroyBukti(Request $request, RuangUjianCbt $ruangUjianCbt, BuktiRuangUjianCbt $buktiRuangUjianCbt)
    {
        $this->pastikanBuktiMilikRuang($ruangUjianCbt, $buktiRuangUjianCbt);
        $this->pastikanBolehMengunggah($request->user(), $ruangUjianCbt);
        $this->pastikanBuktiDapatDiubah($ruangUjianCbt);
        $lokasiFile = $buktiRuangUjianCbt->lokasi_file;

        DB::transaction(function () use ($buktiRuangUjianCbt, $ruangUjianCbt) {
            $buktiRuangUjianCbt->delete();
            $this->segarkanStatusBukti($ruangUjianCbt);
        });
        Storage::disk('local')->delete($lokasiFile);

        return back()->with('berhasil', 'Bukti berhasil dihapus.');
    }

    public function kirim(Request $request, RuangUjianCbt $ruangUjianCbt)
    {
        $this->pastikanBolehMengunggah($request->user(), $ruangUjianCbt);
        $this->pastikanBuktiDapatDiubah($ruangUjianCbt);

        $jumlahPerJenis = $ruangUjianCbt->buktiRuangUjianCbt()
            ->selectRaw('jenis, count(*) as jumlah')
            ->groupBy('jenis')
            ->pluck('jumlah', 'jenis');

        if (! $jumlahPerJenis->get(BuktiRuangUjianCbt::JENIS_DAFTAR_HADIR)
            || ! $jumlahPerJenis->get(BuktiRuangUjianCbt::JENIS_BERITA_ACARA)) {
            throw ValidationException::withMessages([
                'bukti' => 'Unggah seluruh halaman daftar hadir dan berita acara sebelum mengirim bukti.',
            ]);
        }

        $ruangUjianCbt->update([
            'status_bukti' => 'menunggu_pemeriksaan',
            'bukti_diajukan_pada' => now(),
            'bukti_diajukan_oleh_pengguna_id' => $request->user()?->id,
            'catatan_pemeriksaan_bukti' => null,
            'bukti_diperiksa_pada' => null,
            'bukti_diperiksa_oleh_pengguna_id' => null,
        ]);

        return back()->with('berhasil', 'Bukti ruang berhasil dikirim dan menunggu pemeriksaan panitia.');
    }

    public function periksa(Request $request, RuangUjianCbt $ruangUjianCbt)
    {
        abort_unless($this->bolehMemeriksa($request->user(), $ruangUjianCbt), 403);
        abort_unless($ruangUjianCbt->status_bukti === 'menunggu_pemeriksaan', 422, 'Bukti belum dikirim oleh pengawas.');

        $data = $request->validate([
            'hasil' => ['required', Rule::in(['valid', 'perlu_diulang'])],
            'catatan' => [
                'nullable',
                'string',
                'max:1000',
                Rule::requiredIf(fn () => $request->input('hasil') === 'perlu_diulang'),
            ],
        ]);

        $ruangUjianCbt->update([
            'status_bukti' => $data['hasil'],
            'catatan_pemeriksaan_bukti' => filled($data['catatan'] ?? null) ? trim($data['catatan']) : null,
            'bukti_diperiksa_pada' => now(),
            'bukti_diperiksa_oleh_pengguna_id' => $request->user()?->id,
        ]);

        return back()->with(
            'berhasil',
            $data['hasil'] === 'valid'
                ? 'Bukti ruang dinyatakan lengkap dan valid.'
                : 'Bukti dikembalikan kepada pengawas untuk diulang.',
        );
    }

    private function segarkanStatusBukti(RuangUjianCbt $ruang): void
    {
        $jenis = $ruang->buktiRuangUjianCbt()->distinct()->pluck('jenis');
        $lengkap = $jenis->contains(BuktiRuangUjianCbt::JENIS_DAFTAR_HADIR)
            && $jenis->contains(BuktiRuangUjianCbt::JENIS_BERITA_ACARA);

        $ruang->update([
            'status_bukti' => $lengkap ? 'siap_dikirim' : ($jenis->isNotEmpty() ? 'sebagian' : 'belum_diunggah'),
        ]);
    }

    private function pastikanBuktiDapatDiubah(RuangUjianCbt $ruang): void
    {
        abort_if(
            in_array($ruang->status_bukti, ['menunggu_pemeriksaan', 'valid'], true),
            422,
            'Bukti yang sudah dikirim tidak dapat diubah. Minta panitia mengembalikannya jika perlu diperbaiki.',
        );
    }

    private function pastikanBuktiMilikRuang(RuangUjianCbt $ruang, BuktiRuangUjianCbt $bukti): void
    {
        abort_unless((int) $bukti->ruang_ujian_cbt_id === (int) $ruang->id, 404);
    }

    private function pastikanBolehMelihat(?Pengguna $pengguna, RuangUjianCbt $ruang): void
    {
        abort_unless($this->bolehMelihat($pengguna, $ruang), 403);
    }

    private function pastikanBolehMengunggah(?Pengguna $pengguna, RuangUjianCbt $ruang): void
    {
        abort_unless($this->bolehMengunggah($pengguna, $ruang), 403);
    }

    private function bolehMelihat(?Pengguna $pengguna, RuangUjianCbt $ruang): bool
    {
        if (! $pengguna) {
            return false;
        }

        if ($this->pengawasDitugaskan($pengguna, $ruang) || $pengguna->memilikiIzin('cbt.kelola')) {
            return true;
        }

        $ruang->loadMissing('jadwalUjianCbt.kegiatanUjianCbt');
        $kegiatan = $ruang->jadwalUjianCbt?->kegiatanUjianCbt;

        return $kegiatan
            && $pengguna->memilikiIzin(['cbt.panitia', 'cbt.terpusat_lihat'])
            && $kegiatan->dapatDiaksesOleh($pengguna);
    }

    private function bolehMengunggah(?Pengguna $pengguna, RuangUjianCbt $ruang): bool
    {
        return (bool) ($pengguna
            && ($this->pengawasDitugaskan($pengguna, $ruang) || $pengguna->memilikiIzin('cbt.kelola')));
    }

    private function bolehMemeriksa(?Pengguna $pengguna, RuangUjianCbt $ruang): bool
    {
        if (! $pengguna) {
            return false;
        }

        if ($pengguna->memilikiIzin('cbt.kelola')) {
            return true;
        }

        $ruang->loadMissing('jadwalUjianCbt.kegiatanUjianCbt');
        $kegiatan = $ruang->jadwalUjianCbt?->kegiatanUjianCbt;

        return $kegiatan
            && $pengguna->memilikiIzin('cbt.panitia')
            && $kegiatan->dapatDiaksesOleh($pengguna);
    }

    private function pengawasDitugaskan(Pengguna $pengguna, RuangUjianCbt $ruang): bool
    {
        $pegawaiId = (int) ($pengguna->pegawai_id ?? 0);

        return $pegawaiId > 0 && in_array($pegawaiId, [
            (int) $ruang->pengawas_utama_pegawai_id,
            (int) $ruang->pengawas_pendamping_pegawai_id,
        ], true);
    }
}
