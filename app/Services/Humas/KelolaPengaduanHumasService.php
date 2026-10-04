<?php

namespace App\Services\Humas;

use App\Models\PengaduanHumas;
use App\Models\Pengguna;
use App\Models\PesanPengaduanHumas;
use App\Services\Notifikasi\NotifikasiPenggunaService;
use Illuminate\Validation\ValidationException;

class KelolaPengaduanHumasService
{
    public function __construct(private readonly NotifikasiPenggunaService $notifikasi) {}

    public function akses(PengaduanHumas $tiket, Pengguna $pengguna): void
    {
        abort_unless(PengaduanHumas::untukPengguna($pengguna)->whereKey($tiket->id)->exists(), 404);
    }

    public function versi(PengaduanHumas $tiket, int $versi): void
    {
        if ($tiket->versi !== $versi) {
            throw ValidationException::withMessages(['versi' => 'Tiket telah berubah. Muat ulang dan periksa riwayat terbaru sebelum melanjutkan.']);
        }
    }

    public function catat(PengaduanHumas $tiket, Pengguna $pengguna, string $aksi, string $catatan): void
    {
        $tiket->riwayat()->create(['versi' => $tiket->versi, 'aksi' => $aksi, 'status' => $tiket->status, 'catatan' => $catatan,
            'snapshot' => $tiket->snapshot(), 'pengguna_id' => $pengguna->id, 'created_at' => now()]);
    }

    public function kandidat()
    {
        return Pengguna::where('aktif', true)->whereNotNull('pegawai_id')->where('akun_sistem', false)
            ->whereHas('pegawai', fn ($q) => $q->where('aktif', true))->with('daftarPeran.izin')->orderBy('nama')->get()
            ->filter(fn ($p) => $p->memilikiIzin('pengaduan_humas.tangani'));
    }

    public function kirimPesan(PengaduanHumas $tiket, Pengguna $pengguna, array $data, string $asal): void
    {
        abort_unless($tiket->dariOrangTua() && $tiket->pelapor_pengguna_id, 404);
        if ($asal === 'humas') {
            $this->akses($tiket, $pengguna);
            abort_unless($pengguna->memilikiIzin('pengaduan_humas.kelola'), 403);
        } else {
            abort_unless($asal === 'orang_tua' && $pengguna->aktif && $pengguna->akunOrangTua() && $tiket->pelapor_pengguna_id === $pengguna->id, 404);
        }
        $pesan = PesanPengaduanHumas::where('token_pengiriman', $data['token_pengiriman'])->first();
        if ($pesan) {
            abort_unless($pesan->pengaduan_humas_id === $tiket->id && $pesan->pengguna_id === $pengguna->id && $pesan->asal === $asal, 403);

            return;
        }
        $this->versi($tiket, (int) $data['versi']);
        if ($asal === 'orang_tua' && ! $tiket->aktif()) {
            throw ValidationException::withMessages(['status' => 'Laporan sudah selesai atau ditutup. Hubungi Humas bila perlu dibuka kembali.']);
        }
        if ($asal === 'orang_tua' && $tiket->status === 'menunggu') {
            $tiket->status = $tiket->petugas_pengguna_id ? 'diproses' : 'baru';
        }
        $tiket->pesan()->create(['token_pengiriman' => $data['token_pengiriman'], 'asal' => $asal, 'isi' => $data['isi_pesan'], 'pengguna_id' => $pengguna->id, 'created_at' => now()]);
        $tiket->forceFill(['versi' => $tiket->versi + 1])->save();
        $this->catat($tiket, $pengguna, $asal === 'humas' ? 'Balasan resmi dikirim' : 'Informasi pelapor ditambahkan',
            $asal === 'humas' ? 'Balasan resmi tersedia pada komunikasi dengan pelapor.' : 'Pelapor mengirim informasi tambahan.');
        if ($asal === 'humas') {
            $pelapor = Pengguna::find($tiket->pelapor_pengguna_id);
            if ($pelapor?->aktif && $pelapor->akunOrangTua()) {
                $this->notifikasi->kirim($pelapor, 'informasi', 'Balasan Humas diterima', $tiket->nomor.' memiliki balasan resmi.',
                    '/pengaduan-saya/'.$tiket->id, 'pengaduan-pesan-'.$data['token_pengiriman']);
            }
        } else {
            $penerima = $this->notifikasi->penggunaDenganIzin('pengaduan_humas.kelola');
            $petugas = $tiket->petugas_pengguna_id ? Pengguna::find($tiket->petugas_pengguna_id) : null;
            if ($petugas?->aktif && $petugas->akunPegawai() && $petugas->pegawai?->aktif && $petugas->memilikiIzin('pengaduan_humas.tangani')) {
                $penerima->push($petugas);
            }
            $this->notifikasi->kirimKeBanyak($penerima->unique('id'), 'informasi', 'Informasi tiket Humas',
                $tiket->nomor.' memiliki informasi tambahan.', '/pengaduan-humas/'.$tiket->id, 'pengaduan-pesan-'.$data['token_pengiriman']);
        }
    }

