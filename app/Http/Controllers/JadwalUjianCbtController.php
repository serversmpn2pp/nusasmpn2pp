<?php

namespace App\Http\Controllers;

use App\Models\JadwalUjianCbt;
use App\Models\JenisUjianCbt;
use App\Models\KegiatanUjianCbt;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\TahunPelajaran;
use App\Models\UjianCbt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class JadwalUjianCbtController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'tahun_pelajaran_id' => ['nullable', 'integer', 'exists:tahun_pelajaran,id'],
            'status' => ['nullable', Rule::in(['semua', ...array_keys(KegiatanUjianCbt::DAFTAR_STATUS)])],
            'kegiatan_ujian_cbt_id' => ['nullable', 'integer', 'exists:kegiatan_ujian_cbt,id'],
        ]);

        $tahunPelajaranId = $data['tahun_pelajaran_id'] ?? null;
        $status = $data['status'] ?? 'semua';
        $kegiatanUjianCbtId = $data['kegiatan_ujian_cbt_id'] ?? null;

        $daftarKegiatan = KegiatanUjianCbt::query()
            ->with(['jenisUjianCbt', 'tahunPelajaran'])
            ->withCount('jadwalUjianCbt')
            ->when($tahunPelajaranId, fn ($query, $id) => $query->where('tahun_pelajaran_id', $id))
            ->when($status !== 'semua', fn ($query) => $query->where('status', $status))
            ->orderByDesc('tanggal_mulai')
            ->orderByDesc('id')
            ->get();

        $kegiatanTerpilih = $kegiatanUjianCbtId
            ? $daftarKegiatan->firstWhere('id', (int) $kegiatanUjianCbtId)
            : $daftarKegiatan->first();

        if ($kegiatanTerpilih) {
            $kegiatanTerpilih->load([
                'jenisUjianCbt',
                'tahunPelajaran',
                'jadwalUjianCbt' => fn ($query) => $query
                    ->with(['ujianCbt', 'mataPelajaran', 'kelas', 'dikunciOleh'])
                    ->orderBy('tanggal')
                    ->orderBy('waktu_mulai')
                    ->orderBy('urutan')
                    ->orderBy('id'),
            ]);
        }

        $tahunRujukanId = $kegiatanTerpilih?->tahun_pelajaran_id ?: $tahunPelajaranId;

        return view('jadwal-ujian-cbt.index', [
            'daftarKegiatan' => $daftarKegiatan,
            'kegiatanTerpilih' => $kegiatanTerpilih,
            'tahunPelajaranId' => $tahunPelajaranId,
            'status' => $status,
            'daftarStatusKegiatan' => KegiatanUjianCbt::DAFTAR_STATUS,
            'daftarStatusJadwal' => JadwalUjianCbt::DAFTAR_STATUS,
            'daftarTahunPelajaran' => TahunPelajaran::query()
                ->orderByDesc('aktif')
                ->orderByDesc('tanggal_mulai')
                ->get(),
            'daftarJenisUjianCbt' => JenisUjianCbt::query()
                ->where('aktif', true)
                ->orderBy('urutan')
                ->orderBy('nama')
                ->get(),
            'daftarMataPelajaran' => MataPelajaran::query()
                ->where('aktif', true)
                ->orderBy('urutan')
                ->orderBy('nama')
                ->get(),
            'daftarKelas' => Kelas::query()
                ->where('aktif', true)
                ->when($tahunRujukanId, fn ($query, $id) => $query->where('tahun_pelajaran_id', $id))
                ->orderBy('tingkat')
                ->orderBy('nama')
                ->get(),
            'daftarPaketCbt' => UjianCbt::query()
                ->with(['mataPelajaran', 'jenisUjianCbt', 'kelasUjianCbt.kelas'])
                ->when($kegiatanTerpilih, function ($query, KegiatanUjianCbt $kegiatan) {
                    $query->where('tahun_pelajaran_id', $kegiatan->tahun_pelajaran_id)
                        ->where('jenis_ujian_cbt_id', $kegiatan->jenis_ujian_cbt_id)
                        ->where('semester', $kegiatan->semester)
                        ->where('status', '!=', 'nonaktif');
                })
                ->orderBy('tingkat')
                ->orderBy('nama')
                ->get(),
            'ringkasan' => [
                'kegiatan' => KegiatanUjianCbt::where('status', '!=', 'nonaktif')->count(),
                'jadwal' => JadwalUjianCbt::count(),
                'jadwal_siap' => JadwalUjianCbt::where('status', 'siap')->count(),
                'jadwal_terkunci' => JadwalUjianCbt::whereNotNull('dikunci_pada')->count(),
                'jadwal_tanpa_paket' => JadwalUjianCbt::whereNull('ujian_cbt_id')->count(),
            ],
        ]);
    }

    public function storeKegiatan(Request $request)
    {
        $data = $this->rapikanKegiatan($request->validate($this->aturanValidasiKegiatan()));

        $kegiatan = KegiatanUjianCbt::create([
            ...$data,
            'dibuat_oleh_pengguna_id' => $request->user()?->id,
        ]);

        return redirect()
            ->route('jadwal-ujian-cbt.index', ['kegiatan_ujian_cbt_id' => $kegiatan->id])
            ->with('berhasil', 'Kegiatan ujian CBT berhasil dibuat.');
    }

    public function updateKegiatan(Request $request, KegiatanUjianCbt $kegiatanUjianCbt)
    {
        $data = $this->rapikanKegiatan($request->validate($this->aturanValidasiKegiatan($kegiatanUjianCbt)));
        $kegiatanUjianCbt->update($data);

        return redirect()
            ->route('jadwal-ujian-cbt.index', ['kegiatan_ujian_cbt_id' => $kegiatanUjianCbt->id])
            ->with('berhasil', 'Kegiatan ujian CBT berhasil diperbarui.');
    }

    public function destroyKegiatan(KegiatanUjianCbt $kegiatanUjianCbt)
    {
        $kegiatanUjianCbt->update(['status' => 'nonaktif']);

        return redirect()
            ->route('jadwal-ujian-cbt.index')
            ->with('berhasil', 'Kegiatan ujian CBT berhasil dinonaktifkan.');
    }

    public function storeJadwal(Request $request)
    {
        $kegiatan = KegiatanUjianCbt::findOrFail($request->integer('kegiatan_ujian_cbt_id'));
        $data = $this->rapikanJadwal($kegiatan, $request->validate($this->aturanValidasiJadwal()));
        $kelasIds = $this->pastikanKelasJadwalValid($kegiatan, $data, $request->input('kelas_peserta', []));

        $jadwal = DB::transaction(function () use ($data, $kelasIds) {
            $jadwal = JadwalUjianCbt::create($this->dataJadwal($data));
            $jadwal->kelas()->sync($kelasIds);

            return $jadwal;
        });

        return redirect()
            ->route('jadwal-ujian-cbt.index', ['kegiatan_ujian_cbt_id' => $jadwal->kegiatan_ujian_cbt_id])
            ->with('berhasil', 'Jadwal ujian CBT berhasil ditambahkan.');
    }

    public function updateJadwal(Request $request, JadwalUjianCbt $jadwalUjianCbt)
    {
        $this->pastikanJadwalBelumTerkunci($jadwalUjianCbt);

        $kegiatan = KegiatanUjianCbt::findOrFail($request->integer('kegiatan_ujian_cbt_id'));
        $this->pastikanJadwalMilikKegiatan($jadwalUjianCbt, $kegiatan);
        $data = $this->rapikanJadwal($kegiatan, $request->validate($this->aturanValidasiJadwal()));
        $kelasIds = $this->pastikanKelasJadwalValid($kegiatan, $data, $request->input('kelas_peserta', []));

        DB::transaction(function () use ($jadwalUjianCbt, $data, $kelasIds) {
            $jadwalUjianCbt->update($this->dataJadwal($data));
            $jadwalUjianCbt->kelas()->sync($kelasIds);
        });

        return redirect()
            ->route('jadwal-ujian-cbt.index', ['kegiatan_ujian_cbt_id' => $kegiatan->id])
            ->with('berhasil', 'Jadwal ujian CBT berhasil diperbarui.');
    }

    public function destroyJadwal(JadwalUjianCbt $jadwalUjianCbt)
    {
        $this->pastikanJadwalBelumTerkunci($jadwalUjianCbt);

        $kegiatanId = $jadwalUjianCbt->kegiatan_ujian_cbt_id;
        $jadwalUjianCbt->delete();

        return redirect()
            ->route('jadwal-ujian-cbt.index', ['kegiatan_ujian_cbt_id' => $kegiatanId])
            ->with('berhasil', 'Jadwal ujian CBT berhasil dihapus.');
    }

    public function kunciJadwal(Request $request, JadwalUjianCbt $jadwalUjianCbt)
    {
        if (! $jadwalUjianCbt->terkunci()) {
            $jadwalUjianCbt->update([
                'dikunci_pada' => now(),
                'dikunci_oleh_pengguna_id' => $request->user()?->id,
                'status' => $jadwalUjianCbt->status === 'draft' ? 'siap' : $jadwalUjianCbt->status,
            ]);
        }

        return redirect()
            ->route('jadwal-ujian-cbt.index', ['kegiatan_ujian_cbt_id' => $jadwalUjianCbt->kegiatan_ujian_cbt_id])
            ->with('berhasil', 'Jadwal ujian CBT berhasil dikunci.');
    }

    public function bukaKunciJadwal(JadwalUjianCbt $jadwalUjianCbt)
    {
        $jadwalUjianCbt->update([
            'dikunci_pada' => null,
            'dikunci_oleh_pengguna_id' => null,
        ]);

        return redirect()
            ->route('jadwal-ujian-cbt.index', ['kegiatan_ujian_cbt_id' => $jadwalUjianCbt->kegiatan_ujian_cbt_id])
            ->with('berhasil', 'Kunci jadwal ujian CBT berhasil dibuka.');
    }

    private function aturanValidasiKegiatan(?KegiatanUjianCbt $kegiatanUjianCbt = null): array
    {
        return [
            'jenis_ujian_cbt_id' => ['required', 'integer', Rule::exists('jenis_ujian_cbt', 'id')->where('aktif', true)],
            'tahun_pelajaran_id' => ['required', 'integer', 'exists:tahun_pelajaran,id'],
            'kode' => ['required', 'string', 'max:50', Rule::unique('kegiatan_ujian_cbt', 'kode')->ignore($kegiatanUjianCbt)],
            'nama' => ['required', 'string', 'max:180'],
            'semester' => ['required', Rule::in(['ganjil', 'genap'])],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'status' => ['required', Rule::in(array_keys(KegiatanUjianCbt::DAFTAR_STATUS))],
            'keterangan' => ['nullable', 'string'],
        ];
    }

    private function aturanValidasiJadwal(): array
    {
        return [
            'kegiatan_ujian_cbt_id' => ['required', 'integer', 'exists:kegiatan_ujian_cbt,id'],
            'ujian_cbt_id' => ['nullable', 'integer', 'exists:ujian_cbt,id'],
            'mata_pelajaran_id' => ['required', 'integer', 'exists:mata_pelajaran,id'],
            'tanggal' => ['required', 'date'],
            'waktu_mulai' => ['required', 'date_format:H:i'],
            'waktu_selesai' => ['required', 'date_format:H:i'],
            'label_sesi' => ['nullable', 'string', 'max:80'],
            'tingkat' => ['required', 'integer', Rule::in([7, 8, 9])],
            'urutan' => ['required', 'integer', 'min:1', 'max:20'],
            'status' => ['required', Rule::in(array_keys(JadwalUjianCbt::DAFTAR_STATUS))],
            'keterangan' => ['nullable', 'string'],
            'kelas_peserta' => ['required', 'array', 'min:1'],
            'kelas_peserta.*' => ['integer', 'exists:kelas,id'],
        ];
    }

    private function rapikanKegiatan(array $data): array
    {
        return [
            'jenis_ujian_cbt_id' => (int) $data['jenis_ujian_cbt_id'],
            'tahun_pelajaran_id' => (int) $data['tahun_pelajaran_id'],
            'kode' => mb_strtoupper(trim($data['kode'])),
            'nama' => trim($data['nama']),
            'semester' => $data['semester'],
            'tanggal_mulai' => $data['tanggal_mulai'] ?? null,
            'tanggal_selesai' => $data['tanggal_selesai'] ?? null,
            'status' => $data['status'],
            'keterangan' => filled($data['keterangan'] ?? null) ? trim($data['keterangan']) : null,
        ];
    }

    private function rapikanJadwal(KegiatanUjianCbt $kegiatan, array $data): array
    {
        if ($data['waktu_selesai'] <= $data['waktu_mulai']) {
            throw ValidationException::withMessages([
                'waktu_selesai' => 'Waktu selesai harus setelah waktu mulai.',
            ]);
        }

        if ($kegiatan->tanggal_mulai && $data['tanggal'] < $kegiatan->tanggal_mulai->format('Y-m-d')) {
            throw ValidationException::withMessages([
                'tanggal' => 'Tanggal jadwal tidak boleh sebelum tanggal mulai kegiatan.',
            ]);
        }

        if ($kegiatan->tanggal_selesai && $data['tanggal'] > $kegiatan->tanggal_selesai->format('Y-m-d')) {
            throw ValidationException::withMessages([
                'tanggal' => 'Tanggal jadwal tidak boleh setelah tanggal selesai kegiatan.',
            ]);
        }

        $ujianCbt = filled($data['ujian_cbt_id'] ?? null) ? UjianCbt::findOrFail($data['ujian_cbt_id']) : null;

        if ($ujianCbt) {
            if (
                (int) $ujianCbt->tahun_pelajaran_id !== (int) $kegiatan->tahun_pelajaran_id
                || (int) $ujianCbt->jenis_ujian_cbt_id !== (int) $kegiatan->jenis_ujian_cbt_id
                || $ujianCbt->semester !== $kegiatan->semester
                || $ujianCbt->status === 'nonaktif'
            ) {
                throw ValidationException::withMessages([
                    'ujian_cbt_id' => 'Paket CBT harus sesuai dengan jenis ujian, tahun pelajaran, dan semester kegiatan.',
                ]);
            }

            $data['mata_pelajaran_id'] = $ujianCbt->mata_pelajaran_id;
            $data['tingkat'] = $ujianCbt->tingkat;
        }

        return [
            'kegiatan_ujian_cbt_id' => $kegiatan->id,
            'ujian_cbt_id' => filled($data['ujian_cbt_id'] ?? null) ? (int) $data['ujian_cbt_id'] : null,
            'mata_pelajaran_id' => (int) $data['mata_pelajaran_id'],
            'tanggal' => $data['tanggal'],
            'waktu_mulai' => $data['waktu_mulai'],
            'waktu_selesai' => $data['waktu_selesai'],
            'label_sesi' => filled($data['label_sesi'] ?? null) ? trim($data['label_sesi']) : null,
            'tingkat' => (int) $data['tingkat'],
            'urutan' => (int) $data['urutan'],
            'status' => $data['status'],
            'keterangan' => filled($data['keterangan'] ?? null) ? trim($data['keterangan']) : null,
        ];
    }

    private function pastikanKelasJadwalValid(KegiatanUjianCbt $kegiatan, array $data, array $kelasPeserta): array
    {
        $kelasIds = collect($kelasPeserta)->map(fn ($id) => (int) $id)->unique()->values();

        if ($kelasIds->isEmpty()) {
            throw ValidationException::withMessages([
                'kelas_peserta' => 'Pilih minimal satu kelas peserta jadwal.',
            ]);
        }

        $kelas = Kelas::query()
            ->whereIn('id', $kelasIds)
            ->get()
            ->keyBy('id');

        if ($kelas->count() !== $kelasIds->count()) {
            throw ValidationException::withMessages([
                'kelas_peserta' => 'Ada kelas peserta yang tidak ditemukan.',
            ]);
        }

        foreach ($kelasIds as $kelasId) {
            $kelasDipilih = $kelas[$kelasId];

            if (
                (int) $kelasDipilih->tahun_pelajaran_id !== (int) $kegiatan->tahun_pelajaran_id
                || (int) $kelasDipilih->tingkat !== (int) $data['tingkat']
            ) {
                throw ValidationException::withMessages([
                    'kelas_peserta' => 'Kelas peserta harus sesuai dengan tahun pelajaran dan tingkat jadwal.',
                ]);
            }
        }

        if ($data['ujian_cbt_id']) {
            $kelasPaket = UjianCbt::findOrFail($data['ujian_cbt_id'])
                ->kelasUjianCbt()
                ->pluck('kelas_id');

            if ($kelasIds->diff($kelasPaket)->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'kelas_peserta' => 'Jika jadwal memakai paket CBT, kelas peserta harus termasuk kelas peserta paket tersebut.',
                ]);
            }
        }

        return $kelasIds->all();
    }

    private function pastikanJadwalMilikKegiatan(JadwalUjianCbt $jadwalUjianCbt, KegiatanUjianCbt $kegiatanUjianCbt): void
    {
        abort_unless((int) $jadwalUjianCbt->kegiatan_ujian_cbt_id === (int) $kegiatanUjianCbt->id, 404);
    }

    private function pastikanJadwalBelumTerkunci(JadwalUjianCbt $jadwalUjianCbt): void
    {
        if (! $jadwalUjianCbt->terkunci()) {
            return;
        }

        throw ValidationException::withMessages([
            'jadwal' => 'Jadwal ujian sudah dikunci. Buka kunci jadwal terlebih dahulu jika perlu revisi.',
        ]);
    }

    private function dataJadwal(array $data): array
    {
        return collect($data)->only([
            'kegiatan_ujian_cbt_id',
            'ujian_cbt_id',
            'mata_pelajaran_id',
            'tanggal',
            'waktu_mulai',
            'waktu_selesai',
            'label_sesi',
            'tingkat',
            'urutan',
            'status',
            'keterangan',
        ])->all();
    }
}
