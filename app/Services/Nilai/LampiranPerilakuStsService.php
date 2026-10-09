<?php

namespace App\Services\Nilai;

use App\Models\KegiatanUjianCbt;
use App\Models\Kelas;
use App\Models\LampiranPerilakuSts;
use App\Models\LaporanPembinaanSiswa;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\PenugasanGuruBkTingkat;
use App\Models\RaporStsKelas;
use App\Models\SanksiPoinSiswa;
use App\Models\TransaksiPoinSiswa;
use App\Services\Pembinaan\PenugasanGuruBkTingkatService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LampiranPerilakuStsService
{
    public static function petugas(?Pengguna $pengguna): bool
    {
        return (bool) ($pengguna?->aktif && ! $pengguna->akunSiswa() && ! $pengguna->akunOrangTua()
            && ($pengguna->administrator() || $pengguna->memilikiIzin(['poin_siswa.verifikasi_bk', 'poin_siswa.sahkan_wakil'])));
    }

    public function kelasDalamCakupan(Pengguna $pengguna): Builder
    {
        abort_unless(self::petugas($pengguna), 403);
        $query = Kelas::query();
        if ($pengguna->administrator() || $pengguna->memilikiIzin('poin_siswa.sahkan_wakil')) {
            return $query;
        }

        return $query->whereHas('tahunPelajaran.penugasanGuruBkTingkat', fn ($q) => $q
            ->where('pegawai_id', $pengguna->pegawai_id)->where('aktif', true)
            ->whereColumn('penugasan_guru_bk_tingkat.tingkat', 'kelas.tingkat'));
    }

    public function pastikanCakupan(Pengguna $pengguna, KegiatanUjianCbt $kegiatan, Kelas $kelas): void
    {
        abort_unless($this->kelasDalamCakupan($pengguna)->whereKey($kelas->id)->exists(), 403);
        abort_unless($kegiatan->jenisUjianCbt?->kode === 'STS'
            && (int) $kegiatan->tahun_pelajaran_id === (int) $kelas->tahun_pelajaran_id, 404);
    }

    public function konteks(KegiatanUjianCbt $kegiatan, Kelas $kelas): array
    {
        $pengaturan = app(RaporStsService::class)->pengaturan($kegiatan, $kelas);
        $anggota = $kelas->anggotaKelas()->with('siswa')->where('status_keanggotaan', 'aktif')
            ->orderByRaw('nomor_absen IS NULL')->orderBy('nomor_absen')->orderBy('id')->get();
        $siswaIds = $anggota->pluck('siswa_id');
        $awal = $pengaturan->tanggal_awal_presensi->toDateString();
        $akhir = $pengaturan->tanggal_akhir_presensi->copy()->endOfDay();
        // Only established incidents enter the parent-facing review; private counseling is not a source.
        $laporan = LaporanPembinaanSiswa::query()->where('tahun_pelajaran_id', $kegiatan->tahun_pelajaran_id)
            ->whereIn('siswa_id', $siswaIds)->where('status', '!=', 'dibatalkan')
            ->whereBetween('tanggal_kejadian', [$awal, $akhir->toDateString()])
            ->where(fn ($q) => $q->whereIn('status_verifikasi', ['disahkan', 'ditetapkan_pembinaan'])
                ->orWhere(fn ($q) => $q->where('jenis_laporan', 'kejadian')->where('status', 'selesai')->where('status_verifikasi', 'tidak_perlu')))
            ->with(['butirPelanggaranLaporan', 'kategoriPembinaanSiswa',
                'tindakLanjutPembinaanSiswa' => fn ($q) => $q->where('jenis_tindak_lanjut', 'keputusan_akhir')
                    ->whereDate('tanggal_tindak_lanjut', '<=', $akhir)->orderBy('tanggal_tindak_lanjut')->orderBy('id')])
            ->orderBy('tanggal_kejadian')->orderBy('id')->get()->groupBy('siswa_id');
        $sanksi = SanksiPoinSiswa::with('aturanSanksiPoin')->where('tahun_pelajaran_id', $kegiatan->tahun_pelajaran_id)
            ->whereIn('siswa_id', $siswaIds)->where('status', '!=', 'dibatalkan')
            ->whereBetween('terpicu_pada', [$pengaturan->tanggal_awal_presensi->copy()->startOfDay(), $akhir])
            ->orderBy('terpicu_pada')->orderBy('id')->get()->groupBy('siswa_id');
        $transaksi = TransaksiPoinSiswa::where('tahun_pelajaran_id', $kegiatan->tahun_pelajaran_id)
            ->whereIn('siswa_id', $siswaIds)->where('tercatat_pada', '<=', $akhir)->orderBy('id')->get()->groupBy('siswa_id');
        $tersimpan = $pengaturan->exists ? LampiranPerilakuSts::with(['guruBk', 'wakilKesiswaan', 'pemeriksa'])
            ->where('rapor_sts_kelas_id', $pengaturan->id)->get()->keyBy('anggota_kelas_id') : collect();
        $guruBk = $this->guruBk($kegiatan, $kelas, $pengaturan);
        $wakilKesiswaan = Pegawai::where('aktif', true)->with('pengguna.daftarPeran')->orderBy('nama_lengkap')->get()
            ->filter(fn ($p) => $p->pengguna?->aktif && $p->pengguna->memilikiPeran('wakil_pimpinan_kesiswaan'))->values();
        $penandatangan = [$guruBk->map(fn ($p) => [$p->id, $p->nama_lengkap, $p->nip])->all(),
            $wakilKesiswaan->map(fn ($p) => [$p->id, $p->nama_lengkap, $p->nip])->all()];
        $baris = $anggota->map(function ($a) use ($laporan, $sanksi, $transaksi, $tersimpan, $pengaturan, $penandatangan, $awal) {
            $kasus = $laporan->get($a->siswa_id, collect());
            $hukuman = $sanksi->get($a->siswa_id, collect());
            $ledger = $transaksi->get($a->siswa_id, collect());
            $periode = $ledger->filter(fn ($t) => $t->tercatat_pada->toDateString() >= $awal);
            $ringkasan = ['jumlah_kejadian' => $kasus->count(),
                'poin_masuk' => (int) $periode->where('poin', '>', 0)->sum('poin'),
                'poin_dikurangi' => abs((int) $periode->where('poin', '<', 0)->sum('poin')),
                'saldo' => max(0, (int) $ledger->sum('poin'))];
            $sumber = $kasus->map(function ($l) {
                $keputusan = $l->tindakLanjutPembinaanSiswa->last();

                return ['kunci' => 'laporan-'.$l->id, 'tanggal' => $l->tanggal_kejadian->toDateString(),
                    'kejadian' => $this->teks($l->butirPelanggaranLaporan->pluck('nama_pelanggaran')->implode('; ') ?: $l->kategoriPembinaanSiswa?->nama ?: 'Kejadian yang telah ditangani'),
                    'tindakan' => $this->teks($keputusan?->hasil ?: $l->tindakan_awal ?: ($l->total_poin > 0 ? 'Penetapan poin pelanggaran' : 'Teguran / pembinaan')),
                    'poin' => (int) $l->total_poin, 'status' => $l->labelStatus(),
                    'sidik' => [$l->updated_at?->toJSON(), $l->status_verifikasi, $keputusan?->updated_at?->toJSON()]];
            })->merge($hukuman->map(fn ($s) => ['kunci' => 'sanksi-'.$s->id,
                'tanggal' => $s->terpicu_pada->toDateString(), 'kejadian' => $this->teks($s->aturanSanksiPoin?->nama ?: 'Sanksi lanjutan'),
                'tindakan' => 'Tindak lanjut akumulasi poin', 'poin' => null, 'status' => $s->labelStatus(),
                'sidik' => [$s->updated_at?->toJSON(), $s->aturanSanksiPoin?->updated_at?->toJSON()]]))
                ->sortBy(fn ($r) => $r['tanggal'].'-'.$r['kunci'])->values();
            $sidik = hash('sha256', json_encode([$a->id, $a->kelas_id, $a->siswa_id, $a->siswa->nama_lengkap,
                $pengaturan->tanggal_awal_presensi->toDateString(), $pengaturan->tanggal_akhir_presensi->toDateString(),
                $pengaturan->tanggal_rapor->toDateString(), $sumber->all(), $ringkasan,
                $ledger->map(fn ($t) => [$t->id, $t->poin, $t->tercatat_pada->toJSON()])->all(), $penandatangan]));
            $simpan = $tersimpan->get($a->id);
            $siap = $simpan && hash_equals($simpan->sidik_sumber, $sidik);

            return ['anggota' => $a, 'sumber' => $sumber, 'ringkasan' => $ringkasan, 'sidik_sumber' => $sidik,
                'tersimpan' => $simpan, 'siap' => (bool) $siap,
                'baris' => $siap ? collect($simpan->baris) : $sumber->map(fn ($r) => collect($r)->except('sidik')->all())];
        });

        return compact('kegiatan', 'kelas', 'pengaturan', 'guruBk', 'wakilKesiswaan', 'baris');
    }

    public function simpan(Pengguna $pengguna, KegiatanUjianCbt $kegiatan, Kelas $kelas, array $data): void
    {
        $this->simpanKolektif($pengguna, $kegiatan, $kelas, [
            'guru_bk_id' => $data['guru_bk_id'], 'wakil_kesiswaan_id' => $data['wakil_kesiswaan_id'],
            'anggota_ids' => [$data['anggota_id']], 'siswa' => [$data['anggota_id'] => $data],
        ]);
    }

    public function simpanKolektif(Pengguna $pengguna, KegiatanUjianCbt $kegiatan, Kelas $kelas, array $data): void
    {
        $this->pastikanCakupan($pengguna, $kegiatan, $kelas);
        DB::transaction(function () use ($pengguna, $kegiatan, $kelas, $data) {
            Kelas::whereKey($kelas->id)->lockForUpdate()->firstOrFail();
            $konteks = $this->konteks($kegiatan, $kelas);
            $p = $konteks['pengaturan'];
            if (! $p->exists || ! $p->tanggal_akhir_presensi->copy()->endOfDay()->isPast()) {
                throw ValidationException::withMessages(['lampiran' => 'Simpan periode rapor dan tunggu batas periode berakhir sebelum mengesahkan lampiran.']);
            }
            $dipilih = collect($data['anggota_ids'])->map(fn ($id) => (int) $id);
            if ($dipilih->isEmpty() || $dipilih->unique()->count() !== $dipilih->count()
                || $dipilih->sort()->values()->all() !== collect(array_keys($data['siswa']))->sort()->values()->all()) {
                throw ValidationException::withMessages(['anggota_ids' => 'Pilih siswa dan kirim ringkasan lengkap untuk setiap siswa yang dipilih.']);
            }
            foreach (['guru_bk_id' => 'guruBk', 'wakil_kesiswaan_id' => 'wakilKesiswaan'] as $field => $key) {
                if (! $konteks[$key]->contains('id', (int) $data[$field])) {
                    throw ValidationException::withMessages([$field => 'Penandatangan tidak sesuai penugasan aktif pada periode rapor.']);
                }
            }
            if (! $pengguna->administrator() && ! $pengguna->memilikiIzin('poin_siswa.sahkan_wakil')
                && ! $konteks['guruBk']->contains('id', (int) $pengguna->pegawai_id)) {
                abort(403);
            }
            // One class context and one transaction keep collective reviews consistent and atomic.
            $daftar = $konteks['baris']->keyBy('anggota.id');
            foreach ($dipilih as $id) {
                $siswa = $daftar->get($id);
                abort_unless($siswa, 404);
                $edit = $data['siswa'][$id];
                if (! hash_equals($siswa['sidik_sumber'], $edit['sidik_sumber'])
                    || (int) ($siswa['tersimpan']?->versi ?? 0) !== (int) $edit['versi']) {
                    throw ValidationException::withMessages(['lampiran' => 'Sumber atau pemeriksaan lampiran '.$siswa['anggota']->siswa->nama_lengkap.' berubah. Tidak ada perubahan disimpan. Muat ulang dan periksa kembali.']);
                }
                $input = collect($edit['baris'] ?? []);
                if ($input->keys()->sort()->values()->all() !== $siswa['sumber']->pluck('kunci')->sort()->values()->all()) {
                    throw ValidationException::withMessages(['baris' => 'Seluruh kejadian terverifikasi harus diperiksa; baris tidak boleh ditambah atau dihilangkan.']);
                }
                $baris = $siswa['sumber']->map(function ($r) use ($input) {
                    $edit = $input->get($r['kunci']);

                    return collect($r)->except('sidik')->merge(['kejadian' => trim($edit['kejadian']), 'tindakan' => trim($edit['tindakan'])])->all();
                });
                LampiranPerilakuSts::updateOrCreate(['rapor_sts_kelas_id' => $p->id, 'anggota_kelas_id' => $id], [
                    'guru_bk_id' => $data['guru_bk_id'], 'wakil_kesiswaan_id' => $data['wakil_kesiswaan_id'],
                    'baris' => $baris->all(), 'ringkasan' => $siswa['ringkasan'], 'catatan' => trim($edit['catatan'] ?? '') ?: null,
                    'sidik_sumber' => $siswa['sidik_sumber'], 'versi' => $edit['versi'] + 1,
                    'diperiksa_pada' => now(), 'diperiksa_oleh_pengguna_id' => $pengguna->id,
                ]);
            }
        });
    }

    private function guruBk(KegiatanUjianCbt $kegiatan, Kelas $kelas, RaporStsKelas $p): Collection
    {
        return PenugasanGuruBkTingkat::where('tahun_pelajaran_id', $kegiatan->tahun_pelajaran_id)
            ->where('tingkat', $kelas->tingkat)->where('aktif', true)
            ->whereDate('tanggal_mulai', '<=', $p->tanggal_rapor)
            ->where(fn ($q) => $q->whereNull('tanggal_selesai')->orWhereDate('tanggal_selesai', '>=', $p->tanggal_rapor))
            ->with('pegawai.pengguna.daftarPeran')->get()->pluck('pegawai')
            ->filter(fn ($p) => $p?->aktif && $p->pengguna?->aktif
                && app(PenugasanGuruBkTingkatService::class)->petugasBk($p->pengguna))
            ->unique('id')->sortBy('nama_lengkap')->values();
    }

    private function teks(string $teks): string
    {
        return Str::limit(Str::squish(strip_tags($teks)), 200, '');
    }

    public function halaman(Collection $baris, ?string $catatan): Collection
    {
        $halaman = collect();
        $isi = collect();
        $beban = 0;
        foreach ($baris as $r) {
            $tinggi = 2 + max((int) ceil(mb_strlen($r['kejadian']) / 20),
                (int) ceil(mb_strlen($r['tindakan']) / 20));
            if ($isi->isNotEmpty() && $beban + $tinggi > 30) {
                $halaman->push($isi);
                $isi = collect();
                $beban = 0;
            }
            $isi->push($r);
            $beban += $tinggi;
        }
        $halaman->push($isi);
        if ($catatan && $beban + (int) ceil(mb_strlen($catatan) / 65) + 3 > 30) {
            $halaman->push(collect());
        }

        return $halaman;
    }
}
