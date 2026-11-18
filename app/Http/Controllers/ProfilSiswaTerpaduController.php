<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Services\ProfilSiswa\AksesProfilSiswaTerpadu;
use App\Services\ProfilSiswa\DataProfilSiswaTerpadu;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class ProfilSiswaTerpaduController extends Controller
{
    private const IDENTITAS = ['siswa.id', 'nama_lengkap', 'nis', 'nisn', 'aktif', 'foto'];

    public function __construct(
        private readonly AksesProfilSiswaTerpadu $akses,
        private readonly DataProfilSiswaTerpadu $data,
    ) {}

    public function index(Request $request): Response
    {
        [$lingkup, $daftarTahun, $tahun, $filter] = $this->periode($request);
        $siswa = null;
        $daftarKelas = collect();

        if ($tahun) {
            $user = $request->user();
            $anggota = $this->akses->anggotaKelas($user, $lingkup, $tahun, 'identitas');
            $daftarKelas = Kelas::query()->where('tahun_pelajaran_id', $tahun->id)
                ->when(! $this->akses->semuaIdentitas($lingkup),
                    fn (Builder $q) => $q->whereIn('id', (clone $anggota)->select('kelas_id')))
                ->orderBy('tingkat')->orderBy('nama')->get(['id', 'nama']);
            $kelas = isset($filter['kelas']) ? (int) $filter['kelas'] : null;
            abort_if($kelas && ! $daftarKelas->contains('id', $kelas), 404);

            $cari = trim($filter['q'] ?? '');
            $siswa = $this->akses->siswa($user, $lingkup, $tahun, $kelas)
                ->select(self::IDENTITAS)
                ->with(['anggotaKelas' => fn ($q) => $q
                    ->whereIn('anggota_kelas.id', (clone $anggota)->select('anggota_kelas.id'))
                    ->when($kelas, fn ($a) => $a->where('kelas_id', $kelas))
                    ->with('kelas:id,nama')->orderByDesc('tanggal_masuk')->orderByDesc('id')])
                ->when($cari !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q
                    ->whereLike('nama_lengkap', '%'.$cari.'%')
                    ->orWhere('nis', $cari)->orWhere('nisn', $cari)))
                ->orderBy('nama_lengkap')->orderBy('id')->paginate(20)->withQueryString();
        }

        return $this->halaman('index', compact('siswa', 'tahun', 'daftarTahun', 'daftarKelas', 'filter'));
    }

    public function show(Request $request, string $siswa): Response
    {
        [$lingkup, $daftarTahun, $tahun, $filter] = $this->periode($request);
        abort_unless($tahun, 404);

        $user = $request->user();
        $siswa = $this->akses->siswa($user, $lingkup, $tahun)->select(self::IDENTITAS)->findOrFail((int) $siswa);
        $anggota = $this->akses->anggotaKelas($user, $lingkup, $tahun, 'identitas')
            ->where('siswa_id', $siswa->id)->with('kelas:id,nama,tingkat')
            ->orderByDesc('tanggal_masuk')->orderByDesc('id')->get();
        $cakupan = $this->data->cakupan($user, $lingkup, $siswa, $tahun, $anggota->isNotEmpty());
        $tabs = $this->data->tabs($cakupan);
        $tab = $filter['tab'] ?? 'ringkasan';
        abort_unless(isset($tabs[$tab]), 403);

        $tahunPenempatan = $siswa->anggotaKelas()->pluck('tahun_pelajaran_id');
        $daftarTahun = $daftarTahun->filter(fn (TahunPelajaran $pilihan) => $pilihan->is($tahun)
            || ($tahunPenempatan->contains($pilihan->id)
                && $this->akses->siswa($user, $lingkup, $pilihan)->whereKey($siswa->id)->exists()));
        $semester = $filter['semester'] ?? (now()->month >= 7 ? 'ganjil' : 'genap');
        $kosongSaja = $request->boolean('kosong');
        $isi = $this->data->isi($tab, $siswa, $tahun, $semester, $cakupan, $kosongSaja);

        return $this->halaman('show', compact(
            'siswa', 'anggota', 'tahun', 'daftarTahun', 'semester', 'tabs', 'tab', 'isi', 'kosongSaja'
        ));
    }

    private function periode(Request $request): array
    {
        $user = $request->user();
        $lingkup = $this->akses->lingkup($user);
        abort_unless(in_array(true, $lingkup, true), 403);
        $filter = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'tahun' => ['nullable', 'integer', 'min:1'],
            'kelas' => ['nullable', 'integer', 'min:1'],
            'tab' => ['nullable', 'string', 'max:30'],
            'semester' => ['nullable', Rule::in(['ganjil', 'genap'])],
        ]);
        $daftar = $this->akses->tahun($user, $lingkup)->orderByDesc('aktif')->orderByDesc('tanggal_mulai')->get();
        $tahun = isset($filter['tahun']) ? $daftar->firstWhere('id', (int) $filter['tahun']) : $daftar->first();
        abort_if(isset($filter['tahun']) && ! $tahun instanceof TahunPelajaran, 404);

        return [$lingkup, $daftar, $tahun, $filter];
    }

    private function halaman(string $view, array $data): Response
    {
        return response()->view('profil-siswa-terpadu.'.$view, $data, 200, [
            'Cache-Control' => 'private, no-store',
            'Referrer-Policy' => 'same-origin',
        ]);
    }
}
