<?php

namespace App\Http\Controllers;

use App\Models\KelompokTahfidz;
use App\Models\KelulusanTahfidz;
use App\Models\Siswa;
use App\Models\WisudaTahfidz;
use App\Services\Tahfidz\AksesTahfidz;
use App\Services\Tahfidz\CapaianTahfidzService;
use App\Services\Tahfidz\PublikasiTahfidzService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WisudaTahfidzController extends Controller
{
    public function __construct(private readonly AksesTahfidz $akses, private readonly CapaianTahfidzService $capaian) {}

    public function index(Request $request)
    {
        abort_unless($this->akses->kelola($request->user()), 403);
        $acaraId = $request->validate(['acara' => ['nullable', 'integer', 'min:1']])['acara'] ?? null;
        $acara = $acaraId ? WisudaTahfidz::findOrFail($acaraId) : null;
        $acaraList = WisudaTahfidz::withCount('kelulusan')->latest('tanggal')->latest('id')->get();
        $kelulusan = KelulusanTahfidz::with(['siswa:id,nama_lengkap', 'wisuda'])->latest('diverifikasi_pada')->latest('id')->paginate(20)->withQueryString();

        return view('tahfidz.wisuda', compact('acara', 'acaraList', 'kelulusan'));
    }

    public function simpanAcara(Request $request, ?int $wisuda = null)
    {
        abort_unless($this->akses->kelola($request->user()), 403);
        $data = $request->validate(['nama' => ['required', 'string', 'max:150'], 'tanggal' => ['required', 'date_format:Y-m-d'],
            'lokasi' => ['required', 'string', 'max:120'], 'kepala_sekolah' => ['required', 'string', 'max:150']]);
        DB::transaction(function () use ($data, $wisuda) {
            if ($wisuda === null) {
                WisudaTahfidz::create($data);
            } else {
                $acara = WisudaTahfidz::lockForUpdate()->findOrFail($wisuda);
                if (KelulusanTahfidz::where('wisuda_tahfidz_id', $acara->id)->exists()) {
                    throw ValidationException::withMessages(['acara' => 'Data acara tidak dapat diubah setelah sertifikat diterbitkan.']);
                }
                $acara->update($data);
            }
        });

        return redirect()->route('tahfidz.wisuda.index')->with('berhasil', 'Acara wisuda disimpan.');
    }

    public function verifikasi(Request $request, int $kelompok, int $anggota)
    {
        [$kelompok, $anggota] = $this->akses->pesertaDokumen($request->user(), $kelompok, $anggota);
        $pengguna = $request->user();
        DB::transaction(function () use ($kelompok, $anggota, $pengguna) {
            $kelompok = KelompokTahfidz::lockForUpdate()->findOrFail($kelompok->id);
            $anggota = $kelompok->anggotaAktif()->findOrFail($anggota->id);
            abort_unless($this->akses->verifikasiWisuda($pengguna, $kelompok, $anggota), 403);
            Siswa::lockForUpdate()->findOrFail($anggota->siswa_id);
            if ($this->capaian->jumlahSurah($anggota->siswa_id) !== 37) {
                throw ValidationException::withMessages(['kelulusan' => 'Kelulusan hanya bisa diverifikasi setelah semua 37 surah Juz 30 disetor.']);
            }
            KelulusanTahfidz::firstOrCreate(['siswa_id' => $anggota->siswa_id], [
                'anggota_tahfidz_id' => $anggota->id, 'pembimbing_id' => $pengguna->id,
                'nama_pembimbing' => $pengguna->pegawai->nama_lengkap, 'diverifikasi_pada' => now(),
            ]);
        });

        return redirect()->route('tahfidz.rekap.siswa', [$kelompok, $anggota])->with('berhasil', 'Kelulusan Tahfidz sudah diverifikasi.');
    }

    public function terbitkan(Request $request, int $kelulusan)
    {
        abort_unless($this->akses->kelola($request->user()), 403);
        $data = $request->validate(['wisuda_tahfidz_id' => ['required', 'integer', 'exists:wisuda_tahfidz,id']]);
        DB::transaction(function () use ($request, $kelulusan, $data) {
            $acara = WisudaTahfidz::lockForUpdate()->findOrFail($data['wisuda_tahfidz_id']);
            $lulus = KelulusanTahfidz::lockForUpdate()->findOrFail($kelulusan);
            if ($lulus->diterbitkan_pada) {
                if ((int) $lulus->wisuda_tahfidz_id !== (int) $acara->id) {
                    throw ValidationException::withMessages(['sertifikat' => 'Sertifikat sudah terbit untuk acara lain.']);
                }

                return;
            }
            if ($this->capaian->jumlahSurah($lulus->siswa_id) !== 37) {
                throw ValidationException::withMessages(['sertifikat' => 'Data setoran 37 surah tidak lengkap.']);
            }
            $lulus->update(['wisuda_tahfidz_id' => $acara->id, 'nomor_sertifikat' => sprintf('THF/%d/%05d', $acara->tanggal->year, $lulus->id),
                'nama_penerima' => $lulus->siswa->nama_lengkap, 'penerbit_id' => $request->user()->id, 'diterbitkan_pada' => now()]);
        });

        return redirect()->route('tahfidz.wisuda.index')->with('berhasil', 'Sertifikat Tahfidz sudah diterbitkan.');
    }

    public function cetak(Request $request, int $kelulusan)
    {
        abort_unless($this->akses->kelola($request->user()), 403);

        return $this->sertifikat(KelulusanTahfidz::findOrFail($kelulusan), route('tahfidz.wisuda.index'));
    }

    public function publikasi(Request $request, int $kelulusan, PublikasiTahfidzService $publikasi)
    {
        abort_unless($this->akses->kelola($request->user()), 403);
        $data = $request->validate(['bagikan' => ['required', 'boolean']]);
        $publikasi->sertifikat($request->user(), $kelulusan, (bool) $data['bagikan']);

        return redirect()->route('tahfidz.wisuda.index')->with('berhasil',
            $data['bagikan'] ? 'Sertifikat dibagikan ke akun orang tua.' : 'Publikasi sertifikat ditarik.');
    }

    public function cetakSiswa(Request $request, int $kelompok, int $anggota)
    {
        [$kelompok, $anggota] = $this->akses->pesertaDokumen($request->user(), $kelompok, $anggota);
        abort_unless($this->akses->lihatWisuda($request->user(), $kelompok, $anggota), 403);
        $lulus = KelulusanTahfidz::where('siswa_id', $anggota->siswa_id)->firstOrFail();

        return $this->sertifikat($lulus, route('tahfidz.rekap.siswa', [$kelompok, $anggota]));
    }

    private function sertifikat(KelulusanTahfidz $lulus, string $kembali)
    {
        abort_unless($lulus->diterbitkan_pada && $lulus->wisuda, 404);

        return response()->view('tahfidz.sertifikat-cetak', compact('lulus', 'kembali'))->header('Cache-Control', 'private, no-store');
    }
}
