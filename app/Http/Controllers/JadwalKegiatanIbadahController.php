<?php

namespace App\Http\Controllers;

use App\Models\JadwalKegiatanIbadah;
use App\Models\KegiatanIbadah;
use App\Models\TahunPelajaran;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class JadwalKegiatanIbadahController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'tahun_pelajaran_id' => ['nullable', 'integer', 'exists:tahun_pelajaran,id'],
            'kegiatan_ibadah_id' => ['nullable', 'integer', 'exists:kegiatan_ibadah,id'],
        ]);
        $tahunPelajaran = $this->daftarTahunPelajaran();
        $tahunPelajaranId = $this->ambilTahunPelajaranId($data['tahun_pelajaran_id'] ?? null, $tahunPelajaran);
        $kegiatanIbadah = KegiatanIbadah::query()->orderByDesc('aktif')->orderBy('nama')->get();
        $kegiatanIbadahId = isset($data['kegiatan_ibadah_id']) && $kegiatanIbadah->contains('id', (int) $data['kegiatan_ibadah_id'])
            ? (int) $data['kegiatan_ibadah_id']
            : $kegiatanIbadah->firstWhere('aktif', true)?->id;
        $jadwal = JadwalKegiatanIbadah::query()
            ->with(['kegiatanIbadah:id,nama,kode,aktif', 'tahunPelajaran:id,nama'])
            ->when($tahunPelajaranId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId))
            ->when($kegiatanIbadahId, fn ($query) => $query->where('kegiatan_ibadah_id', $kegiatanIbadahId))
            ->orderBy('urutan_hari')
            ->get();

        return view('jadwal-kegiatan-ibadah.index', [
            'tahunPelajaran' => $tahunPelajaran,
            'tahunPelajaranId' => $tahunPelajaranId,
            'kegiatanIbadah' => $kegiatanIbadah,
            'kegiatanIbadahId' => $kegiatanIbadahId,
            'kegiatanDipilih' => $kegiatanIbadah->firstWhere('id', $kegiatanIbadahId),
            'jadwalPerHari' => $jadwal->keyBy('hari'),
            'jumlahAktif' => $jadwal->where('aktif', true)->count(),
            'daftarHari' => JadwalKegiatanIbadah::DAFTAR_HARI,
        ]);
    }

    public function create(Request $request)
    {
        $tahunPelajaran = $this->daftarTahunPelajaran();
        $kegiatanIbadah = KegiatanIbadah::query()->where('aktif', true)->orderBy('nama')->get();
        $tahunPelajaranId = $this->ambilTahunPelajaranId(
            $request->integer('tahun_pelajaran_id') ?: null,
            $tahunPelajaran,
        );
        $kegiatanIbadahId = $request->integer('kegiatan_ibadah_id');

        if (! $kegiatanIbadah->contains('id', $kegiatanIbadahId)) {
            $kegiatanIbadahId = $kegiatanIbadah->first()?->id;
        }

        return view('jadwal-kegiatan-ibadah.create', [
            'tahunPelajaran' => $tahunPelajaran,
            'tahunPelajaranId' => $tahunPelajaranId,
            'kegiatanIbadah' => $kegiatanIbadah,
            'kegiatanIbadahId' => $kegiatanIbadahId,
            'daftarHari' => JadwalKegiatanIbadah::DAFTAR_HARI,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'kegiatan_ibadah_id' => ['required', 'integer', 'exists:kegiatan_ibadah,id'],
            'tahun_pelajaran_id' => ['required', 'integer', 'exists:tahun_pelajaran,id'],
            'hari' => ['required', 'array', 'min:1'],
            'hari.*' => ['required', 'distinct', Rule::in(array_keys(JadwalKegiatanIbadah::DAFTAR_HARI))],
            'jam_scan_mulai' => ['required', 'date_format:H:i'],
            'jam_pelaksanaan' => ['required', 'date_format:H:i'],
            'jam_scan_selesai' => ['required', 'date_format:H:i'],
            'aktif' => ['nullable', 'boolean'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ]);
        $this->pastikanKegiatanAktif((int) $data['kegiatan_ibadah_id']);
        $this->pastikanUrutanWaktuBenar($data);

        DB::transaction(function () use ($data, $request) {
            foreach ($data['hari'] as $hari) {
                JadwalKegiatanIbadah::query()->updateOrCreate(
                    [
                        'kegiatan_ibadah_id' => $data['kegiatan_ibadah_id'],
                        'tahun_pelajaran_id' => $data['tahun_pelajaran_id'],
                        'hari' => $hari,
                    ],
                    [
                        'urutan_hari' => JadwalKegiatanIbadah::DAFTAR_HARI[$hari]['urutan'],
                        'jam_scan_mulai' => $data['jam_scan_mulai'],
                        'jam_pelaksanaan' => $data['jam_pelaksanaan'],
                        'jam_scan_selesai' => $data['jam_scan_selesai'],
                        'aktif' => $request->boolean('aktif'),
                        'keterangan' => filled($data['keterangan'] ?? null) ? trim($data['keterangan']) : null,
                    ],
                );
            }
        });

        return redirect()->route('jadwal-kegiatan-ibadah.index', [
            'tahun_pelajaran_id' => $data['tahun_pelajaran_id'],
            'kegiatan_ibadah_id' => $data['kegiatan_ibadah_id'],
        ])->with('berhasil', count($data['hari']).' jadwal kegiatan ibadah berhasil diterapkan.');
    }

    public function edit(JadwalKegiatanIbadah $jadwalKegiatanIbadah)
    {
        $jadwalKegiatanIbadah->load(['kegiatanIbadah:id,nama', 'tahunPelajaran:id,nama']);

        return view('jadwal-kegiatan-ibadah.edit', [
            'jadwalKegiatanIbadah' => $jadwalKegiatanIbadah,
            'daftarHari' => JadwalKegiatanIbadah::DAFTAR_HARI,
        ]);
    }

    public function update(Request $request, JadwalKegiatanIbadah $jadwalKegiatanIbadah)
    {
        $data = $request->validate([
            'jam_scan_mulai' => ['required', 'date_format:H:i'],
            'jam_pelaksanaan' => ['required', 'date_format:H:i'],
            'jam_scan_selesai' => ['required', 'date_format:H:i'],
            'aktif' => ['nullable', 'boolean'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ]);
        $this->pastikanUrutanWaktuBenar($data);
        $jadwalKegiatanIbadah->update([
            ...$data,
            'aktif' => $request->boolean('aktif'),
            'keterangan' => filled($data['keterangan'] ?? null) ? trim($data['keterangan']) : null,
        ]);

        return redirect()->route('jadwal-kegiatan-ibadah.index', [
            'tahun_pelajaran_id' => $jadwalKegiatanIbadah->tahun_pelajaran_id,
            'kegiatan_ibadah_id' => $jadwalKegiatanIbadah->kegiatan_ibadah_id,
        ])->with('berhasil', 'Jadwal '.$jadwalKegiatanIbadah->labelHari().' berhasil diperbarui.');
    }

    public function destroy(JadwalKegiatanIbadah $jadwalKegiatanIbadah)
    {
        $jadwalKegiatanIbadah->update(['aktif' => false]);

        return back()->with('berhasil', 'Jadwal kegiatan ibadah berhasil dinonaktifkan.');
    }

    private function daftarTahunPelajaran()
    {
        return TahunPelajaran::query()->orderByDesc('aktif')->orderByDesc('tanggal_mulai')->get();
    }

    private function ambilTahunPelajaranId(?int $tahunPelajaranId, $tahunPelajaran): ?int
    {
        if ($tahunPelajaranId && $tahunPelajaran->contains('id', $tahunPelajaranId)) {
            return $tahunPelajaranId;
        }

        return $tahunPelajaran->firstWhere('aktif', true)?->id ?? $tahunPelajaran->first()?->id;
    }

    private function pastikanKegiatanAktif(int $kegiatanIbadahId): void
    {
        if (! KegiatanIbadah::query()->whereKey($kegiatanIbadahId)->where('aktif', true)->exists()) {
            throw ValidationException::withMessages([
                'kegiatan_ibadah_id' => 'Kegiatan ibadah yang dipilih sudah tidak aktif.',
            ]);
        }
    }

    private function pastikanUrutanWaktuBenar(array $data): void
    {
        $mulai = $this->menit($data['jam_scan_mulai']);
        $pelaksanaan = $this->menit($data['jam_pelaksanaan']);
        $selesai = $this->menit($data['jam_scan_selesai']);

        if ($mulai > $pelaksanaan || $pelaksanaan > $selesai) {
            throw ValidationException::withMessages([
                'jam_pelaksanaan' => 'Waktu pelaksanaan harus berada di antara mulai dan batas akhir scan.',
            ]);
        }
    }

    private function menit(string $jam): int
    {
        [$jamAngka, $menit] = array_map('intval', explode(':', substr($jam, 0, 5)));

        return ($jamAngka * 60) + $menit;
    }
}
