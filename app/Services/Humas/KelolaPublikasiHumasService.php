<?php

namespace App\Services\Humas;

use App\Models\AsetPromosiHumas;
use App\Models\Pengguna;
use App\Models\PublikasiHumas;
use App\Services\Notifikasi\NotifikasiPenggunaService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class KelolaPublikasiHumasService
{
    public function __construct(private readonly NotifikasiPenggunaService $notifikasi) {}

    public function pastikanVersi(PublikasiHumas $publikasi, int $versi): void
    {
        if ($publikasi->versi !== $versi) {
            throw ValidationException::withMessages(['versi' => 'Data telah berubah. Muat ulang halaman dan periksa data terbaru sebelum melanjutkan.']);
        }
    }

    public function pastikanBolehEdit(PublikasiHumas $publikasi): void
    {
        if (! $publikasi->bolehDiedit()) {
            throw ValidationException::withMessages(['status' => 'Konten dikunci selama pemeriksaan, setelah disetujui, dan setelah tayang. Tarik pengajuan atau buka revisi terlebih dahulu jika tersedia.']);
        }
    }

    public function catat(PublikasiHumas $publikasi, Pengguna $pengguna, string $aksi, ?string $catatan = null): void
    {
        $snapshot = $publikasi->only(['judul', 'jenis', 'ringkasan', 'isi', 'kanal', 'rencana_tayang', 'agenda_humas_id', 'status', 'url_tayang']);
        $snapshot['waktu_tayang'] = $publikasi->waktu_tayang?->format('d-m-Y H:i');
        $snapshot['rencana_tayang'] = $publikasi->rencana_tayang?->format('d-m-Y');
        $snapshot['lampiran'] = $publikasi->lampiran()->whereNull('dihapus_pada')->get(['id', 'jenis', 'nama_file_asli'])->toArray();
        $snapshot['aset'] = $publikasi->aset()->get()->map(fn ($item) => $item->snapshot + ['aset_id' => $item->aset_promosi_humas_id, 'berkas_id' => $item->riwayat_dokumen_humas_id])->all();
        $publikasi->riwayat()->create(['aksi' => $aksi, 'versi' => $publikasi->versi, 'snapshot' => $snapshot,
            'catatan' => $catatan, 'pengguna_id' => $pengguna->id, 'created_at' => now()]);
    }

    // Caller holds a row lock so edits, reviews, and publication cannot race.
    public function transisi(PublikasiHumas $publikasi, Pengguna $pengguna, string $aksi, array $data): void
    {
        $this->pastikanVersi($publikasi, (int) $data['versi']);
        $pemeriksaan = in_array($aksi, ['setujui', 'minta-revisi'], true);
        abort_unless($pengguna->aktif && $pengguna->memilikiIzin($pemeriksaan ? 'publikasi_humas.periksa' : 'publikasi_humas.kelola'), 403);
        $asal = match ($aksi) {
            'ajukan' => ['draf', 'revisi'], 'tarik', 'setujui', 'minta-revisi' => ['diajukan'],
            'buka-revisi', 'tayang' => ['disetujui'], default => [],
        };
        if (! in_array($publikasi->status, $asal, true)) {
            throw ValidationException::withMessages(['status' => 'Tindakan ini tidak tersedia untuk status konten saat ini.']);
        }
        if ($pemeriksaan && in_array($pengguna->id, [$publikasi->dibuat_oleh_pengguna_id, $publikasi->diajukan_oleh_pengguna_id], true)) {
            throw ValidationException::withMessages(['pemeriksaan' => 'Pembuat atau pengaju tidak dapat memeriksa kontennya sendiri. Minta pemeriksaan dari pimpinan lain yang berwenang.']);
        }
        if (in_array($aksi, ['ajukan', 'setujui', 'tayang'], true)) {
            $aset = $publikasi->aset()->with('berkas')->get();
            if ($aset->isNotEmpty()) {
                abort_unless($pengguna->memilikiIzin(AsetPromosiHumas::IZIN_LIHAT), 403);
            }
            foreach ($aset as $item) {
                if ($item->snapshot['sumber'] === 'berkas') {
                    abort_unless($pengguna->memilikiIzin(['dokumen_humas.lihat', 'dokumen_humas.kelola']), 403);
                    app(KelolaAsetPromosiHumasService::class)->pastikanBerkas($item->berkas);
                }
            }
            foreach ($publikasi->lampiran()->whereNull('dihapus_pada')->get() as $file) {
                if (! Storage::disk('local')->exists($file->lokasi_file)) {
                    throw ValidationException::withMessages(['foto' => 'Ada berkas konten yang tidak ditemukan. Pulihkan berkas atau perbaiki draf sebelum melanjutkan.']);
                }
            }
        }
        if ($aksi === 'ajukan') {
            $publikasi->forceFill(['status' => 'diajukan', 'diajukan_pada' => now(), 'diajukan_oleh_pengguna_id' => $pengguna->id,
                'diperiksa_pada' => null, 'diperiksa_oleh_pengguna_id' => null, 'catatan_pemeriksaan' => null]);
        } elseif ($pemeriksaan) {
            $publikasi->forceFill(['status' => $aksi === 'setujui' ? 'disetujui' : 'revisi', 'diperiksa_pada' => now(),
                'diperiksa_oleh_pengguna_id' => $pengguna->id, 'catatan_pemeriksaan' => $data['catatan'] ?? null]);
        } elseif ($aksi === 'tayang') {
            $publikasi->forceFill(['status' => 'tayang', 'waktu_tayang' => $data['waktu_tayang'], 'url_tayang' => $data['url_tayang']]);
        } else {
            $publikasi->forceFill(['status' => $aksi === 'tarik' ? 'draf' : 'revisi', 'diperiksa_pada' => null,
                'diperiksa_oleh_pengguna_id' => null, 'catatan_pemeriksaan' => null]);
        }
        $publikasi->forceFill(['versi' => $publikasi->versi + 1, 'diubah_oleh_pengguna_id' => $pengguna->id])->save();
        $label = ['ajukan' => 'Konten diajukan', 'tarik' => 'Pengajuan ditarik', 'setujui' => 'Konten disetujui',
            'minta-revisi' => 'Revisi diminta', 'buka-revisi' => 'Persetujuan dibuka untuk revisi', 'tayang' => 'Bukti tayang dicatat'][$aksi];
        $this->catat($publikasi, $pengguna, $label, $data['catatan'] ?? null);
        if ($aksi === 'ajukan' || $pemeriksaan) {
            $penerima = $this->notifikasi->penggunaDenganIzin($aksi === 'ajukan' ? 'publikasi_humas.periksa' : 'publikasi_humas.kelola', $pengguna->id);
            if ($aksi === 'ajukan') {
                $penerima = $penerima->where('id', '!=', $publikasi->dibuat_oleh_pengguna_id);
            }
            $this->notifikasi->kirimKeBanyak($penerima, $aksi === 'minta-revisi' ? 'peringatan' : 'informasi', $label,
                $publikasi->judul, '/publikasi-humas/'.$publikasi->id, 'publikasi-humas-'.$publikasi->id.'-'.$publikasi->versi);
        }
    }
}
