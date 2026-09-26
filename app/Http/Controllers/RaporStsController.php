<?php

namespace App\Http\Controllers;

use App\Models\KegiatanUjianCbt;
use App\Models\Kelas;
use App\Models\RaporStsKelas;
use App\Services\Nilai\RaporStsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RaporStsController extends Controller
{
    public function index(Request $request, RaporStsService $service)
    {
        abort_unless(RaporStsService::dapatMengakses($request->user()), 403);
        $data = $request->validate(['kegiatan_id' => ['nullable', 'integer'], 'kelas_id' => ['nullable', 'integer']]);
        $daftarKegiatan = KegiatanUjianCbt::whereHas('jenisUjianCbt', fn ($q) => $q->where('kode', 'STS'))
            ->whereIn('tahun_pelajaran_id', $service->kelasDalamCakupan($request->user())->select('tahun_pelajaran_id'))
            ->with('tahunPelajaran')->orderByDesc('tanggal_mulai')->get();
        $kegiatan = isset($data['kegiatan_id']) ? $daftarKegiatan->firstWhere('id', $data['kegiatan_id']) : $daftarKegiatan->first();
        abort_if(isset($data['kegiatan_id']) && ! $kegiatan, 404);
        $daftarKelas = $kegiatan ? $service->kelasDalamCakupan($request->user())
            ->where('tahun_pelajaran_id', $kegiatan->tahun_pelajaran_id)->orderBy('tingkat')->orderBy('nama')->get() : collect();
        $kelas = isset($data['kelas_id']) ? $daftarKelas->firstWhere('id', $data['kelas_id']) : $daftarKelas->first();
        abort_if(isset($data['kelas_id']) && ! $kelas, 404);
        $laporan = $kelas && $kegiatan ? $service->bangun($kegiatan, $kelas) : null;

        return view('rapor-sts.index', compact('daftarKegiatan', 'daftarKelas', 'kegiatan', 'kelas', 'laporan'));
    }

    public function pengaturan(Request $request, KegiatanUjianCbt $kegiatan, Kelas $kelas, RaporStsService $service)
    {
        $service->pastikanCakupan($request->user(), $kegiatan, $kelas);
        $tahun = $kegiatan->tahunPelajaran;
        $data = $request->validate([
            'versi' => ['required', 'integer', 'min:0'],
            'tanggal_awal_presensi' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$tahun->tanggal_mulai->toDateString()],
            'tanggal_akhir_presensi' => ['required', 'date_format:Y-m-d', 'after_or_equal:tanggal_awal_presensi', 'before_or_equal:'.$tahun->tanggal_selesai->toDateString()],
            'tanggal_rapor' => ['required', 'date_format:Y-m-d', 'after_or_equal:tanggal_akhir_presensi'],
        ]);
        DB::transaction(function () use ($kegiatan, $kelas, $request, $data) {
            Kelas::whereKey($kelas->id)->lockForUpdate()->firstOrFail();
            $pengaturan = RaporStsKelas::where('kegiatan_ujian_cbt_id', $kegiatan->id)->where('kelas_id', $kelas->id)->first();
            $this->pastikanVersi($pengaturan, (int) $data['versi']);
            $pengaturan ??= new RaporStsKelas(['kegiatan_ujian_cbt_id' => $kegiatan->id, 'kelas_id' => $kelas->id]);
            $pengaturan->fill([...$data, 'versi' => $data['versi'] + 1, 'diubah_oleh_pengguna_id' => $request->user()->id])->save();
        });

        return $this->kembali($kegiatan, $kelas)->with('berhasil', 'Periode rapor disimpan. Periksa rekap kehadiran sebelum mencetak.');
    }

    public function kehadiran(Request $request, KegiatanUjianCbt $kegiatan, Kelas $kelas, RaporStsService $service)
    {
        $service->pastikanCakupan($request->user(), $kegiatan, $kelas);
        $data = $request->validate([
            'versi' => ['required', 'integer', 'min:1'],
            'siswa' => ['required', 'array', 'min:1', 'max:500'],
            'siswa.*.sakit' => ['required', 'integer', 'min:0', 'max:366'],
            'siswa.*.izin' => ['required', 'integer', 'min:0', 'max:366'],
            'siswa.*.alfa' => ['required', 'integer', 'min:0', 'max:366'],
            'siswa.*.sidik_sumber' => ['required', 'string', 'size:64'],
            'siswa.*.diperiksa' => ['required', 'boolean'],
            'siswa.*.catatan_koreksi' => ['nullable', 'string', 'max:500'],
        ]);
        DB::transaction(function () use ($kegiatan, $kelas, $service, $request, $data) {
            Kelas::whereKey($kelas->id)->lockForUpdate()->firstOrFail();
            $pengaturan = RaporStsKelas::where('kegiatan_ujian_cbt_id', $kegiatan->id)->where('kelas_id', $kelas->id)->firstOrFail();
            $this->pastikanVersi($pengaturan, (int) $data['versi']);
            $laporan = $service->bangun($kegiatan, $kelas, $pengaturan);
            $baris = $laporan['baris']->keyBy('anggota.id');
            $maksimal = (int) $pengaturan->tanggal_awal_presensi->diffInDays($pengaturan->tanggal_akhir_presensi) + 1;
            foreach ($data['siswa'] as $id => $input) {
                $siswa = $baris->get($id);
                abort_unless($siswa, 404);
                if (! hash_equals($siswa['sidik_sumber'], $input['sidik_sumber'])) {
                    throw ValidationException::withMessages(['kehadiran' => 'Presensi sumber berubah. Muat ulang halaman dan periksa kembali sebelum menyimpan.']);
                }
                $angka = array_map('intval', array_intersect_key($input, array_flip(['sakit', 'izin', 'alfa'])));
                if (array_sum($angka) > $maksimal) {
                    throw ValidationException::withMessages(["siswa.$id.sakit" => 'Jumlah sakit, izin, dan alfa tidak boleh melebihi jumlah hari dalam periode.']);
                }
                $berubah = collect($angka)->contains(fn ($jumlah, $jenis) => $jumlah !== $siswa['sumber'][$jenis]);
                $catatan = trim($input['catatan_koreksi'] ?? '');
                if ($berubah && $catatan === '') {
                    throw ValidationException::withMessages(["siswa.$id.catatan_koreksi" => 'Isi alasan koreksi karena jumlah berbeda dari presensi sumber.']);
                }
                $pengaturan->kehadiran()->updateOrCreate(['anggota_kelas_id' => $id], [
                    ...$angka, 'rekap_sumber' => $siswa['sumber'], 'catatan_koreksi' => $catatan ?: null,
                    'diperiksa_pada' => $input['diperiksa'] ? now() : null,
                    'diperiksa_oleh_pengguna_id' => $request->user()->id,
                ]);
            }
            $pengaturan->update(['versi' => $pengaturan->versi + 1, 'diubah_oleh_pengguna_id' => $request->user()->id]);
        });

        return $this->kembali($kegiatan, $kelas)->with('berhasil', 'Pemeriksaan kehadiran rapor disimpan. Presensi harian siswa tidak diubah.');
    }

    public function pengecualian(Request $request, KegiatanUjianCbt $kegiatan, Kelas $kelas, RaporStsService $service)
    {
        $service->pastikanCakupan($request->user(), $kegiatan, $kelas);
        $data = $request->validate([
            'versi' => ['required', 'integer', 'min:1'],
            'anggota_id' => ['required', 'integer'],
            'pengecualian' => ['required', 'array', 'min:1', 'max:100'],
            'pengecualian.*.tidak_mengikuti' => ['required', 'boolean'],
            'pengecualian.*.alasan' => ['nullable', 'string', 'max:500'],
            'pengecualian.*.sidik_kondisi' => ['nullable', 'string', 'size:64'],
        ]);
        DB::transaction(function () use ($request, $kegiatan, $kelas, $service, $data) {
            Kelas::whereKey($kelas->id)->lockForUpdate()->firstOrFail();
            $pengaturan = RaporStsKelas::where('kegiatan_ujian_cbt_id', $kegiatan->id)->where('kelas_id', $kelas->id)->firstOrFail();
            $this->pastikanVersi($pengaturan, (int) $data['versi']);
            $laporan = $service->bangun($kegiatan, $kelas, $pengaturan);
            $siswa = $laporan['baris']->firstWhere('anggota.id', (int) $data['anggota_id']);
            abort_unless($siswa, 404);
            foreach ($data['pengecualian'] as $id => $input) {
                $nilai = $siswa['nilai']->firstWhere('mapel.id', $id);
                abort_unless($nilai, 404);
                if (! $input['tidak_mengikuti']) {
                    if ($nilai['pengecualian']?->aktif) {
                        $nilai['pengecualian']->update(['aktif' => false, 'dibatalkan_pada' => now(), 'dibatalkan_oleh_pengguna_id' => $request->user()->id]);
                    }

                    continue;
                }
                if (! $nilai['dapat_dikecualikan']) {
                    throw ValidationException::withMessages(["pengecualian.$id.tidak_mengikuti" => 'Keterangan hanya untuk siswa yang tercatat sakit, izin, atau alfa, belum mengerjakan ujian, dan tidak memiliki susulan berjalan. Hasil ujian harus sudah final dan jadwal berakhir.']);
                }
                if (! hash_equals($nilai['sidik_kondisi'], $input['sidik_kondisi'] ?? '')) {
                    throw ValidationException::withMessages(["pengecualian.$id.tidak_mengikuti" => 'Data ujian berubah. Muat ulang halaman dan periksa kembali keterangan siswa.']);
                }
                $alasan = trim($input['alasan'] ?? '');
                if ($alasan === '') {
                    throw ValidationException::withMessages(["pengecualian.$id.alasan" => 'Isi alasan siswa tidak mengikuti STS maupun susulan.']);
                }
                $pengaturan->pengecualian()->updateOrCreate(['anggota_kelas_id' => $data['anggota_id'], 'mata_pelajaran_id' => $id], [
                    'peserta_ujian_cbt_id' => $nilai['peserta_id'], 'aktif' => true, 'alasan' => $alasan,
                    'sidik_kondisi' => $nilai['sidik_kondisi'], 'ditetapkan_pada' => now(),
                    'ditetapkan_oleh_pengguna_id' => $request->user()->id,
                    'dibatalkan_pada' => null, 'dibatalkan_oleh_pengguna_id' => null,
                ]);
            }
            $pengaturan->update(['versi' => $pengaturan->versi + 1, 'diubah_oleh_pengguna_id' => $request->user()->id]);
        });

        return $this->kembali($kegiatan, $kelas)->with('berhasil', 'Keterangan tidak mengikuti STS disimpan.')
            ->with('rincian_siswa', $data['anggota_id']);
    }

    public function cetak(Request $request, KegiatanUjianCbt $kegiatan, Kelas $kelas, RaporStsService $service)
    {
        $service->pastikanCakupan($request->user(), $kegiatan, $kelas);
        $data = $request->validate(['anggota_id' => ['nullable', 'integer'], 'pratinjau' => ['nullable', 'boolean']]);
        $laporan = $service->bangun($kegiatan, $kelas);
        if (isset($data['anggota_id'])) {
            $laporan['baris'] = $laporan['baris']->where('anggota.id', (int) $data['anggota_id'])->values();
        }
        abort_if($laporan['baris']->isEmpty(), 404);
        $pratinjau = (bool) ($data['pratinjau'] ?? false);
        if (! $pratinjau && ! $laporan['baris']->every(fn ($siswa) => $siswa['siap'])) {
            return $this->kembali($kegiatan, $kelas)->withErrors(['cetak' => 'Rapor belum siap dicetak. Lengkapi nilai final STS atau keterangan tidak mengikuti STS yang sah, wali kelas, dan pemeriksaan kehadiran. Periode presensi harus sudah berakhir.']);
        }

        return response()->view('rapor-sts.cetak', [...$laporan, 'pratinjau' => $pratinjau])
            ->header('Cache-Control', 'private, no-store');
    }

    private function pastikanVersi(?RaporStsKelas $pengaturan, int $versi): void
    {
        if (($pengaturan?->versi ?? 0) !== $versi) {
            throw ValidationException::withMessages(['versi' => 'Rapor telah diperbarui pada halaman lain. Muat ulang halaman agar perubahan tidak saling menimpa.']);
        }
    }

    private function kembali(KegiatanUjianCbt $kegiatan, Kelas $kelas)
    {
        return redirect()->route('rapor-sts.index', ['kegiatan_id' => $kegiatan->id, 'kelas_id' => $kelas->id]);
    }
}
