<?php

namespace App\Http\Controllers;

use App\Models\JenisUjianCbt;
use App\Models\Kelas;
use App\Models\KomponenNilai;
use App\Models\MataPelajaran;
use App\Models\TahunPelajaran;
use App\Models\UjianCbt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UjianCbtController extends Controller
{
    public function index()
    {
        return redirect()->route('pusat-cbt.index');
    }

    public function create(Request $request)
    {
        $alur = $request->string('alur')->lower()->value();
        $jenisAwal = $alur === 'kelas'
            ? JenisUjianCbt::query()->where('kode', 'ASESMEN_KELAS')->first()
            : null;

        return view('ujian-cbt.create', $this->dataForm([
            'kodeSaran' => $this->buatKodeSaran(),
            'jenisAwal' => $jenisAwal,
            'tahunPelajaranAwal' => TahunPelajaran::query()->where('aktif', true)->first(),
            'alur' => $alur,
        ]));
    }

    public function store(Request $request)
    {
        $data = $this->rapikanData($request, $request->validate($this->aturanValidasi()));
        $kelasPeserta = $this->pastikanKelasPesertaCocok($data);
        $data = $this->lengkapiTokenJikaPerlu($data);

        $ujianCbt = DB::transaction(function () use ($data, $kelasPeserta, $request) {
            $ujianCbt = UjianCbt::create([
                ...$this->dataUjian($data),
                'dibuat_oleh_pengguna_id' => $request->user()?->id,
            ]);
            $this->sinkronkanKelasPeserta($ujianCbt, $kelasPeserta);

            return $ujianCbt;
        });

        return redirect()
            ->route('ujian-cbt.show', $ujianCbt)
            ->with('berhasil', 'Paket ujian CBT berhasil ditambahkan.');
    }

    public function show(UjianCbt $ujianCbt)
    {
        abort_unless($ujianCbt->ujianTerpusat(), 404);

        $jadwalTerpusat = $ujianCbt->jadwalUjianCbt()->orderBy('id')->first();
        if ($jadwalTerpusat) {
            return redirect()->route('paket-soal-terpusat.show', $jadwalTerpusat);
        }

        $ujianCbt->load([
            'jenisUjianCbt',
            'tahunPelajaran',
            'mataPelajaran',
            'dibuatOleh',
            'kelasUjianCbt.kelas',
            'kelasUjianCbt.komponenNilai.guruMataPelajaran.pegawai',
            'soalUjianCbt.soalCbt',
            'sesiUjianCbt.pesertaUjianCbt',
        ]);
        $ujianCbt->loadCount(['sesiUjianCbt', 'pesertaUjianCbt']);

        return view('ujian-cbt.show', compact('ujianCbt'));
    }

    public function edit(UjianCbt $ujianCbt)
    {
        abort_unless($ujianCbt->ujianTerpusat(), 404);

        $ujianCbt->load('kelasUjianCbt');

        return view('ujian-cbt.edit', $this->dataForm([
            'ujianCbt' => $ujianCbt,
            'kodeSaran' => null,
        ]));
    }

    public function update(Request $request, UjianCbt $ujianCbt)
    {
        abort_unless($ujianCbt->ujianTerpusat(), 404);

        $data = $this->rapikanData($request, $request->validate($this->aturanValidasi($ujianCbt)));
        $kelasPeserta = $this->pastikanKelasPesertaCocok($data);
        $data = $this->lengkapiTokenJikaPerlu($data);

        DB::transaction(function () use ($ujianCbt, $data, $kelasPeserta) {
            $ujianCbt->update($this->dataUjian($data));
            $this->sinkronkanKelasPeserta($ujianCbt, $kelasPeserta);
        });

        return redirect()
            ->route('ujian-cbt.show', $ujianCbt)
            ->with('berhasil', 'Paket ujian CBT berhasil diperbarui.');
    }

    public function destroy(UjianCbt $ujianCbt)
    {
        abort_unless($ujianCbt->ujianTerpusat(), 404);

        $ujianCbt->update(['status' => 'nonaktif']);

        return redirect()
            ->route('ujian-cbt.index')
            ->with('berhasil', 'Paket ujian CBT berhasil dinonaktifkan.');
    }

    private function dataForm(array $tambahan = []): array
    {
        return array_merge([
            'daftarTahunPelajaran' => $this->daftarTahunPelajaran(),
            'daftarJenisUjianCbt' => $this->daftarJenisUjianCbt(),
            'daftarMataPelajaran' => MataPelajaran::query()
                ->where('aktif', true)
                ->orderBy('urutan')
                ->orderBy('nama')
                ->get(),
            'daftarKelas' => Kelas::query()
                ->with('tahunPelajaran')
                ->where('aktif', true)
                ->orderBy('tingkat')
                ->orderBy('nama')
                ->get(),
            'daftarKomponenNilai' => KomponenNilai::query()
                ->with([
                    'guruMataPelajaran.tahunPelajaran',
                    'guruMataPelajaran.kelas',
                    'guruMataPelajaran.mataPelajaran',
                    'guruMataPelajaran.pegawai',
                ])
                ->where('aktif', true)
                ->whereIn('jenis_komponen', ['sumatif', 'sts', 'sas_saj'])
                ->whereHas('guruMataPelajaran', fn ($query) => $query->where('aktif', true))
                ->orderBy('semester')
                ->orderBy('nama')
                ->get(),
            'daftarStatus' => UjianCbt::DAFTAR_STATUS,
        ], $tambahan);
    }

    private function daftarTahunPelajaran()
    {
        return TahunPelajaran::query()
            ->orderByDesc('aktif')
            ->orderByDesc('nama')
            ->get();
    }

    private function daftarJenisUjianCbt()
    {
        return JenisUjianCbt::query()
            ->where('aktif', true)
            ->orderBy('urutan')
            ->orderBy('nama')
            ->get();
    }

    private function aturanValidasi(?UjianCbt $ujianCbt = null): array
    {
        return [
            'jenis_ujian_cbt_id' => ['required', 'integer', Rule::exists('jenis_ujian_cbt', 'id')->where('aktif', true)],
            'tahun_pelajaran_id' => ['required', 'integer', 'exists:tahun_pelajaran,id'],
            'mata_pelajaran_id' => ['required', 'integer', 'exists:mata_pelajaran,id'],
            'kode' => ['required', 'string', 'max:50', Rule::unique('ujian_cbt', 'kode')->ignore($ujianCbt)],
            'nama' => ['required', 'string', 'max:180'],
            'semester' => ['required', Rule::in(['ganjil', 'genap'])],
            'tingkat' => ['required', 'integer', Rule::in([7, 8, 9])],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'durasi_menit' => ['required', 'integer', 'min:10', 'max:300'],
            'jumlah_soal' => ['required', 'integer', 'min:1', 'max:120'],
            'kkm' => ['nullable', 'integer', 'min:0', 'max:100'],
            'token' => ['nullable', 'string', 'max:20'],
            'acak_soal' => ['nullable', 'boolean'],
            'acak_jawaban' => ['nullable', 'boolean'],
            'batasi_satu_perangkat' => ['nullable', 'boolean'],
            'deteksi_pindah_tab' => ['nullable', 'boolean'],
            'wajib_fullscreen' => ['nullable', 'boolean'],
            'tampilkan_hasil' => ['nullable', 'boolean'],
            'status' => ['required', Rule::in(array_keys(UjianCbt::DAFTAR_STATUS))],
            'petunjuk' => ['nullable', 'string'],
            'keterangan' => ['nullable', 'string'],
            'kelas_peserta' => ['required', 'array'],
            'kelas_peserta.*.dipilih' => ['nullable', 'boolean'],
            'kelas_peserta.*.komponen_nilai_id' => ['nullable', 'integer', 'exists:komponen_nilai,id'],
        ];
    }

    private function rapikanData(Request $request, array $data): array
    {
        $data['kode'] = mb_strtoupper(trim($data['kode']));
        $data['nama'] = trim($data['nama']);
        $data['token'] = filled($data['token'] ?? null)
            ? mb_strtoupper(trim($data['token']))
            : null;
        $data['petunjuk'] = filled($data['petunjuk'] ?? null) ? trim($data['petunjuk']) : null;
        $data['keterangan'] = filled($data['keterangan'] ?? null) ? trim($data['keterangan']) : null;
        $data['acak_soal'] = $request->boolean('acak_soal');
        $data['acak_jawaban'] = $request->boolean('acak_jawaban');
        $data['batasi_satu_perangkat'] = $request->boolean('batasi_satu_perangkat');
        $data['deteksi_pindah_tab'] = $request->boolean('deteksi_pindah_tab');
        $data['wajib_fullscreen'] = $request->boolean('wajib_fullscreen');
        $data['tampilkan_hasil'] = $request->boolean('tampilkan_hasil');

        return $data;
    }

    private function dataUjian(array $data): array
    {
        return collect($data)->only([
            'jenis_ujian_cbt_id',
            'tahun_pelajaran_id',
            'mata_pelajaran_id',
            'kode',
            'nama',
            'semester',
            'tingkat',
            'tanggal_mulai',
            'tanggal_selesai',
            'durasi_menit',
            'jumlah_soal',
            'kkm',
            'token',
            'acak_soal',
            'acak_jawaban',
            'batasi_satu_perangkat',
            'deteksi_pindah_tab',
            'wajib_fullscreen',
            'tampilkan_hasil',
            'status',
            'petunjuk',
            'keterangan',
        ])->prepend('terpusat', 'alur')->all();
    }

    private function pastikanKelasPesertaCocok(array $data): array
    {
        $kelasPeserta = collect($data['kelas_peserta'])
            ->filter(fn ($item) => filter_var($item['dipilih'] ?? false, FILTER_VALIDATE_BOOLEAN))
            ->mapWithKeys(fn ($item, $kelasId) => [
                (int) $kelasId => filled($item['komponen_nilai_id'] ?? null) ? (int) $item['komponen_nilai_id'] : null,
            ]);

        if ($kelasPeserta->isEmpty()) {
            throw ValidationException::withMessages([
                'kelas_peserta' => 'Pilih minimal satu kelas peserta CBT.',
            ]);
        }

        $jenisUjian = JenisUjianCbt::find($data['jenis_ujian_cbt_id']);
        $mataPelajaran = MataPelajaran::find($data['mata_pelajaran_id']);

        if (
            ! $mataPelajaran
            || ! $mataPelajaran->tersediaUntuk(
                (int) $data['tahun_pelajaran_id'],
                (int) $data['tingkat'],
            )
        ) {
            throw ValidationException::withMessages([
                'mata_pelajaran_id' => 'Mata pelajaran belum diaktifkan untuk tingkat dan tahun pelajaran paket ujian.',
            ]);
        }

        $kelas = Kelas::query()->whereIn('id', $kelasPeserta->keys())->get()->keyBy('id');
        $komponenNilai = KomponenNilai::query()
            ->with('guruMataPelajaran')
            ->whereIn('id', $kelasPeserta->filter()->values())
            ->get()
            ->keyBy('id');

        foreach ($kelasPeserta as $kelasId => $komponenNilaiId) {
            $kelasDipilih = $kelas[$kelasId] ?? null;
            $komponenDipilih = $komponenNilaiId ? ($komponenNilai[$komponenNilaiId] ?? null) : null;
            $guruMapel = $komponenDipilih?->guruMataPelajaran;

            if (
                ! $kelasDipilih
                || (int) $kelasDipilih->tahun_pelajaran_id !== (int) $data['tahun_pelajaran_id']
                || (int) $kelasDipilih->tingkat !== (int) $data['tingkat']
            ) {
                throw ValidationException::withMessages([
                    'kelas_peserta' => 'Ada kelas peserta yang tidak sesuai dengan tahun pelajaran atau tingkat paket ujian.',
                ]);
            }

            if ($jenisUjian?->dapat_diterapkan_ke_nilai && ! $komponenNilaiId) {
                throw ValidationException::withMessages([
                    'kelas_peserta' => 'Pilih komponen nilai tujuan untuk setiap kelas peserta karena jenis ujian ini diterapkan ke nilai.',
                ]);
            }

            if (! $komponenNilaiId) {
                continue;
            }

            if (
                ! $komponenDipilih
                || ! $komponenDipilih->aktif
                || ! in_array($komponenDipilih->jenis_komponen, ['sumatif', 'sts', 'sas_saj'], true)
                || $komponenDipilih->semester !== $data['semester']
                || ! $guruMapel?->aktif
                || (int) $guruMapel->kelas_id !== $kelasId
                || (int) $guruMapel->tahun_pelajaran_id !== (int) $data['tahun_pelajaran_id']
                || (int) $guruMapel->mata_pelajaran_id !== (int) $data['mata_pelajaran_id']
            ) {
                throw ValidationException::withMessages([
                    'kelas_peserta' => 'Komponen nilai tujuan harus aktif dan sesuai dengan kelas, semester, tahun pelajaran, serta mata pelajaran CBT.',
                ]);
            }
        }

        return $kelasPeserta->all();
    }

    private function sinkronkanKelasPeserta(UjianCbt $ujianCbt, array $kelasPeserta): void
    {
        $ujianCbt->kelasUjianCbt()->whereNotIn('kelas_id', array_keys($kelasPeserta))->delete();

        foreach ($kelasPeserta as $kelasId => $komponenNilaiId) {
            $ujianCbt->kelasUjianCbt()->updateOrCreate(
                ['kelas_id' => $kelasId],
                ['komponen_nilai_id' => $komponenNilaiId],
            );
        }
    }

    private function lengkapiTokenJikaPerlu(array $data): array
    {
        $jenisUjian = JenisUjianCbt::find($data['jenis_ujian_cbt_id']);

        if ($jenisUjian?->memerlukan_token && blank($data['token'])) {
            $data['token'] = (string) random_int(100000, 999999);
        }

        if (! $jenisUjian?->memerlukan_token) {
            $data['token'] = null;
        }

        return $data;
    }

    private function buatKodeSaran(): string
    {
        $prefix = 'CBT-'.now()->format('Ymd');
        $urutan = UjianCbt::where('kode', 'like', $prefix.'-%')->count() + 1;

        return sprintf('%s-%03d', $prefix, $urutan);
    }
}
