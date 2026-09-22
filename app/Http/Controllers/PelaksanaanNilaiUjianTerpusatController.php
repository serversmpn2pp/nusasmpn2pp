<?php

namespace App\Http\Controllers;

use App\Models\BuktiRuangUjianCbt;
use App\Models\JadwalUjianCbt;
use App\Models\JawabanPesertaUjianCbt;
use App\Models\KegiatanUjianCbt;
use App\Models\Pegawai;
use App\Models\PengawasRuangUjianTerpusat;
use App\Models\Pengguna;
use App\Models\PesertaUjianCbt;
use App\Models\RiwayatPergantianPengawasUjian;
use App\Models\RuangKegiatanUjianCbt;
use App\Models\RuangUjianCbt;
use App\Services\Cbt\KoreksiOtomatisCbtService;
use App\Services\Cbt\NotifikasiUjianTerpusatService;
use App\Services\Cbt\SinkronkanPelaksanaanUjianTerpusat;
use App\Services\Notifikasi\NotifikasiPenggunaService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PelaksanaanNilaiUjianTerpusatController extends Controller
{
    public function index(
        Request $request,
        KegiatanUjianCbt $kegiatanUjianCbt,
        SinkronkanPelaksanaanUjianTerpusat $sinkronisasi,
    ) {
        return $this->tampilkan($request, $kegiatanUjianCbt, $sinkronisasi, 'pelaksanaan');
    }

    public function hasil(
        Request $request,
        KegiatanUjianCbt $kegiatanUjianCbt,
        SinkronkanPelaksanaanUjianTerpusat $sinkronisasi,
    ) {
        return $this->tampilkan($request, $kegiatanUjianCbt, $sinkronisasi, 'hasil');
    }

    private function tampilkan(
        Request $request,
        KegiatanUjianCbt $kegiatanUjianCbt,
        SinkronkanPelaksanaanUjianTerpusat $sinkronisasi,
        string $mode,
    ) {
        $aksesPenuh = $kegiatanUjianCbt->dapatDiaksesOleh($request->user());
        $jadwalCakupan = $kegiatanUjianCbt->jadwalUjianCbt()
            ->with('ujianCbt')
            ->get()
            ->filter(fn (JadwalUjianCbt $jadwal) => $aksesPenuh || $jadwal->ujianCbt?->dapatDikelolaOleh($request->user()));

        abort_if(! $aksesPenuh && $jadwalCakupan->isEmpty(), 403);
        $sinkronisasi->sinkronkanKegiatan($kegiatanUjianCbt, $request->user());

        $kegiatanUjianCbt->load([
            'jenisUjianCbt',
            'tahunPelajaran',
            'panitiaUjianCbt',
            'sesiKegiatanUjianCbt',
            'ruangKegiatanUjianCbt',
            'kelompokPesertaKegiatanUjianCbt.ruangKegiatanUjianCbt',
            'kelompokPesertaKegiatanUjianCbt' => fn ($query) => $query->withCount('penempatanPesertaUjianCbt'),
            'jadwalUjianCbt' => fn ($query) => $query
                ->with([
                    'sesiKegiatanUjianCbt',
                    'mataPelajaran',
                    'kelas',
                    'pengawasRuangUjianTerpusat.pengawasUtama',
                    'pengawasRuangUjianTerpusat.pengawasPendamping',
                    'pengawasRuangUjianTerpusat.riwayatPergantian' => fn ($query) => $query
                        ->with(['pegawaiLama', 'pegawaiBaru', 'digantiOleh']),
                    'ujianCbt' => fn ($query) => $query->withCount([
                        'soalUjianCbt',
                        'pesertaUjianCbt',
                        'pesertaUjianCbt as peserta_sedang_count' => fn ($query) => $query->where('status', 'sedang_mengerjakan'),
                        'pesertaUjianCbt as peserta_selesai_count' => fn ($query) => $query->where('status', 'selesai'),
                        'pesertaUjianCbt as nilai_diterapkan_count' => fn ($query) => $query->whereNotNull('nilai_siswa_id'),
                        'pesertaUjianCbt as peserta_tidak_hadir_count' => fn ($query) => $query->whereIn('status_kehadiran_ujian', ['sakit', 'izin', 'alfa']),
                        'pesertaUjianCbt as peserta_susulan_dijadwalkan_count' => fn ($query) => $query->where('status_susulan', 'dijadwalkan'),
                        'pesertaUjianCbt as peserta_susulan_selesai_count' => fn ($query) => $query->where('status_susulan', 'selesai'),
                    ]),
                ])
                ->orderBy('tanggal')
                ->orderBy('waktu_mulai')
                ->orderBy('tingkat'),
        ]);

        $jadwal = $kegiatanUjianCbt->jadwalUjianCbt
            ->filter(fn (JadwalUjianCbt $item) => $aksesPenuh || $item->ujianCbt?->dapatDikelolaOleh($request->user()))
            ->values();
        $kelompokPerTingkat = $kegiatanUjianCbt->kelompokPesertaKegiatanUjianCbt->keyBy('tingkat');

        $jadwal->each(function (JadwalUjianCbt $item) use ($kelompokPerTingkat, $request) {
            $paket = $item->ujianCbt;
            $kelompok = $kelompokPerTingkat->get($item->tingkat);
            $item->setRelation('ruangPelaksanaan', $kelompok?->ruangKegiatanUjianCbt ?? collect());
            $item->setRelation('ruangOperasional', $paket
                ? $paket->ruangUjianCbt()
                    ->where('jadwal_ujian_cbt_id', $item->id)
                    ->withCount([
                        'buktiRuangUjianCbt as bukti_daftar_hadir_count' => fn ($query) => $query->where('jenis', BuktiRuangUjianCbt::JENIS_DAFTAR_HADIR),
                        'buktiRuangUjianCbt as bukti_berita_acara_count' => fn ($query) => $query->where('jenis', BuktiRuangUjianCbt::JENIS_BERITA_ACARA),
                    ])
                    ->get()
                    ->keyBy('ruang_kegiatan_ujian_cbt_id')
                : collect());
            $item->setAttribute('boleh_kelola_nilai', $paket?->dapatDikelolaOleh($request->user()) ?? false);
            $item->setAttribute('perlu_koreksi_manual', $paket ? $this->jumlahPerluKoreksiManual($paket->id) : 0);
            $item->setRelation('pesertaSusulan', $paket
                ? $paket->pesertaUjianCbt()
                    ->with([
                        'anggotaKelas.siswa',
                        'kelasUjianCbt.kelas',
                        'pengawasSusulan:id,nama_lengkap,nip',
                    ])
                    ->withCount('jawabanPesertaUjianCbt')
                    ->where(function ($query) {
                        $query->whereIn('status_kehadiran_ujian', ['sakit', 'izin', 'alfa'])
                            ->orWhereNotNull('status_susulan');
                    })
                    ->get()
                    ->sortBy(fn (PesertaUjianCbt $peserta) => sprintf(
                        '%s|%s',
                        $peserta->kelasUjianCbt?->kelas?->nama ?? '',
                        $peserta->anggotaKelas?->siswa?->nama_lengkap ?? '',
                    ))
                    ->values()
                : collect());
        });

        $paket = $jadwal->pluck('ujianCbt')->filter();
        $ruangOperasional = $jadwal->flatMap(fn (JadwalUjianCbt $item) => $item->ruangOperasional);
        $bolehAturPengawas = $mode === 'pelaksanaan'
            && $aksesPenuh
            && $request->user()->memilikiIzin(['cbt.panitia', 'cbt.kelola']);
        $bolehAturSusulan = $bolehAturPengawas;

        return view('ujian-terpusat.pelaksanaan-nilai.index', [
            'kegiatan' => $kegiatanUjianCbt,
            'jadwal' => $jadwal,
            'mode' => $mode,
            'tahapAktif' => $mode === 'hasil' ? 10 : 9,
            'bolehAturPengawas' => $bolehAturPengawas,
            'bolehAturSusulan' => $bolehAturSusulan,
            'bolehCetakDokumen' => $mode === 'pelaksanaan' && $aksesPenuh,
            'pegawai' => $bolehAturPengawas || $bolehAturSusulan
                ? Pegawai::query()->where('aktif', true)->orderBy('nama_lengkap')->get(['id', 'nama_lengkap', 'nip'])
                : collect(),
            'ringkasan' => [
                'paket_siap' => $paket->filter(fn ($item) => in_array($item->status, ['terjadwal', 'berlangsung', 'selesai'], true))->count(),
                'peserta' => $paket->sum('peserta_ujian_cbt_count'),
                'sedang' => $paket->sum('peserta_sedang_count'),
                'selesai' => $paket->sum('peserta_selesai_count'),
                'belum_mulai' => max(0, $paket->sum('peserta_ujian_cbt_count') - $paket->sum('peserta_sedang_count') - $paket->sum('peserta_selesai_count')),
                'nilai_diterapkan' => $paket->sum('nilai_diterapkan_count'),
                'perlu_manual' => $jadwal->sum('perlu_koreksi_manual'),
                'bukti_ruang' => $ruangOperasional->count(),
                'bukti_valid' => $ruangOperasional->where('status_bukti', 'valid')->count(),
                'bukti_menunggu' => $ruangOperasional->where('status_bukti', 'menunggu_pemeriksaan')->count(),
                'bukti_belum_lengkap' => $ruangOperasional->whereIn('status_bukti', [
                    'belum_diunggah',
                    'sebagian',
                    'siap_dikirim',
                    'perlu_diulang',
                ])->count(),
            ],
        ]);
    }

    public function cetakDokumenRuang(
        Request $request,
        KegiatanUjianCbt $kegiatanUjianCbt,
        JadwalUjianCbt $jadwalUjianCbt,
        RuangKegiatanUjianCbt $ruangKegiatanUjianCbt,
        SinkronkanPelaksanaanUjianTerpusat $sinkronisasi,
    ) {
        abort_unless($kegiatanUjianCbt->dapatDiaksesOleh($request->user()), 403);
        abort_unless((int) $jadwalUjianCbt->kegiatan_ujian_cbt_id === (int) $kegiatanUjianCbt->id, 404);
        abort_unless((int) $ruangKegiatanUjianCbt->kegiatan_ujian_cbt_id === (int) $kegiatanUjianCbt->id, 404);

        $ruangDipakai = $kegiatanUjianCbt->kelompokPesertaKegiatanUjianCbt()
            ->where('tingkat', $jadwalUjianCbt->tingkat)
            ->whereHas('ruangKegiatanUjianCbt', fn ($query) => $query->whereKey($ruangKegiatanUjianCbt->id))
            ->exists();
        abort_unless($ruangDipakai, 404);

        $sinkronisasi->sinkronkanJadwal($jadwalUjianCbt->fresh(), $request->user());
        $jadwalUjianCbt->loadMissing('ujianCbt');
        $paket = $jadwalUjianCbt->ujianCbt;
        abort_unless($paket, 404, 'Paket soal untuk jadwal ini belum diterbitkan.');

        $paket->load(['jenisUjianCbt', 'tahunPelajaran', 'mataPelajaran']);
        $ruang = RuangUjianCbt::query()
            ->where('ujian_cbt_id', $paket->id)
            ->where('jadwal_ujian_cbt_id', $jadwalUjianCbt->id)
            ->where('ruang_kegiatan_ujian_cbt_id', $ruangKegiatanUjianCbt->id)
            ->with([
                'sesiUjianCbt',
                'jadwalUjianCbt.kegiatanUjianCbt',
                'jadwalUjianCbt.mataPelajaran',
                'pengawasUtama',
                'pengawasPendamping',
                'pesertaUjianCbt.sesiUjianCbt',
                'pesertaUjianCbt.kelasUjianCbt.kelas',
                'pesertaUjianCbt.anggotaKelas.siswa',
            ])
            ->firstOrFail();

        abort_unless($ruang->pengawas_utama_pegawai_id, 422, 'Tentukan pengawas utama sebelum mencetak dokumen ruang.');
        $ruang->setRelation('pesertaUjianCbt', $ruang->pesertaUjianCbt
            ->sortBy(fn ($peserta) => sprintf(
                '%05d|%s|%05d|%s',
                $peserta->nomor_meja ?? 999,
                $peserta->kelasUjianCbt?->kelas?->nama ?? '',
                $peserta->anggotaKelas?->nomor_absen ?? 999,
                $peserta->anggotaKelas?->siswa?->nama_lengkap ?? '',
            ))
            ->values());

        return view('ujian-cbt.ruang.cetak', [
            'ujianCbt' => $paket,
            'ruangUjianCbt' => collect([$ruang]),
            'sesiUjianCbtId' => $ruang->sesi_ujian_cbt_id,
            'jadwalUjianCbtId' => $jadwalUjianCbt->id,
            'ruangUjianCbtId' => $ruang->id,
            'daftarStatusKehadiran' => PesertaUjianCbt::DAFTAR_STATUS_KEHADIRAN,
            'routeKembali' => route('ujian-terpusat.pelaksanaan-nilai.index', $kegiatanUjianCbt),
        ]);
    }

    public function jadwalkanSusulan(
        Request $request,
        KegiatanUjianCbt $kegiatanUjianCbt,
        JadwalUjianCbt $jadwalUjianCbt,
        NotifikasiPenggunaService $notifikasi,
    ) {
        $this->pastikanBolehAturSusulan($request, $kegiatanUjianCbt, $jadwalUjianCbt);

        $data = $request->validate([
            'peserta_ids' => ['required', 'array', 'min:1'],
            'peserta_ids.*' => ['required', 'integer', 'distinct'],
            'susulan_mulai' => ['required', 'date'],
            'susulan_selesai' => ['required', 'date', 'after:susulan_mulai', 'after:now'],
            'ruang_susulan' => ['required', 'string', 'max:120'],
            'pengawas_susulan_pegawai_id' => ['nullable', 'integer', Rule::exists('pegawai', 'id')->where('aktif', true)],
            'catatan_susulan' => ['nullable', 'string', 'max:1000'],
        ], [
            'peserta_ids.required' => 'Pilih minimal satu siswa yang akan mengikuti ujian susulan.',
            'susulan_mulai.required' => 'Tentukan waktu mulai ujian susulan.',
            'susulan_selesai.after' => 'Waktu selesai harus setelah waktu mulai.',
            'susulan_selesai.after_now' => 'Waktu selesai ujian susulan harus belum berlalu.',
            'ruang_susulan.required' => 'Pilih ruang ujian susulan.',
        ]);

        $pesertaIds = collect($data['peserta_ids'])->map(fn ($id) => (int) $id)->unique()->values();
        $token = $this->buatTokenSusulan();
        $kelompokSusulan = (string) Str::uuid();

        $peserta = DB::transaction(function () use ($data, $jadwalUjianCbt, $pesertaIds, $request, $token, $kelompokSusulan) {
            $daftar = PesertaUjianCbt::query()
                ->where('ujian_cbt_id', $jadwalUjianCbt->ujian_cbt_id)
                ->whereIn('id', $pesertaIds)
                ->with(['anggotaKelas.siswa', 'ujianCbt.mataPelajaran'])
                ->lockForUpdate()
                ->get();

            if ($daftar->count() !== $pesertaIds->count()) {
                throw ValidationException::withMessages([
                    'peserta_ids' => 'Ada peserta yang tidak termasuk dalam paket ujian ini.',
                ]);
            }

            foreach ($daftar as $item) {
                if (! in_array($item->status_kehadiran_ujian, ['sakit', 'izin', 'alfa'], true)) {
                    throw ValidationException::withMessages([
                        'peserta_ids' => "{$item->anggotaKelas?->siswa?->nama_lengkap} tidak tercatat Sakit, Izin, atau Alfa.",
                    ]);
                }

                if (in_array($item->status, ['sedang_mengerjakan', 'selesai'], true)
                    || $item->jawabanPesertaUjianCbt()->exists()
                    || $item->nilai_siswa_id) {
                    throw ValidationException::withMessages([
                        'peserta_ids' => "{$item->anggotaKelas?->siswa?->nama_lengkap} sudah mulai atau sudah menyelesaikan ujian.",
                    ]);
                }
            }

            foreach ($daftar as $item) {
                $item->update([
                    'status' => 'aktif',
                    'status_susulan' => 'dijadwalkan',
                    'kelompok_susulan' => $kelompokSusulan,
                    'susulan_mulai' => $data['susulan_mulai'],
                    'susulan_selesai' => $data['susulan_selesai'],
                    'token_susulan' => $token,
                    'ruang_susulan' => $data['ruang_susulan'],
                    'pengawas_susulan_pegawai_id' => $data['pengawas_susulan_pegawai_id'] ?? null,
                    'catatan_susulan' => $data['catatan_susulan'] ?? null,
                    'susulan_ditetapkan_pada' => now(),
                    'susulan_ditetapkan_oleh_pengguna_id' => $request->user()->id,
                    'waktu_mulai' => null,
                    'waktu_selesai' => null,
                    'menit_tersisa' => $item->ujianCbt?->durasi_menit,
                ]);
            }

            return $daftar;
        });

        $jadwalUjianCbt->loadMissing(['kegiatanUjianCbt', 'mataPelajaran']);
        $mulai = Carbon::parse($data['susulan_mulai']);
        $selesai = Carbon::parse($data['susulan_selesai']);
        $namaUjian = $jadwalUjianCbt->kegiatanUjianCbt?->nama ?? 'ujian terpusat';
        $namaMapel = $jadwalUjianCbt->mataPelajaran?->nama ?? 'mata pelajaran';

        $akunSiswa = Pengguna::query()
            ->whereIn('siswa_id', $peserta->pluck('anggotaKelas.siswa_id')->filter())
            ->where('aktif', true)
            ->get();
        $notifikasi->kirimKeBanyak(
            $akunSiswa,
            'penting',
            'Jadwal ujian susulan',
            "Ujian susulan {$namaUjian} - {$namaMapel} dijadwalkan {$mulai->locale('id')->translatedFormat('l, d F Y')} pukul {$mulai->format('H:i')}-{$selesai->format('H:i')} di {$data['ruang_susulan']}. Token diberikan pengawas saat ujian dimulai.",
            route('ujian-saya.index'),
            null,
            ['jadwal_ujian_cbt_id' => $jadwalUjianCbt->id, 'jenis' => 'ujian_susulan'],
        );

        if (filled($data['pengawas_susulan_pegawai_id'] ?? null)) {
            $notifikasi->kirimKeBanyak(
                $notifikasi->penggunaUntukPegawai((int) $data['pengawas_susulan_pegawai_id']),
                'penting',
                'Tugas pengawas ujian susulan',
                "Anda ditugaskan mengawasi ujian susulan {$namaUjian} - {$namaMapel} pada {$mulai->format('d-m-Y')} pukul {$mulai->format('H:i')}-{$selesai->format('H:i')} di {$data['ruang_susulan']}. Token: {$token}.",
                route('tugas-pengawas-ujian.susulan.show', $kelompokSusulan),
                null,
                ['jadwal_ujian_cbt_id' => $jadwalUjianCbt->id, 'jenis' => 'pengawas_ujian_susulan'],
            );
        }

        return back()->with('berhasil', $peserta->count().' siswa berhasil dijadwalkan mengikuti ujian susulan.');
    }

    public function batalkanSusulan(
        Request $request,
        KegiatanUjianCbt $kegiatanUjianCbt,
        JadwalUjianCbt $jadwalUjianCbt,
        PesertaUjianCbt $pesertaUjianCbt,
        NotifikasiPenggunaService $notifikasi,
    ) {
        $this->pastikanBolehAturSusulan($request, $kegiatanUjianCbt, $jadwalUjianCbt);
        abort_unless((int) $pesertaUjianCbt->ujian_cbt_id === (int) $jadwalUjianCbt->ujian_cbt_id, 404);

        if ($pesertaUjianCbt->status_susulan !== 'dijadwalkan') {
            throw ValidationException::withMessages([
                'susulan' => 'Peserta ini tidak memiliki jadwal susulan yang aktif.',
            ]);
        }

        if ($pesertaUjianCbt->status === 'sedang_mengerjakan' || $pesertaUjianCbt->jawabanPesertaUjianCbt()->exists()) {
            throw ValidationException::withMessages([
                'susulan' => 'Ujian susulan tidak dapat dibatalkan karena siswa sudah mulai mengerjakan.',
            ]);
        }

        $pesertaUjianCbt->loadMissing('anggotaKelas.siswa');
        $kelompokSusulan = $pesertaUjianCbt->kelompok_susulan;
        $pengawasSusulanId = $pesertaUjianCbt->pengawas_susulan_pegawai_id;
        $namaSiswa = $pesertaUjianCbt->anggotaKelas?->siswa?->nama_lengkap ?: 'Seorang peserta';

        $pesertaUjianCbt->update([
            'status_susulan' => 'dibatalkan',
            'token_susulan' => null,
        ]);

        $akunSiswa = Pengguna::query()
            ->where('siswa_id', $pesertaUjianCbt->anggotaKelas()->value('siswa_id'))
            ->where('aktif', true)
            ->get();
        $notifikasi->kirimKeBanyak(
            $akunSiswa,
            'peringatan',
            'Jadwal ujian susulan dibatalkan',
            'Jadwal ujian susulan Anda dibatalkan oleh panitia. Silakan menunggu jadwal pengganti dari sekolah.',
            route('ujian-saya.index'),
            null,
            ['jadwal_ujian_cbt_id' => $jadwalUjianCbt->id, 'jenis' => 'ujian_susulan_dibatalkan'],
        );

        if ($pengawasSusulanId) {
            $sisaPeserta = $kelompokSusulan
                ? PesertaUjianCbt::query()
                    ->where('kelompok_susulan', $kelompokSusulan)
                    ->whereIn('status_susulan', ['dijadwalkan', 'selesai'])
                    ->count()
                : 0;
            $masihAdaTugas = $kelompokSusulan && $sisaPeserta > 0;

            $notifikasi->kirimKeBanyak(
                $notifikasi->penggunaUntukPegawai((int) $pengawasSusulanId),
                'peringatan',
                $masihAdaTugas ? 'Perubahan peserta ujian susulan' : 'Jadwal pengawasan susulan dibatalkan',
                $masihAdaTugas
                    ? "{$namaSiswa} dikeluarkan dari jadwal susulan oleh panitia. Masih ada {$sisaPeserta} peserta dalam tugas ini."
                    : 'Seluruh peserta telah dikeluarkan dari jadwal susulan oleh panitia. Tugas pengawasan ini dibatalkan.',
                $masihAdaTugas
                    ? route('tugas-pengawas-ujian.susulan.show', $kelompokSusulan)
                    : route('tugas-pengawas-ujian.index'),
                null,
                ['jadwal_ujian_cbt_id' => $jadwalUjianCbt->id, 'jenis' => 'pengawas_ujian_susulan_dibatalkan'],
            );
        }

        return back()->with('berhasil', 'Jadwal ujian susulan siswa berhasil dibatalkan.');
    }

    public function updatePengawas(
        Request $request,
        KegiatanUjianCbt $kegiatanUjianCbt,
        JadwalUjianCbt $jadwalUjianCbt,
        RuangKegiatanUjianCbt $ruangKegiatanUjianCbt,
        SinkronkanPelaksanaanUjianTerpusat $sinkronisasi,
        NotifikasiUjianTerpusatService $notifikasi,
    ) {
        abort_unless($kegiatanUjianCbt->dapatDiaksesOleh($request->user()), 403);
        abort_unless((int) $jadwalUjianCbt->kegiatan_ujian_cbt_id === (int) $kegiatanUjianCbt->id, 404);
        abort_unless((int) $ruangKegiatanUjianCbt->kegiatan_ujian_cbt_id === (int) $kegiatanUjianCbt->id, 404);

        $ruangDipakai = $kegiatanUjianCbt->kelompokPesertaKegiatanUjianCbt()
            ->where('tingkat', $jadwalUjianCbt->tingkat)
            ->whereHas('ruangKegiatanUjianCbt', fn ($query) => $query->whereKey($ruangKegiatanUjianCbt->id))
            ->exists();
        abort_unless($ruangDipakai, 404);

        $data = $request->validate([
            'pengawas_utama_pegawai_id' => ['nullable', 'integer', Rule::exists('pegawai', 'id')->where('aktif', true)],
            'pengawas_pendamping_pegawai_id' => ['nullable', 'integer', Rule::exists('pegawai', 'id')->where('aktif', true)],
            'catatan' => ['nullable', 'string', 'max:500'],
        ]);

        if (filled($data['pengawas_utama_pegawai_id'] ?? null)
            && (int) $data['pengawas_utama_pegawai_id'] === (int) ($data['pengawas_pendamping_pegawai_id'] ?? 0)) {
            throw ValidationException::withMessages([
                'pengawas_pendamping_pegawai_id' => 'Pengawas utama dan pendamping harus orang yang berbeda.',
            ]);
        }

        $nilai = [
            'pengawas_utama_pegawai_id' => $data['pengawas_utama_pegawai_id'] ?? null,
            'pengawas_pendamping_pegawai_id' => $data['pengawas_pendamping_pegawai_id'] ?? null,
            'catatan' => filled($data['catatan'] ?? null) ? trim($data['catatan']) : null,
        ];
        $penugasanLama = PengawasRuangUjianTerpusat::query()
            ->where('jadwal_ujian_cbt_id', $jadwalUjianCbt->id)
            ->where('ruang_kegiatan_ujian_cbt_id', $ruangKegiatanUjianCbt->id)
            ->first();

        foreach ([
            'pengawas_utama_pegawai_id',
            'pengawas_pendamping_pegawai_id',
        ] as $kolom) {
            $pegawaiLamaId = (int) ($penugasanLama?->{$kolom} ?? 0);
            $pegawaiBaruId = (int) ($nilai[$kolom] ?? 0);

            if ($pegawaiLamaId > 0 && $pegawaiBaruId !== $pegawaiLamaId) {
                throw ValidationException::withMessages([
                    $kolom => 'Pengawas yang sudah ditugaskan harus diubah melalui Ganti pengawas mendadak agar riwayatnya tercatat.',
                ]);
            }
        }

        $this->pastikanPengawasTidakBentrok(
            $jadwalUjianCbt,
            $ruangKegiatanUjianCbt,
            [
                $nilai['pengawas_utama_pegawai_id'],
                $nilai['pengawas_pendamping_pegawai_id'],
            ],
        );

        if (collect($nilai)->filter()->isEmpty()) {
            PengawasRuangUjianTerpusat::query()
                ->where('jadwal_ujian_cbt_id', $jadwalUjianCbt->id)
                ->where('ruang_kegiatan_ujian_cbt_id', $ruangKegiatanUjianCbt->id)
                ->delete();
        } else {
            PengawasRuangUjianTerpusat::query()->updateOrCreate(
                [
                    'jadwal_ujian_cbt_id' => $jadwalUjianCbt->id,
                    'ruang_kegiatan_ujian_cbt_id' => $ruangKegiatanUjianCbt->id,
                ],
                [
                    ...$nilai,
                    'ditugaskan_oleh_pengguna_id' => $request->user()?->id,
                ],
            );
        }

        $sinkronisasi->sinkronkanJadwal($jadwalUjianCbt->fresh(), $request->user());

        foreach ([
            'utama' => 'pengawas_utama_pegawai_id',
            'pendamping' => 'pengawas_pendamping_pegawai_id',
        ] as $peran => $kolom) {
            $pegawaiBaruId = (int) ($nilai[$kolom] ?? 0);
            $pegawaiLamaId = (int) ($penugasanLama?->{$kolom} ?? 0);

            if ($pegawaiBaruId > 0 && $pegawaiBaruId !== $pegawaiLamaId) {
                $notifikasi->kirimTugasPengawas(
                    $jadwalUjianCbt,
                    $ruangKegiatanUjianCbt,
                    $pegawaiBaruId,
                    $peran,
                );
            }
        }

        return back()->with('berhasil', "Pengawas {$ruangKegiatanUjianCbt->nama} berhasil diperbarui.");
    }

    public function gantiPengawas(
        Request $request,
        KegiatanUjianCbt $kegiatanUjianCbt,
        JadwalUjianCbt $jadwalUjianCbt,
        RuangKegiatanUjianCbt $ruangKegiatanUjianCbt,
        SinkronkanPelaksanaanUjianTerpusat $sinkronisasi,
        NotifikasiUjianTerpusatService $notifikasi,
    ) {
        abort_unless($kegiatanUjianCbt->dapatDiaksesOleh($request->user()), 403);
        abort_unless((int) $jadwalUjianCbt->kegiatan_ujian_cbt_id === (int) $kegiatanUjianCbt->id, 404);
        abort_unless((int) $ruangKegiatanUjianCbt->kegiatan_ujian_cbt_id === (int) $kegiatanUjianCbt->id, 404);

        $ruangDipakai = $kegiatanUjianCbt->kelompokPesertaKegiatanUjianCbt()
            ->where('tingkat', $jadwalUjianCbt->tingkat)
            ->whereHas('ruangKegiatanUjianCbt', fn ($query) => $query->whereKey($ruangKegiatanUjianCbt->id))
            ->exists();
        abort_unless($ruangDipakai, 404);

        $data = $request->validate([
            'peran_pengawas' => ['required', Rule::in(['utama', 'pendamping'])],
            'pegawai_pengganti_id' => ['required', 'integer', Rule::exists('pegawai', 'id')->where('aktif', true)],
            'alasan' => ['required', 'string', 'min:5', 'max:1000'],
        ], [
            'pegawai_pengganti_id.required' => 'Pilih pegawai yang menjadi pengawas pengganti.',
            'alasan.required' => 'Alasan penggantian wajib ditulis agar riwayat tugas jelas.',
            'alasan.min' => 'Alasan penggantian perlu ditulis sedikit lebih jelas.',
        ]);

        [$pengawasLama, $pengawasBaru] = DB::transaction(function () use (
            $data,
            $jadwalUjianCbt,
            $ruangKegiatanUjianCbt,
            $request,
        ) {
            $penugasan = PengawasRuangUjianTerpusat::query()
                ->where('jadwal_ujian_cbt_id', $jadwalUjianCbt->id)
                ->where('ruang_kegiatan_ujian_cbt_id', $ruangKegiatanUjianCbt->id)
                ->lockForUpdate()
                ->firstOrFail();
            $kolomDiganti = $data['peran_pengawas'] === 'utama'
                ? 'pengawas_utama_pegawai_id'
                : 'pengawas_pendamping_pegawai_id';
            $kolomLain = $data['peran_pengawas'] === 'utama'
                ? 'pengawas_pendamping_pegawai_id'
                : 'pengawas_utama_pegawai_id';
            $pegawaiLamaId = (int) ($penugasan->{$kolomDiganti} ?? 0);
            $pegawaiBaruId = (int) $data['pegawai_pengganti_id'];

            if ($pegawaiLamaId < 1) {
                throw ValidationException::withMessages([
                    'peran_pengawas' => 'Posisi tersebut belum memiliki pengawas. Gunakan form penugasan biasa untuk mengisinya.',
                ]);
            }

            if ($pegawaiLamaId === $pegawaiBaruId) {
                throw ValidationException::withMessages([
                    'pegawai_pengganti_id' => 'Pengawas pengganti harus berbeda dari pengawas sebelumnya.',
                ]);
            }

            if ((int) ($penugasan->{$kolomLain} ?? 0) === $pegawaiBaruId) {
                throw ValidationException::withMessages([
                    'pegawai_pengganti_id' => 'Pegawai ini sudah bertugas pada posisi pengawas lainnya di ruang yang sama.',
                ]);
            }

            $this->pastikanPengawasTidakBentrok(
                $jadwalUjianCbt,
                $ruangKegiatanUjianCbt,
                [$pegawaiBaruId],
                'pegawai_pengganti_id',
            );

            $pengawasLama = Pegawai::query()->findOrFail($pegawaiLamaId);
            $pengawasBaru = Pegawai::query()->findOrFail($pegawaiBaruId);

            RiwayatPergantianPengawasUjian::query()->create([
                'pengawas_ruang_ujian_terpusat_id' => $penugasan->id,
                'jadwal_ujian_cbt_id' => $jadwalUjianCbt->id,
                'ruang_kegiatan_ujian_cbt_id' => $ruangKegiatanUjianCbt->id,
                'peran_pengawas' => $data['peran_pengawas'],
                'pegawai_lama_id' => $pengawasLama->id,
                'pegawai_baru_id' => $pengawasBaru->id,
                'alasan' => trim($data['alasan']),
                'diganti_oleh_pengguna_id' => $request->user()?->id,
                'diganti_pada' => now(),
            ]);

            $penugasan->update([
                $kolomDiganti => $pengawasBaru->id,
                'ditugaskan_oleh_pengguna_id' => $request->user()?->id,
            ]);

            return [$pengawasLama, $pengawasBaru];
        });

        $sinkronisasi->sinkronkanJadwal($jadwalUjianCbt->fresh(), $request->user());
        $notifikasi->kirimPenggantianPengawas(
            $jadwalUjianCbt,
            $ruangKegiatanUjianCbt,
            $pengawasLama,
            $pengawasBaru,
            $data['peran_pengawas'],
            trim($data['alasan']),
        );

        return back()->with(
            'berhasil',
            "Pengawas {$ruangKegiatanUjianCbt->nama} berhasil diganti dari {$pengawasLama->nama_lengkap} menjadi {$pengawasBaru->nama_lengkap}.",
        );
    }

    private function jumlahPerluKoreksiManual(int $ujianId): int
    {
        return JawabanPesertaUjianCbt::query()
            ->whereNotNull('jawaban')
            ->whereNull('skor')
            ->whereHas('pesertaUjianCbt', fn ($query) => $query->where('ujian_cbt_id', $ujianId))
            ->whereHas('soalCbt', fn ($query) => $query->whereNotIn('jenis_soal', KoreksiOtomatisCbtService::JENIS_OTOMATIS))
            ->count();
    }

    private function pastikanBolehAturSusulan(
        Request $request,
        KegiatanUjianCbt $kegiatanUjianCbt,
        JadwalUjianCbt $jadwalUjianCbt,
    ): void {
        abort_unless($kegiatanUjianCbt->dapatDiaksesOleh($request->user()), 403);
        abort_unless($request->user()->memilikiIzin(['cbt.panitia', 'cbt.kelola']), 403);
        abort_unless((int) $jadwalUjianCbt->kegiatan_ujian_cbt_id === (int) $kegiatanUjianCbt->id, 404);
        abort_unless($jadwalUjianCbt->ujian_cbt_id, 422, 'Paket soal untuk jadwal ini belum diterbitkan.');

        $paket = $jadwalUjianCbt->ujianCbt()->first();

        if ($paket?->hasil_difinalisasi_pada) {
            throw ValidationException::withMessages([
                'susulan' => 'Hasil ujian sudah difinalisasi. Batalkan publikasi dan finalisasi hasil terlebih dahulu sebelum menjadwalkan susulan.',
            ]);
        }
    }

    private function buatTokenSusulan(): string
    {
        do {
            $token = (string) random_int(100000, 999999);
        } while (PesertaUjianCbt::query()
            ->where('status_susulan', 'dijadwalkan')
            ->where('token_susulan', $token)
            ->exists());

        return $token;
    }

    private function pastikanPengawasTidakBentrok(
        JadwalUjianCbt $jadwal,
        RuangKegiatanUjianCbt $ruang,
        array $pegawaiIds,
        string $kunciValidasi = 'pengawas_utama_pegawai_id',
    ): void {
        $pegawaiIds = collect($pegawaiIds)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($pegawaiIds->isEmpty()) {
            return;
        }

        $bentrok = PengawasRuangUjianTerpusat::query()
            ->where(function ($query) use ($jadwal, $ruang) {
                $query->where('jadwal_ujian_cbt_id', '!=', $jadwal->id)
                    ->orWhere('ruang_kegiatan_ujian_cbt_id', '!=', $ruang->id);
            })
            ->where(function ($query) use ($pegawaiIds) {
                $query->whereIn('pengawas_utama_pegawai_id', $pegawaiIds)
                    ->orWhereIn('pengawas_pendamping_pegawai_id', $pegawaiIds);
            })
            ->whereHas('jadwalUjianCbt', fn ($query) => $query
                ->whereDate('tanggal', $jadwal->tanggal)
                ->where('waktu_mulai', '<', $jadwal->waktu_selesai)
                ->where('waktu_selesai', '>', $jadwal->waktu_mulai))
            ->with(['jadwalUjianCbt.mataPelajaran', 'ruangKegiatanUjianCbt'])
            ->first();

        if (! $bentrok) {
            return;
        }

        throw ValidationException::withMessages([
            $kunciValidasi => 'Pengawas sudah bertugas pada '
                .($bentrok->jadwalUjianCbt?->mataPelajaran?->nama ?: 'mata pelajaran lain')
                .' di '.($bentrok->ruangKegiatanUjianCbt?->nama ?: 'ruang lain')
                .' pukul '.($bentrok->jadwalUjianCbt?->labelWaktu() ?: '-').'.',
        ]);
    }
}
