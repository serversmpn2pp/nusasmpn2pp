<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\KegiatanUjianCbt;
use App\Models\Kelas;
use App\Services\Nilai\LampiranPerilakuStsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LampiranPerilakuStsController extends Controller
{
    public function index(Request $request, LampiranPerilakuStsService $service): JsonResponse
    {
        $filter = $request->validate([
            'kegiatan_id' => ['nullable', 'integer', 'min:1'],
            'kelas_id' => ['nullable', 'integer', 'min:1'],
        ]);
        $pengguna = $request->user();
        $kegiatanList = KegiatanUjianCbt::whereHas('jenisUjianCbt', fn ($q) => $q->where('kode', 'STS'))
            ->whereIn('tahun_pelajaran_id', $service->kelasDalamCakupan($pengguna)->select('tahun_pelajaran_id'))
            ->with('tahunPelajaran')->orderByDesc('tanggal_mulai')->get();
        $kegiatan = isset($filter['kegiatan_id']) ? $kegiatanList->firstWhere('id', $filter['kegiatan_id']) : $kegiatanList->first();
        abort_if(isset($filter['kegiatan_id']) && ! $kegiatan, 404);
        $kelasList = $kegiatan ? $service->kelasDalamCakupan($pengguna)
            ->where('tahun_pelajaran_id', $kegiatan->tahun_pelajaran_id)->orderBy('tingkat')->orderBy('nama')->get() : collect();
        $kelas = isset($filter['kelas_id']) ? $kelasList->firstWhere('id', $filter['kelas_id']) : $kelasList->first();
        abort_if(isset($filter['kelas_id']) && ! $kelas, 404);
        $data = [
            'filter' => ['kegiatan_id' => $kegiatan?->id, 'kelas_id' => $kelas?->id],
            'opsi' => [
                'kegiatan' => $kegiatanList->map(fn ($k) => ['id' => $k->id, 'label' => $k->nama.' · '.$k->tahunPelajaran?->nama])->values(),
                'kelas' => $kelasList->map(fn ($k) => ['id' => $k->id, 'label' => $k->nama])->values(),
                'isi' => collect(LampiranPerilakuStsService::PILIHAN_ISI)->map(fn ($label, $kode) => compact('kode', 'label'))->values(),
            ],
            'konteks' => null,
        ];
        if ($kelas && $kegiatan) {
            $k = $service->konteks($kegiatan, $kelas);
            $p = $k['pengaturan'];
            $petugas = $pengguna->administrator() || $pengguna->memilikiIzin('poin_siswa.sahkan_wakil')
                || $k['guruBk']->contains('id', (int) $pengguna->pegawai_id);
            $pesan = [];
            if (! $p->exists) {
                $pesan[] = 'Periode rapor belum disimpan oleh wali kelas.';
            }
            if (! $p->tanggal_akhir_presensi->copy()->endOfDay()->isPast()) {
                $pesan[] = 'Pemeriksaan dapat disahkan setelah batas periode laporan berakhir.';
            }
            if ($k['guruBk']->isEmpty() || $k['wakilKesiswaan']->isEmpty()) {
                $pesan[] = 'Penandatangan Guru BK atau Wakil Kesiswaan yang sesuai belum tersedia.';
            }
            if (! $petugas) {
                $pesan[] = 'Anda tidak ditugaskan sebagai petugas pada tanggal rapor ini.';
            }
            $data['konteks'] = [
                'awal' => $p->tanggal_awal_presensi->toDateString(), 'akhir' => $p->tanggal_akhir_presensi->toDateString(),
                'tanggal_rapor' => $p->tanggal_rapor->toDateString(), 'versi_rapor' => (int) $p->versi,
                'versi_isi_perilaku' => (int) $p->versi_isi_perilaku, 'isi' => $k['isiLampiran'],
                'pesan_kosong' => $k['pesanKosong'], 'pesan' => $pesan,
                'dapat_simpan_isi' => $petugas && $p->exists,
                'dapat_periksa' => $petugas && $p->exists && $p->tanggal_akhir_presensi->copy()->endOfDay()->isPast()
                    && $k['guruBk']->isNotEmpty() && $k['wakilKesiswaan']->isNotEmpty(),
                'guru_bk' => $k['guruBk']->map(fn ($p) => ['id' => $p->id, 'label' => $p->nama_lengkap])->values(),
                'wakil_kesiswaan' => $k['wakilKesiswaan']->map(fn ($p) => ['id' => $p->id, 'label' => $p->nama_lengkap])->values(),
                // Allowlist saja: bukan serialisasi model laporan, siswa, atau kronologi/catatan rahasia.
                'siswa' => $k['baris']->map(fn ($r) => [
                    'id' => $r['anggota']->id, 'nama' => $r['anggota']->siswa->nama_lengkap,
                    'nomor_absen' => $r['anggota']->nomor_absen, 'siap' => $r['siap'],
                    'versi' => (int) ($r['tersimpan']?->versi ?? 0), 'sidik_sumber' => $r['sidik_sumber'],
                    'catatan' => $r['siap'] ? $r['tersimpan']?->catatan : null,
                    'guru_bk_id' => $r['siap'] ? $r['tersimpan']?->guru_bk_id : null,
                    'wakil_kesiswaan_id' => $r['siap'] ? $r['tersimpan']?->wakil_kesiswaan_id : null,
                    'ringkasan' => $r['ringkasan'],
                    'baris' => $r['baris']->map(fn ($b) => collect($b)->only(['kunci', 'tanggal', 'kejadian', 'tindakan', 'poin', 'status'])->all())->values(),
                ])->values(),
            ];
        }

        return $this->tanpaCache($data);
    }

    public function isi(Request $request, KegiatanUjianCbt $kegiatan, Kelas $kelas, LampiranPerilakuStsService $service): JsonResponse
    {
        $service->pastikanCakupan($request->user(), $kegiatan, $kelas);
        $data = $request->validate([
            'isi_lampiran_perilaku' => ['required', Rule::in(array_keys(LampiranPerilakuStsService::PILIHAN_ISI))],
            'versi_isi_perilaku' => ['required', 'integer', 'min:0'], 'versi_rapor' => ['required', 'integer', 'min:0'],
        ]);
        $service->simpanIsi($request->user(), $kegiatan, $kelas, $data);

        return $this->tanpaCache(['pesan' => 'Pilihan isi lampiran disimpan. Muat ulang dan periksa kembali lampiran.']);
    }

    public function pemeriksaan(Request $request, KegiatanUjianCbt $kegiatan, Kelas $kelas, LampiranPerilakuStsService $service): JsonResponse
    {
        $service->pastikanCakupan($request->user(), $kegiatan, $kelas);
        $data = $request->validate([
            'anggota_ids' => ['required', 'array', 'min:1', 'max:500'],
            'anggota_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'guru_bk_id' => ['required', 'integer'], 'wakil_kesiswaan_id' => ['required', 'integer'],
            'diperiksa' => ['required', 'accepted'], 'siswa' => ['required', 'array', 'min:1', 'max:500'],
            'siswa.*' => ['required', 'array'], 'siswa.*.versi' => ['required', 'integer', 'min:0'],
            'siswa.*.sidik_sumber' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'],
            'siswa.*.baris' => ['nullable', 'array', 'max:1000'],
            'siswa.*.baris.*.kejadian' => ['required', 'string', 'max:200', 'regex:/\S/'],
            'siswa.*.baris.*.tindakan' => ['required', 'string', 'max:200', 'regex:/\S/'],
            'siswa.*.catatan' => ['nullable', 'string', 'max:600'],
        ]);
        $service->simpanKolektif($request->user(), $kegiatan, $kelas, $data);

        return $this->tanpaCache(['pesan' => count($data['anggota_ids']).' lampiran telah diperiksa dan disimpan.']);
    }

    private function tanpaCache(array $data): JsonResponse
    {
        return response()->json(['data' => $data])->header('Cache-Control', 'private, no-store');
    }
}
