<?php

namespace App\Services\Humas;

use App\Models\AgendaHumas;
use App\Models\JawabanUmpanBalikHumas;
use App\Models\OrangTuaWali;
use App\Models\Pengguna;
use App\Models\UmpanBalikHumas;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UmpanBalikHumasService
{
    public function kunci(UmpanBalikHumas $f, int $versi): UmpanBalikHumas
    {
        $f = UmpanBalikHumas::lockForUpdate()->findOrFail($f->id);
        if ($f->versi !== $versi) {
            $this->gagal('Formulir atau tindak lanjut telah berubah. Muat ulang sebelum menyimpan.');
        }

        return $f;
    }

    public function pratinjau(UmpanBalikHumas $f): array
    {
        $preview = app(UndanganOrangTuaHumasService::class)->pratinjau(['tahun_pelajaran_id' => $f->tahun_pelajaran_id,
            'cakupan' => $f->cakupan === 'agenda' ? 'seluruh' : $f->cakupan, 'tingkat' => $f->tingkat, 'kelas_ids' => $f->kelas_ids ?? []]);
        if ($f->cakupan === 'agenda') {
            $agenda = AgendaHumas::findOrFail($f->agenda_humas_id);
            if ($agenda->status === 'dibatalkan') {
                $this->gagal('Pertemuan dibatalkan. Pilih agenda lain sebelum membuka formulir.');
            }
            $undangan = $agenda->peserta()->whereNotNull('orang_tua_wali_id')->get()->keyBy('orang_tua_wali_id');
            $preview['undangan'] = $preview['undangan']->filter(function ($data, $id) use ($undangan) {
                $anak = array_column($undangan->get($id)?->anak_undangan ?? [], 'siswa_id');

                return count(array_intersect(array_column($data['anak'], 'siswa_id'), $anak)) > 0;
            });
            $preview['tanpaAkun'] = collect();
        }

        return $preview;
    }

    public function bekukanSasaran(UmpanBalikHumas $f): int
    {
        $preview = $this->pratinjau($f);
        if ($preview['undangan']->isEmpty()) {
            $this->gagal('Tidak ada akun orang tua aktif dan terhubung pada sasaran ini.');
        }
        if ($preview['undangan']->count() > 5000) {
            $this->gagal('Maksimal 5.000 akun sasaran per formulir. Pisahkan formulir berdasarkan tingkat atau kelas.');
        }
        foreach ($preview['undangan'] as $id => $data) {
            $f->sasaran()->create(['orang_tua_wali_id' => $id, 'siswa_ids' => array_values(array_unique(array_column($data['anak'], 'siswa_id')))]);
        }

        return $preview['undangan']->count();
    }

    public function wali(Request $request): OrangTuaWali
    {
        $u = $request->user();
        abort_unless($u->aktif && $u->akunOrangTua(), 403, 'Gunakan akun orang tua/wali yang aktif.');
        $wali = $u->orangTuaWali;
        abort_unless($wali->siswa()->exists(), 403, 'Akun belum terhubung dengan siswa. Hubungi sekolah.');

        return $wali;
    }

    public function sasaran(UmpanBalikHumas $f, OrangTuaWali $wali)
    {
        abort_if($f->status === 'draf', 404);
        $sasaran = $f->sasaran()->where('orang_tua_wali_id', $wali->id)->firstOrFail();
        abort_unless($wali->siswa()->whereIn('siswa.id', $sasaran->siswa_ids)->exists(), 404);
        abort_if($f->status === 'arsip' && ! $sasaran->dikirim_pada, 404);

        return $sasaran;
    }

    public function rekap(UmpanBalikHumas $f): array
    {
        $f->loadMissing('pertanyaan');
        $ids = $f->pertanyaan->pluck('id');
        $kelompok = JawabanUmpanBalikHumas::whereIn('pertanyaan_umpan_balik_humas_id', $ids)->whereNotNull('nilai')
            ->selectRaw('pertanyaan_umpan_balik_humas_id, nilai, COUNT(*) AS jumlah')->groupBy('pertanyaan_umpan_balik_humas_id', 'nilai')->get()->groupBy('pertanyaan_umpan_balik_humas_id');
        $teks = JawabanUmpanBalikHumas::whereIn('pertanyaan_umpan_balik_humas_id', $ids)->whereNotNull('teks')
            ->selectRaw('pertanyaan_umpan_balik_humas_id, COUNT(*) AS jumlah')->groupBy('pertanyaan_umpan_balik_humas_id')->pluck('jumlah', 'pertanyaan_umpan_balik_humas_id');
        $rekap = [];
        foreach ($f->pertanyaan as $p) {
            $angka = ($kelompok->get($p->id) ?? collect())->pluck('jumlah', 'nilai');
            $skala = collect(range(0, 4))->mapWithKeys(fn ($nilai) => [$nilai => (int) ($angka[$nilai] ?? 0)])->all();
            $n = array_sum($skala) - $skala[0];
            $sum = collect($skala)->sum(fn ($jumlah, $nilai) => $jumlah * $nilai);
            $rekap[$p->id] = ['skala' => $skala, 'menilai' => $n, 'rata' => $n ? $sum / $n : null,
                'puas' => $n ? ($skala[3] + $skala[4]) / $n * 100 : null, 'teks' => (int) ($teks[$p->id] ?? 0)];
        }
        $sasaran = $f->sasaran()->count();
        $respons = $f->sasaran()->whereNotNull('dikirim_pada')->count();

        return ['pertanyaan' => $rekap, 'sasaran' => $sasaran, 'respons' => $respons, 'persen' => $sasaran ? $respons / $sasaran * 100 : 0];
    }

    public function catat(UmpanBalikHumas $f, Pengguna $u, string $aksi, ?string $catatan = null, array $tambahan = []): void
    {
        $f->forceFill(['versi' => $f->versi + 1])->save();
        $f->load('pertanyaan');
        $f->riwayat()->create(['aksi' => $aksi, 'versi' => $f->versi, 'catatan' => $catatan, 'pengguna_id' => $u->id, 'created_at' => now(),
            'snapshot' => ['formulir' => $f->only([...UmpanBalikHumas::KOLOM, 'status']), 'pertanyaan' => $f->pertanyaan->map(fn ($p) => $p->only(['urutan', 'jenis', 'teks', 'wajib']))->all(), ...$tambahan]]);
    }

    public function gagal(string $pesan): never
    {
        throw ValidationException::withMessages(['formulir' => $pesan]);
    }
}
