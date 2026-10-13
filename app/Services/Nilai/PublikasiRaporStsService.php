<?php

namespace App\Services\Nilai;

use App\Models\KegiatanUjianCbt;
use App\Models\Kelas;
use App\Models\Pengguna;
use App\Models\PublikasiRaporSts;
use App\Services\Notifikasi\NotifikasiPenggunaService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PublikasiRaporStsService
{
    public const PENERIMA = ['orang_tua' => 'Orang tua saja', 'orang_tua_dan_siswa' => 'Orang tua dan siswa'];

    public function __construct(private RaporStsService $rapor, private LampiranPerilakuStsService $perilaku,
        private NotifikasiPenggunaService $notifikasi) {}

    public function konteks(Pengguna $pengguna, KegiatanUjianCbt $kegiatan, Kelas $kelas): array
    {
        $this->rapor->pastikanCakupan($pengguna, $kegiatan, $kelas);
        $k = $this->sumber($kegiatan, $kelas);

        return ['kegiatan_id' => $kegiatan->id, 'kelas_id' => $kelas->id, 'kegiatan' => $kegiatan->nama,
            'kelas' => $kelas->nama, 'penerima' => self::PENERIMA,
            'siswa' => $k['baris']->map(function ($b) {
                $p = $b['publikasi'];
                $sidik = $p?->sertakan_perilaku ? $b['sidik_gabungan'] : $b['sidik_rapor'];

                return ['anggota_id' => $b['anggota_id'], 'nama' => $b['salinan']['siswa']['nama'],
                    'nomor_absen' => $b['salinan']['siswa']['nomor_absen'], 'siap' => $b['siap'], 'perilaku_siap' => $b['perilaku_siap'],
                    'sidik_rapor' => $this->sidikPermintaan($b, false), 'sidik_gabungan' => $this->sidikPermintaan($b, true),
                    'versi_publikasi' => $p?->versi ?? 0, 'aktif' => $p?->aktif ?? false,
                    'penerima' => $p?->penerima, 'sertakan_perilaku' => $p?->sertakan_perilaku ?? false,
                    'dipublikasikan_pada' => $p?->dipublikasikan_pada?->toIso8601String(),
                    'berubah' => $p?->aktif && ! hash_equals($p->sidik_sumber, $sidik),
                    'riwayat' => $b['riwayat']->map(fn ($v) => ['versi' => $v->versi, 'aktif' => $v->aktif,
                        'penerima' => $v->penerima, 'sertakan_perilaku' => $v->sertakan_perilaku,
                        'dipublikasikan_pada' => $v->dipublikasikan_pada->toIso8601String(),
                        'dibatalkan_pada' => $v->dibatalkan_pada?->toIso8601String(), 'alasan_pembatalan' => $v->alasan_pembatalan])->values()->all()];
            })->values()->all()];
    }

    public function publikasikan(Pengguna $pengguna, KegiatanUjianCbt $kegiatan, Kelas $kelas, array $input): int
    {
        $this->rapor->pastikanCakupan($pengguna, $kegiatan, $kelas);
        $data = Validator::make($input, [
            'penerima' => ['required', Rule::in(array_keys(self::PENERIMA))],
            'sertakan_perilaku' => ['required', 'boolean'], 'konfirmasi' => ['required', 'accepted'],
            'siswa' => ['required', 'array', 'min:1', 'max:500'],
            'siswa.*.anggota_id' => ['required', 'integer', 'min:1', 'distinct'],
            'siswa.*.versi_publikasi' => ['required', 'integer', 'min:0'],
            'siswa.*.sidik_sumber' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'],
        ])->validate();

        return DB::transaction(function () use ($pengguna, $kegiatan, $kelas, $data) {
            Kelas::whereKey($kelas->id)->lockForUpdate()->firstOrFail();
            $k = $this->sumber($kegiatan, $kelas);
            $dipilih = collect($data['siswa'])->map(function ($input) use ($k, $data) {
                $b = $k['baris']->firstWhere('anggota_id', (int) $input['anggota_id']);
                abort_unless($b, 404);
                $this->pastikanVersi($b['publikasi'], (int) $input['versi_publikasi']);
                $sidik = $data['sertakan_perilaku'] ? $b['sidik_gabungan'] : $b['sidik_rapor'];
                if (! hash_equals($this->sidikPermintaan($b, (bool) $data['sertakan_perilaku']), $input['sidik_sumber'])) {
                    throw ValidationException::withMessages(['publikasi' => 'Data rapor berubah. Muat ulang, periksa, lalu konfirmasi kembali. Tidak ada rapor diterbitkan.']);
                }
                if (! $b['siap'] || ($data['sertakan_perilaku'] && ! $b['perilaku_siap'])) {
                    throw ValidationException::withMessages(['publikasi' => 'Rapor '.$b['salinan']['siswa']['nama'].' belum siap. Periksa periode dan kehadiran; lampiran perlu pemeriksaan BK jika disertakan. Nilai lengkap tidak diwajibkan.']);
                }

                return $b;
            });
            foreach ($dipilih as $b) {
                $lama = $b['publikasi'];
                $salinan = $b['salinan'];
                if (! $data['sertakan_perilaku']) {
                    $salinan['perilaku'] = null;
                }
                $sidik = $data['sertakan_perilaku'] ? $b['sidik_gabungan'] : $b['sidik_rapor'];
                if ($lama?->aktif && $lama->penerima === $data['penerima']
                    && $lama->sertakan_perilaku === (bool) $data['sertakan_perilaku'] && hash_equals($lama->sidik_sumber, $sidik)) {
                    continue;
                }
                if ($lama?->aktif) {
                    $this->tutup($lama, $pengguna, 'Digantikan versi rapor yang lebih baru.');
                }
                $p = PublikasiRaporSts::create(['rapor_sts_kelas_id' => $k['pengaturan']->id,
                    'anggota_kelas_id' => $b['anggota_id'], 'siswa_id' => $salinan['siswa']['id'],
                    'versi' => ($lama?->versi ?? 0) + 1, 'penerima' => $data['penerima'],
                    'sertakan_perilaku' => (bool) $data['sertakan_perilaku'], 'aktif' => true,
                    'salinan' => $salinan, 'sidik_sumber' => $sidik, 'dipublikasikan_pada' => now(),
                    'dipublikasikan_oleh_pengguna_id' => $pengguna->id]);
                $this->kirimNotifikasi($p);
            }

            return $dipilih->count();
        });
    }

    public function batalkan(Pengguna $pengguna, KegiatanUjianCbt $kegiatan, Kelas $kelas, array $input): int
    {
        $this->rapor->pastikanCakupan($pengguna, $kegiatan, $kelas);
        $data = Validator::make($input, ['konfirmasi' => ['required', 'accepted'],
            'alasan' => ['required', 'string', 'min:10', 'max:500', 'regex:/\S/'],
            'siswa' => ['required', 'array', 'min:1', 'max:500'],
            'siswa.*.anggota_id' => ['required', 'integer', 'min:1', 'distinct'],
            'siswa.*.versi_publikasi' => ['required', 'integer', 'min:1']])->validate();

        return DB::transaction(function () use ($pengguna, $kegiatan, $kelas, $data) {
            Kelas::whereKey($kelas->id)->lockForUpdate()->firstOrFail();
            $pengaturan = $this->rapor->pengaturan($kegiatan, $kelas);
            abort_unless($pengaturan->exists, 404);
            $dipilih = collect($data['siswa'])->map(function ($s) use ($pengaturan) {
                $p = PublikasiRaporSts::where('rapor_sts_kelas_id', $pengaturan->id)
                    ->where('anggota_kelas_id', $s['anggota_id'])->orderByDesc('versi')->first();
                abort_unless($p, 404);
                $this->pastikanVersi($p, (int) $s['versi_publikasi']);

                return $p;
            });
            foreach ($dipilih as $p) {
                if ($p->aktif) {
                    $this->tutup($p, $pengguna, trim($data['alasan']));
                }
            }

            return $dipilih->count();
        });
    }

    private function sumber(KegiatanUjianCbt $kegiatan, Kelas $kelas): array
    {
        $laporan = $this->rapor->bangun($kegiatan, $kelas);
        $lampiran = $this->perilaku->konteks($kegiatan, $kelas);
        $perilaku = $lampiran['baris']->keyBy('anggota.id');
        $p = $laporan['pengaturan'];
        $riwayat = $p->exists ? PublikasiRaporSts::where('rapor_sts_kelas_id', $p->id)->orderByDesc('versi')->get()->groupBy('anggota_kelas_id') : collect();
        $baris = $laporan['baris']->map(function ($b) use ($perilaku, $riwayat, $lampiran, $kegiatan, $kelas, $p) {
            $anggota = $b['anggota'];
            $l = $perilaku->get($anggota->id);
            $simpan = $l['tersimpan'] ?? null;
            $salinan = ['format' => 1,
                'kegiatan' => ['id' => $kegiatan->id, 'nama' => $kegiatan->nama, 'semester' => $kegiatan->semester, 'tahun_pelajaran' => $kegiatan->tahunPelajaran->nama],
                'kelas' => ['id' => $kelas->id, 'nama' => $kelas->nama, 'tingkat' => $kelas->tingkat],
                'siswa' => ['id' => $anggota->siswa_id, 'anggota_id' => $anggota->id, 'nama' => $anggota->siswa->nama_lengkap, 'nomor_absen' => $anggota->nomor_absen],
                'periode' => ['awal' => $p->tanggal_awal_presensi->toDateString(), 'akhir' => $p->tanggal_akhir_presensi->toDateString(), 'tanggal_rapor' => $p->tanggal_rapor->toDateString()],
                'wali_kelas' => $this->penandatangan($kelas->waliKelas),
                'nilai' => $b['nilai']->map(fn ($n) => ['mapel_id' => $n['mapel']->id, 'mapel' => $n['mapel']->nama,
                    'nilai' => $n['nilai'], 'keterangan' => $n['keterangan'], 'dikecualikan' => $n['dikecualikan']])->values()->all(),
                ...collect($b)->only(['nilai_tuntas', 'jumlah_bernilai', 'jumlah_pengecualian', 'jumlah', 'rata', 'kehadiran', 'keterlambatan'])->all(),
                'perilaku' => ($l['siap'] ?? false) ? ['label_isi' => $lampiran['labelIsiLampiran'], 'pesan_kosong' => $lampiran['pesanKosong'],
                    'baris' => $l['baris']->map(fn ($r) => collect($r)->only(['kunci', 'tanggal', 'kejadian', 'tindakan', 'poin'])->all())->values()->all(),
                    'ringkasan' => $simpan->ringkasan, 'catatan' => $simpan->catatan,
                    'guru_bk' => $this->penandatangan($simpan->guruBk), 'wakil_kesiswaan' => $this->penandatangan($simpan->wakilKesiswaan)] : null];
            $akademik = [...$salinan, 'perilaku' => null];
            $riwayatSiswa = $riwayat->get($anggota->id, collect());

            return ['anggota_id' => $anggota->id, 'salinan' => $salinan, 'siap' => $b['siap'], 'perilaku_siap' => (bool) ($l['siap'] ?? false),
                'sidik_rapor' => $this->sidik([$akademik, $b['siap'], $b['sidik_sumber']]),
                'sidik_gabungan' => $this->sidik([$salinan, $b['siap'], $b['sidik_sumber'], $l['siap'] ?? false]),
                'publikasi' => $riwayatSiswa->first(), 'riwayat' => $riwayatSiswa];
        });

        return ['pengaturan' => $p, 'baris' => $baris];
    }

    public function daftarUntukPenerima(Pengguna $pengguna): array
    {
        $query = $this->untukPenerima($pengguna);
        $items = $query->orderByDesc('dipublikasikan_pada')->orderByDesc('id')->get()->map(fn ($p) => [
            'id' => $p->id, 'versi' => $p->versi, 'dipublikasikan_pada' => $p->dipublikasikan_pada->toIso8601String(),
            'siswa' => $p->salinan['siswa'], 'kelas' => $p->salinan['kelas'], 'kegiatan' => $p->salinan['kegiatan'],
            'sertakan_perilaku' => $p->sertakan_perilaku])->values()->all();

        return ['items' => $items, 'mode' => $pengguna->akunOrangTua() ? 'orang_tua' : 'siswa'];
    }

    public function untukPenerima(Pengguna $pengguna): Builder
    {
        abort_unless($pengguna->aktif && ($pengguna->akunSiswa() || $pengguna->akunOrangTua()), 403);
        $q = PublikasiRaporSts::where('aktif', true);
        if ($pengguna->akunOrangTua()) {
            $ids = $pengguna->orangTuaWali?->siswa()->pluck('siswa.id') ?? collect();

            return $q->whereIn('siswa_id', $ids);
        }

        return $q->where('penerima', 'orang_tua_dan_siswa')->where('siswa_id', $pengguna->siswa_id ?: 0);
    }

    public function baca(Pengguna $pengguna, int $id): PublikasiRaporSts
    {
        return $this->untukPenerima($pengguna)->whereKey($id)->firstOrFail();
    }

    public function dataCetak(PublikasiRaporSts $publikasi): array
    {
        // Hydrate only the immutable, explicitly selected print fields. No live report queries.
        $s = $publikasi->salinan;
        $kelas = (object) [...$s['kelas'], 'waliKelas' => (object) $s['wali_kelas']];
        $kegiatan = (object) [...$s['kegiatan'], 'tahunPelajaran' => (object) ['nama' => $s['kegiatan']['tahun_pelajaran']]];
        $p = (object) ['tanggal_awal_presensi' => Carbon::parse($s['periode']['awal']),
            'tanggal_akhir_presensi' => Carbon::parse($s['periode']['akhir']), 'tanggal_rapor' => Carbon::parse($s['periode']['tanggal_rapor'])];
        $item = [...collect($s)->only(['nilai_tuntas', 'jumlah_bernilai', 'jumlah_pengecualian', 'jumlah', 'rata', 'kehadiran', 'keterlambatan'])->all(),
            'siap' => true, 'anggota' => (object) ['id' => $s['siswa']['anggota_id'], 'siswa' => (object) ['nama_lengkap' => $s['siswa']['nama']]],
            'nilai' => collect($s['nilai'])->map(fn ($n) => [...$n, 'mapel' => (object) ['nama' => $n['mapel']]])];
        $lampiran = null;
        if ($l = $s['perilaku']) {
            $simpan = (object) [...$l, 'guruBk' => (object) $l['guru_bk'], 'wakilKesiswaan' => (object) $l['wakil_kesiswaan']];
            $lampiran = ['labelIsiLampiran' => $l['label_isi'], 'pesanKosong' => $l['pesan_kosong'],
                'baris' => collect([$s['siswa']['anggota_id'] => ['tersimpan' => $simpan, 'baris' => collect($l['baris'])]])];
        }

        return ['kelas' => $kelas, 'kegiatan' => $kegiatan, 'pengaturan' => $p, 'baris' => collect([$item]),
            'mapel' => collect($s['nilai']), 'pratinjau' => false, 'lampiran' => $lampiran,
            'tautan_kembali' => route('rapor-sts-saya.index'), 'publikasi' => $publikasi];
    }

    private function kirimNotifikasi(PublikasiRaporSts $p): void
    {
        $penerima = $this->notifikasi->penggunaOrangTuaUntukSiswa($p->siswa_id)->filter(fn ($u) => $u->akunOrangTua());
        if ($p->penerima === 'orang_tua_dan_siswa') {
            $penerima = $penerima->concat($this->notifikasi->penggunaUntukSiswa($p->siswa_id)->filter(fn ($u) => $u->akunSiswa()));
        }
        $this->notifikasi->kirimKeBanyak($penerima->unique('id'), 'informasi', 'Rapor STS telah tersedia',
            'Sekolah telah menerbitkan Rapor STS. Silakan buka rapor melalui akun Anda.',
            route('rapor-sts-saya.show', $p->id, false), 'publikasi-rapor-sts:'.$p->id);
    }

    private function penandatangan($pegawai): ?array
    {
        return $pegawai ? ['nama_lengkap' => $pegawai->nama_lengkap, 'nip' => $pegawai->nip] : null;
    }

    private function sidik(array $data): string
    {
        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
    }

    private function sidikPermintaan(array $b, bool $perilaku): string
    {
        $p = $b['publikasi'];

        return $this->sidik([$perilaku ? $b['sidik_gabungan'] : $b['sidik_rapor'],
            $p?->id, $p?->aktif, $p?->dibatalkan_pada?->toJSON()]);
    }

    private function pastikanVersi(?PublikasiRaporSts $p, int $versi): void
    {
        if (($p?->versi ?? 0) !== $versi) {
            throw ValidationException::withMessages(['publikasi' => 'Versi publikasi berubah. Muat ulang halaman sebelum melanjutkan.']);
        }
    }

    private function tutup(PublikasiRaporSts $p, Pengguna $pengguna, string $alasan): void
    {
        $p->update(['aktif' => false, 'dibatalkan_pada' => now(),
            'dibatalkan_oleh_pengguna_id' => $pengguna->id, 'alasan_pembatalan' => $alasan]);
    }
}
