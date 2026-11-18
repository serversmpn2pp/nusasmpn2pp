<?php

namespace App\Services\Nilai;

use App\Models\AnggotaKelas;
use App\Models\KomponenNilai;
use App\Models\NilaiSiswa;
use App\Models\Pengguna;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class InputNilaiKolektifService
{
    public const MAKS_KOMPONEN = 50;

    public function __construct(
        private readonly InputNilaiService $inputNilai,
        private readonly PublikasiNilaiService $publikasi,
    ) {}

    public function alasanTerkunci(KomponenNilai $komponen): ?string
    {
        return match (true) {
            $komponen->kelas_ujian_cbt_exists => 'Dikelola melalui CBT',
            $komponen->sts_manual_difinalisasi_pada !== null => 'STS sudah final',
            default => null,
        };
    }

    public function simpan(Pengguna $pengguna, int $penugasanId, string $semester, array $perubahan): array
    {
        return DB::transaction(function () use ($pengguna, $penugasanId, $semester, $perubahan) {
            $penugasan = $this->inputNilai->queryGuruMataPelajaranDalamCakupan($pengguna)
                ->where('aktif', true)->with('mataPelajaran')->lockForUpdate()->findOrFail($penugasanId);
            $komponenIds = collect($perubahan)->pluck('komponen_nilai_id')->map(fn ($id) => (int) $id)->unique()->values();
            if ($komponenIds->count() > self::MAKS_KOMPONEN) {
                throw ValidationException::withMessages(['perubahan' => 'Terlalu banyak komponen dalam satu penyimpanan.']);
            }

            $komponen = $penugasan->komponenNilai()->where('aktif', true)->where('semester', $semester)
                ->whereKey($komponenIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($komponen->count() !== $komponenIds->count()) {
                throw ValidationException::withMessages(['perubahan' => 'Ada komponen yang tidak sesuai dengan penugasan atau semester ini.']);
            }
            $komponen->loadExists('kelasUjianCbt');

            $siswaIds = collect($perubahan)->pluck('siswa_id')->map(fn ($id) => (int) $id)->unique();
            $siswaAktif = AnggotaKelas::query()
                ->where('kelas_id', $penugasan->kelas_id)
                ->where('tahun_pelajaran_id', $penugasan->tahun_pelajaran_id)
                ->where('status_keanggotaan', 'aktif')
                ->whereIntegerInRaw('siswa_id', $siswaIds)->pluck('siswa_id');
            if ($siswaIds->diff($siswaAktif)->isNotEmpty()) {
                throw ValidationException::withMessages(['perubahan' => 'Ada siswa yang tidak terdaftar aktif pada kelas ini.']);
            }

            $predikat = $penugasan->mataPelajaran->menggunakanPredikat();
            $kunciNilai = $predikat ? 'predikat' : 'nilai';
            $masukan = array_column($perubahan, 'nilai');
            if (! $predikat) {
                $masukan = $this->inputNilai->normalisasiNilaiMasukan($masukan);
            }
            $pesan = collect($this->inputNilai->pesanValidasi())->mapWithKeys(
                fn ($pesan, $key) => [str_replace($kunciNilai.'.*.', 'sel.*.', $key) => $pesan]
            )->all();
            Validator::make(['sel' => $masukan], ['sel.*' => $this->inputNilai->aturanIsiNilai($predikat)], $pesan)->validate();

            $nilaiLama = NilaiSiswa::query()->whereIntegerInRaw('komponen_nilai_id', $komponenIds)
                ->whereIntegerInRaw('siswa_id', $siswaIds)->lockForUpdate()->get()
                ->groupBy('komponen_nilai_id')->map(fn ($nilai) => $nilai->keyBy('siswa_id'));
            $sudahDilihat = [];
            $simpan = [];
            $hapus = [];
            foreach ($perubahan as $index => $sel) {
                $komponenId = (int) $sel['komponen_nilai_id'];
                $siswaId = (int) $sel['siswa_id'];
                $lokasi = 'sel.'.$index;
                if (isset($sudahDilihat[$komponenId][$siswaId])) {
                    throw ValidationException::withMessages([$lokasi => 'Sel nilai yang sama dikirim lebih dari sekali.']);
                }
                $sudahDilihat[$komponenId][$siswaId] = true;

                if ($alasan = $this->alasanTerkunci($komponen->get($komponenId))) {
                    throw ValidationException::withMessages([$lokasi => $alasan.'. Nilai tidak diubah.']);
                }
                $tersimpan = $nilaiLama->get($komponenId)?->get($siswaId);
                $asal = $tersimpan?->{$kunciNilai};
                if ($asal !== $sel['nilai_awal']) {
                    throw ValidationException::withMessages([$lokasi => 'Nilai ini sudah berubah. Muat ulang untuk memeriksa sebelum menyimpan.']);
                }

                $nilai = blank($masukan[$index]) ? null : ($predikat ? $masukan[$index] : number_format((float) $masukan[$index], 2, '.', ''));
                if ($nilai === $asal) {
                    continue;
                }
                if ($nilai === null && blank($tersimpan?->catatan)) {
                    $hapus[] = $tersimpan->id;
                    continue;
                }
                $simpan[] = [
                    'komponen_nilai_id' => $komponenId,
                    'siswa_id' => $siswaId,
                    'nilai' => $predikat ? null : $nilai,
                    'predikat' => $predikat ? $nilai : null,
                ];
            }

            if ($hapus !== []) {
                NilaiSiswa::query()->whereIntegerInRaw('id', $hapus)->delete();
            }
            foreach (array_chunk($simpan, 150) as $batch) {
                NilaiSiswa::query()->upsert($batch, ['komponen_nilai_id', 'siswa_id'], ['nilai', 'predikat']);
            }

            $jumlah = count($simpan) + count($hapus);

            return [
                'jumlah' => $jumlah,
                'kembali_draf' => $jumlah > 0 && $this->publikasi->tandaiDraf($penugasanId, $semester),
            ];
        }, 3);
    }
}
