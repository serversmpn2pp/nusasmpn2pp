<?php

namespace App\Services\Mobile;

use App\Models\AgendaHumas;
use App\Models\DokumenHumas;
use App\Models\PengaduanHumas;
use App\Models\PesertaPertemuanHumas;
use App\Models\UmpanBalikHumas;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class HumasMobileService
{
    public function dokumen(DokumenHumas $d, string $sidik, bool $detail = false): array
    {
        $data = $d->only(['id', 'judul', 'kategori', 'nomor_dokumen', 'status']) + [
            'kategori_label' => DokumenHumas::KATEGORI[$d->kategori], 'status_label' => DokumenHumas::STATUS[$d->status],
            'masa_berlaku' => $d->statusMasaBerlaku(), 'berlaku_mulai' => $d->berlaku_mulai?->format('Y-m-d'),
            'berlaku_sampai' => $d->berlaku_sampai?->format('Y-m-d'), 'sidik' => $sidik,
            'versi_berkas' => (int) ($d->riwayat_max_versi ?? 0),
        ];
        if ($detail) {
            $data += $d->only(['deskripsi', 'ingatkan_hari_sebelum']);
            $data['berkas'] = ['nama' => $d->nama_file_asli, 'tipe' => $d->tipe_file, 'ukuran_byte' => $d->ukuran_file,
                'url' => route('api.v1.humas.dokumen.unduh', $d->id)];
            $data['updated_at'] = $d->updated_at?->toIso8601String();
        }

        return $data;
    }

    public function paginasi(LengthAwarePaginator $p, callable $map): array
    {
        return ['items' => collect($p->items())->map($map)->values(), 'paginasi' => [
            'halaman' => $p->currentPage(), 'halaman_terakhir' => $p->lastPage(), 'per_halaman' => $p->perPage(),
            'total' => $p->total(), 'ada_halaman_berikutnya' => $p->hasMorePages(),
        ]];
    }

    public function agenda(AgendaHumas $a, bool $detail = false): array
    {
        $data = $a->only(['id', 'judul', 'jenis', 'tempat', 'sasaran', 'status', 'tautan_pertemuan']);
        $data += ['jenis_label' => AgendaHumas::JENIS[$a->jenis], 'status_label' => $a->labelWaktu(),
            'waktu_mulai' => $a->waktu_mulai->toIso8601String(), 'waktu_selesai' => $a->waktu_selesai->toIso8601String()];
        if ($detail) {
            $data += $a->only(['topik', 'pemimpin', 'notulis', 'pembahasan', 'keputusan', 'alasan_pembatalan']);
            $data['presensi_dibuka'] = $a->menerimaPresensiQr();
            $data['rekap_presensi'] = $a->rekapPresensi();
        } else {
            $data['jumlah_peserta'] = (int) $a->peserta_count;
            $data['jumlah_hadir'] = (int) $a->hadir_count;
            $data['tindak_lanjut_tertunda'] = (int) $a->tertunda_count;
        }

        return $data;
    }

    public function peserta(PesertaPertemuanHumas $p): array
    {
        return $p->only(['id', 'nama', 'instansi', 'peran', 'status_kehadiran', 'catatan', 'sumber_kehadiran', 'versi_presensi'])
            + ['status_label' => PesertaPertemuanHumas::KEHADIRAN[$p->status_kehadiran], 'hadir_pada' => $p->hadir_pada?->toIso8601String()];
    }

    public function pertemuanSaya(PesertaPertemuanHumas $p): array
    {
        $a = $p->agenda;

        return ['agenda' => $a->only(['id', 'judul', 'tempat', 'tautan_pertemuan', 'status', 'alasan_pembatalan']) + [
            'waktu_mulai' => $a->waktu_mulai->toIso8601String(), 'waktu_selesai' => $a->waktu_selesai->toIso8601String(),
            'status_label' => $a->labelWaktu(), 'presensi_dibuka' => $a->menerimaPresensiQr(),
        ], 'kehadiran' => array_diff_key($this->peserta($p), ['catatan' => true])];
    }

    public function formulir(UmpanBalikHumas $f): array
    {
        return $f->only(['id', 'judul', 'pengantar', 'status', 'versi']) + ['status_label' => $f->labelStatus(),
            'mulai_pada' => $f->mulai_pada->toIso8601String(), 'selesai_pada' => $f->selesai_pada->toIso8601String(),
            'menerima_jawaban' => $f->menerimaJawaban()];
    }

    public function pengaduan(PengaduanHumas $t, bool $orangTua = false, bool $pengelola = false, bool $detail = false): array
    {
        $data = $t->only(['id', 'judul', 'jenis', 'kategori', 'status', 'versi']);
        $data += ['nomor' => $t->nomor, 'status_label' => ($orangTua ? PengaduanHumas::STATUS_ORANG_TUA : PengaduanHumas::STATUS)[$t->status],
            'tanggal_diterima' => $t->tanggal_diterima->format('Y-m-d'), 'created_at' => $t->created_at->toIso8601String()];
        if ($detail) {
            $data['isi'] = $t->isi;
            $data['rahasiakan_identitas'] = $t->rahasiakan_identitas;
        }
        if (! $orangTua) {
            $data += $t->only(['prioritas', 'kanal', 'petugas_pengguna_id']);
            $data['batas_tanggal'] = $t->batas_tanggal?->format('Y-m-d');
            if ($detail) {
                $data['hasil_penanganan'] = $t->hasil_penanganan;
            }
        }
        if ($pengelola && $detail) {
            $data['identitas_privat'] = ['nama' => $t->anonim ? null : $t->nama_pelapor, 'kontak' => $t->kontak_pelapor, 'anonim' => $t->anonim];
        }

        return $data;
    }
}
