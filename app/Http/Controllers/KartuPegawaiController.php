<?php

namespace App\Http\Controllers;

use App\Models\Pegawai;
use App\Support\QrCodeNisn;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class KartuPegawaiController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'kata_kunci' => ['nullable', 'string', 'max:100'],
            'jenis_pegawai' => ['nullable', 'string', 'max:100'],
            'pegawai_id' => ['nullable', 'integer', 'exists:pegawai,id'],
            'status' => ['nullable', 'in:semua,aktif,nonaktif'],
        ]);

        $kataKunci = trim((string) ($data['kata_kunci'] ?? ''));
        $jenisPegawai = $data['jenis_pegawai'] ?? '';
        $pegawaiId = $data['pegawai_id'] ?? null;
        $status = $data['status'] ?? 'aktif';
        $daftarJenisPegawai = Pegawai::query()
            ->whereNotNull('jenis_pegawai')
            ->where('jenis_pegawai', '!=', '')
            ->select('jenis_pegawai')
            ->distinct()
            ->orderBy('jenis_pegawai')
            ->pluck('jenis_pegawai');
        $daftarPegawai = Pegawai::query()
            ->when($status === 'aktif', fn ($query) => $query->where('aktif', true))
            ->when($status === 'nonaktif', fn ($query) => $query->where('aktif', false))
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'nip']);

        $kartuPegawai = Pegawai::query()
            ->when($status === 'aktif', fn ($query) => $query->where('aktif', true))
            ->when($status === 'nonaktif', fn ($query) => $query->where('aktif', false))
            ->when($jenisPegawai, fn ($query) => $query->where('jenis_pegawai', $jenisPegawai))
            ->when($pegawaiId, fn ($query) => $query->whereKey($pegawaiId))
            ->when($kataKunci !== '', function ($query) use ($kataKunci) {
                $query->where(function ($query) use ($kataKunci) {
                    $query->where('nama_lengkap', 'ilike', '%' . $kataKunci . '%')
                        ->orWhere('nip', 'ilike', '%' . $kataKunci . '%')
                        ->orWhere('nuptk', 'ilike', '%' . $kataKunci . '%')
                        ->orWhere('jabatan_utama', 'ilike', '%' . $kataKunci . '%')
                        ->orWhere('jenis_pegawai', 'ilike', '%' . $kataKunci . '%');
                });
            })
            ->orderBy('nama_lengkap')
            ->get()
            ->map(fn (Pegawai $pegawai) => $this->buatDataKartu($pegawai));

        return view('kartu-pegawai.index', compact(
            'kartuPegawai',
            'daftarJenisPegawai',
            'daftarPegawai',
            'kataKunci',
            'jenisPegawai',
            'pegawaiId',
            'status',
        ));
    }

    private function buatDataKartu(Pegawai $pegawai): array
    {
        $nip = trim((string) ($pegawai->nip ?? ''));

        return [
            'pegawai' => $pegawai,
            'foto_url' => $this->fotoUrl($pegawai),
            'jabatan' => $pegawai->jabatan_utama ?: $pegawai->jenis_pegawai ?: $pegawai->status_kepegawaian,
            'ukuran_font_nama' => $this->ukuranFontNama($pegawai->nama_lengkap),
            'qr_svg' => preg_match('/^[0-9]{1,41}$/', $nip) ? QrCodeNisn::svg($nip) : null,
            'qr_bisa_dibuat' => preg_match('/^[0-9]{1,41}$/', $nip) === 1,
        ];
    }

    private function fotoUrl(Pegawai $pegawai): string
    {
        if ($pegawai->foto && Storage::disk('public')->exists($pegawai->foto)) {
            return asset('storage/' . $pegawai->foto);
        }

        return asset('images/kartu-pelajar/default-user.png');
    }

    private function ukuranFontNama(?string $nama): float
    {
        $panjang = mb_strlen(trim((string) $nama));

        return match (true) {
            $panjang <= 16 => 11.2,
            $panjang <= 20 => 9.7,
            $panjang <= 24 => 8.5,
            $panjang <= 30 => 7.2,
            $panjang <= 38 => 6.2,
            $panjang <= 46 => 5.2,
            default => 4.7,
        };
    }
}
