<?php

namespace App\Http\Controllers;

use App\Models\GuruMataPelajaran;
use App\Models\JenisUjianCbt;
use App\Models\KomponenNilai;
use App\Models\PengaturanMataPelajaran;
use App\Models\TahunPelajaran;
use App\Models\UjianCbt;
use App\Services\Cbt\SinkronkanPesertaAsesmenKelas;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AsesmenKelasCbtController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'kata_kunci' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['semua', ...array_keys(UjianCbt::DAFTAR_STATUS)])],
        ]);
        $kataKunci = trim((string) ($data['kata_kunci'] ?? ''));
        $status = $data['status'] ?? 'semua';

        $query = UjianCbt::query()
            ->where('alur', 'kelas')
            ->with(['mataPelajaran', 'tahunPelajaran', 'kelasUjianCbt.kelas'])
            ->withCount(['soalUjianCbt', 'pesertaUjianCbt']);

        if (! $request->user()->memilikiIzin('cbt.kelola')) {
            $query->where('dibuat_oleh_pengguna_id', $request->user()->id);
        }

        $asesmen = $query
            ->when($status !== 'semua', fn ($query) => $query->where('status', $status))
            ->when($kataKunci !== '', function ($query) use ($kataKunci) {
                $query->where(function ($query) use ($kataKunci) {
                    $query->where('nama', 'like', '%'.$kataKunci.'%')
                        ->orWhereHas('mataPelajaran', fn ($query) => $query->where('nama', 'like', '%'.$kataKunci.'%'))
                        ->orWhereHas('kelasUjianCbt.kelas', fn ($query) => $query->where('nama', 'like', '%'.$kataKunci.'%'));
                });
            })
            ->latest('tanggal_mulai')
            ->latest('id')
            ->paginate(12)
            ->withQueryString();

        return view('asesmen-kelas-cbt.index', [
            'asesmen' => $asesmen,
            'kataKunci' => $kataKunci,
            'status' => $status,
            'daftarStatus' => UjianCbt::DAFTAR_STATUS,
        ]);
    }

    public function create(Request $request)
    {
        return view('asesmen-kelas-cbt.create', $this->dataForm($request));
    }

    public function store(Request $request, SinkronkanPesertaAsesmenKelas $sinkronkanPeserta)
    {
        [$data, $kelompok, $kelasPeserta] = $this->validasiDanSiapkan($request);

        $ujianCbt = DB::transaction(function () use ($request, $data, $kelompok, $kelasPeserta, $sinkronkanPeserta) {
            $ujianCbt = UjianCbt::create([
                ...$this->dataUjian($data, $kelompok),
                'dibuat_oleh_pengguna_id' => $request->user()->id,
            ]);

            $this->sinkronkanKelasDanKomponen($ujianCbt, $data, $kelompok, $kelasPeserta);
            $sinkronkanPeserta->jalankan($ujianCbt, $request->user()->id);

            return $ujianCbt;
        });

        return redirect()
            ->route('ujian-cbt.soal.edit', $ujianCbt)
            ->with('berhasil', 'Asesmen tersimpan. Sekarang pilih soal yang akan ditampilkan kepada siswa.');
    }

    public function show(Request $request, UjianCbt $ujianCbt)
    {
        $this->pastikanAsesmenBolehDikelola($request, $ujianCbt);
        $ujianCbt->load([
            'tahunPelajaran',
            'mataPelajaran',
            'dibuatOleh.pegawai',
            'kelasUjianCbt.kelas',
            'kelasUjianCbt.komponenNilai',
        ])->loadCount(['soalUjianCbt', 'pesertaUjianCbt']);

        return view('asesmen-kelas-cbt.show', compact('ujianCbt'));
    }

    public function edit(Request $request, UjianCbt $ujianCbt)
    {
        $this->pastikanAsesmenBolehDikelola($request, $ujianCbt);
        $ujianCbt->load('kelasUjianCbt.komponenNilai.guruMataPelajaran');

        return view('asesmen-kelas-cbt.edit', $this->dataForm($request, $ujianCbt));
    }

    public function update(Request $request, UjianCbt $ujianCbt, SinkronkanPesertaAsesmenKelas $sinkronkanPeserta)
    {
        $this->pastikanAsesmenBolehDikelola($request, $ujianCbt);
        [$data, $kelompok, $kelasPeserta] = $this->validasiDanSiapkan($request, $ujianCbt);

        DB::transaction(function () use ($request, $ujianCbt, $data, $kelompok, $kelasPeserta, $sinkronkanPeserta) {
            $ujianCbt->update($this->dataUjian($data, $kelompok, $ujianCbt));
            $this->sinkronkanKelasDanKomponen($ujianCbt, $data, $kelompok, $kelasPeserta);
            $sinkronkanPeserta->jalankan($ujianCbt, $request->user()->id);
        });

        return redirect()
            ->route('asesmen-kelas-cbt.show', $ujianCbt)
            ->with('berhasil', 'Pengaturan asesmen berhasil diperbarui.');
    }

    public function destroy(Request $request, UjianCbt $ujianCbt)
    {
        $this->pastikanAsesmenBolehDikelola($request, $ujianCbt);
        $ujianCbt->update(['status' => 'nonaktif']);

        return redirect()
            ->route('asesmen-kelas-cbt.index')
            ->with('berhasil', 'Asesmen berhasil dinonaktifkan.');
    }

    private function dataForm(Request $request, ?UjianCbt $ujianCbt = null): array
    {
        $tahunPelajaran = TahunPelajaran::query()->where('aktif', true)->firstOrFail();
        $kelompokPengajaran = $this->kelompokPengajaran($request, $tahunPelajaran->id);

        return compact('tahunPelajaran', 'kelompokPengajaran', 'ujianCbt') + [
            'daftarStatus' => collect(UjianCbt::DAFTAR_STATUS)->only(['draft', 'terjadwal', 'berlangsung', 'selesai']),
        ];
    }

    private function validasiDanSiapkan(Request $request, ?UjianCbt $ujianCbt = null): array
    {
        $tahunPelajaran = TahunPelajaran::query()->where('aktif', true)->firstOrFail();
        $kelompokTersedia = $this->kelompokPengajaran($request, $tahunPelajaran->id);
        $data = $request->validate([
            'kelompok_pengajaran' => ['required', 'string', Rule::in($kelompokTersedia->keys()->all())],
            'nama' => ['required', 'string', 'max:180'],
            'semester' => ['required', Rule::in(['ganjil', 'genap'])],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'durasi_menit' => ['required', 'integer', 'min:10', 'max:300'],
            'jumlah_soal' => ['required', 'integer', 'min:1', 'max:120'],
            'status' => ['required', Rule::in(['draft', 'terjadwal', 'berlangsung', 'selesai'])],
            'acak_soal' => ['nullable', 'boolean'],
            'acak_jawaban' => ['nullable', 'boolean'],
            'batasi_satu_perangkat' => ['nullable', 'boolean'],
            'deteksi_pindah_tab' => ['nullable', 'boolean'],
            'tampilkan_hasil' => ['nullable', 'boolean'],
            'petunjuk' => ['nullable', 'string', 'max:3000'],
            'kelas_peserta' => ['required', 'array'],
            'kelas_peserta.*.dipilih' => ['nullable', 'boolean'],
            'kelas_peserta.*.komponen_nilai_id' => ['nullable', 'string', 'max:30'],
        ]);

        $kelompok = $kelompokTersedia->get($data['kelompok_pengajaran']);
        $kelasPeserta = collect($data['kelas_peserta'])
            ->filter(fn ($item) => filter_var($item['dipilih'] ?? false, FILTER_VALIDATE_BOOLEAN));

        if ($kelasPeserta->isEmpty()) {
            throw ValidationException::withMessages(['kelas_peserta' => 'Pilih minimal satu kelas.']);
        }

        $kelasKelompok = collect($kelompok['kelas'])->keyBy('kelas_id');

        foreach ($kelasPeserta as $kelasId => $item) {
            $kelas = $kelasKelompok->get((int) $kelasId);
            $komponenId = $item['komponen_nilai_id'] ?? null;

            if (! $kelas) {
                throw ValidationException::withMessages(['kelas_peserta' => 'Ada kelas yang tidak termasuk dalam penugasan guru.']);
            }

            if ($komponenId !== 'baru') {
                $komponenValid = collect($kelas['komponen'])
                    ->contains(fn ($komponen) => (string) $komponen['id'] === (string) $komponenId && $komponen['semester'] === $data['semester']);

                if (! $komponenValid) {
                    throw ValidationException::withMessages(['kelas_peserta' => 'Pilih tujuan nilai yang sesuai untuk setiap kelas.']);
                }
            }
        }

        $data['nama'] = trim($data['nama']);
        $data['petunjuk'] = filled($data['petunjuk'] ?? null) ? trim($data['petunjuk']) : null;
        foreach (['acak_soal', 'acak_jawaban', 'batasi_satu_perangkat', 'deteksi_pindah_tab', 'tampilkan_hasil'] as $field) {
            $data[$field] = $request->boolean($field);
        }

        return [$data, $kelompok, $kelasPeserta];
    }

    private function kelompokPengajaran(Request $request, int $tahunPelajaranId): Collection
    {
        $query = GuruMataPelajaran::query()
            ->with([
                'kelas:id,nama,tingkat',
                'mataPelajaran:id,nama,kkm',
                'pegawai:id,nama_lengkap',
                'komponenNilai' => fn ($query) => $query
                    ->where('aktif', true)
                    ->where('jenis_komponen', 'sumatif')
                    ->orderBy('semester')
                    ->orderBy('urutan'),
            ])
            ->where('tahun_pelajaran_id', $tahunPelajaranId)
            ->where('aktif', true);

        if (! $request->user()->memilikiIzin('cbt.kelola')) {
            $query->where('pegawai_id', $request->user()->pegawai_id ?: 0);
        }

        return $query->get()
            ->filter(fn ($item) => $item->kelas && $item->mataPelajaran)
            ->groupBy(fn ($item) => implode('-', [$item->pegawai_id, $item->mata_pelajaran_id, $item->kelas->tingkat]))
            ->map(function (Collection $items, string $key) use ($request, $tahunPelajaranId) {
                $acuan = $items->first();
                $kkm = PengaturanMataPelajaran::query()
                    ->where('tahun_pelajaran_id', $tahunPelajaranId)
                    ->where('mata_pelajaran_id', $acuan->mata_pelajaran_id)
                    ->where('tingkat', $acuan->kelas->tingkat)
                    ->value('kkm') ?? $acuan->mataPelajaran->kkm;

                return [
                    'key' => $key,
                    'mata_pelajaran_id' => $acuan->mata_pelajaran_id,
                    'mata_pelajaran' => $acuan->mataPelajaran->nama,
                    'pegawai' => $acuan->pegawai?->nama_lengkap,
                    'tingkat' => (int) $acuan->kelas->tingkat,
                    'kkm' => $kkm,
                    'label' => $acuan->mataPelajaran->nama.' - Kelas '.$acuan->kelas->tingkat
                        .($request->user()->memilikiIzin('cbt.kelola') ? ' - '.($acuan->pegawai?->nama_lengkap ?: 'Tanpa guru') : ''),
                    'kelas' => $items->sortBy('kelas.nama')->map(fn ($item) => [
                        'kelas_id' => $item->kelas_id,
                        'nama' => $item->kelas->nama,
                        'guru_mata_pelajaran_id' => $item->id,
                        'komponen' => $item->komponenNilai->map(fn ($komponen) => [
                            'id' => $komponen->id,
                            'nama' => $komponen->nama,
                            'semester' => $komponen->semester,
                        ])->values()->all(),
                    ])->values()->all(),
                ];
            })
            ->sortBy('label');
    }

    private function dataUjian(array $data, array $kelompok, ?UjianCbt $ujianCbt = null): array
    {
        $jenis = JenisUjianCbt::query()->where('kode', 'ASESMEN_KELAS')->firstOrFail();
        $tahunPelajaranId = TahunPelajaran::query()->where('aktif', true)->value('id');

        return [
            'alur' => 'kelas',
            'jenis_ujian_cbt_id' => $jenis->id,
            'tahun_pelajaran_id' => $tahunPelajaranId,
            'mata_pelajaran_id' => $kelompok['mata_pelajaran_id'],
            'kode' => $ujianCbt?->kode ?: $this->buatKodeSaran(),
            'nama' => $data['nama'],
            'semester' => $data['semester'],
            'tingkat' => $kelompok['tingkat'],
            'tanggal_mulai' => $data['tanggal_mulai'],
            'tanggal_selesai' => $data['tanggal_selesai'],
            'durasi_menit' => $data['durasi_menit'],
            'jumlah_soal' => $data['jumlah_soal'],
            'kkm' => $kelompok['kkm'],
            'token' => null,
            'acak_soal' => $data['acak_soal'],
            'acak_jawaban' => $data['acak_jawaban'],
            'batasi_satu_perangkat' => $data['batasi_satu_perangkat'],
            'deteksi_pindah_tab' => $data['deteksi_pindah_tab'],
            'wajib_fullscreen' => false,
            'tampilkan_hasil' => $data['tampilkan_hasil'],
            'status' => $data['status'],
            'petunjuk' => $data['petunjuk'],
            'keterangan' => null,
        ];
    }

    private function sinkronkanKelasDanKomponen(UjianCbt $ujianCbt, array $data, array $kelompok, Collection $kelasPeserta): void
    {
        $kelasKelompok = collect($kelompok['kelas'])->keyBy('kelas_id');
        $ujianCbt->kelasUjianCbt()->whereNotIn('kelas_id', $kelasPeserta->keys())->delete();

        foreach ($kelasPeserta as $kelasId => $item) {
            $kelas = $kelasKelompok->get((int) $kelasId);
            $komponenId = $item['komponen_nilai_id'];

            if ($komponenId === 'baru') {
                $urutan = KomponenNilai::query()
                    ->where('guru_mata_pelajaran_id', $kelas['guru_mata_pelajaran_id'])
                    ->where('semester', $data['semester'])
                    ->max('urutan') ?? 0;
                $komponenId = KomponenNilai::create([
                    'guru_mata_pelajaran_id' => $kelas['guru_mata_pelajaran_id'],
                    'semester' => $data['semester'],
                    'jenis_komponen' => 'sumatif',
                    'nama' => $data['nama'],
                    'tanggal_penilaian' => substr($data['tanggal_mulai'], 0, 10),
                    'urutan' => $urutan + 1,
                    'aktif' => true,
                    'keterangan' => 'Dibuat otomatis dari Asesmen Kelas CBT.',
                ])->id;
            }

            $ujianCbt->kelasUjianCbt()->updateOrCreate(
                ['kelas_id' => $kelasId],
                ['komponen_nilai_id' => $komponenId],
            );
        }
    }

    private function pastikanAsesmenBolehDikelola(Request $request, UjianCbt $ujianCbt): void
    {
        abort_unless($ujianCbt->asesmenKelas() && $ujianCbt->dapatDikelolaOleh($request->user()), 403);
    }

    private function buatKodeSaran(): string
    {
        do {
            $kode = 'AK-'.now()->format('Ymd-His').'-'.random_int(10, 99);
        } while (UjianCbt::query()->where('kode', $kode)->exists());

        return $kode;
    }
}
