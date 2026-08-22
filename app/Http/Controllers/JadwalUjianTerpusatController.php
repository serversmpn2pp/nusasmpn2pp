<?php

namespace App\Http\Controllers;

use App\Models\JadwalUjianCbt;
use App\Models\KegiatanUjianCbt;
use App\Models\KelompokPesertaKegiatanUjianCbt;
use App\Models\MataPelajaran;
use App\Services\Cbt\SinkronkanPelaksanaanUjianTerpusat;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class JadwalUjianTerpusatController extends Controller
{
    public function store(Request $request, KegiatanUjianCbt $kegiatanUjianCbt)
    {
        $this->pastikanAkses($request, $kegiatanUjianCbt);
        $data = $request->validate([
            'tanggal' => ['required', 'date'],
            'mata_pelajaran_id' => ['required', 'integer', 'exists:mata_pelajaran,id'],
            'tingkat' => ['required', 'array', 'min:1'],
            'tingkat.*' => ['integer', Rule::in([7, 8, 9])],
            'keterangan' => ['nullable', 'string', 'max:500'],
        ]);
        $tingkat = collect($data['tingkat'])->map(fn ($item) => (int) $item)->unique()->values();
        [$tanggal, $mataPelajaran, $kelompok] = $this->dataValid($kegiatanUjianCbt, $data, $tingkat);

        $bentrok = JadwalUjianCbt::query()
            ->where('kegiatan_ujian_cbt_id', $kegiatanUjianCbt->id)
            ->whereDate('tanggal', $tanggal)
            ->whereIn('tingkat', $tingkat)
            ->exists();

        if ($bentrok) {
            throw ValidationException::withMessages(['tingkat' => 'Salah satu tingkat sudah memiliki jadwal pada tanggal tersebut.']);
        }

        DB::transaction(function () use ($kegiatanUjianCbt, $data, $tanggal, $mataPelajaran, $tingkat, $kelompok) {
            $urutanAwal = (int) $kegiatanUjianCbt->jadwalUjianCbt()->max('urutan');
            foreach ($tingkat as $nomor => $item) {
                $kelompokTingkat = $kelompok->get($item);
                $sesi = $kelompokTingkat->sesiKegiatanUjianCbt;
                $jadwal = JadwalUjianCbt::create([
                    'kegiatan_ujian_cbt_id' => $kegiatanUjianCbt->id,
                    'sesi_kegiatan_ujian_cbt_id' => $sesi->id,
                    'ujian_cbt_id' => null,
                    'mata_pelajaran_id' => $mataPelajaran->id,
                    'tanggal' => $tanggal,
                    'waktu_mulai' => $sesi->waktu_mulai,
                    'waktu_selesai' => $sesi->waktu_selesai,
                    'label_sesi' => $sesi->nama,
                    'tingkat' => $item,
                    'urutan' => $urutanAwal + $nomor + 1,
                    'status' => 'draft',
                    'keterangan' => filled($data['keterangan'] ?? null) ? trim($data['keterangan']) : null,
                ]);
                $jadwal->kelas()->sync($kelompokTingkat->kelas->modelKeys());
            }
        });

        return redirect()
            ->route('ujian-terpusat.pelaksanaan.index', $kegiatanUjianCbt)
            ->with('berhasil', "Jadwal {$mataPelajaran->nama} berhasil ditambahkan untuk {$tingkat->count()} tingkat.");
    }

    public function update(
        Request $request,
        KegiatanUjianCbt $kegiatanUjianCbt,
        JadwalUjianCbt $jadwalUjianCbt,
        SinkronkanPelaksanaanUjianTerpusat $sinkronisasi,
    ) {
        $this->pastikanAkses($request, $kegiatanUjianCbt);
        $this->pastikanMilikKegiatan($kegiatanUjianCbt, $jadwalUjianCbt);
        $data = $request->validate([
            'tanggal' => ['required', 'date'],
            'mata_pelajaran_id' => ['required', 'integer', 'exists:mata_pelajaran,id'],
            'keterangan' => ['nullable', 'string', 'max:500'],
        ]);
        $tingkat = collect([(int) $jadwalUjianCbt->tingkat]);
        [$tanggal, $mataPelajaran, $kelompok] = $this->dataValid($kegiatanUjianCbt, $data, $tingkat);
        $kelompokTingkat = $kelompok->first();
        $paket = $jadwalUjianCbt->ujianCbt()->withCount('soalUjianCbt')->first();

        if ($paket?->soal_ujian_cbt_count > 0 && (int) $mataPelajaran->id !== (int) $jadwalUjianCbt->mata_pelajaran_id) {
            throw ValidationException::withMessages([
                'mata_pelajaran_id' => 'Mata pelajaran tidak dapat diganti karena paket sudah berisi soal. Kosongkan soal paket terlebih dahulu.',
            ]);
        }

        $bentrok = JadwalUjianCbt::query()
            ->where('kegiatan_ujian_cbt_id', $kegiatanUjianCbt->id)
            ->whereDate('tanggal', $tanggal)
            ->where('tingkat', $jadwalUjianCbt->tingkat)
            ->where('id', '!=', $jadwalUjianCbt->id)
            ->exists();

        if ($bentrok) {
            throw ValidationException::withMessages(['tanggal' => 'Tingkat ini sudah memiliki jadwal pada tanggal tersebut.']);
        }

        $sesi = $kelompokTingkat->sesiKegiatanUjianCbt;
        $jadwalUjianCbt->update([
            'sesi_kegiatan_ujian_cbt_id' => $sesi->id,
            'mata_pelajaran_id' => $mataPelajaran->id,
            'tanggal' => $tanggal,
            'waktu_mulai' => $sesi->waktu_mulai,
            'waktu_selesai' => $sesi->waktu_selesai,
            'label_sesi' => $sesi->nama,
            'keterangan' => filled($data['keterangan'] ?? null) ? trim($data['keterangan']) : null,
        ]);
        $jadwalUjianCbt->kelas()->sync($kelompokTingkat->kelas->modelKeys());

        if ($paket) {
            $mulai = Carbon::parse($tanggal.' '.$sesi->waktu_mulai);
            $selesai = Carbon::parse($tanggal.' '.$sesi->waktu_selesai);
            $mataPelajaran->load('pengaturanTingkat');
            $paket->update([
                'mata_pelajaran_id' => $mataPelajaran->id,
                'nama' => "{$kegiatanUjianCbt->nama} - {$mataPelajaran->nama} Tingkat {$jadwalUjianCbt->tingkat}",
                'tanggal_mulai' => $mulai,
                'tanggal_selesai' => $selesai,
                'durasi_menit' => max(10, $mulai->diffInMinutes($selesai)),
                'kkm' => $mataPelajaran->pengaturanUntuk((int) $kegiatanUjianCbt->tahun_pelajaran_id, (int) $jadwalUjianCbt->tingkat)?->kkm ?? $mataPelajaran->kkm,
            ]);

            $sinkronisasi->sinkronkanJadwal($jadwalUjianCbt->fresh(), $request->user());
        }

        return redirect()
            ->route('ujian-terpusat.pelaksanaan.index', $kegiatanUjianCbt)
            ->with('berhasil', 'Jadwal ujian berhasil diperbarui.');
    }

    public function destroy(Request $request, KegiatanUjianCbt $kegiatanUjianCbt, JadwalUjianCbt $jadwalUjianCbt)
    {
        $this->pastikanAkses($request, $kegiatanUjianCbt);
        $this->pastikanMilikKegiatan($kegiatanUjianCbt, $jadwalUjianCbt);

        if ($jadwalUjianCbt->ujian_cbt_id || $jadwalUjianCbt->terkunci()) {
            throw ValidationException::withMessages(['jadwal' => 'Jadwal yang sudah terhubung ke paket atau dikunci tidak dapat dihapus.']);
        }

        $jadwalUjianCbt->delete();

        return redirect()
            ->route('ujian-terpusat.pelaksanaan.index', $kegiatanUjianCbt)
            ->with('berhasil', 'Jadwal ujian berhasil dihapus.');
    }

    private function dataValid(KegiatanUjianCbt $kegiatan, array $data, $tingkat): array
    {
        $tanggal = Carbon::parse($data['tanggal'])->startOfDay();
        if ($tanggal->lt($kegiatan->tanggal_mulai->startOfDay()) || $tanggal->gt($kegiatan->tanggal_selesai->startOfDay())) {
            throw ValidationException::withMessages([
                'tanggal' => "Tanggal harus berada dalam periode {$kegiatan->labelPeriode()}.",
            ]);
        }

        $mataPelajaran = MataPelajaran::query()->where('aktif', true)->find($data['mata_pelajaran_id']);
        if (! $mataPelajaran) {
            throw ValidationException::withMessages(['mata_pelajaran_id' => 'Mata pelajaran tidak aktif.']);
        }

        foreach ($tingkat as $item) {
            if (! $mataPelajaran->tersediaUntuk($kegiatan->tahun_pelajaran_id, $item)) {
                throw ValidationException::withMessages([
                    'mata_pelajaran_id' => "{$mataPelajaran->nama} tidak diterapkan untuk tingkat {$item} pada tahun pelajaran ini.",
                ]);
            }
        }

        $kelompok = KelompokPesertaKegiatanUjianCbt::query()
            ->where('kegiatan_ujian_cbt_id', $kegiatan->id)
            ->whereIn('tingkat', $tingkat)
            ->with(['sesiKegiatanUjianCbt', 'kelas'])
            ->get()
            ->keyBy('tingkat');

        if ($kelompok->count() !== $tingkat->count()) {
            throw ValidationException::withMessages(['tingkat' => 'Buat pembagian peserta untuk setiap tingkat yang dipilih terlebih dahulu.']);
        }

        return [$tanggal->toDateString(), $mataPelajaran, $kelompok];
    }

    private function pastikanAkses(Request $request, KegiatanUjianCbt $kegiatan): void
    {
        abort_unless($kegiatan->dapatDiaksesOleh($request->user()), 403);
    }

    private function pastikanMilikKegiatan(KegiatanUjianCbt $kegiatan, JadwalUjianCbt $jadwal): void
    {
        abort_unless((int) $jadwal->kegiatan_ujian_cbt_id === (int) $kegiatan->id, 404);
    }
}
