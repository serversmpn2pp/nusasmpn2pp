<?php

namespace App\Services\Humas;

use App\Models\AgendaHumas;
use App\Models\DokumenHumas;
use App\Models\Pengguna;
use Illuminate\Http\Request;

class NavigasiHumasService
{
    public function ruangKerja(Pengguna $akun): array
    {
        if (! $this->bolehMembuka($akun)) {
            return [];
        }

        return collect($this->definisi())->map(function (array $ruang) use ($akun) {
            if ($akun->memilikiIzin(['dokumen_humas.lihat', 'dokumen_humas.kelola'])) {
                $urusan = str_replace('-', '_', $ruang['kode']);
                $ruang['items'][] = $this->item('arsip-'.$ruang['kode'].'-humas', 'Arsip '.[
                    'kedinasan' => 'Kedinasan', 'komite' => 'Komite', 'orang_tua' => 'Orang Tua', 'kemitraan' => 'Kemitraan',
                    'publikasi' => 'Publikasi', 'alumni' => 'Alumni', 'pengaduan' => 'Pengaduan'][$urusan],
                    'dokumen-humas.index', ['dokumen_humas.lihat', 'dokumen_humas.kelola'], 'folder-open', ['urusan' => $urusan]);
            }
            $ruang['items'] = array_values(array_filter($ruang['items'], fn ($item) => $akun->memilikiIzin($item['izin'])));

            return $ruang;
        })->filter(fn ($ruang) => count($ruang['items']) > 0)->values()->all();
    }

    public function pendukung(Pengguna $akun): array
    {
        if (! $this->bolehMembuka($akun)) {
            return [];
        }

        $items = [];
        if ($akun->memilikiIzin(['agenda_humas.lihat', 'agenda_humas.kelola'])) {
            $items[] = $this->item('agenda-humas', 'Agenda & Pertemuan', 'agenda-humas.index', ['agenda_humas.lihat', 'agenda_humas.kelola'], 'calendar-days');
        }
        if (app(AksesBukuTamuService::class)->bolehMembuka($akun)) {
            $items[] = $this->item('buku-tamu', 'Buku Tamu Digital', 'buku-tamu.index', null, 'book-user');
        }

        return $items;
    }

    public function sidebar(Pengguna $akun): array
    {
        if (! $this->bolehMembuka($akun)) {
            return [];
        }

        $items = [];
        if ($akun->memilikiIzin('dashboard_humas.lihat')) {
            $items[] = $this->item('dashboard-humas', 'Dashboard Humas', 'dashboard-humas.index', 'dashboard_humas.lihat', 'layout-dashboard');
        }
        foreach ($this->ruangKerja($akun) as $ruang) {
            foreach ($ruang['items'] as $item) {
                $items[] = $item + ['subgroup' => $ruang['label']];
            }
        }
        foreach ($this->pendukung($akun) as $item) {
            $items[] = $item + ['subgroup' => 'Agenda & Layanan Pendukung'];
        }

        return $items;
    }

    public function aktif(array $item, Request $request): bool
    {
        if (! $request->routeIs(...$item['active'])) {
            return false;
        }
        if ($item['route'] === 'dokumen-humas.index') {
            $dokumen = $request->route('dokumenHumas');
            $urusan = $dokumen instanceof DokumenHumas ? $dokumen->urusan : $request->query('urusan');

            return ($item['parameters']['urusan'] ?? null) === $urusan;
        }
        if ($item['route'] !== 'agenda-humas.index') {
            return true;
        }

        $agenda = $request->route('agendaHumas');
        $jenis = $agenda instanceof AgendaHumas ? $agenda->jenis : $request->query('jenis');
        $khusus = in_array($jenis, ['komite', 'orang_tua', 'kemitraan'], true) ? $jenis : null;

        return ($item['parameters']['jenis'] ?? null) === $khusus;
    }

    public function untukApi(Pengguna $akun): array
    {
        $native = ['agenda-humas', 'dokumen-humas', 'pengaduan-humas', 'umpan-balik-humas'];

        return array_map(function ($ruang) use ($native) {
            $ruang['items'] = array_map(function ($item) use ($native) {
                $kode = match ($item['route']) {
                    'agenda-humas.index' => 'agenda-humas', 'dokumen-humas.index' => 'dokumen-humas', default => $item['kode'],
                };

                return [
                    'kode' => $item['kode'], 'label' => $item['label'], 'ikon' => $item['icon'],
                    'menu_mobile' => in_array($kode, $native, true) ? $kode : null,
                    'tersedia_mobile' => in_array($kode, $native, true),
                    'filter' => (object) $item['parameters'],
                ];
            }, $ruang['items']);

            return $ruang;
        }, $this->ruangKerja($akun));
    }

    public function subkelompokMobile(string $kode): ?string
    {
        foreach ($this->definisi() as $ruang) {
            if (in_array($kode, array_column($ruang['items'], 'kode'), true)) {
                return $ruang['label'];
            }
        }

        return $kode === 'agenda-humas' ? 'Agenda & Layanan Pendukung' : null;
    }

