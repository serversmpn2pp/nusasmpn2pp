<?php

namespace App\Http\Controllers;

use App\Models\KonfirmasiBerhalanganIbadah;
use App\Models\PeriodeBerhalanganIbadah;
use App\Models\TahunPelajaran;
use App\Services\Ibadah\AksesBerhalanganIbadah;
use App\Services\Ibadah\ProsesKonfirmasiBerhalanganIbadah;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KonfirmasiBerhalanganIbadahController extends Controller
{
    public function index(Request $request, AksesBerhalanganIbadah $akses)
    {
        $tahunPelajaran = $this->tahunPelajaranAktif();
        abort_unless($akses->dapatMengonfirmasi($request->user(), $tahunPelajaran), 403, 'Halaman privat ini hanya dapat dibuka oleh pendamping yang ditugaskan.');

        $dataFilter = $request->validate([
            'kelas_id' => ['nullable', 'integer'],
            'cari' => ['nullable', 'string', 'max:100'],
        ]);
        $daftarKelas = $akses->kelasTercakup($request->user(), $tahunPelajaran);
        $kelasId = filled($dataFilter['kelas_id'] ?? null) ? (int) $dataFilter['kelas_id'] : null;

        if ($kelasId && ! $daftarKelas->contains('id', $kelasId)) {
            abort(403, 'Kelas berada di luar cakupan pendampingan Anda.');
        }

        $dasarPeriode = PeriodeBerhalanganIbadah::query()
            ->where('tahun_pelajaran_id', $tahunPelajaran->id);
        $akses->batasiPeriodeSesuaiCakupan($dasarPeriode, $request->user(), $tahunPelajaran);

        $daftarPeriode = (clone $dasarPeriode)
            ->with(['siswa:id,nama_lengkap,nisn,foto', 'kelas:id,nama'])
            ->withCount('presensiHarian')
            ->where('status', PeriodeBerhalanganIbadah::STATUS_PERLU_KONFIRMASI)
            ->when($kelasId, fn ($query) => $query->where('kelas_id', $kelasId))
            ->when(filled($dataFilter['cari'] ?? null), function ($query) use ($dataFilter) {
                $cari = trim($dataFilter['cari']);
                $query->whereHas('siswa', fn ($query) => $query
                    ->whereRaw('LOWER(nama_lengkap) LIKE ?', ['%'.mb_strtolower($cari).'%'])
                    ->orWhere('nisn', 'like', "%{$cari}%"));
            })
            ->orderBy('perlu_konfirmasi_sejak')
            ->orderBy('id')
            ->paginate(12)
            ->withQueryString();

        $jumlahPerluKonfirmasi = (clone $dasarPeriode)
            ->where('status', PeriodeBerhalanganIbadah::STATUS_PERLU_KONFIRMASI)
            ->count();
        $jumlahDipantau = (clone $dasarPeriode)
            ->where('status', PeriodeBerhalanganIbadah::STATUS_AKTIF)
            ->count();
        $jumlahSelesaiBulanIni = (clone $dasarPeriode)
            ->where('status', PeriodeBerhalanganIbadah::STATUS_SELESAI)
            ->whereBetween('tanggal_selesai', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->count();

        return view('konfirmasi-berhalangan-ibadah.index', compact(
            'tahunPelajaran',
            'daftarKelas',
            'daftarPeriode',
            'jumlahPerluKonfirmasi',
            'jumlahDipantau',
            'jumlahSelesaiBulanIni',
            'kelasId',
        ));
    }

    public function show(
        Request $request,
        PeriodeBerhalanganIbadah $periodeBerhalanganIbadah,
        AksesBerhalanganIbadah $akses,
    ) {
        $tahunPelajaran = $this->tahunPelajaranAktif();
        $this->pastikanDapatMengakses($request, $periodeBerhalanganIbadah, $tahunPelajaran, $akses);
        $periodeBerhalanganIbadah->load([
            'siswa:id,nama_lengkap,nisn,foto',
            'kelas:id,nama',
            'presensiHarian' => fn ($query) => $query->latest('tanggal'),
            'riwayatKonfirmasi' => fn ($query) => $query
                ->with('dikonfirmasiOlehPengguna:id,nama')
                ->latest('dikonfirmasi_pada'),
        ]);

        return view('konfirmasi-berhalangan-ibadah.show', [
            'periode' => $periodeBerhalanganIbadah,
            'tahunPelajaran' => $tahunPelajaran,
            'hariKe' => $periodeBerhalanganIbadah->tanggal_mulai->copy()->startOfDay()->diffInDays(now()->startOfDay()) + 1,
            'jedaAwal' => min(3, max(1, (int) $periodeBerhalanganIbadah->batas_hari_konfirmasi)),
        ]);
    }

    public function update(
        Request $request,
        PeriodeBerhalanganIbadah $periodeBerhalanganIbadah,
        AksesBerhalanganIbadah $akses,
        ProsesKonfirmasiBerhalanganIbadah $proses,
    ) {
        $tahunPelajaran = $this->tahunPelajaranAktif();
        $this->pastikanDapatMengakses($request, $periodeBerhalanganIbadah, $tahunPelajaran, $akses);
        $data = $request->validate([
            'hasil' => ['required', Rule::in(array_keys(KonfirmasiBerhalanganIbadah::DAFTAR_HASIL))],
            'jeda_konfirmasi_hari' => [
                'nullable',
                'required_if:hasil,'.KonfirmasiBerhalanganIbadah::HASIL_MASIH_BERHALANGAN,
                'integer',
                'min:1',
                'max:14',
            ],
            'catatan_privat' => ['nullable', 'string', 'max:500'],
        ], [
            'jeda_konfirmasi_hari.required_if' => 'Pilih waktu pengingat berikutnya.',
        ]);

        $proses->proses(
            periode: $periodeBerhalanganIbadah,
            petugas: $request->user(),
            hasil: $data['hasil'],
            jedaKonfirmasiHari: isset($data['jeda_konfirmasi_hari']) ? (int) $data['jeda_konfirmasi_hari'] : null,
            catatanPrivat: $data['catatan_privat'] ?? null,
        );

        $pesan = $data['hasil'] === KonfirmasiBerhalanganIbadah::HASIL_MASIH_BERHALANGAN
            ? 'Konfirmasi privat tersimpan. Periode tetap dipantau sampai pengingat berikutnya.'
            : 'Konfirmasi privat tersimpan dan periode telah ditutup.';

        return redirect()->route('konfirmasi-berhalangan-ibadah.index')->with('berhasil', $pesan);
    }

    private function pastikanDapatMengakses(
        Request $request,
        PeriodeBerhalanganIbadah $periode,
        TahunPelajaran $tahunPelajaran,
        AksesBerhalanganIbadah $akses,
    ): void {
        abort_unless(
            (int) $periode->tahun_pelajaran_id === (int) $tahunPelajaran->id
                && $periode->kelas_id
                && $akses->dapatMengonfirmasiKelas($request->user(), $tahunPelajaran, $periode->kelas_id),
            403,
            'Data berada di luar cakupan pendampingan Anda.'
        );
    }

    private function tahunPelajaranAktif(): TahunPelajaran
    {
        return TahunPelajaran::query()
            ->where('aktif', true)
            ->orderByDesc('tanggal_mulai')
            ->firstOrFail();
    }
}
