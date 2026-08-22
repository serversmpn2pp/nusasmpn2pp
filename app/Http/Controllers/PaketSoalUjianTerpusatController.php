<?php

namespace App\Http\Controllers;

use App\Models\GuruMataPelajaran;
use App\Models\JadwalUjianCbt;
use App\Models\KomponenNilai;
use App\Models\Pengguna;
use App\Models\SoalCbt;
use App\Models\UjianCbt;
use App\Services\Cbt\SinkronkanPelaksanaanUjianTerpusat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PaketSoalUjianTerpusatController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'kegiatan' => ['nullable', 'integer', 'exists:kegiatan_ujian_cbt,id'],
        ]);

        $jadwal = $this->queryJadwalDalamCakupan($request)
            ->with([
                'kegiatanUjianCbt.jenisUjianCbt',
                'kegiatanUjianCbt.tahunPelajaran',
                'sesiKegiatanUjianCbt',
                'mataPelajaran',
                'kelas',
                'ujianCbt' => fn ($query) => $query->withCount('soalUjianCbt'),
            ])
            ->when($data['kegiatan'] ?? null, fn (Builder $query, $id) => $query->where('kegiatan_ujian_cbt_id', $id))
            ->whereHas('kegiatanUjianCbt', fn (Builder $query) => $query->where('status', '!=', 'nonaktif'))
            ->orderBy('tanggal')
            ->orderBy('waktu_mulai')
            ->orderBy('tingkat')
            ->get();

        $jadwal->each(function (JadwalUjianCbt $item) use ($request) {
            $item->setAttribute('boleh_kelola_paket', $this->bolehMengelola($request->user(), $item));
        });

        return view('paket-soal-ujian-terpusat.index', [
            'jadwalPerKegiatan' => $jadwal->groupBy('kegiatan_ujian_cbt_id'),
            'jumlahJadwal' => $jadwal->count(),
            'jumlahSiap' => $jadwal->filter(fn (JadwalUjianCbt $item) => $this->paketSiap($item->ujianCbt))->count(),
            'jumlahDraf' => $jadwal->filter(fn (JadwalUjianCbt $item) => $item->ujianCbt?->status === 'draft')->count(),
            'jumlahBelumDisusun' => $jadwal->whereNull('ujian_cbt_id')->count(),
        ]);
    }

    public function show(Request $request, JadwalUjianCbt $jadwalUjianCbt)
    {
        $jadwalUjianCbt->load([
            'kegiatanUjianCbt.jenisUjianCbt',
            'kegiatanUjianCbt.tahunPelajaran',
            'sesiKegiatanUjianCbt',
            'mataPelajaran.pengaturanTingkat',
            'kelas',
            'ujianCbt.soalUjianCbt.soalCbt',
        ]);
        $this->pastikanBolehMelihat($request->user(), $jadwalUjianCbt);

        $bolehKelola = $this->bolehMengelola($request->user(), $jadwalUjianCbt);
        $soalDipilih = $jadwalUjianCbt->ujianCbt?->soalUjianCbt?->keyBy('soal_cbt_id') ?? collect();
        $soal = SoalCbt::query()
            ->where('mata_pelajaran_id', $jadwalUjianCbt->mata_pelajaran_id)
            ->where('tingkat', $jadwalUjianCbt->tingkat)
            ->when(
                $bolehKelola,
                fn (Builder $query) => $query->where(function (Builder $query) use ($soalDipilih) {
                    $query->where(fn (Builder $query) => $query->where('aktif', true)->where('status', 'siap'))
                        ->when($soalDipilih->isNotEmpty(), fn (Builder $query) => $query->orWhereIn('id', $soalDipilih->keys()));
                }),
                fn (Builder $query) => $query->whereIn('id', $soalDipilih->keys()),
            )
            ->orderBy('jenis_soal')
            ->orderBy('tingkat_kesulitan')
            ->orderBy('kode')
            ->get();

        $pengaturan = $jadwalUjianCbt->mataPelajaran?->pengaturanUntuk(
            (int) $jadwalUjianCbt->kegiatanUjianCbt->tahun_pelajaran_id,
            (int) $jadwalUjianCbt->tingkat,
        );

        return view('paket-soal-ujian-terpusat.show', [
            'jadwal' => $jadwalUjianCbt,
            'paket' => $jadwalUjianCbt->ujianCbt,
            'soal' => $soal,
            'soalDipilih' => $soalDipilih,
            'bolehKelola' => $bolehKelola,
            'kkm' => $pengaturan?->kkm ?? $jadwalUjianCbt->mataPelajaran?->kkm,
            'daftarJenisSoal' => SoalCbt::DAFTAR_JENIS,
            'daftarKesulitan' => SoalCbt::DAFTAR_KESULITAN,
        ]);
    }

    public function update(
        Request $request,
        JadwalUjianCbt $jadwalUjianCbt,
        SinkronkanPelaksanaanUjianTerpusat $sinkronisasi,
    ) {
        $jadwalUjianCbt->load([
            'kegiatanUjianCbt.jenisUjianCbt',
            'sesiKegiatanUjianCbt',
            'mataPelajaran.pengaturanTingkat',
            'kelas',
            'ujianCbt',
        ]);
        abort_unless($this->bolehMengelola($request->user(), $jadwalUjianCbt), 403);

        $data = $request->validate([
            'aksi' => ['required', Rule::in(['draf', 'simpan', 'terbitkan'])],
            'soal' => ['nullable', 'array'],
            'soal.*.dipilih' => ['nullable', 'boolean'],
            'soal.*.bobot' => ['nullable', 'numeric', 'min:0.25', 'max:100'],
        ]);
        $soalTerpilih = collect($data['soal'] ?? [])
            ->filter(fn ($item) => filter_var($item['dipilih'] ?? false, FILTER_VALIDATE_BOOLEAN))
            ->mapWithKeys(fn ($item, $id) => [(int) $id => (float) ($item['bobot'] ?? 1)]);

        $this->pastikanPaketBelumDikerjakan($jadwalUjianCbt->ujianCbt);
        $this->pastikanSoalValid($jadwalUjianCbt, $soalTerpilih);

        if ($data['aksi'] === 'terbitkan' && $soalTerpilih->isEmpty()) {
            throw ValidationException::withMessages([
                'soal' => 'Pilih minimal satu soal sebelum paket diterbitkan.',
            ]);
        }

        $status = match ($data['aksi']) {
            'terbitkan' => 'terjadwal',
            'draf' => 'draft',
            default => $jadwalUjianCbt->ujianCbt?->status ?? 'draft',
        };

        DB::transaction(function () use ($request, $jadwalUjianCbt, $soalTerpilih, $status) {
            $paketSiap = in_array($status, ['terjadwal', 'berlangsung', 'selesai'], true);
            $paket = $jadwalUjianCbt->ujianCbt ?: new UjianCbt;
            $paket->fill($this->dataPaketOtomatis($jadwalUjianCbt, $soalTerpilih->count(), $status));
            if (! $paket->exists) {
                $paket->dibuat_oleh_pengguna_id = $request->user()?->id;
            }
            $paket->save();

            $paket->soalUjianCbt()->whereNotIn('soal_cbt_id', $soalTerpilih->keys())->delete();
            foreach ($soalTerpilih as $nomor => $bobot) {
                $paket->soalUjianCbt()->updateOrCreate(
                    ['soal_cbt_id' => $nomor],
                    ['nomor_urut' => $soalTerpilih->keys()->search($nomor) + 1, 'bobot' => $bobot],
                );
            }

            $this->sinkronkanKelasDanKomponen($paket, $jadwalUjianCbt, $paketSiap);
            $jadwalUjianCbt->update([
                'ujian_cbt_id' => $paket->id,
                'status' => $paketSiap ? 'siap' : 'draft',
            ]);
        });

        $jadwalUjianCbt->refresh();
        $sinkronisasi->sinkronkanJadwal($jadwalUjianCbt, $request->user());

        $pesan = $status === 'terjadwal'
            ? 'Paket soal berhasil diterbitkan dan siap digunakan pada jadwal ini.'
            : 'Draf paket soal berhasil disimpan.';

        return redirect()->route('paket-soal-terpusat.show', $jadwalUjianCbt)->with('berhasil', $pesan);
    }

    private function queryJadwalDalamCakupan(Request $request): Builder
    {
        $pengguna = $request->user();
        $query = JadwalUjianCbt::query();

        if ($pengguna->memilikiIzin(['cbt.kelola', 'cbt.terpusat_lihat'])) {
            return $query;
        }

        $cakupanGuru = $this->cakupanGuru($pengguna);

        return $query->where(function (Builder $query) use ($pengguna, $cakupanGuru) {
            if ($pengguna->pegawai_id && $pengguna->memilikiIzin('cbt.panitia')) {
                $query->orWhereHas('kegiatanUjianCbt.panitiaUjianCbt', fn (Builder $query) => $query
                    ->where('pegawai_id', $pengguna->pegawai_id)
                    ->where('aktif', true));
            }

            if ($pengguna->memilikiIzin('cbt.soal_kelola')) {
                foreach ($cakupanGuru as $cakupan) {
                    $query->orWhere(function (Builder $query) use ($cakupan) {
                        $query->where('mata_pelajaran_id', $cakupan['mata_pelajaran_id'])
                            ->where('tingkat', $cakupan['tingkat'])
                            ->whereHas('kelas', fn (Builder $query) => $query->whereIn('kelas.id', $cakupan['kelas_ids']))
                            ->whereHas('kegiatanUjianCbt', fn (Builder $query) => $query
                                ->where('tahun_pelajaran_id', $cakupan['tahun_pelajaran_id']));
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

        return GuruMataPelajaran::query()
            ->with('kelas:id,tingkat')
            ->where('pegawai_id', $pengguna->pegawai_id)
            ->where('aktif', true)
            ->get()
            ->filter(fn (GuruMataPelajaran $item) => filled($item->kelas?->tingkat))
            ->map(fn (GuruMataPelajaran $item) => [
                'tahun_pelajaran_id' => (int) $item->tahun_pelajaran_id,
                'mata_pelajaran_id' => (int) $item->mata_pelajaran_id,
                'tingkat' => (int) $item->kelas->tingkat,
                'kelas_id' => (int) $item->kelas_id,
            ])
            ->groupBy(fn (array $item) => implode(':', collect($item)->only(['tahun_pelajaran_id', 'mata_pelajaran_id', 'tingkat'])->all()))
            ->map(fn (Collection $items) => [
                ...$items->first(),
                'kelas_ids' => $items->pluck('kelas_id')->unique()->values()->all(),
            ])
            ->values();
    }

    private function bolehMengelola(Pengguna $pengguna, JadwalUjianCbt $jadwal): bool
    {
        if ($pengguna->memilikiIzin('cbt.kelola')) {
            return true;
        }

        if (! $pengguna->pegawai_id || ! $pengguna->memilikiIzin('cbt.soal_kelola')) {
            return false;
        }

        return GuruMataPelajaran::query()
            ->where('pegawai_id', $pengguna->pegawai_id)
            ->where('tahun_pelajaran_id', $jadwal->kegiatanUjianCbt->tahun_pelajaran_id)
            ->where('mata_pelajaran_id', $jadwal->mata_pelajaran_id)
            ->whereIn('kelas_id', $jadwal->kelas->modelKeys())
            ->where('aktif', true)
            ->exists();
    }

    private function pastikanBolehMelihat(Pengguna $pengguna, JadwalUjianCbt $jadwal): void
    {
        if ($this->bolehMengelola($pengguna, $jadwal) || $pengguna->memilikiIzin(['cbt.kelola', 'cbt.terpusat_lihat'])) {
            return;
        }

        $panitia = $pengguna->pegawai_id
            && $pengguna->memilikiIzin('cbt.panitia')
            && $jadwal->kegiatanUjianCbt->panitiaUjianCbt()
                ->where('pegawai_id', $pengguna->pegawai_id)
                ->where('aktif', true)
                ->exists();

        abort_unless($panitia, 403);
    }

    private function pastikanSoalValid(JadwalUjianCbt $jadwal, Collection $soal): void
    {
        if ($soal->isEmpty()) {
            return;
        }

        $jumlahValid = SoalCbt::query()
            ->whereIn('id', $soal->keys())
            ->where('mata_pelajaran_id', $jadwal->mata_pelajaran_id)
            ->where('tingkat', $jadwal->tingkat)
            ->where('aktif', true)
            ->where('status', 'siap')
            ->count();

        if ($jumlahValid !== $soal->count()) {
            throw ValidationException::withMessages([
                'soal' => 'Ada soal yang tidak sesuai dengan mata pelajaran, tingkat, atau belum siap digunakan.',
            ]);
        }
    }

    private function pastikanPaketBelumDikerjakan(?UjianCbt $paket): void
    {
        if ($paket?->pesertaUjianCbt()->whereIn('status', ['sedang_mengerjakan', 'selesai'])->exists()) {
            throw ValidationException::withMessages([
                'paket' => 'Paket tidak dapat diubah karena sudah dikerjakan oleh peserta.',
            ]);
        }
    }

    private function dataPaketOtomatis(JadwalUjianCbt $jadwal, int $jumlahSoal, string $status): array
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
            'alur' => 'terpusat',
            'jenis_ujian_cbt_id' => $kegiatan->jenis_ujian_cbt_id,
            'tahun_pelajaran_id' => $kegiatan->tahun_pelajaran_id,
            'mata_pelajaran_id' => $jadwal->mata_pelajaran_id,
            'kode' => "UT-{$kegiatan->id}-JADWAL-{$jadwal->id}",
            'nama' => "{$kegiatan->nama} - {$jadwal->mataPelajaran->nama} Tingkat {$jadwal->tingkat}",
            'semester' => $kegiatan->semester,
            'tingkat' => $jadwal->tingkat,
            'tanggal_mulai' => $mulai,
            'tanggal_selesai' => $selesai,
            'durasi_menit' => max(10, $mulai->diffInMinutes($selesai)),
            'jumlah_soal' => $jumlahSoal,
            'kkm' => $pengaturan?->kkm ?? $jadwal->mataPelajaran?->kkm,
            'token' => $kegiatan->jenisUjianCbt?->memerlukan_token ? $token : null,
            'acak_soal' => true,
            'acak_jawaban' => true,
            'batasi_satu_perangkat' => true,
            'deteksi_pindah_tab' => false,
            'wajib_fullscreen' => false,
            'tampilkan_hasil' => false,
            'status' => $status,
            'petunjuk' => 'Baca setiap soal dengan teliti. Pastikan jawaban tersimpan sebelum mengakhiri ujian.',
            'keterangan' => 'Dibuat otomatis dari Jadwal Ujian Terpusat.',
        ];
    }

    private function sinkronkanKelasDanKomponen(UjianCbt $paket, JadwalUjianCbt $jadwal, bool $buatKomponen): void
    {
        $paket->kelasUjianCbt()->whereNotIn('kelas_id', $jadwal->kelas->modelKeys())->delete();
        $jenisKomponen = match ($jadwal->kegiatanUjianCbt->jenisUjianCbt?->kode) {
            'STS' => 'sts',
            'SAS', 'SAJ' => 'sas_saj',
            default => 'sumatif',
        };

        foreach ($jadwal->kelas as $kelas) {
            $komponenId = null;
            $guruMapel = GuruMataPelajaran::query()
                ->where('tahun_pelajaran_id', $jadwal->kegiatanUjianCbt->tahun_pelajaran_id)
                ->where('mata_pelajaran_id', $jadwal->mata_pelajaran_id)
                ->where('kelas_id', $kelas->id)
                ->where('aktif', true)
                ->orderBy('id')
                ->first();

            if ($buatKomponen && $guruMapel && $jadwal->kegiatanUjianCbt->jenisUjianCbt?->dapat_diterapkan_ke_nilai) {
                $komponen = KomponenNilai::query()
                    ->where('guru_mata_pelajaran_id', $guruMapel->id)
                    ->where('semester', $jadwal->kegiatanUjianCbt->semester)
                    ->where('jenis_komponen', $jenisKomponen)
                    ->when($jenisKomponen === 'sumatif', fn (Builder $query) => $query->where('nama', $jadwal->kegiatanUjianCbt->nama))
                    ->first();

                if (! $komponen) {
                    $komponen = KomponenNilai::create([
                        'guru_mata_pelajaran_id' => $guruMapel->id,
                        'semester' => $jadwal->kegiatanUjianCbt->semester,
                        'jenis_komponen' => $jenisKomponen,
                        'nama' => $jadwal->kegiatanUjianCbt->nama,
                        'tanggal_penilaian' => $jadwal->tanggal,
                        'urutan' => ((int) KomponenNilai::where('guru_mata_pelajaran_id', $guruMapel->id)->where('semester', $jadwal->kegiatanUjianCbt->semester)->max('urutan')) + 1,
                        'aktif' => true,
                        'keterangan' => 'Dibuat otomatis dari Ujian Terpusat CBT.',
                    ]);
                } elseif (! $komponen->aktif) {
                    $komponen->update([
                        'aktif' => true,
                        'tanggal_penilaian' => $jadwal->tanggal,
                        'keterangan' => 'Diaktifkan kembali dari Ujian Terpusat CBT.',
                    ]);
                }

                $komponenId = $komponen->id;
            }

            $paket->kelasUjianCbt()->updateOrCreate(
                ['kelas_id' => $kelas->id],
                ['komponen_nilai_id' => $komponenId],
            );
        }
    }

    private function paketSiap(?UjianCbt $paket): bool
    {
        return $paket && in_array($paket->status, ['terjadwal', 'berlangsung', 'selesai'], true);
    }
}
