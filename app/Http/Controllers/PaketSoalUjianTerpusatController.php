<?php

namespace App\Http\Controllers;

use App\Models\FolderSoalCbt;
use App\Models\GuruMataPelajaran;
use App\Models\JadwalUjianCbt;
use App\Models\KegiatanUjianCbt;
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
            'tampilan' => ['nullable', Rule::in(['paket', 'hasil'])],
        ]);
        $tampilanHasil = ($data['tampilan'] ?? 'paket') === 'hasil';

        $jadwal = $this->queryJadwalDalamCakupan($request)
            ->with([
                'kegiatanUjianCbt.jenisUjianCbt',
                'kegiatanUjianCbt.tahunPelajaran',
                'sesiKegiatanUjianCbt',
                'mataPelajaran',
                'kelas',
                'ujianCbt' => fn ($query) => $query->withCount('soalUjianCbt')
                    ->when($tampilanHasil, fn ($query) => $query->withCount([
                        'pesertaUjianCbt',
                        'pesertaUjianCbt as peserta_selesai_count' => fn ($query) => $query->where('status', 'selesai'),
                    ])),
            ])
            ->when($data['kegiatan'] ?? null, fn (Builder $query, $id) => $query->where('kegiatan_ujian_cbt_id', $id))
            ->whereHas('kegiatanUjianCbt', fn (Builder $query) => $query->where('status', '!=', 'nonaktif'))
            ->orderBy('tanggal')
            ->orderBy('waktu_mulai')
            ->orderBy('tingkat')
            ->get();

        $jadwal->each(function (JadwalUjianCbt $item) use ($request) {
            $item->setAttribute('boleh_kelola_paket', $this->bolehMengelola($request->user(), $item));
            $item->setAttribute('boleh_lihat_hasil', $item->ujianCbt?->dapatDiaksesOperasionalOleh($request->user()) ?? false);
        });

        $jadwalPerKegiatan = $jadwal->groupBy('kegiatan_ujian_cbt_id');
        if (! $tampilanHasil) {
            $jadwalPerKegiatan = $jadwalPerKegiatan->sortByDesc(function (Collection $daftarJadwal) {
                $jadwalPertama = $daftarJadwal->first();

                return ($jadwalPertama->kegiatanUjianCbt->tanggal_mulai ?? $jadwalPertama->tanggal)->toDateString();
            });
        }

        $kegiatanAlur = filled($data['kegiatan'] ?? null)
            ? $jadwal->first()?->kegiatanUjianCbt
            : null;
        if ($kegiatanAlur) {
            $this->muatDataAlur($kegiatanAlur);
        }

        return view('paket-soal-ujian-terpusat.index', [
            'tampilanHasil' => $tampilanHasil,
            'modePaketAwal' => $this->modePaketAwal($request->user()),
            'kegiatanAlur' => $kegiatanAlur,
            'jadwalPerKegiatan' => $jadwalPerKegiatan,
            'jumlahJadwal' => $jadwal->count(),
            'jumlahSiap' => $jadwal->filter(fn (JadwalUjianCbt $item) => $this->paketSiap($item->ujianCbt))->count(),
            'jumlahDraf' => $jadwal->filter(fn (JadwalUjianCbt $item) => $item->ujianCbt?->status === 'draft')->count(),
            'jumlahBelumDisusun' => $jadwal->whereNull('ujian_cbt_id')->count(),
            'jumlahHasilTersedia' => $jadwal->where('boleh_lihat_hasil', true)->count(),
            'jumlahPesertaSelesai' => $jadwal->sum(fn ($item) => $item->ujianCbt?->peserta_selesai_count ?? 0),
        ]);
    }

    public function show(Request $request, JadwalUjianCbt $jadwalUjianCbt)
    {
        $jadwalUjianCbt->load([
            'kegiatanUjianCbt.jenisUjianCbt',
            'kegiatanUjianCbt.tahunPelajaran',
            'sesiKegiatanUjianCbt',
            'mataPelajaran',
            'kelas',
            'ujianCbt.soalUjianCbt.soalCbt',
        ]);
        $this->pastikanBolehMelihat($request->user(), $jadwalUjianCbt);
        $this->muatDataAlur($jadwalUjianCbt->kegiatanUjianCbt);

        $paket = $jadwalUjianCbt->ujianCbt;
        $sudahDikerjakan = $this->paketSudahDikerjakan($paket);
        $bolehKelola = $this->bolehMengelola($request->user(), $jadwalUjianCbt) && ! $sudahDikerjakan;
        $sedangMenyusun = $bolehKelola && $request->query('mode', $this->modePaketAwal($request->user())) === 'susun';
        $soalDipilih = $paket?->soalUjianCbt?->sortBy('nomor_urut')->keyBy('soal_cbt_id') ?? collect();
        $soal = $soalDipilih->map(fn ($item) => $item->soalCbt)->filter()->values();
        $folder = collect();

        if ($sedangMenyusun) {
            $folder = FolderSoalCbt::query()
                ->where('mata_pelajaran_id', $jadwalUjianCbt->mata_pelajaran_id)
                ->where('tingkat', $jadwalUjianCbt->tingkat)
                ->orderBy('nama')->get(['id', 'nama', 'kegiatan_ujian_cbt_id']);

            if ($request->old('daftar_soal_dikirim')) {
                $ids = $this->idSoalTerpilih((array) $request->old('soal', []));
                $soalLama = $this->soalDalamCakupan($jadwalUjianCbt)
                    ->whereKey($ids)
                    ->where(fn (Builder $query) => $query
                        ->where(fn (Builder $query) => $query->where('aktif', true)->where('status', 'siap'))
                        ->orWhereIn('id', $soalDipilih->keys()))
                    ->get()->keyBy('id');
                $soal = $ids->map(fn ($id) => $soalLama->get($id))->filter()->values();
            }
        }

        return view('paket-soal-ujian-terpusat.show', [
            'jadwal' => $jadwalUjianCbt,
            'paket' => $paket,
            'soal' => $soal,
            'soalDipilih' => $soalDipilih,
            'bolehKelola' => $bolehKelola,
            'sedangMenyusun' => $sedangMenyusun,
            'sudahDikerjakan' => $sudahDikerjakan,
            'folder' => $folder,
            'daftarJenisSoal' => SoalCbt::DAFTAR_JENIS,
            'daftarKesulitan' => SoalCbt::DAFTAR_KESULITAN,
        ]);
    }

    public function bankSoal(Request $request, JadwalUjianCbt $jadwalUjianCbt)
    {
        $jadwalUjianCbt->load(['kegiatanUjianCbt', 'kelas', 'ujianCbt']);
        abort_unless($this->bolehMengelola($request->user(), $jadwalUjianCbt), 403);
        abort_if($this->paketSudahDikerjakan($jadwalUjianCbt->ujianCbt), 403);

        $filter = $request->validate([
            'cari' => ['nullable', 'string', 'max:120'],
            'folder' => ['nullable', 'regex:/^(belum|[1-9][0-9]{0,17})$/'],
            'jenis' => ['nullable', Rule::in(array_keys(SoalCbt::DAFTAR_JENIS))],
            'kesulitan' => ['nullable', Rule::in(array_keys(SoalCbt::DAFTAR_KESULITAN))],
            'halaman' => ['nullable', 'integer', 'min:1'],
        ]);
        $cari = trim($filter['cari'] ?? '');
        $soal = $this->soalDalamCakupan($jadwalUjianCbt)
            ->where('aktif', true)->where('status', 'siap')
            ->with('folders:id,nama')
            ->when($cari !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereLike('kode', "%{$cari}%")
                ->orWhereLike('topik', "%{$cari}%")
                ->orWhereLike('materi', "%{$cari}%")
                ->orWhereLike('pertanyaan', "%{$cari}%")))
            ->when($filter['folder'] ?? null, fn (Builder $query, $folder) => $folder === 'belum'
                ? $query->whereDoesntHave('folders')
                : $query->whereHas('folders', fn (Builder $query) => $query->whereKey((int) $folder)))
            ->when($filter['jenis'] ?? null, fn (Builder $query, $jenis) => $query->where('jenis_soal', $jenis))
            ->when($filter['kesulitan'] ?? null, fn (Builder $query, $kesulitan) => $query->where('tingkat_kesulitan', $kesulitan))
            ->orderBy('kode')->orderBy('id')
            ->paginate(20, ['*'], 'halaman');

        return response()->view('paket-soal-ujian-terpusat.partials.hasil-bank', ['soal' => $soal])
            ->header('Cache-Control', 'private, no-store');
    }

    public function update(
        Request $request,
        JadwalUjianCbt $jadwalUjianCbt,
        SinkronkanPelaksanaanUjianTerpusat $sinkronisasi,
    ) {
        $relasiJadwal = [
            'kegiatanUjianCbt.jenisUjianCbt',
            'sesiKegiatanUjianCbt',
            'mataPelajaran.pengaturanTingkat',
            'kelas',
            'ujianCbt',
        ];
        $jadwalUjianCbt->load($relasiJadwal);
        abort_unless($this->bolehMengelola($request->user(), $jadwalUjianCbt), 403);

        $data = $request->validate([
            'aksi' => ['required', Rule::in(['draf', 'simpan', 'terbitkan'])],
            'soal' => ['nullable', 'array'],
            'soal.*.dipilih' => ['nullable', 'boolean'],
            'acak_soal' => ['nullable', 'boolean'],
            'acak_jawaban' => ['nullable', 'boolean'],
            'gunakan_paket_simulasi' => ['nullable', 'boolean'],
        ]);
        if ($request->boolean('gunakan_paket_simulasi')) {
            $this->pastikanPaketBelumDikerjakan($jadwalUjianCbt->ujianCbt);
            $data['soal'] = app(\App\Services\Cbt\PaketSimulasiCbt::class)->siapkan($jadwalUjianCbt);
        }
        $soalIdsTerpilih = $this->idSoalTerpilih($data['soal'] ?? []);
        $skorSoal = SoalCbt::query()
            ->whereIn('id', $soalIdsTerpilih)
            ->pluck('skor_maksimal', 'id');
        $soalTerpilih = $soalIdsTerpilih
            ->mapWithKeys(fn ($id) => [$id => (float) $skorSoal->get($id)]);

        if ($data['aksi'] === 'terbitkan' && $soalTerpilih->isEmpty()) {
            throw ValidationException::withMessages([
                'soal' => 'Pilih minimal satu soal sebelum paket diterbitkan.',
            ]);
        }

        $status = DB::transaction(function () use ($request, $jadwalUjianCbt, $soalTerpilih, $data, $relasiJadwal, $sinkronisasi) {
            $jadwalUjianCbt = JadwalUjianCbt::query()->with($relasiJadwal)->lockForUpdate()->findOrFail($jadwalUjianCbt->id);
            abort_unless($this->bolehMengelola($request->user(), $jadwalUjianCbt), 403);
            $this->pastikanPaketBelumDikerjakan($jadwalUjianCbt->ujianCbt);
            $this->pastikanSoalValid($jadwalUjianCbt, $soalTerpilih);

            $status = match ($data['aksi']) {
                'terbitkan' => 'terjadwal',
                'draf' => 'draft',
                default => $jadwalUjianCbt->ujianCbt?->status ?? 'draft',
            };
            $acakSoal = $request->has('acak_soal')
                ? $request->boolean('acak_soal')
                : ($jadwalUjianCbt->ujianCbt?->acak_soal ?? true);
            $acakJawaban = $request->has('acak_jawaban')
                ? $request->boolean('acak_jawaban')
                : ($jadwalUjianCbt->ujianCbt?->acak_jawaban ?? true);
            $paketSiap = in_array($status, ['terjadwal', 'berlangsung', 'selesai'], true);
            $paket = $jadwalUjianCbt->ujianCbt ?: new UjianCbt;
            $paket->fill($this->dataPaketOtomatis(
                $jadwalUjianCbt,
                $soalTerpilih->count(),
                $status,
                $acakSoal,
                $acakJawaban,
            ));
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
            $sinkronisasi->sinkronkanJadwal($jadwalUjianCbt->fresh(), $request->user());

            return $status;
        });

        $pesan = $status === 'terjadwal'
            ? 'Paket soal berhasil diterbitkan dan siap digunakan pada jadwal ini.'
            : 'Draf paket soal berhasil disimpan.';

        $parameter = ['jadwalUjianCbt' => $jadwalUjianCbt];
        if ($request->query('mode') === 'susun') {
            $parameter['mode'] = 'susun';
        }

        return redirect()->route('paket-soal-terpusat.show', $parameter)->with('berhasil', $pesan);
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

    private function modePaketAwal(Pengguna $pengguna): string
    {
        return $pengguna->memilikiIzin(['cbt.kelola', 'cbt.panitia']) ? 'periksa' : 'susun';
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

    private function soalDalamCakupan(JadwalUjianCbt $jadwal): Builder
    {
        return SoalCbt::query()
            ->where('mata_pelajaran_id', $jadwal->mata_pelajaran_id)
            ->where('tingkat', $jadwal->tingkat);
    }

    private function idSoalTerpilih(array $soal): Collection
    {
        return collect($soal)
            ->filter(fn ($item) => filter_var($item['dipilih'] ?? false, FILTER_VALIDATE_BOOLEAN))
            ->keys()
            ->filter(fn ($id) => ctype_digit((string) $id) && (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()->values();
    }

    private function pastikanSoalValid(JadwalUjianCbt $jadwal, Collection $soal): void
    {
        if ($soal->isEmpty()) {
            return;
        }

        $jumlahValid = $this->soalDalamCakupan($jadwal)
            ->whereIn('id', $soal->keys())
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
        if ($this->paketSudahDikerjakan($paket)) {
            throw ValidationException::withMessages([
                'paket' => 'Paket tidak dapat diubah karena sudah dikerjakan oleh peserta.',
            ]);
        }
    }

    private function paketSudahDikerjakan(?UjianCbt $paket): bool
    {
        return $paket?->pesertaUjianCbt()->whereIn('status', ['sedang_mengerjakan', 'selesai'])->exists() ?? false;
    }

    private function dataPaketOtomatis(
        JadwalUjianCbt $jadwal,
        int $jumlahSoal,
        string $status,
        bool $acakSoal,
        bool $acakJawaban,
    ): array {
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
            'nama' => $kegiatan->jenisUjianCbt?->kode === 'SIMULASI_CBT' ? 'Simulasi CBT' : "{$kegiatan->nama} - {$jadwal->mataPelajaran->nama} Tingkat {$jadwal->tingkat}",
            'semester' => $kegiatan->semester,
            'tingkat' => $jadwal->tingkat,
            'tanggal_mulai' => $mulai,
            'tanggal_selesai' => $selesai,
            'durasi_menit' => $kegiatan->jenisUjianCbt?->kode === 'SIMULASI_CBT' ? 20 : max(10, $mulai->diffInMinutes($selesai)),
            'jumlah_soal' => $jumlahSoal,
            'kkm' => $pengaturan?->kkm ?? $jadwal->mataPelajaran?->kkm,
            'token' => $kegiatan->jenisUjianCbt?->memerlukan_token ? $token : null,
            'acak_soal' => $acakSoal,
            'acak_jawaban' => $acakJawaban,
            'batasi_satu_perangkat' => true,
            'deteksi_pindah_tab' => true,
            'wajib_fullscreen' => true,
            'blokir_tangkapan_layar' => true,
            'toleransi_pindah_aplikasi_detik' => 3,
            'batas_pindah_aplikasi' => 3,
            'tindakan_pindah_aplikasi' => 'tahan',
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

    private function muatDataAlur(KegiatanUjianCbt $kegiatan): void
    {
        $kegiatan->loadMissing([
            'panitiaUjianCbt',
            'sesiKegiatanUjianCbt',
            'ruangKegiatanUjianCbt',
            'kelompokPesertaKegiatanUjianCbt' => fn ($query) => $query->withCount('penempatanPesertaUjianCbt'),
            'jadwalUjianCbt.ujianCbt',
        ]);
    }
}
