<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class PermintaanPenghapusanController extends Controller
{
    public function __invoke(): Response
    {
        // Kanal informasi statis, bukan endpoint penghapusan atau pengumpulan identitas.
        $isi = Str::markdown(File::get(resource_path('legal/permintaan-penghapusan-nusa.md')), [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
        $email = 'official@smpn2padangpanjang.sch.id';
        $mailto = 'mailto:'.$email.'?'.http_build_query([
            'subject' => 'Permintaan Penghapusan Akun/Data NUSA',
            'body' => "Nama pemohon: \nNama pengguna NUSA (jika diketahui): \nJenis akun (pegawai/guru/siswa/orang tua): \nPermintaan (hapus akun dan data terkait / hapus data tertentu): \nData yang dimaksud: \nHubungan dengan pemilik akun (jika mewakili): \nKontak balasan: \n\nSaya meminta petugas memverifikasi dan meninjau permintaan ini. Jangan lampirkan kata sandi, OTP, atau dokumen identitas lengkap.",
        ], '', '&', PHP_QUERY_RFC3986);

        return response()->view('legal.permintaan-penghapusan', compact('isi', 'email', 'mailto'))
            ->header('Cache-Control', 'no-store, no-transform')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('Content-Security-Policy', "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'");
    }
}