    private function bolehMembuka(Pengguna $akun): bool
    {
        return $akun->aktif && ! $akun->akunSiswa() && ! $akun->akunOrangTua();
    }

    private function item(string $kode, string $label, string $route, string|array|null $izin, string $icon, array $parameters = []): array
    {
        return compact('kode', 'label', 'route', 'izin', 'icon', 'parameters') + [
            'active' => [substr($route, 0, strrpos($route, '.')).'.*'], 'initial' => 'HM', 'humas_menu' => true,
        ];
    }

    private function definisi(): array
    {
        $agenda = ['agenda_humas.lihat', 'agenda_humas.kelola'];

        return [
            ['kode' => 'kedinasan', 'label' => 'Dokumen Kedinasan Humas', 'ikon' => 'school', 'warna' => 'blue', 'items' => [
                $this->item('program-kerja-humas', 'Program Kerja Humas', 'program-kerja-humas.index', ['program_kerja_humas.lihat', 'program_kerja_humas.kelola'], 'clipboard-list'),
                $this->item('dokumen-humas', 'Pusat Dokumen Humas', 'dokumen-humas.index', ['dokumen_humas.lihat', 'dokumen_humas.kelola'], 'files'),
                $this->item('akreditasi-humas', 'Portofolio Akreditasi', 'akreditasi-humas.index', ['akreditasi_humas.lihat', 'akreditasi_humas.kelola'], 'clipboard-check'),
            ]],
            ['kode' => 'komite', 'label' => 'Dokumen Komite Sekolah', 'ikon' => 'users-round', 'warna' => 'teal', 'items' => [
                $this->item('komite-humas', 'Komite Sekolah', 'komite-humas.index', ['komite_humas.lihat', 'komite_humas.kelola'], 'users-round'),
                $this->item('rapat-komite-humas', 'Rapat Komite', 'agenda-humas.index', $agenda, 'calendar-days', ['jenis' => 'komite']),
            ]],
            ['kode' => 'orang-tua', 'label' => 'Hubungan Orang Tua/Wali', 'ikon' => 'users', 'warna' => 'yellow', 'items' => [
                $this->item('pertemuan-orang-tua-humas', 'Pertemuan Orang Tua', 'agenda-humas.index', $agenda, 'calendar-days', ['jenis' => 'orang_tua']),
                $this->item('umpan-balik-humas', 'Umpan Balik Orang Tua', 'umpan-balik-humas.index', ['umpan_balik_humas.lihat', 'umpan_balik_humas.kelola'], 'message-square'),
            ]],
            ['kode' => 'kemitraan', 'label' => 'Kemitraan & Kerja Sama', 'ikon' => 'handshake', 'warna' => 'cyan', 'items' => [
                $this->item('kemitraan-humas', 'Kemitraan & MoU', 'kemitraan-humas.index', ['kemitraan_humas.lihat', 'kemitraan_humas.kelola'], 'handshake'),
                $this->item('pertemuan-mitra-humas', 'Pertemuan Mitra', 'agenda-humas.index', $agenda, 'calendar-days', ['jenis' => 'kemitraan']),
            ]],
            ['kode' => 'publikasi', 'label' => 'Publikasi & Promosi Sekolah', 'ikon' => 'megaphone', 'warna' => 'rose', 'items' => [
                $this->item('publikasi-humas', 'Publikasi & Persetujuan', 'publikasi-humas.index', ['publikasi_humas.lihat', 'publikasi_humas.kelola', 'publikasi_humas.periksa'], 'megaphone'),
                $this->item('aset-promosi-humas', 'Bank Aset Promosi', 'aset-promosi-humas.index', ['aset_promosi_humas.lihat', 'aset_promosi_humas.kelola'], 'images'),
                $this->item('media-resmi-humas', 'Daftar Media Resmi', 'media-resmi-humas.index', ['media_resmi_humas.lihat', 'media_resmi_humas.kelola'], 'globe'),
                $this->item('kliping-berita-humas', 'Kliping Berita', 'kliping-berita-humas.index', ['kliping_berita_humas.lihat', 'kliping_berita_humas.kelola'], 'newspaper'),
                $this->item('prestasi-sekolah', 'Database Prestasi', 'prestasi-sekolah.index', ['prestasi_sekolah.lihat', 'prestasi_sekolah.kelola'], 'trophy'),
            ]],
            ['kode' => 'alumni', 'label' => 'Database & Portofolio Alumni', 'ikon' => 'graduation-cap', 'warna' => 'green', 'items' => [
                $this->item('alumni-humas', 'Database Alumni', 'alumni-humas.index', ['alumni_humas.lihat', 'alumni_humas.kelola'], 'graduation-cap'),
            ]],
            ['kode' => 'pengaduan', 'label' => 'Aspirasi & Pengaduan', 'ikon' => 'messages-square', 'warna' => 'amber', 'items' => [
                $this->item('pengaduan-humas', 'Aspirasi & Pengaduan', 'pengaduan-humas.index', ['pengaduan_humas.lihat', 'pengaduan_humas.kelola', 'pengaduan_humas.tangani'], 'messages-square'),
            ]],
        ];
    }
}
