<?php

namespace App\Http\Controllers;

use App\Models\BuktiRuangUjianCbt;
use App\Models\PengawasRuangUjianTerpusat;
use App\Models\Pengguna;
use App\Models\PesertaUjianCbt;
use App\Models\RuangUjianCbt;
use App\Services\Cbt\KeamananUjianService;
use App\Services\Cbt\NotifikasiUjianTerpusatService;
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
            '%d %s %s %03d',
            $penugasan->jadwalUjianCbt?->tanggal?->isToday() ? 0 : 1,
            $penugasan->jadwalUjianCbt?->tanggal?->format('Y-m-d') ?? '9999-12-31',
            substr((string) $penugasan->jadwalUjianCbt?->waktu_mulai, 0, 5),
            $penugasan->ruangKegiatanUjianCbt?->urutan ?? 999,
        ))->values();

        $pesertaSusulan = PesertaUjianCbt::query()
            ->where('pengawas_susulan_pegawai_id', $pengguna->pegawai_id)
            ->whereNotNull('kelompok_susulan')
            ->whereIn('status_susulan', ['dijadwalkan', 'selesai'])
            ->with([
                'ujianCbt.jenisUjianCbt',
                'ujianCbt.mataPelajaran',
                'ujianCbt.jadwalUjianCbt.kegiatanUjianCbt.jenisUjianCbt',
                'ujianCbt.jadwalUjianCbt.kegiatanUjianCbt.tahunPelajaran',
                'kelasUjianCbt.kelas',
                'anggotaKelas.siswa',
            ])
            ->get();
        $tugasSusulan = $pesertaSusulan
            ->groupBy('kelompok_susulan')
            ->map(function ($peserta, string $kode) {
                $pertama = $peserta->first();
                $status = $this->statusTugasSusulan($peserta);

                return [
                    'kode' => $kode,
                    'peserta' => $peserta,
                    'pertama' => $pertama,
                    'ujian' => $pertama?->ujianCbt,
                    'jadwal' => $pertama?->ujianCbt?->jadwalUjianCbt?->first(),
                    'mulai' => $pertama?->susulan_mulai,
                    'selesai' => $pertama?->susulan_selesai,
                    'ruang' => $pertama?->ruang_susulan,
                    'jumlah' => $peserta->count(),
                    'jumlah_selesai' => $peserta->where('status_susulan', 'selesai')->count(),
                    ...$status,
                ];
            })
            ->sortBy(fn (array $item) => sprintf(
                '%d %s',
                $item['mulai']?->isToday() ? 0 : 1,
                $item['mulai']?->format('Y-m-d H:i:s') ?? '9999-12-31 23:59:59',
            ))
            ->values();

        [$tugasRiwayat, $tugasPerluDikerjakan] = $tugas->partition(
            fn (PengawasRuangUjianTerpusat $item) => $this->tugasRegulerSelesai($item),
        );
        $tugasRiwayat = $tugasRiwayat->sortByDesc(fn (PengawasRuangUjianTerpusat $item) => sprintf(
            '%s %s',
            $item->jadwalUjianCbt?->tanggal?->format('Y-m-d') ?? '',
            substr((string) $item->jadwalUjianCbt?->waktu_mulai, 0, 5),
        ))->values();
        [$tugasSusulanRiwayat, $tugasSusulanPerluDikerjakan] = $tugasSusulan->partition(
            fn (array $item) => $item['kode_status'] === 'selesai',
        );
        $tugasSusulanRiwayat = $tugasSusulanRiwayat->sortByDesc(fn (array $item) => $item['mulai']?->timestamp ?? 0)->values();
        $tabTugas = $request->query('tab') === 'riwayat' ? 'riwayat' : 'perlu';

        return view('tugas-pengawas-ujian.index', [
            'tabTugas' => $tabTugas,
            'tugas' => $tabTugas === 'riwayat' ? $tugasRiwayat : $tugasPerluDikerjakan->values(),
            'tugasSusulan' => $tabTugas === 'riwayat' ? $tugasSusulanRiwayat : $tugasSusulanPerluDikerjakan->values(),
            'ringkasan' => [
                'perlu' => $tugasPerluDikerjakan->count() + $tugasSusulanPerluDikerjakan->count(),
                'hari_ini' => $tugasPerluDikerjakan->filter(fn ($item) => $item->jadwalUjianCbt?->tanggal?->isToday())->count()
                    + $tugasSusulanPerluDikerjakan->filter(fn ($item) => $item['mulai']?->isToday())->count(),
                'riwayat' => $tugasRiwayat->count() + $tugasSusulanRiwayat->count(),
                'perlu_bukti' => $tugasPerluDikerjakan->filter(fn ($item) => $item->ruangOperasional
                    && $this->jadwalRegulerBerakhir($item)
                    && ! in_array(
                        $item->ruangOperasional?->status_bukti,
                        ['menunggu_pemeriksaan', 'valid'],
                        true,
                    ))->count(),
            ],
        ]);
    }

    public function showSusulan(Request $request, string $kelompokSusulan)
    {
        $peserta = PesertaUjianCbt::query()
            ->where('kelompok_susulan', $kelompokSusulan)
            ->with([
                'ujianCbt.jenisUjianCbt',
                'ujianCbt.tahunPelajaran',
                'ujianCbt.mataPelajaran',
                'ujianCbt.jadwalUjianCbt.kegiatanUjianCbt.jenisUjianCbt',
                'ujianCbt.jadwalUjianCbt.kegiatanUjianCbt.tahunPelajaran',
                'kelasUjianCbt.kelas',
                'anggotaKelas.siswa',
                'pengawasSusulan',
            ])
            ->withCount(['jawabanPesertaUjianCbt as jawaban_tersimpan' => fn ($query) => $query->whereNotNull('jawaban')])
            ->orderBy('id')
            ->get();

        abort_if($peserta->isEmpty(), 404);
        $pertama = $peserta->first();
        $jadwal = $pertama->ujianCbt?->jadwalUjianCbt?->first();
        $this->pastikanBolehMelihatSusulan($request->user(), $peserta, $jadwal?->kegiatanUjianCbt);
        $pesertaAktif = $peserta->whereIn('status_susulan', ['dijadwalkan', 'selesai'])->values();

        return view('tugas-pengawas-ujian.susulan', [
            'kelompokSusulan' => $kelompokSusulan,
            'pesertaPantau' => $pesertaAktif,
            'pesertaDibatalkan' => $peserta->where('status_susulan', 'dibatalkan')->values(),
            'pesertaPertama' => $pertama,
            'ujian' => $pertama->ujianCbt,
            'jadwal' => $jadwal,
            'pengawas' => $pertama->pengawasSusulan,
            'jumlahSoalPantau' => min(
                (int) $pertama->ujianCbt?->jumlah_soal,
                $pertama->ujianCbt?->soalUjianCbt()->count() ?? 0,
            ),
            ...$this->statusTugasSusulan($pesertaAktif),
        ]);
    }

    public function show(Request $request, RuangUjianCbt $ruangUjianCbt, KeamananUjianService $keamanan)
    {
        $this->pastikanBolehMelihat($request->user(), $ruangUjianCbt);

        $data = $request->validate(['tahap' => ['nullable', Rule::in(['persiapan', 'pantau', 'bukti'])]]);

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
        $peserta = $ruangUjianCbt->pesertaUjianCbt->sortBy('nomor_meja')->values();
        $peserta->loadCount([
            'jawabanPesertaUjianCbt as jawaban_tersimpan' => fn ($query) => $query->whereNotNull('jawaban'),
            'aktivitasKeamananUjianCbt as jumlah_aktivitas_keamanan',
        ]);

        return view('tugas-pengawas-ujian.show', [
            'ruang' => $ruangUjianCbt,
            'tahap' => $data['tahap'] ?? (request('kembali') === 'panitia' ? 'bukti' : 'persiapan'),
            'pesertaPantau' => $peserta,
            'jumlahSoalPantau' => min((int) $ruangUjianCbt->ujianCbt->jumlah_soal, $ruangUjianCbt->ujianCbt->soalUjianCbt()->count()),
            'bolehUnggah' => $this->bolehMengunggah($pengguna, $ruangUjianCbt),
            'bolehMemeriksa' => $this->bolehMemeriksa($pengguna, $ruangUjianCbt),
            'pesertaDapatDibuka' => $peserta->filter(fn (PesertaUjianCbt $item) => $item->status === 'terblokir'
                && $keamanan->dapatMembuka($pengguna, $item))->pluck('id')->all(),
            'sebagaiPengawasUtama' => (int) $pengguna?->pegawai_id === (int) $ruangUjianCbt->pengawas_utama_pegawai_id,
        ]);
    }

    public function riwayatModeAman(
        Request $request,
        RuangUjianCbt $ruangUjianCbt,
        PesertaUjianCbt $pesertaUjianCbt,
        KeamananUjianService $keamanan,
    ) {
        abort_unless((int) $pesertaUjianCbt->ruang_ujian_cbt_id === (int) $ruangUjianCbt->id, 404);
        $this->pastikanBolehMelihat($request->user(), $ruangUjianCbt);

        $ruangUjianCbt->load(['ujianCbt', 'jadwalUjianCbt.mataPelajaran']);
        $pesertaUjianCbt->load(['anggotaKelas.siswa', 'kelasUjianCbt.kelas', 'dibukaModeAmanOleh']);

        return view('tugas-pengawas-ujian.riwayat-mode-aman', [
            'ruang' => $ruangUjianCbt,
            'peserta' => $pesertaUjianCbt,
            'aktivitas' => $pesertaUjianCbt->aktivitasKeamananUjianCbt()
                ->with('dibukaOleh')
                ->orderByDesc('mulai_pada')
                ->orderByDesc('id')
                ->paginate(20),
            'dapatMembuka' => $pesertaUjianCbt->status === 'terblokir'
                && $keamanan->dapatMembuka($request->user(), $pesertaUjianCbt),
        ]);
    }

    public function bukaModeAman(
        Request $request,
        RuangUjianCbt $ruangUjianCbt,
        PesertaUjianCbt $pesertaUjianCbt,
        KeamananUjianService $keamanan,
    ) {
        abort_unless((int) $pesertaUjianCbt->ruang_ujian_cbt_id === (int) $ruangUjianCbt->id, 404);
        abort_unless($keamanan->dapatMembuka($request->user(), $pesertaUjianCbt), 403);
        $data = $request->validate([
            'alasan_pembukaan' => ['required', 'string', 'min:10', 'max:500'],
        ]);
        $keamanan->bukaTahanan($request->user(), $pesertaUjianCbt, $data['alasan_pembukaan']);

        return redirect()
            ->route('tugas-pengawas-ujian.mode-aman.riwayat', [$ruangUjianCbt, $pesertaUjianCbt])
            ->with('berhasil', 'Mode Aman peserta telah dibuka. Siswa dapat melanjutkan ujian.');
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

    public function kirim(
        Request $request,
        RuangUjianCbt $ruangUjianCbt,
        NotifikasiUjianTerpusatService $notifikasi,
    ) {
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
        $notifikasi->kirimBuktiKepadaPanitia($ruangUjianCbt->fresh(), $request->user()?->id);

        return back()->with('berhasil', 'Bukti ruang berhasil dikirim dan menunggu pemeriksaan panitia.');
    }

    public function periksa(
        Request $request,
        RuangUjianCbt $ruangUjianCbt,
        NotifikasiUjianTerpusatService $notifikasi,
    ) {
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

        if ($data['hasil'] === 'perlu_diulang') {
            $notifikasi->kirimPermintaanFotoUlang(
                $ruangUjianCbt->fresh(),
                trim($data['catatan']),
                $request->user()?->id,
            );
        }

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

    private function tugasRegulerSelesai(PengawasRuangUjianTerpusat $penugasan): bool
    {
        if ($penugasan->jadwalUjianCbt?->status === 'dibatalkan') {
            return true;
        }

        return in_array($penugasan->ruangOperasional?->status_bukti, ['menunggu_pemeriksaan', 'valid'], true)
            && $this->jadwalRegulerBerakhir($penugasan);
    }

    private function jadwalRegulerBerakhir(PengawasRuangUjianTerpusat $penugasan): bool
    {
        $jadwal = $penugasan->jadwalUjianCbt;
        if ($jadwal?->status === 'selesai' || $penugasan->ruangOperasional?->status === 'selesai') {
            return true;
        }

        return (bool) ($jadwal?->tanggal && filled($jadwal->waktu_selesai)
            && now()->gte($jadwal->tanggal->copy()->setTimeFromTimeString($jadwal->waktu_selesai)));
    }

    private function pastikanBolehMelihatSusulan(?Pengguna $pengguna, $peserta, $kegiatan): void
    {
        abort_unless($pengguna, 403);
        $pengawasIds = $peserta->pluck('pengawas_susulan_pegawai_id')->filter()->map(fn ($id) => (int) $id);

        if ($pengguna->pegawai_id && $pengawasIds->contains((int) $pengguna->pegawai_id)) {
            return;
        }

        if ($pengguna->memilikiIzin('cbt.kelola')) {
            return;
        }

        abort_unless(
            $kegiatan
                && $pengguna->memilikiIzin(['cbt.panitia', 'cbt.terpusat_lihat'])
                && $kegiatan->dapatDiaksesOleh($pengguna),
            403,
        );
    }

    private function statusTugasSusulan($peserta): array
    {
        $pertama = $peserta->first();
        $mulai = $pertama?->susulan_mulai;
        $selesai = $pertama?->susulan_selesai;

        return match (true) {
            $peserta->isNotEmpty() && $peserta->every(fn (PesertaUjianCbt $item) => $item->status_susulan === 'selesai') => [
                'kode_status' => 'selesai',
                'label_status' => 'Selesai',
                'kelas_status' => 'badge-active',
            ],
            $mulai && now()->lt($mulai) => [
                'kode_status' => 'akan_datang',
                'label_status' => 'Akan datang',
                'kelas_status' => 'badge-muted',
            ],
            $selesai && now()->gt($selesai) => [
                'kode_status' => 'berakhir',
                'label_status' => 'Waktu berakhir',
                'kelas_status' => 'badge-danger',
            ],
            default => [
                'kode_status' => 'berlangsung',
                'label_status' => 'Sedang berlangsung',
                'kelas_status' => 'badge-warning',
            ],
        };
    }
}
