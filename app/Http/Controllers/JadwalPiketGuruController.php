<?php

namespace App\Http\Controllers;

use App\Models\GuruMataPelajaran;
use App\Models\JadwalPiketGuru;
use App\Models\Pegawai;
use App\Models\TahunPelajaran;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class JadwalPiketGuruController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'tahun_pelajaran_id' => ['nullable', 'integer', 'exists:tahun_pelajaran,id'],
        ]);
        $tahunPelajaran = $this->daftarTahunPelajaran();
        $tahunPelajaranId = $this->ambilTahunPelajaranId(
            $data['tahun_pelajaran_id'] ?? null,
            $tahunPelajaran,
        );
        $jadwal = JadwalPiketGuru::query()
            ->with(['pegawai:id,nama_lengkap,nip', 'tahunPelajaran:id,nama'])
            ->when($tahunPelajaranId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId))
            ->orderByRaw("case hari when 'senin' then 1 when 'selasa' then 2 when 'rabu' then 3 when 'kamis' then 4 when 'jumat' then 5 when 'sabtu' then 6 else 7 end")
            ->orderBy(
                Pegawai::select('nama_lengkap')
                    ->whereColumn('pegawai.id', 'jadwal_piket_guru.pegawai_id')
                    ->limit(1),
            )
            ->get();

        return view('jadwal-piket-guru.index', [
            'tahunPelajaran' => $tahunPelajaran,
            'tahunPelajaranId' => $tahunPelajaranId,
            'jadwalPerHari' => $jadwal->groupBy('hari'),
            'jumlahJadwalAktif' => $jadwal->where('aktif', true)->count(),
            'jumlahGuru' => $jadwal->where('aktif', true)->pluck('pegawai_id')->unique()->count(),
            'jumlahHariTerisi' => $jadwal->where('aktif', true)->pluck('hari')->unique()->count(),
            'daftarHari' => JadwalPiketGuru::DAFTAR_HARI,
        ]);
    }

    public function create(Request $request)
    {
        $tahunPelajaran = $this->daftarTahunPelajaran();
        $tahunPelajaranId = $this->ambilTahunPelajaranId(
            $request->integer('tahun_pelajaran_id') ?: null,
            $tahunPelajaran,
        );

        return view('jadwal-piket-guru.create', [
            'tahunPelajaran' => $tahunPelajaran,
            'tahunPelajaranId' => $tahunPelajaranId,
            'guruMapel' => $this->daftarGuruMapel($tahunPelajaranId),
            'daftarHari' => JadwalPiketGuru::DAFTAR_HARI,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'tahun_pelajaran_id' => ['required', 'integer', 'exists:tahun_pelajaran,id'],
            'hari' => ['required', Rule::in(array_keys(JadwalPiketGuru::DAFTAR_HARI))],
            'pegawai_ids' => ['required', 'array', 'min:1'],
            'pegawai_ids.*' => ['required', 'integer', 'distinct', 'exists:pegawai,id'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
            'aktif' => ['nullable', 'boolean'],
        ]);
        $pegawaiIds = collect($data['pegawai_ids'])->map(fn ($id) => (int) $id)->unique()->values();
        $this->pastikanGuruMapel($pegawaiIds, (int) $data['tahun_pelajaran_id']);

        DB::transaction(function () use ($data, $pegawaiIds, $request) {
            foreach ($pegawaiIds as $pegawaiId) {
                JadwalPiketGuru::query()->updateOrCreate(
                    [
                        'tahun_pelajaran_id' => $data['tahun_pelajaran_id'],
                        'hari' => $data['hari'],
                        'pegawai_id' => $pegawaiId,
                    ],
                    [
                        'aktif' => $request->boolean('aktif', true),
                        'keterangan' => $data['keterangan'] ?? null,
                    ],
                );
            }
        });

        return redirect()
            ->route('jadwal-piket-guru.index', ['tahun_pelajaran_id' => $data['tahun_pelajaran_id']])
            ->with('berhasil', $pegawaiIds->count().' guru berhasil dimasukkan ke jadwal piket '.JadwalPiketGuru::DAFTAR_HARI[$data['hari']].'.');
    }

    public function edit(JadwalPiketGuru $jadwalPiketGuru)
    {
        return view('jadwal-piket-guru.edit', [
            'jadwalPiketGuru' => $jadwalPiketGuru,
            'tahunPelajaran' => $this->daftarTahunPelajaran(),
            'guruMapel' => $this->daftarGuruMapel((int) $jadwalPiketGuru->tahun_pelajaran_id),
            'daftarHari' => JadwalPiketGuru::DAFTAR_HARI,
        ]);
    }

    public function update(Request $request, JadwalPiketGuru $jadwalPiketGuru)
    {
        $data = $request->validate([
            'tahun_pelajaran_id' => ['required', 'integer', 'exists:tahun_pelajaran,id'],
            'hari' => ['required', Rule::in(array_keys(JadwalPiketGuru::DAFTAR_HARI))],
            'pegawai_id' => ['required', 'integer', 'exists:pegawai,id'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
            'aktif' => ['nullable', 'boolean'],
        ]);
        $this->pastikanGuruMapel(collect([(int) $data['pegawai_id']]), (int) $data['tahun_pelajaran_id']);

        $duplikat = JadwalPiketGuru::query()
            ->where('tahun_pelajaran_id', $data['tahun_pelajaran_id'])
            ->where('hari', $data['hari'])
            ->where('pegawai_id', $data['pegawai_id'])
            ->whereKeyNot($jadwalPiketGuru->id)
            ->exists();

        if ($duplikat) {
            throw ValidationException::withMessages([
                'pegawai_id' => 'Guru tersebut sudah terdaftar pada hari yang dipilih.',
            ]);
        }

        $jadwalPiketGuru->update([
            ...$data,
            'aktif' => $request->boolean('aktif'),
        ]);

        return redirect()
            ->route('jadwal-piket-guru.index', ['tahun_pelajaran_id' => $data['tahun_pelajaran_id']])
            ->with('berhasil', 'Jadwal guru piket berhasil diperbarui.');
    }

    public function destroy(JadwalPiketGuru $jadwalPiketGuru)
    {
        $tahunPelajaranId = $jadwalPiketGuru->tahun_pelajaran_id;
        $jadwalPiketGuru->delete();

        return redirect()
            ->route('jadwal-piket-guru.index', ['tahun_pelajaran_id' => $tahunPelajaranId])
            ->with('berhasil', 'Guru berhasil dikeluarkan dari jadwal piket.');
    }

    private function daftarTahunPelajaran()
    {
        return TahunPelajaran::query()
            ->orderByDesc('aktif')
            ->orderByDesc('tanggal_mulai')
            ->get();
    }

    private function ambilTahunPelajaranId(?int $tahunPelajaranId, $tahunPelajaran): ?int
    {
        if ($tahunPelajaranId && $tahunPelajaran->contains('id', $tahunPelajaranId)) {
            return $tahunPelajaranId;
        }

        return $tahunPelajaran->firstWhere('aktif', true)?->id ?? $tahunPelajaran->first()?->id;
    }

    private function daftarGuruMapel(?int $tahunPelajaranId)
    {
        if (! $tahunPelajaranId) {
            return collect();
        }

        return Pegawai::query()
            ->where('aktif', true)
            ->whereHas('guruMataPelajaran', fn ($query) => $query
                ->where('tahun_pelajaran_id', $tahunPelajaranId)
                ->where('jenis_penugasan', 'pengampu')
                ->where('aktif', true))
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'nip']);
    }

    private function pastikanGuruMapel($pegawaiIds, int $tahunPelajaranId): void
    {
        $guruMapelIds = GuruMataPelajaran::query()
            ->where('tahun_pelajaran_id', $tahunPelajaranId)
            ->where('jenis_penugasan', 'pengampu')
            ->where('aktif', true)
            ->whereIn('pegawai_id', $pegawaiIds)
            ->pluck('pegawai_id')
            ->map(fn ($id) => (int) $id)
            ->unique();

        if ($pegawaiIds->diff($guruMapelIds)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'pegawai_ids' => 'Guru piket harus merupakan guru mata pelajaran aktif pada tahun pelajaran tersebut.',
            ]);
        }
    }
}