    // Caller holds the ticket row lock; access and version are rechecked after waiting.
    public function tindakan(PengaduanHumas $tiket, Pengguna $pengguna, string $aksi, array $data): void
    {
        $this->akses($tiket, $pengguna);
        $this->versi($tiket, (int) $data['versi']);
        $manager = $pengguna->memilikiIzin('pengaduan_humas.kelola');
        $operasional = in_array($aksi, ['proses', 'menunggu', 'usulkan-selesai'], true);
        abort_unless($manager || ($operasional && $pengguna->akunPegawai() && $pengguna->memilikiIzin('pengaduan_humas.tangani') && $tiket->petugas_pengguna_id === $pengguna->id), 403);
        $asal = match ($aksi) {
            'buka-kembali' => ['selesai', 'ditutup', 'verifikasi'],
            'proses', 'menunggu', 'usulkan-selesai' => ['ditugaskan', 'diproses', 'menunggu'],
            'disposisi', 'tarik', 'selesaikan', 'tutup' => PengaduanHumas::AKTIF,
            default => [],
        };
        if (! in_array($tiket->status, $asal, true)) {
            throw ValidationException::withMessages(['status' => 'Tindakan tidak tersedia pada status tiket saat ini.']);
        }
        $lama = $tiket->petugas_pengguna_id;
        if ($aksi === 'disposisi') {
            $petugas = $this->kandidat()->firstWhere('id', (int) $data['petugas_pengguna_id']);
            if (! $petugas) {
                throw ValidationException::withMessages(['petugas_pengguna_id' => 'Pilih akun pegawai aktif dengan izin menangani pengaduan.']);
            }
            $tiket->forceFill(['petugas_pengguna_id' => $petugas->id, 'batas_tanggal' => $data['batas_tanggal'], 'status' => 'ditugaskan']);
        } elseif ($aksi === 'tarik') {
            if (! $tiket->petugas_pengguna_id) {
                throw ValidationException::withMessages(['status' => 'Belum ada penugasan untuk ditarik.']);
            }
            $tiket->forceFill(['petugas_pengguna_id' => null, 'batas_tanggal' => null, 'status' => 'baru']);
        } elseif (in_array($aksi, ['selesaikan', 'tutup'], true)) {
            if ($tiket->dariOrangTua() && $tiket->pelapor_pengguna_id && ! $tiket->pesan()->where('asal', 'humas')->exists()) {
                throw ValidationException::withMessages(['balasan' => 'Kirim balasan resmi kepada orang tua sebelum menyelesaikan atau menutup laporan. Catatan internal tidak ditampilkan kepada pelapor.']);
            }
            $tiket->forceFill(['status' => $aksi === 'selesaikan' ? 'selesai' : 'ditutup', 'hasil_penanganan' => $data['catatan'], 'diselesaikan_pada' => now()]);
        } elseif ($aksi === 'buka-kembali') {
            $tiket->forceFill(['status' => $tiket->petugas_pengguna_id ? 'diproses' : 'baru', 'hasil_penanganan' => null, 'diselesaikan_pada' => null]);
        } else {
            $tiket->forceFill(['status' => ['proses' => 'diproses', 'menunggu' => 'menunggu', 'usulkan-selesai' => 'verifikasi'][$aksi]]);
        }
        $tiket->forceFill(['versi' => $tiket->versi + 1])->save();
        $label = ['disposisi' => 'Disposisi petugas', 'tarik' => 'Penugasan ditarik', 'proses' => 'Tiket diproses', 'menunggu' => 'Informasi tambahan ditunggu',
            'usulkan-selesai' => 'Penyelesaian diajukan', 'selesaikan' => 'Tiket diselesaikan', 'tutup' => 'Tiket ditutup dengan alasan', 'buka-kembali' => 'Tiket dibuka kembali'][$aksi];
        $this->catat($tiket, $pengguna, $label, $data['catatan']);
        $penerima = $this->notifikasi->penggunaDenganIzin('pengaduan_humas.kelola', $pengguna->id);
        if ($tiket->petugas_pengguna_id && $tiket->petugas_pengguna_id !== $pengguna->id) {
            $petugas = Pengguna::find($tiket->petugas_pengguna_id);
            if ($petugas && $petugas->akunPegawai() && $petugas->memilikiIzin('pengaduan_humas.tangani')) {
                $penerima->push($petugas);
            }
        }
        if ($lama && $lama !== $tiket->petugas_pengguna_id && $lama !== $pengguna->id) {
            $this->notifikasi->kirim($lama, 'informasi', 'Penugasan tiket berakhir', $tiket->nomor.' tidak lagi menjadi penugasan Anda.', '/pengaduan-humas', 'pengaduan-'.$tiket->id.'-'.$tiket->versi.'-akhir');
        }
        $this->notifikasi->kirimKeBanyak($penerima->unique('id'), 'informasi', 'Pembaruan tiket Humas', $tiket->nomor.' memiliki pembaruan penanganan.',
            '/pengaduan-humas/'.$tiket->id, 'pengaduan-'.$tiket->id.'-'.$tiket->versi);
        if ($tiket->dariOrangTua() && $tiket->pelapor_pengguna_id) {
            $this->notifikasi->kirim($tiket->pelapor_pengguna_id, 'informasi', 'Status laporan diperbarui', $tiket->nomor.' memiliki pembaruan status.',
                '/pengaduan-saya/'.$tiket->id, 'pengaduan-status-'.$tiket->id.'-'.$tiket->versi);
        }
    }
}
