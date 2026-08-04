<?php

namespace App\Http\Controllers;

use App\Models\GuruMataPelajaran;
use App\Models\JadwalPelajaran;
use App\Models\JamPelajaran;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\TahunPelajaran;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class JadwalPelajaranController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'tahun_pelajaran_id' => ['nullable', 'integer', 'exists:tahun_pelajaran,id'],
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
            'hari' => ['nullable', Rule::in(array_merge(['semua'], array_keys(JamPelajaran::DAFTAR_HARI)))],
            'status' => ['nullable', Rule::in(['semua', 'aktif', 'nonaktif'])],
        ]);

        $tahunPelajaran = TahunPelajaran::query()
            ->orderByDesc('aktif')
            ->orderByDesc('tanggal_mulai')
            ->get();
        $tahunPelajaranId = $this->ambilTahunPelajaranId($data['tahun_pelajaran_id'] ?? null, $tahunPelajaran);
        $cakupanWaliKelas = $request->user()?->membatasiCakupanWaliKelas() ?? false;
        $kelasWaliIds = $cakupanWaliKelas ? $request->user()->kelasWaliIds() : [];
        $kelas = $tahunPelajaranId
            ? Kelas::query()
                ->where('tahun_pelajaran_id', $tahunPelajaranId)
                ->where('aktif', true)
                ->when($cakupanWaliKelas, fn ($query) => $query->whereIn('id', $kelasWaliIds))
                ->orderBy('tingkat')
                ->orderBy('nama')
                ->get()
            : collect();
        $kelasId = $data['kelas_id'] ?? null;
        $hari = $data['hari'] ?? 'semua';
        $status = $data['status'] ?? 'aktif';

        if ($kelasId && ! $kelas->contains('id', (int) $kelasId)) {
            $kelasId = null;
        }

        $queryCakupan = JadwalPelajaran::query()
            ->when($cakupanWaliKelas, fn ($query) => $query->whereIn('kelas_id', $kelasWaliIds));

        $jadwalPelajaran = (clone $queryCakupan)
            ->with([
                'tahunPelajaran',
                'kelas',
                'jamPelajaran',
                'mataPelajaran.pengaturanTingkat',
                'guruMataPelajaran.mataPelajaran.pengaturanTingkat',
                'guruMataPelajaran.pegawai',
            ])
            ->when($tahunPelajaranId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId))
            ->when($kelasId, fn ($query) => $query->where('kelas_id', $kelasId))
            ->when($hari !== 'semua', fn ($query) => $query->where('hari', $hari))
            ->when($status === 'aktif', fn ($query) => $query->where('aktif', true))
            ->when($status === 'nonaktif', fn ($query) => $query->where('aktif', false))
            ->orderByRaw("case hari when 'senin' then 1 when 'selasa' then 2 when 'rabu' then 3 when 'kamis' then 4 when 'jumat' then 5 when 'sabtu' then 6 else 7 end")
            ->orderBy(
                JamPelajaran::select('nomor_jam')
                    ->whereColumn('jam_pelajaran.id', 'jadwal_pelajaran.jam_pelajaran_id')
                    ->limit(1)
            )
            ->paginate(15)
            ->withQueryString();

        return view('jadwal-pelajaran.index', [
            'jadwalPelajaran' => $jadwalPelajaran,
            'tahunPelajaran' => $tahunPelajaran,
            'kelas' => $kelas,
            'tahunPelajaranId' => $tahunPelajaranId,
            'kelasId' => $kelasId,
            'hari' => $hari,
            'status' => $status,
            'cakupanWaliKelas' => $cakupanWaliKelas,
            'daftarHari' => JamPelajaran::DAFTAR_HARI,
            'jumlahJadwal' => (clone $queryCakupan)->count(),
            'jumlahAktif' => (clone $queryCakupan)->where('aktif', true)->count(),
            'jumlahNonaktif' => (clone $queryCakupan)->where('aktif', false)->count(),
        ]);
    }

    public function create(Request $request)
    {
        return view('jadwal-pelajaran.create', $this->dataForm([
            'tahunPelajaranDipilih' => $request->input('tahun_pelajaran_id'),
            'kelasDipilih' => $request->input('kelas_id'),
        ]));
    }

    public function susun(Request $request)
    {
        $data = $request->validate([
            'tahun_pelajaran_id' => ['nullable', 'integer', 'exists:tahun_pelajaran,id'],
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
        ]);
        $tahunPelajaran = TahunPelajaran::query()
            ->orderByDesc('aktif')
            ->orderByDesc('tanggal_mulai')
            ->get();
        $tahunPelajaranId = $this->ambilTahunPelajaranId(
            $data['tahun_pelajaran_id'] ?? null,
            $tahunPelajaran,
        );
        $kelas = $tahunPelajaranId
            ? Kelas::query()
                ->where('tahun_pelajaran_id', $tahunPelajaranId)
                ->where('aktif', true)
                ->orderBy('tingkat')
                ->orderBy('nama')
                ->get()
            : collect();
        $kelasId = isset($data['kelas_id']) && $kelas->contains('id', (int) $data['kelas_id'])
            ? (int) $data['kelas_id']
            : null;
        $kelasDipilih = $kelas->firstWhere('id', $kelasId);
        $jamPelajaran = collect();
        $hariTersedia = collect();
        $nomorJam = collect();
        $jamPerHari = collect();
        $guruMataPelajaran = collect();
        $kegiatanJadwal = collect();
        $jadwalTersimpan = collect();

        if ($kelasDipilih) {
            $jamPelajaran = JamPelajaran::query()
                ->where('aktif', true)
                ->orderByRaw("case hari when 'senin' then 1 when 'selasa' then 2 when 'rabu' then 3 when 'kamis' then 4 when 'jumat' then 5 when 'sabtu' then 6 else 7 end")
                ->orderBy('nomor_jam')
                ->get();
            $hariTersedia = collect(JamPelajaran::DAFTAR_HARI)
                ->only($jamPelajaran->pluck('hari')->unique()->all());
            $nomorJam = $jamPelajaran->pluck('nomor_jam')->unique()->sort()->values();
            $jamPerHari = $jamPelajaran
                ->groupBy('hari')
                ->map(fn ($items) => $items->keyBy('nomor_jam'));
            $guruMataPelajaran = GuruMataPelajaran::query()
                ->with(['mataPelajaran', 'pegawai'])
                ->where('tahun_pelajaran_id', $tahunPelajaranId)
                ->where('kelas_id', $kelasId)
                ->where('jenis_penugasan', 'pengampu')
                ->where('aktif', true)
                ->orderBy(
                    MataPelajaran::select('nama')
                        ->whereColumn('mata_pelajaran.id', 'guru_mata_pelajaran.mata_pelajaran_id')
                        ->limit(1)
                )
                ->orderBy(
                    Pegawai::select('nama_lengkap')
                        ->whereColumn('pegawai.id', 'guru_mata_pelajaran.pegawai_id')
                        ->limit(1)
                )
                ->get();
            $kegiatanJadwal = $this->ambilKegiatanJadwal(
                $tahunPelajaranId,
                (int) $kelasDipilih->tingkat,
            );
            $jadwalTersimpan = JadwalPelajaran::query()
                ->where('tahun_pelajaran_id', $tahunPelajaranId)
                ->where('kelas_id', $kelasId)
                ->where('aktif', true)
                ->get()
                ->keyBy('jam_pelajaran_id');
        }

        return view('jadwal-pelajaran.susun', [
            'tahunPelajaran' => $tahunPelajaran,
            'tahunPelajaranId' => $tahunPelajaranId,
            'kelas' => $kelas,
            'kelasId' => $kelasId,
            'kelasDipilih' => $kelasDipilih,
            'jamPelajaran' => $jamPelajaran,
            'hariTersedia' => $hariTersedia,
            'nomorJam' => $nomorJam,
            'jamPerHari' => $jamPerHari,
            'guruMataPelajaran' => $guruMataPelajaran,
            'kegiatanJadwal' => $kegiatanJadwal,
            'jadwalTersimpan' => $jadwalTersimpan,
        ]);
    }

    public function simpanMassal(Request $request)
    {
        $data = $request->validate([
            'tahun_pelajaran_id' => ['required', 'integer', 'exists:tahun_pelajaran,id'],
            'kelas_id' => ['required', 'integer', 'exists:kelas,id'],
            'jadwal' => ['nullable', 'array'],
            'jadwal.*' => ['nullable'],
        ]);
        $kelas = Kelas::query()
            ->whereKey($data['kelas_id'])
            ->where('tahun_pelajaran_id', $data['tahun_pelajaran_id'])
            ->where('aktif', true)
            ->first();

        if (! $kelas) {
            throw ValidationException::withMessages([
                'kelas_id' => 'Kelas tidak sesuai dengan tahun pelajaran atau sudah tidak aktif.',
            ]);
        }

        $slotPelajaran = JamPelajaran::query()
            ->where('aktif', true)
            ->where('jenis', 'pelajaran')
            ->get()
            ->keyBy('id');
        $jadwalDikirim = collect($data['jadwal'] ?? [])
            ->mapWithKeys(function ($nilai, $jamId) {
                $pilihan = $this->uraikanPilihanJadwal($nilai);

                if (filled($nilai) && ! $pilihan) {
                    throw ValidationException::withMessages([
                        "jadwal.{$jamId}" => 'Pilihan jadwal tidak valid.',
                    ]);
                }

                return [(int) $jamId => $pilihan];
            });
        $slotTidakSah = $jadwalDikirim->keys()->diff($slotPelajaran->keys());

        if ($slotTidakSah->isNotEmpty()) {
            throw ValidationException::withMessages([
                'jadwal' => 'Terdapat slot yang tidak aktif atau bukan jam pelajaran.',
            ]);
        }

        $penugasan = GuruMataPelajaran::query()
            ->with(['mataPelajaran', 'pegawai'])
            ->where('tahun_pelajaran_id', $data['tahun_pelajaran_id'])
            ->where('kelas_id', $data['kelas_id'])
            ->where('jenis_penugasan', 'pengampu')
            ->where('aktif', true)
            ->get()
            ->keyBy('id');
        $penugasanTidakSah = $jadwalDikirim
            ->pluck('guru_mata_pelajaran_id')
            ->filter()
            ->diff($penugasan->keys());

        if ($penugasanTidakSah->isNotEmpty()) {
            throw ValidationException::withMessages([
                'jadwal' => 'Ada guru mata pelajaran yang tidak sesuai dengan kelas ini.',
            ]);
        }

        $kegiatanJadwal = $this->ambilKegiatanJadwal(
            (int) $data['tahun_pelajaran_id'],
            (int) $kelas->tingkat,
        )->keyBy('id');
        $kegiatanTidakSah = $jadwalDikirim
            ->pluck('mata_pelajaran_id')
            ->filter()
            ->diff($kegiatanJadwal->keys());

        if ($kegiatanTidakSah->isNotEmpty()) {
            throw ValidationException::withMessages([
                'jadwal' => 'Ada kegiatan yang tidak tersedia untuk tingkat kelas ini.',
            ]);
        }

        $jadwalGuru = $jadwalDikirim
            ->map(fn ($pilihan) => $pilihan['guru_mata_pelajaran_id'] ?? null);
        $this->pastikanJadwalMassalTidakBentrok(
            $data,
            $jadwalGuru,
            $slotPelajaran,
            $penugasan,
        );

        DB::transaction(function () use ($data, $jadwalDikirim, $slotPelajaran) {
            $jadwalSaatIni = JadwalPelajaran::query()
                ->where('tahun_pelajaran_id', $data['tahun_pelajaran_id'])
                ->where('kelas_id', $data['kelas_id'])
                ->whereIn('jam_pelajaran_id', $slotPelajaran->keys())
                ->get()
                ->keyBy('jam_pelajaran_id');

            foreach ($slotPelajaran as $jamId => $slot) {
                $pilihan = $jadwalDikirim->get((int) $jamId);
                $jadwal = $jadwalSaatIni->get($jamId);

                if (! $pilihan) {
                    $jadwal?->update(['aktif' => false]);

                    continue;
                }

                if ($jadwal) {
                    $jadwal->update([
                        'hari' => $slot->hari,
                        'guru_mata_pelajaran_id' => $pilihan['guru_mata_pelajaran_id'],
                        'mata_pelajaran_id' => $pilihan['mata_pelajaran_id'],
                        'aktif' => true,
                    ]);

                    continue;
                }

                JadwalPelajaran::create([
                    'tahun_pelajaran_id' => $data['tahun_pelajaran_id'],
                    'kelas_id' => $data['kelas_id'],
                    'hari' => $slot->hari,
                    'jam_pelajaran_id' => $jamId,
                    'guru_mata_pelajaran_id' => $pilihan['guru_mata_pelajaran_id'],
                    'mata_pelajaran_id' => $pilihan['mata_pelajaran_id'],
                    'aktif' => true,
                ]);
            }
        });

        $jumlahTerisi = $jadwalDikirim->filter()->count();

        return redirect()
            ->route('jadwal-pelajaran.susun', [
                'tahun_pelajaran_id' => $data['tahun_pelajaran_id'],
                'kelas_id' => $data['kelas_id'],
            ])
            ->with(
                'berhasil',
                "Jadwal kelas {$kelas->nama} berhasil disimpan. {$jumlahTerisi} slot pelajaran terisi.",
            );
    }

    public function store(Request $request)
    {
        $this->normalisasiPilihanJadwal($request);
        $data = $request->validate($this->aturanValidasi());
        $data['aktif'] = $request->boolean('aktif');
        $this->pastikanRelasiCocok($data);
        $this->pastikanGuruTidakBentrok($data);

        $jadwalPelajaran = JadwalPelajaran::create($data);

        return redirect()
            ->route('jadwal-pelajaran.show', $jadwalPelajaran)
            ->with('berhasil', 'Jadwal pelajaran berhasil ditambahkan.');
    }

    public function show(Request $request, JadwalPelajaran $jadwalPelajaran)
    {
        $this->pastikanBolehMelihatJadwal($request, $jadwalPelajaran);
        $jadwalPelajaran->load([
            'tahunPelajaran',
            'kelas',
            'jamPelajaran',
            'mataPelajaran.pengaturanTingkat',
            'guruMataPelajaran.mataPelajaran.pengaturanTingkat',
            'guruMataPelajaran.pegawai',
        ]);

        return view('jadwal-pelajaran.show', compact('jadwalPelajaran'));
    }

    public function edit(JadwalPelajaran $jadwalPelajaran)
    {
        return view('jadwal-pelajaran.edit', $this->dataForm([
            'jadwalPelajaran' => $jadwalPelajaran,
            'tahunPelajaranDipilih' => null,
            'kelasDipilih' => null,
        ]));
    }

    public function update(Request $request, JadwalPelajaran $jadwalPelajaran)
    {
        $this->normalisasiPilihanJadwal($request);
        $data = $request->validate($this->aturanValidasi($jadwalPelajaran));
        $data['aktif'] = $request->boolean('aktif');
        $this->pastikanRelasiCocok($data);
        $this->pastikanGuruTidakBentrok($data, $jadwalPelajaran);

        $jadwalPelajaran->update($data);

        return redirect()
            ->route('jadwal-pelajaran.show', $jadwalPelajaran)
            ->with('berhasil', 'Jadwal pelajaran berhasil diperbarui.');
    }

    public function destroy(JadwalPelajaran $jadwalPelajaran)
    {
        $jadwalPelajaran->update(['aktif' => false]);

        return redirect()
            ->route('jadwal-pelajaran.index')
            ->with('berhasil', 'Jadwal pelajaran berhasil dinonaktifkan.');
    }

    private function pastikanBolehMelihatJadwal(Request $request, JadwalPelajaran $jadwalPelajaran): void
    {
        if (! ($request->user()?->membatasiCakupanWaliKelas() ?? false)) {
            return;
        }

        abort_unless(
            in_array((int) $jadwalPelajaran->kelas_id, $request->user()->kelasWaliIds(), true),
            403,
        );
    }

    private function dataForm(array $tambahan = []): array
    {
        return array_merge([
            'tahunPelajaran' => TahunPelajaran::query()
                ->orderByDesc('aktif')
                ->orderByDesc('tanggal_mulai')
                ->get(),
            'kelas' => Kelas::query()
                ->with('tahunPelajaran')
                ->where('aktif', true)
                ->orderByDesc(
                    TahunPelajaran::select('aktif')
                        ->whereColumn('tahun_pelajaran.id', 'kelas.tahun_pelajaran_id')
                        ->limit(1)
                )
                ->orderBy('tingkat')
                ->orderBy('nama')
                ->get(),
            'jamPelajaran' => JamPelajaran::query()
                ->where('aktif', true)
                ->orderByRaw("case hari when 'senin' then 1 when 'selasa' then 2 when 'rabu' then 3 when 'kamis' then 4 when 'jumat' then 5 when 'sabtu' then 6 else 7 end")
                ->orderBy('nomor_jam')
                ->get(),
            'guruMataPelajaran' => GuruMataPelajaran::query()
                ->with(['tahunPelajaran', 'kelas', 'mataPelajaran', 'pegawai'])
                ->where('aktif', true)
                ->where('jenis_penugasan', 'pengampu')
                ->orderBy(
                    Kelas::select('nama')
                        ->whereColumn('kelas.id', 'guru_mata_pelajaran.kelas_id')
                        ->limit(1)
                )
                ->orderBy(
                    MataPelajaran::select('nama')
                        ->whereColumn('mata_pelajaran.id', 'guru_mata_pelajaran.mata_pelajaran_id')
                        ->limit(1)
                )
                ->orderBy(
                    Pegawai::select('nama_lengkap')
                        ->whereColumn('pegawai.id', 'guru_mata_pelajaran.pegawai_id')
                        ->limit(1)
                )
                ->get(),
            'kegiatanJadwal' => MataPelajaran::query()
                ->with('pengaturanTingkat')
                ->where('aktif', true)
                ->whereIn('kelompok', $this->kelompokKegiatanJadwal())
                ->orderBy('kelompok')
                ->orderBy('urutan')
                ->orderBy('nama')
                ->get(),
            'daftarHari' => JamPelajaran::DAFTAR_HARI,
        ], $tambahan);
    }

    private function aturanValidasi(?JadwalPelajaran $jadwalPelajaran = null): array
    {
        return [
            'tahun_pelajaran_id' => ['required', 'exists:tahun_pelajaran,id'],
            'kelas_id' => ['required', 'exists:kelas,id'],
            'hari' => ['required', Rule::in(array_keys(JamPelajaran::DAFTAR_HARI))],
            'jam_pelajaran_id' => [
                'required',
                'exists:jam_pelajaran,id',
                Rule::unique('jadwal_pelajaran', 'jam_pelajaran_id')
                    ->where('tahun_pelajaran_id', request('tahun_pelajaran_id'))
                    ->where('kelas_id', request('kelas_id'))
                    ->where('hari', request('hari'))
                    ->ignore($jadwalPelajaran),
            ],
            'pilihan_jadwal' => ['nullable', 'string'],
            'guru_mata_pelajaran_id' => [
                'nullable',
                'required_without:mata_pelajaran_id',
                'exists:guru_mata_pelajaran,id',
            ],
            'mata_pelajaran_id' => [
                'nullable',
                'required_without:guru_mata_pelajaran_id',
                'exists:mata_pelajaran,id',
            ],
            'aktif' => ['nullable', 'boolean'],
            'keterangan' => ['nullable', 'string'],
        ];
    }

    private function pastikanRelasiCocok(array $data): void
    {
        $kelas = Kelas::find($data['kelas_id']);
        $jamPelajaran = JamPelajaran::find($data['jam_pelajaran_id']);
        $guruMataPelajaran = isset($data['guru_mata_pelajaran_id'])
            ? GuruMataPelajaran::with(['mataPelajaran', 'pegawai'])->find($data['guru_mata_pelajaran_id'])
            : null;

        if (! $kelas || (int) $kelas->tahun_pelajaran_id !== (int) $data['tahun_pelajaran_id']) {
            throw ValidationException::withMessages([
                'kelas_id' => 'Kelas yang dipilih tidak berada pada tahun pelajaran tersebut.',
            ]);
        }

        if (! $jamPelajaran || $jamPelajaran->hari !== $data['hari']) {
            throw ValidationException::withMessages([
                'jam_pelajaran_id' => 'Jam pelajaran yang dipilih tidak sesuai dengan hari.',
            ]);
        }

        if ($jamPelajaran->jenis !== 'pelajaran') {
            throw ValidationException::withMessages([
                'jam_pelajaran_id' => 'Pilih slot dengan jenis Pelajaran. Slot istirahat/upacara tidak diisi guru mapel.',
            ]);
        }

        if ($guruMataPelajaran) {
            if (
                ! $guruMataPelajaran->aktif
                || $guruMataPelajaran->jenis_penugasan !== 'pengampu'
                || (int) $guruMataPelajaran->tahun_pelajaran_id !== (int) $data['tahun_pelajaran_id']
                || (int) $guruMataPelajaran->kelas_id !== (int) $data['kelas_id']
            ) {
                throw ValidationException::withMessages([
                    'guru_mata_pelajaran_id' => 'Guru mata pelajaran harus sesuai dengan tahun pelajaran dan kelas.',
                ]);
            }

            return;
        }

        $mataPelajaran = MataPelajaran::with('pengaturanTingkat')
            ->find($data['mata_pelajaran_id'] ?? null);

        if (
            ! $mataPelajaran
            || ! $mataPelajaran->aktif
            || ! in_array($mataPelajaran->kelompok, $this->kelompokKegiatanJadwal(), true)
            || ! $this->kegiatanTersediaUntuk(
                $mataPelajaran,
                (int) $data['tahun_pelajaran_id'],
                (int) $kelas->tingkat,
            )
        ) {
            throw ValidationException::withMessages([
                'mata_pelajaran_id' => 'Kokurikuler atau ekstrakurikuler tidak tersedia untuk tingkat kelas ini.',
            ]);
        }
    }

    private function pastikanGuruTidakBentrok(array $data, ?JadwalPelajaran $jadwalPelajaran = null): void
    {
        $guruMataPelajaran = GuruMataPelajaran::find($data['guru_mata_pelajaran_id'] ?? null);

        if (! $guruMataPelajaran) {
            return;
        }

        $bentrok = JadwalPelajaran::query()
            ->where('tahun_pelajaran_id', $data['tahun_pelajaran_id'])
            ->where('hari', $data['hari'])
            ->where('jam_pelajaran_id', $data['jam_pelajaran_id'])
            ->where('aktif', true)
            ->whereHas('guruMataPelajaran', fn ($query) => $query->where('pegawai_id', $guruMataPelajaran->pegawai_id))
            ->when($jadwalPelajaran, fn ($query) => $query->whereKeyNot($jadwalPelajaran->id))
            ->exists();

        if ($bentrok) {
            throw ValidationException::withMessages([
                'guru_mata_pelajaran_id' => 'Guru ini sudah memiliki jadwal pada hari dan jam yang sama.',
            ]);
        }
    }

    private function pastikanJadwalMassalTidakBentrok(
        array $data,
        $jadwalDikirim,
        $slotPelajaran,
        $penugasan,
    ): void {
        $jadwalTerpilih = $jadwalDikirim->filter();

        if ($jadwalTerpilih->isEmpty()) {
            return;
        }

        $jadwalKelasLain = JadwalPelajaran::query()
            ->with(['kelas', 'guruMataPelajaran.pegawai'])
            ->where('tahun_pelajaran_id', $data['tahun_pelajaran_id'])
            ->where('kelas_id', '!=', $data['kelas_id'])
            ->where('aktif', true)
            ->whereIn('jam_pelajaran_id', $jadwalTerpilih->keys())
            ->get()
            ->groupBy('jam_pelajaran_id');
        $kesalahan = [];

        foreach ($jadwalTerpilih as $jamId => $penugasanId) {
            $guru = $penugasan->get($penugasanId);
            $bentrok = $jadwalKelasLain
                ->get($jamId, collect())
                ->first(fn (JadwalPelajaran $jadwal) => (
                    (int) $jadwal->guruMataPelajaran?->pegawai_id === (int) $guru?->pegawai_id
                ));

            if (! $bentrok) {
                continue;
            }

            $slot = $slotPelajaran->get($jamId);
            $namaGuru = $guru?->pegawai?->nama_lengkap ?? 'Guru';
            $kelasBentrok = $bentrok->kelas?->nama ?? 'kelas lain';
            $kesalahan["jadwal.{$jamId}"] = "{$namaGuru} sudah mengajar di {$kelasBentrok} pada "
                .($slot?->labelHari() ?? 'hari tersebut').' '
                .($slot?->labelJam() ?? 'pada jam yang sama').'.';
        }

        if ($kesalahan !== []) {
            throw ValidationException::withMessages($kesalahan);
        }
    }

    private function ambilTahunPelajaranId(?int $tahunPelajaranId, $tahunPelajaran): ?int
    {
        if ($tahunPelajaranId && $tahunPelajaran->contains('id', $tahunPelajaranId)) {
            return $tahunPelajaranId;
        }

        return $tahunPelajaran->firstWhere('aktif', true)?->id ?? $tahunPelajaran->first()?->id;
    }

    private function kelompokKegiatanJadwal(): array
    {
        return ['Kokurikuler', 'Ekstrakurikuler'];
    }

    private function ambilKegiatanJadwal(int $tahunPelajaranId, int $tingkat)
    {
        return MataPelajaran::query()
            ->with('pengaturanTingkat')
            ->where('aktif', true)
            ->whereIn('kelompok', $this->kelompokKegiatanJadwal())
            ->orderBy('kelompok')
            ->orderBy('urutan')
            ->orderBy('nama')
            ->get()
            ->filter(fn (MataPelajaran $mataPelajaran) => $this->kegiatanTersediaUntuk(
                $mataPelajaran,
                $tahunPelajaranId,
                $tingkat,
            ))
            ->values();
    }

    private function kegiatanTersediaUntuk(
        MataPelajaran $mataPelajaran,
        int $tahunPelajaranId,
        int $tingkat,
    ): bool {
        $pengaturan = $mataPelajaran->pengaturanTingkat;

        if ($pengaturan->isNotEmpty()) {
            return $pengaturan->contains(fn ($item) => (
                (int) $item->tahun_pelajaran_id === $tahunPelajaranId
                && (int) $item->tingkat === $tingkat
                && $item->aktif
            ));
        }

        return ! $mataPelajaran->tingkat || (int) $mataPelajaran->tingkat === $tingkat;
    }

    private function uraikanPilihanJadwal(mixed $nilai): ?array
    {
        if (! filled($nilai)) {
            return null;
        }

        if (is_numeric($nilai)) {
            return [
                'guru_mata_pelajaran_id' => (int) $nilai,
                'mata_pelajaran_id' => null,
            ];
        }

        if (! preg_match('/^(guru|kegiatan):(\d+)$/', (string) $nilai, $bagian)) {
            return null;
        }

        return [
            'guru_mata_pelajaran_id' => $bagian[1] === 'guru' ? (int) $bagian[2] : null,
            'mata_pelajaran_id' => $bagian[1] === 'kegiatan' ? (int) $bagian[2] : null,
        ];
    }

    private function normalisasiPilihanJadwal(Request $request): void
    {
        if (! $request->filled('pilihan_jadwal')) {
            return;
        }

        $pilihan = $this->uraikanPilihanJadwal($request->input('pilihan_jadwal'));

        if (! $pilihan) {
            return;
        }

        $request->merge($pilihan);
    }
}
