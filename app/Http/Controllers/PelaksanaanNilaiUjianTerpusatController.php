<?php

namespace App\Http\Controllers;

use App\Models\BuktiRuangUjianCbt;
use App\Models\JadwalUjianCbt;
use App\Models\JawabanPesertaUjianCbt;
use App\Models\KegiatanUjianCbt;
use App\Models\Pegawai;
use App\Models\PengawasRuangUjianTerpusat;
use App\Models\PesertaUjianCbt;
use App\Models\RuangKegiatanUjianCbt;
use App\Models\RuangUjianCbt;
use App\Services\Cbt\KoreksiOtomatisCbtService;
use App\Services\Cbt\SinkronkanPelaksanaanUjianTerpusat;
use Illuminate\Http\Request;
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
                    'ujianCbt' => fn ($query) => $query->withCount([
                        'soalUjianCbt',
                        'pesertaUjianCbt',
                        'pesertaUjianCbt as peserta_sedang_count' => fn ($query) => $query->where('status', 'sedang_mengerjakan'),
                        'pesertaUjianCbt as peserta_selesai_count' => fn ($query) => $query->where('status', 'selesai'),
                        'pesertaUjianCbt as nilai_diterapkan_count' => fn ($query) => $query->whereNotNull('nilai_siswa_id'),
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
        });

        $paket = $jadwal->pluck('ujianCbt')->filter();
        $ruangOperasional = $jadwal->flatMap(fn (JadwalUjianCbt $item) => $item->ruangOperasional);
        $bolehAturPengawas = $mode === 'pelaksanaan'
            && $aksesPenuh
            && $request->user()->memilikiIzin(['cbt.panitia', 'cbt.kelola']);

        return view('ujian-terpusat.pelaksanaan-nilai.index', [
            'kegiatan' => $kegiatanUjianCbt,
            'jadwal' => $jadwal,
            'mode' => $mode,
            'tahapAktif' => $mode === 'hasil' ? 10 : 9,
            'bolehAturPengawas' => $bolehAturPengawas,
            'bolehCetakDokumen' => $mode === 'pelaksanaan' && $aksesPenuh,
            'pegawai' => $bolehAturPengawas
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

    public function updatePengawas(
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

        return back()->with('berhasil', "Pengawas {$ruangKegiatanUjianCbt->nama} berhasil diperbarui.");
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
}
