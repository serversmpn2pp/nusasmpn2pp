<?php

namespace App\Http\Controllers;

use App\Models\JadwalUjianCbt;
use App\Models\JawabanPesertaUjianCbt;
use App\Models\KegiatanUjianCbt;
use App\Models\Pegawai;
use App\Models\PengawasRuangUjianTerpusat;
use App\Models\RuangKegiatanUjianCbt;
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
            $item->setAttribute('boleh_kelola_nilai', $paket?->dapatDikelolaOleh($request->user()) ?? false);
            $item->setAttribute('perlu_koreksi_manual', $paket ? $this->jumlahPerluKoreksiManual($paket->id) : 0);
        });

        $paket = $jadwal->pluck('ujianCbt')->filter();
        $bolehAturPengawas = $mode === 'pelaksanaan'
            && $aksesPenuh
            && $request->user()->memilikiIzin(['cbt.panitia', 'cbt.kelola']);

        return view('ujian-terpusat.pelaksanaan-nilai.index', [
            'kegiatan' => $kegiatanUjianCbt,
            'jadwal' => $jadwal,
            'mode' => $mode,
            'tahapAktif' => $mode === 'hasil' ? 10 : 9,
            'bolehAturPengawas' => $bolehAturPengawas,
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
            ],
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
