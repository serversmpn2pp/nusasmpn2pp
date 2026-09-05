<?php

namespace App\Services\Mobile;

use App\Models\GuruMataPelajaran;
use App\Models\JadwalUjianCbt;
use App\Models\KomponenNilai;
use App\Models\Pengguna;
use App\Models\SoalCbt;
use App\Models\UjianCbt;
use App\Services\Cbt\SinkronkanPelaksanaanUjianTerpusat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PaketSoalMobileService
{
    public function __construct(private readonly SinkronkanPelaksanaanUjianTerpusat $sinkronisasi) {}

    public function daftar(Pengguna $pengguna, array $filter): array
    {
        $kataKunci = trim((string) ($filter['kata_kunci'] ?? ''));
        $kegiatanId = isset($filter['kegiatan_id']) ? (int) $filter['kegiatan_id'] : null;
        $status = $filter['status'] ?? 'semua';
        $halaman = (int) ($filter['halaman'] ?? 1);
        $perHalaman = (int) ($filter['per_halaman'] ?? 12);

        $cakupan = $this->queryJadwalDalamCakupan($pengguna)
            ->whereHas('kegiatanUjianCbt', fn (Builder $query) => $query->where('status', '!=', 'nonaktif'));
        $referensiKegiatan = (clone $cakupan)
            ->with('kegiatanUjianCbt.jenisUjianCbt', 'kegiatanUjianCbt.tahunPelajaran')
            ->get()->pluck('kegiatanUjianCbt')->filter()->unique('id')->sortByDesc('tanggal_mulai')
            ->map(fn ($kegiatan) => [
                'id' => (int) $kegiatan->id,
                'nama' => $kegiatan->nama,
                'jenis' => $kegiatan->jenisUjianCbt?->nama,
                'tahun_pelajaran' => $kegiatan->tahunPelajaran?->nama,
            ])->values();

        $query = (clone $cakupan)
            ->with([
                'kegiatanUjianCbt.jenisUjianCbt',
                'kegiatanUjianCbt.tahunPelajaran',
                'sesiKegiatanUjianCbt',
                'mataPelajaran',
                'kelas',
                'ujianCbt.soalUjianCbt',
            ])
            ->when($kegiatanId, fn (Builder $query, int $id) => $query->where('kegiatan_ujian_cbt_id', $id))
            ->when($status === 'belum_disusun', fn (Builder $query) => $query->whereNull('ujian_cbt_id'))
            ->when($status === 'draft', fn (Builder $query) => $query->whereHas('ujianCbt', fn (Builder $query) => $query->where('status', 'draft')))
            ->when($status === 'siap', fn (Builder $query) => $query->whereHas('ujianCbt', fn (Builder $query) => $query->whereIn('status', ['terjadwal', 'berlangsung', 'selesai'])))
            ->when($kataKunci !== '', function (Builder $query) use ($kataKunci) {
                $pola = '%'.mb_strtolower($kataKunci).'%';
                $query->where(function (Builder $query) use ($pola) {
                    $query->whereHas('mataPelajaran', fn (Builder $query) => $query->whereRaw('LOWER(nama) LIKE ?', [$pola]))
                        ->orWhereHas('kegiatanUjianCbt', fn (Builder $query) => $query->whereRaw('LOWER(nama) LIKE ?', [$pola]))
                        ->orWhereHas('kelas', fn (Builder $query) => $query->whereRaw('LOWER(nama) LIKE ?', [$pola]));
                });
            });

        $paginator = $query->orderByDesc('tanggal')->orderBy('waktu_mulai')->orderBy('tingkat')
            ->paginate($perHalaman, ['*'], 'halaman', $halaman);

        return [
            'ringkasan' => [
                'total' => (clone $cakupan)->count(),
                'siap' => (clone $cakupan)->whereHas('ujianCbt', fn (Builder $query) => $query->whereIn('status', ['terjadwal', 'berlangsung', 'selesai']))->count(),
                'draft' => (clone $cakupan)->whereHas('ujianCbt', fn (Builder $query) => $query->where('status', 'draft'))->count(),
                'belum_disusun' => (clone $cakupan)->whereNull('ujian_cbt_id')->count(),
            ],
            'items' => collect($paginator->items())->map(fn (JadwalUjianCbt $jadwal) => $this->ringkasJadwal($pengguna, $jadwal))->values(),
            'referensi' => [
                'kegiatan' => $referensiKegiatan,
                'status' => [
                    ['kode' => 'semua', 'label' => 'Semua status'],
                    ['kode' => 'belum_disusun', 'label' => 'Belum disusun'],
                    ['kode' => 'draft', 'label' => 'Masih draf'],
                    ['kode' => 'siap', 'label' => 'Siap digunakan'],
                ],
            ],
            'filter' => ['kata_kunci' => $kataKunci, 'kegiatan_id' => $kegiatanId, 'status' => $status],
            'paginasi' => [
                'halaman' => $paginator->currentPage(),
                'halaman_terakhir' => $paginator->lastPage(),
                'total' => $paginator->total(),
                'ada_halaman_berikutnya' => $paginator->hasMorePages(),
            ],
        ];
    }

    public function rincian(Pengguna $pengguna, JadwalUjianCbt $jadwal): array
    {
        $jadwal->load([
            'kegiatanUjianCbt.jenisUjianCbt', 'kegiatanUjianCbt.tahunPelajaran',
            'sesiKegiatanUjianCbt', 'mataPelajaran.pengaturanTingkat', 'kelas',
            'ujianCbt.soalUjianCbt.soalCbt',
        ]);
        $this->pastikanBolehMelihat($pengguna, $jadwal);
        $bolehKelola = $this->bolehMengelola($pengguna, $jadwal);
        $dipilih = $jadwal->ujianCbt?->soalUjianCbt?->keyBy('soal_cbt_id') ?? collect();
        $sudahDikerjakan = $this->sudahDikerjakan($jadwal->ujianCbt);

        $soal = SoalCbt::query()
            ->where('mata_pelajaran_id', $jadwal->mata_pelajaran_id)
            ->where('tingkat', $jadwal->tingkat)
            ->when(
                $bolehKelola,
                fn (Builder $query) => $query->where(function (Builder $query) use ($dipilih) {
                    $query->where(fn (Builder $query) => $query->where('aktif', true)->where('status', 'siap'))
                        ->when($dipilih->isNotEmpty(), fn (Builder $query) => $query->orWhereIn('id', $dipilih->keys()));
                }),
                fn (Builder $query) => $query->whereIn('id', $dipilih->keys()),
            )
            ->orderBy('jenis_soal')->orderBy('tingkat_kesulitan')->orderBy('kode')->get();

        return [
            'jadwal' => $this->ringkasJadwal($pengguna, $jadwal),
            'paket' => $jadwal->ujianCbt ? [
                'id' => (int) $jadwal->ujianCbt->id,
                'kode' => $jadwal->ujianCbt->kode,
                'nama' => $jadwal->ujianCbt->nama,
                'status' => $jadwal->ujianCbt->status,
                'label_status' => $jadwal->ujianCbt->labelStatus(),
                'acak_soal' => (bool) $jadwal->ujianCbt->acak_soal,
                'acak_jawaban' => (bool) $jadwal->ujianCbt->acak_jawaban,
                'token' => $jadwal->ujianCbt->token,
                'durasi_menit' => (int) $jadwal->ujianCbt->durasi_menit,
                'kkm' => (int) $jadwal->ujianCbt->kkm,
            ] : null,
            'soal' => $soal->map(fn (SoalCbt $soal) => $this->ringkasSoal($soal, $dipilih->get($soal->id)))->values(),
            'referensi' => [
                'jenis_soal' => $this->pilihan(SoalCbt::DAFTAR_JENIS),
                'tingkat_kesulitan' => $this->pilihan(SoalCbt::DAFTAR_KESULITAN),
            ],
            'hak_akses' => [
                'dapat_kelola' => $bolehKelola,
                'dapat_ubah' => $bolehKelola && ! $sudahDikerjakan,
                'sudah_dikerjakan' => $sudahDikerjakan,
            ],
        ];
    }

    public function simpan(Pengguna $pengguna, JadwalUjianCbt $jadwal, array $data): array
    {
        $jadwal->load(['kegiatanUjianCbt.jenisUjianCbt', 'sesiKegiatanUjianCbt', 'mataPelajaran.pengaturanTingkat', 'kelas', 'ujianCbt']);
        abort_unless($this->bolehMengelola($pengguna, $jadwal), 403);
        $this->pastikanPaketBelumDikerjakan($jadwal->ujianCbt);

        $soalTerpilih = collect($data['soal'] ?? [])->mapWithKeys(fn (array $item) => [(int) $item['id'] => (float) $item['bobot']]);
        $this->pastikanSoalValid($jadwal, $soalTerpilih);
        if ($data['aksi'] === 'terbitkan' && $soalTerpilih->isEmpty()) {
            throw ValidationException::withMessages(['soal' => 'Pilih minimal satu soal sebelum paket diterbitkan.']);
        }

        $status = match ($data['aksi']) {
            'terbitkan' => 'terjadwal',
            'draf' => 'draft',
            default => $jadwal->ujianCbt?->status ?? 'draft',
        };

        DB::transaction(function () use ($pengguna, $jadwal, $soalTerpilih, $status, $data) {
            $siap = in_array($status, ['terjadwal', 'berlangsung', 'selesai'], true);
            $paket = $jadwal->ujianCbt ?: new UjianCbt;
            $paket->fill($this->dataPaketOtomatis($jadwal, $soalTerpilih->count(), $status, (bool) $data['acak_soal'], (bool) $data['acak_jawaban']));
            if (! $paket->exists) {
                $paket->dibuat_oleh_pengguna_id = $pengguna->id;
            }
            $paket->save();
            $paket->soalUjianCbt()->whereNotIn('soal_cbt_id', $soalTerpilih->keys())->delete();
            foreach ($soalTerpilih as $soalId => $bobot) {
                $paket->soalUjianCbt()->updateOrCreate(
                    ['soal_cbt_id' => $soalId],
                    ['nomor_urut' => $soalTerpilih->keys()->search($soalId) + 1, 'bobot' => $bobot],
                );
            }
            $this->sinkronkanKelasDanKomponen($paket, $jadwal, $siap);
            $jadwal->update(['ujian_cbt_id' => $paket->id, 'status' => $siap ? 'siap' : 'draft']);
        });

        $jadwal->refresh();
        $this->sinkronisasi->sinkronkanJadwal($jadwal, $pengguna);

        return $this->rincian($pengguna, $jadwal);
    }

    private function ringkasJadwal(Pengguna $pengguna, JadwalUjianCbt $jadwal): array
    {
        $paket = $jadwal->ujianCbt;
        $siap = $paket && in_array($paket->status, ['terjadwal', 'berlangsung', 'selesai'], true);
        $kodeStatus = $siap ? 'siap' : ($paket ? 'draft' : 'belum_disusun');

        return [
            'id' => (int) $jadwal->id,
            'kegiatan' => [
                'id' => (int) $jadwal->kegiatanUjianCbt?->id,
                'nama' => $jadwal->kegiatanUjianCbt?->nama ?? '-',
                'jenis' => $jadwal->kegiatanUjianCbt?->jenisUjianCbt?->nama,
                'tahun_pelajaran' => $jadwal->kegiatanUjianCbt?->tahunPelajaran?->nama,
                'semester' => $jadwal->kegiatanUjianCbt?->semester,
            ],
            'mata_pelajaran' => $jadwal->mataPelajaran?->nama ?? '-',
            'tingkat' => (int) $jadwal->tingkat,
            'kelas' => $jadwal->kelas->pluck('nama')->values(),
            'tanggal' => $jadwal->tanggal?->toDateString(),
            'waktu' => $jadwal->sesiKegiatanUjianCbt?->labelWaktu() ?: $jadwal->labelWaktu(),
            'sesi' => $jadwal->sesiKegiatanUjianCbt?->nama ?? $jadwal->label_sesi,
            'status' => $kodeStatus,
            'label_status' => match ($kodeStatus) {
                'siap' => 'Siap digunakan', 'draft' => 'Masih draf', default => 'Belum disusun',
            },
            'jumlah_soal' => (int) ($paket?->soalUjianCbt?->count() ?? 0),
            'total_bobot' => (float) ($paket?->soalUjianCbt?->sum('bobot') ?? 0),
            'dapat_kelola' => $this->bolehMengelola($pengguna, $jadwal),
        ];
    }

    private function ringkasSoal(SoalCbt $soal, mixed $relasi): array
    {
        $jawaban = data_get($soal->kunci_jawaban, 'jawaban');
        $path = data_get($soal->media, 'gambar.path');

        return [
            'id' => (int) $soal->id, 'kode' => $soal->kode,
            'jenis_soal' => $soal->jenis_soal, 'label_jenis_soal' => $soal->labelJenis(),
            'tingkat_kesulitan' => $soal->tingkat_kesulitan, 'label_tingkat_kesulitan' => $soal->labelKesulitan(),
            'topik' => $soal->topik, 'materi' => $soal->materi, 'pertanyaan' => $soal->pertanyaan,
            'skor_maksimal' => (float) $soal->skor_maksimal,
            'dipilih' => (bool) $relasi,
            'bobot' => (float) ($relasi?->bobot ?? $soal->skor_maksimal),
            'nomor_urut' => $relasi ? (int) $relasi->nomor_urut : null,
            'dapat_dipilih' => (bool) ($soal->aktif && $soal->status === 'siap'),
            'jawaban' => is_array($jawaban) ? collect($jawaban)->join(', ') : $jawaban,
            'gambar_url' => $path ? url(Storage::url($path)) : null,
        ];
    }

    private function pilihan(array $items): Collection
    {
        return collect($items)->map(fn ($label, $kode) => ['kode' => $kode, 'label' => $label])->values();
    }

    private function queryJadwalDalamCakupan(Pengguna $pengguna): Builder
    {
        $query = JadwalUjianCbt::query();
        if ($pengguna->memilikiIzin(['cbt.kelola', 'cbt.terpusat_lihat'])) {
            return $query;
        }
        $cakupanGuru = $this->cakupanGuru($pengguna);

        return $query->where(function (Builder $query) use ($pengguna, $cakupanGuru) {
            if ($pengguna->pegawai_id && $pengguna->memilikiIzin('cbt.panitia')) {
                $query->orWhereHas('kegiatanUjianCbt.panitiaUjianCbt', fn (Builder $query) => $query->where('pegawai_id', $pengguna->pegawai_id)->where('aktif', true));
            }
            if ($pengguna->memilikiIzin('cbt.soal_kelola')) {
                foreach ($cakupanGuru as $cakupan) {
                    $query->orWhere(function (Builder $query) use ($cakupan) {
                        $query->where('mata_pelajaran_id', $cakupan['mata_pelajaran_id'])
                            ->where('tingkat', $cakupan['tingkat'])
                            ->whereHas('kelas', fn (Builder $query) => $query->whereIn('kelas.id', $cakupan['kelas_ids']))
                            ->whereHas('kegiatanUjianCbt', fn (Builder $query) => $query->where('tahun_pelajaran_id', $cakupan['tahun_pelajaran_id']));
                    });
                }
            }
            if (! $pengguna->pegawai_id || (! $pengguna->memilikiIzin('cbt.panitia') && $cakupanGuru->isEmpty())) {
                $query->whereRaw('1 = 0');
            }
        });
    }

    private function cakupanGuru(Pengguna $pengguna): Collection
    {
        if (! $pengguna->pegawai_id) {
            return collect();
        }

        return GuruMataPelajaran::query()->with('kelas:id,tingkat')
            ->where('pegawai_id', $pengguna->pegawai_id)->where('aktif', true)->get()
            ->filter(fn (GuruMataPelajaran $item) => filled($item->kelas?->tingkat))
            ->map(fn (GuruMataPelajaran $item) => [
                'tahun_pelajaran_id' => (int) $item->tahun_pelajaran_id,
                'mata_pelajaran_id' => (int) $item->mata_pelajaran_id,
                'tingkat' => (int) $item->kelas->tingkat,
                'kelas_id' => (int) $item->kelas_id,
            ])->groupBy(fn (array $item) => implode(':', collect($item)->only(['tahun_pelajaran_id', 'mata_pelajaran_id', 'tingkat'])->all()))
            ->map(fn (Collection $items) => [...$items->first(), 'kelas_ids' => $items->pluck('kelas_id')->unique()->values()->all()])->values();
    }

    private function bolehMengelola(Pengguna $pengguna, JadwalUjianCbt $jadwal): bool
    {
        if ($pengguna->memilikiIzin('cbt.kelola')) {
            return true;
        }
        if (! $pengguna->pegawai_id || ! $pengguna->memilikiIzin('cbt.soal_kelola')) {
            return false;
        }

        return GuruMataPelajaran::query()->where('pegawai_id', $pengguna->pegawai_id)
            ->where('tahun_pelajaran_id', $jadwal->kegiatanUjianCbt->tahun_pelajaran_id)
            ->where('mata_pelajaran_id', $jadwal->mata_pelajaran_id)
            ->whereIn('kelas_id', $jadwal->kelas->modelKeys())->where('aktif', true)->exists();
    }

    private function pastikanBolehMelihat(Pengguna $pengguna, JadwalUjianCbt $jadwal): void
    {
        if ($this->bolehMengelola($pengguna, $jadwal) || $pengguna->memilikiIzin(['cbt.kelola', 'cbt.terpusat_lihat'])) {
            return;
        }
        $panitia = $pengguna->pegawai_id && $pengguna->memilikiIzin('cbt.panitia')
            && $jadwal->kegiatanUjianCbt->panitiaUjianCbt()->where('pegawai_id', $pengguna->pegawai_id)->where('aktif', true)->exists();
        abort_unless($panitia, 403);
    }

    private function pastikanSoalValid(JadwalUjianCbt $jadwal, Collection $soal): void
    {
        if ($soal->isEmpty()) {
            return;
        }
        $jumlah = SoalCbt::query()->whereIn('id', $soal->keys())
            ->where('mata_pelajaran_id', $jadwal->mata_pelajaran_id)->where('tingkat', $jadwal->tingkat)
            ->where('aktif', true)->where('status', 'siap')->count();
        if ($jumlah !== $soal->count()) {
            throw ValidationException::withMessages(['soal' => 'Ada soal yang tidak sesuai dengan mata pelajaran, tingkat, atau belum siap digunakan.']);
        }
    }

    private function sudahDikerjakan(?UjianCbt $paket): bool
    {
        return $paket?->pesertaUjianCbt()->whereIn('status', ['sedang_mengerjakan', 'selesai'])->exists() ?? false;
    }

    private function pastikanPaketBelumDikerjakan(?UjianCbt $paket): void
    {
        if ($this->sudahDikerjakan($paket)) {
            throw ValidationException::withMessages(['paket' => 'Paket tidak dapat diubah karena sudah dikerjakan oleh peserta.']);
        }
    }

    private function dataPaketOtomatis(JadwalUjianCbt $jadwal, int $jumlahSoal, string $status, bool $acakSoal, bool $acakJawaban): array
    {
        $kegiatan = $jadwal->kegiatanUjianCbt;
        $mulai = Carbon::parse($jadwal->tanggal->format('Y-m-d').' '.$jadwal->waktu_mulai);
        $selesai = Carbon::parse($jadwal->tanggal->format('Y-m-d').' '.$jadwal->waktu_selesai);
        $pengaturan = $jadwal->mataPelajaran?->pengaturanUntuk((int) $kegiatan->tahun_pelajaran_id, (int) $jadwal->tingkat);
        $token = $jadwal->ujianCbt?->token;
        if ($status === 'terjadwal' && $kegiatan->jenisUjianCbt?->memerlukan_token && blank($token)) {
            $token = (string) random_int(100000, 999999);
        }

        return [
            'alur' => 'terpusat', 'jenis_ujian_cbt_id' => $kegiatan->jenis_ujian_cbt_id,
            'tahun_pelajaran_id' => $kegiatan->tahun_pelajaran_id, 'mata_pelajaran_id' => $jadwal->mata_pelajaran_id,
            'kode' => "UT-{$kegiatan->id}-JADWAL-{$jadwal->id}",
            'nama' => "{$kegiatan->nama} - {$jadwal->mataPelajaran->nama} Tingkat {$jadwal->tingkat}",
            'semester' => $kegiatan->semester, 'tingkat' => $jadwal->tingkat,
            'tanggal_mulai' => $mulai, 'tanggal_selesai' => $selesai,
            'durasi_menit' => max(10, $mulai->diffInMinutes($selesai)), 'jumlah_soal' => $jumlahSoal,
            'kkm' => $pengaturan?->kkm ?? $jadwal->mataPelajaran?->kkm,
            'token' => $kegiatan->jenisUjianCbt?->memerlukan_token ? $token : null,
            'acak_soal' => $acakSoal, 'acak_jawaban' => $acakJawaban, 'batasi_satu_perangkat' => true,
            'deteksi_pindah_tab' => true, 'wajib_fullscreen' => true, 'blokir_tangkapan_layar' => true,
            'toleransi_pindah_aplikasi_detik' => 3, 'batas_pindah_aplikasi' => 3,
            'tindakan_pindah_aplikasi' => 'tahan', 'tampilkan_hasil' => false,
            'status' => $status, 'petunjuk' => 'Baca setiap soal dengan teliti. Pastikan jawaban tersimpan sebelum mengakhiri ujian.',
            'keterangan' => 'Dibuat otomatis dari Jadwal Ujian Terpusat.',
        ];
    }

    private function sinkronkanKelasDanKomponen(UjianCbt $paket, JadwalUjianCbt $jadwal, bool $buatKomponen): void
    {
        $paket->kelasUjianCbt()->whereNotIn('kelas_id', $jadwal->kelas->modelKeys())->delete();
        $jenisKomponen = match ($jadwal->kegiatanUjianCbt->jenisUjianCbt?->kode) {
            'STS' => 'sts', 'SAS', 'SAJ' => 'sas_saj', default => 'sumatif',
        };
        foreach ($jadwal->kelas as $kelas) {
            $komponenId = null;
            $guru = GuruMataPelajaran::query()->where('tahun_pelajaran_id', $jadwal->kegiatanUjianCbt->tahun_pelajaran_id)
                ->where('mata_pelajaran_id', $jadwal->mata_pelajaran_id)->where('kelas_id', $kelas->id)
                ->where('aktif', true)->orderBy('id')->first();
            if ($buatKomponen && $guru && $jadwal->kegiatanUjianCbt->jenisUjianCbt?->dapat_diterapkan_ke_nilai) {
                $komponen = KomponenNilai::query()->where('guru_mata_pelajaran_id', $guru->id)
                    ->where('semester', $jadwal->kegiatanUjianCbt->semester)->where('jenis_komponen', $jenisKomponen)
                    ->when($jenisKomponen === 'sumatif', fn (Builder $query) => $query->where('nama', $jadwal->kegiatanUjianCbt->nama))->first();
                if (! $komponen) {
                    $komponen = KomponenNilai::create([
                        'guru_mata_pelajaran_id' => $guru->id, 'semester' => $jadwal->kegiatanUjianCbt->semester,
                        'jenis_komponen' => $jenisKomponen, 'nama' => $jadwal->kegiatanUjianCbt->nama,
                        'tanggal_penilaian' => $jadwal->tanggal,
                        'urutan' => ((int) KomponenNilai::where('guru_mata_pelajaran_id', $guru->id)->where('semester', $jadwal->kegiatanUjianCbt->semester)->max('urutan')) + 1,
                        'aktif' => true, 'keterangan' => 'Dibuat otomatis dari Ujian Terpusat CBT.',
                    ]);
                } elseif (! $komponen->aktif) {
                    $komponen->update(['aktif' => true, 'tanggal_penilaian' => $jadwal->tanggal, 'keterangan' => 'Diaktifkan kembali dari Ujian Terpusat CBT.']);
                }
                $komponenId = $komponen->id;
            }
            $paket->kelasUjianCbt()->updateOrCreate(['kelas_id' => $kelas->id], ['komponen_nilai_id' => $komponenId]);
        }
    }
}
