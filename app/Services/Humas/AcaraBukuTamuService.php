<?php

namespace App\Services\Humas;

use App\Models\AcaraBukuTamu;
use App\Models\Pengguna;
use App\Models\ResponAcaraBukuTamu;
use Carbon\Carbon;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AcaraBukuTamuService
{
    public function akses(?Pengguna $u, bool $kelola = false): void
    {
        abort_unless($u?->aktif && ! $u->akunSiswa() && ! $u->akunOrangTua()
            && $u->memilikiIzin($kelola ? 'buku_tamu.kelola' : ['buku_tamu.lihat', 'buku_tamu.kelola']), 403);
    }

    public function simpan(Request $r, ?AcaraBukuTamu $acara = null): AcaraBukuTamu
    {
        $this->akses($r->user(), true);
        $data = $r->validate([
            'nama' => ['required', 'string', 'max:180'], 'lokasi' => ['required', 'string', 'max:200'],
            'pengantar' => ['nullable', 'string', 'max:1200'],
            'mulai_pada' => ['required', 'date_format:Y-m-d\TH:i'],
            'selesai_pada' => ['required', 'date_format:Y-m-d\TH:i', 'after:mulai_pada'],
            'status' => ['required', Rule::in(array_keys(AcaraBukuTamu::STATUS))],
            'kunci_pembuatan' => [$acara ? 'exclude' : 'required', 'uuid'],
            'versi' => [$acara ? 'required' : 'exclude', 'integer', 'min:0'],
            'banner' => ['bail', 'nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.AcaraBukuTamu::BANNER_MAKSIMAL_KB,
                Rule::dimensions()->minWidth(AcaraBukuTamu::BANNER_MIN_LEBAR)->minHeight(AcaraBukuTamu::BANNER_MIN_TINGGI)
                    ->maxWidth(AcaraBukuTamu::BANNER_MAKSIMAL_SISI)->maxHeight(AcaraBukuTamu::BANNER_MAKSIMAL_SISI),
                function ($attribute, $value, $fail) {
                    $size = @getimagesize($value->getPathname());
                    if ($size && $size[0] * $size[1] > AcaraBukuTamu::BANNER_MAKSIMAL_PIKSEL) {
                        $fail('Resolusi gambar maksimal 24 megapiksel. Perkecil gambar sebelum mengunggah.');
                    }
                }],
            'banner_tampilan' => ['sometimes', 'required', Rule::in(array_keys(AcaraBukuTamu::BANNER_TAMPILAN))],
            'banner_fokus_desktop' => ['sometimes', 'required', 'integer', 'between:0,100'],
            'banner_fokus_mobile' => ['sometimes', 'required', 'integer', 'between:0,100'],
            'pilihan_minat' => ['sometimes', 'array', 'min:1', 'max:30'],
            'pilihan_minat.*' => ['required', 'array:kode,nama,ikon,aktif'],
            'pilihan_minat.*.kode' => ['required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/', 'distinct'],
            'pilihan_minat.*.nama' => ['required', 'string', 'max:60'],
            'pilihan_minat.*.ikon' => ['required', Rule::in(array_keys(AcaraBukuTamu::IKON_MINAT))],
            'pilihan_minat.*.aktif' => ['required', 'boolean'],
        ], [
            'banner.dimensions' => 'Gambar minimal 1000 x 600 px dan maksimal 6000 px pada setiap sisi.',
            'banner.max' => 'Ukuran gambar maksimal 4 MB.',
            'banner.image' => 'Berkas harus berupa gambar JPG, PNG, atau WebP yang valid.',
            'banner.mimes' => 'Format gambar harus JPG, PNG, atau WebP.',
        ]);
        if (Carbon::parse($data['mulai_pada'])->diffInDays(Carbon::parse($data['selesai_pada'])) > 366) {
            throw ValidationException::withMessages(['selesai_pada' => 'Satu acara maksimal 366 hari.']);
        }
        $path = null;
        try {
            return DB::transaction(function () use ($r, $data, $acara, &$path) {
                if (! $acara) {
                    $lama = AcaraBukuTamu::where('kunci_pembuatan', $data['kunci_pembuatan'])->lockForUpdate()->first();
                    if ($lama) {
                        abort_unless($lama->dibuat_oleh === $r->user()->id, 403);

                        return $lama;
                    }
                }
                $a = $acara ? AcaraBukuTamu::lockForUpdate()->findOrFail($acara->id) : new AcaraBukuTamu;
                if ($acara && $a->versi !== (int) $data['versi']) {
                    throw ValidationException::withMessages(['versi' => 'Acara telah berubah. Muat ulang sebelum menyimpan.']);
                }
                $nilai = collect($data)->only(['nama', 'lokasi', 'pengantar', 'mulai_pada', 'selesai_pada', 'status',
                    'banner_tampilan', 'banner_fokus_desktop', 'banner_fokus_mobile'])->all();
                $nilai['pilihan_minat'] = $this->validasiPilihanMinat($data['pilihan_minat'] ?? $a->pilihanMinat(), $a);
                $nilai['selesai_pada'] = Carbon::parse($nilai['selesai_pada'])->endOfMinute();
                if ($r->hasFile('banner')) {
                    $path = $r->file('banner')->store('acara-buku-tamu', 'local');
                    $nilai['banner'] = $path;
                }
                $a->fill($nilai);
                if (! $acara) {
                    $a->fill(['kode_publik' => Str::lower(Str::random(24)), 'kunci_pembuatan' => $data['kunci_pembuatan'], 'dibuat_oleh' => $r->user()->id]);
                } else {
                    $a->versi++;
                }
                $perubahan = array_keys($a->getDirty());
                $a->diubah_oleh = $r->user()->id;
                $a->save();
                $this->riwayat($a, $r->user()->id, $acara ? 'acara_diubah' : 'acara_dibuat', $perubahan);

                return $a;
            });
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $e;
        }
    }

    public function tiket(AcaraBukuTamu $acara): array
    {
        $kunci = (string) Str::uuid();

        return ['kunci_kiriman' => $kunci, 'tanda_tangan' => Crypt::encryptString(json_encode([
            'acara' => $acara->id, 'kunci' => $kunci, 'sampai' => now()->addHours(4)->timestamp,
        ], JSON_THROW_ON_ERROR))];
    }

    public function terima(Request $r, AcaraBukuTamu $acara): bool
    {
        $data = $r->validate([
            'nama' => ['required', 'string', 'min:2', 'max:120'], 'instansi' => ['nullable', 'string', 'max:180'],
            'kategori' => ['required', Rule::in(array_keys(ResponAcaraBukuTamu::KATEGORI))],
            'minat' => ['required', 'array', 'min:1', 'max:30'], 'minat.*' => ['required', 'string', 'max:64', 'distinct'],
            'kepuasan' => ['required', 'integer', 'between:1,5'], 'nilai_nusa' => ['nullable', 'integer', 'between:0,5'],
            'saran' => ['nullable', 'string', 'max:1000'], 'persetujuan' => ['accepted'],
            'kunci_kiriman' => ['required', 'uuid'], 'tanda_tangan' => ['required', 'string', 'max:2048'], 'website' => ['prohibited'],
        ]);
        try {
            $tiket = json_decode(Crypt::decryptString($data['tanda_tangan']), true, 8, JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException $e) {
            throw ValidationException::withMessages(['formulir' => 'Formulir tidak valid. Muat ulang halaman untuk mencoba kembali.']);
        }
        if (($tiket['acara'] ?? null) !== $acara->id || ($tiket['kunci'] ?? null) !== $data['kunci_kiriman'] || ($tiket['sampai'] ?? 0) < now()->timestamp) {
            throw ValidationException::withMessages(['formulir' => 'Formulir sudah kedaluwarsa. Muat ulang halaman untuk mencoba kembali.']);
        }
        $nilai = collect($data)->only(['nama', 'instansi', 'kategori', 'minat', 'kepuasan', 'nilai_nusa', 'saran'])->all();
        $nilai['nama'] = trim($nilai['nama']);
        $nilai['instansi'] = filled($nilai['instansi'] ?? null) ? trim($nilai['instansi']) : null;
        $nilai['saran'] = filled($nilai['saran'] ?? null) ? trim($nilai['saran']) : null;
        $nilai['nilai_nusa'] = ! empty($nilai['nilai_nusa']) ? (int) $nilai['nilai_nusa'] : null;
        $nilai['kepuasan'] = (int) $nilai['kepuasan'];
        sort($nilai['minat']);
        $sidik = hash('sha256', json_encode($nilai, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($acara, $data, $nilai, $sidik) {
            // The event lock also serializes closing an event and replayed submissions.
            $a = AcaraBukuTamu::lockForUpdate()->findOrFail($acara->id);
            $lama = $a->respon()->where('kunci_kiriman', $data['kunci_kiriman'])->first();
            if ($lama) {
                if (! hash_equals($lama->sidik_kiriman, $sidik)) {
                    throw ValidationException::withMessages(['formulir' => 'Pengiriman ini sudah digunakan. Buka formulir baru untuk pengunjung berikutnya.']);
                }

                return false;
            }
            abort_unless($a->menerima(), 409, 'Pengisian buku tamu belum dibuka atau sudah ditutup.');
            if (array_diff($nilai['minat'], array_column($a->pilihanMinat(true), 'kode'))) {
                throw ValidationException::withMessages(['minat' => 'Pilihan minat telah berubah atau tidak tersedia. Periksa kembali pilihan Anda.']);
            }
            $a->respon()->create($nilai + ['kunci_kiriman' => $data['kunci_kiriman'], 'sidik_kiriman' => $sidik, 'status' => 'valid']);

            return true;
        });
    }

    public function filter(Request $r, AcaraBukuTamu $a): array
    {
        $r->merge(['dari' => $r->input('dari') ?? $a->mulai_pada->toDateString(),
            'sampai' => $r->input('sampai') ?? $a->selesai_pada->toDateString()]);
        $f = $r->validate(['q' => ['nullable', 'string', 'max:120'], 'kategori' => ['nullable', Rule::in(array_keys(ResponAcaraBukuTamu::KATEGORI))],
            'kepuasan' => ['nullable', 'integer', 'between:1,5'], 'status_respon' => ['nullable', Rule::in(['valid', 'dibatalkan'])],
            'dari' => ['nullable', 'date_format:Y-m-d'], 'sampai' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:dari'],
            'page' => ['nullable', 'integer', 'min:1'], 'halaman' => ['nullable', 'integer', 'min:1']]);
        $f['dari'] = $f['dari'] ?? $a->mulai_pada->toDateString();
        $f['sampai'] = $f['sampai'] ?? $a->selesai_pada->toDateString();
        if ($f['dari'] < $a->mulai_pada->toDateString() || $f['sampai'] > $a->selesai_pada->toDateString() || $f['dari'] > $f['sampai']) {
            throw ValidationException::withMessages(['dari' => 'Rentang harus berada dalam tanggal acara.']);
        }

        return $f;
    }

    public function query(AcaraBukuTamu $a, array $f, bool $daftar = true)
    {
        $q = $a->respon()->where('created_at', '>=', Carbon::parse($f['dari'])->startOfDay())->where('created_at', '<', Carbon::parse($f['sampai'])->addDay()->startOfDay());
        if (! empty($f['kategori'])) {
            $q->where('kategori', $f['kategori']);
        }
        if ($daftar) {
            $q->where('status', $f['status_respon'] ?? 'valid');
            if (! empty($f['kepuasan'])) {
                $q->where('kepuasan', $f['kepuasan']);
            }
            if (! empty($f['q'])) {
                $q->where(fn ($q) => $q->whereLike('nama', '%'.$f['q'].'%')->orWhereLike('instansi', '%'.$f['q'].'%'));
            }
        }

        return $q;
    }

    public function statistik(AcaraBukuTamu $a, array $f): array
    {
        $s = ['total' => 0, 'puas' => 0, 'persen_puas' => null, 'rata_rata' => null, 'penilai_nusa' => 0, 'persen_puas_nusa' => null,
            'kepuasan' => array_fill_keys(array_keys(ResponAcaraBukuTamu::KEPUASAN), 0),
            'kategori' => array_fill_keys(array_keys(ResponAcaraBukuTamu::KATEGORI), 0), 'label_kategori' => ResponAcaraBukuTamu::KATEGORI,
            'minat' => array_fill_keys(array_keys($a->labelMinat()), 0), 'label_minat' => $a->labelMinat(), 'harian' => [], 'jam' => array_fill(0, 24, 0)];
        for ($d = Carbon::parse($f['dari']); $d->toDateString() <= $f['sampai']; $d->addDay()) {
            $s['harian'][$d->toDateString()] = 0;
        }
        $jumlahNilai = $nusaPuas = 0;
        $this->query($a, $f, false)->where('status', 'valid')->select(['id', 'kategori', 'minat', 'kepuasan', 'nilai_nusa', 'created_at'])
            ->chunkById(1000, function ($rows) use (&$s, &$jumlahNilai, &$nusaPuas) {
                foreach ($rows as $r) {
                    $s['total']++;
                    $s['kepuasan'][$r->kepuasan]++;
                    $s['puas'] += (int) ($r->kepuasan >= 4);
                    $jumlahNilai += $r->kepuasan;
                    $s['kategori'][$r->kategori]++;
                    foreach ($r->minat as $m) {
                        $s['minat'][$m] = ($s['minat'][$m] ?? 0) + 1;
                        $s['label_minat'][$m] ??= $m;
                    }
                    $s['harian'][$r->created_at->toDateString()]++;
                    $s['jam'][(int) $r->created_at->format('G')]++;
                    if ($r->nilai_nusa !== null) {
                        $s['penilai_nusa']++;
                        $nusaPuas += (int) ($r->nilai_nusa >= 4);
                    }
                }
            });
        if ($s['total']) {
            $s['persen_puas'] = round(100 * $s['puas'] / $s['total'], 1);
            $s['rata_rata'] = round($jumlahNilai / $s['total'], 2);
        }
        if ($s['penilai_nusa']) {
            $s['persen_puas_nusa'] = round(100 * $nusaPuas / $s['penilai_nusa'], 1);
        }

        return $s;
    }

    public function minatTerpakai(AcaraBukuTamu $a): array
    {
        $kode = [];
        if ($a->exists) {
            $a->respon()->select(['id', 'minat'])->chunkById(1000, function ($rows) use (&$kode) {
                foreach ($rows as $row) {
                    foreach ($row->minat as $minat) {
                        $kode[$minat] = true;
                    }
                }
            });
        }

        return array_keys($kode);
    }

    private function validasiPilihanMinat(array $pilihan, AcaraBukuTamu $a): array
    {
        if (! array_is_list($pilihan)) {
            throw ValidationException::withMessages(['pilihan_minat' => 'Pilihan minat harus berupa daftar berurutan.']);
        }
        $pilihan = array_map(fn ($p) => ['kode' => $p['kode'], 'nama' => trim($p['nama']), 'ikon' => $p['ikon'], 'aktif' => (bool) $p['aktif']], $pilihan);
        $nama = array_map(fn ($p) => mb_strtolower($p['nama']), $pilihan);
        if (in_array('', $nama, true) || count(array_unique($nama)) !== count($nama)) {
            throw ValidationException::withMessages(['pilihan_minat' => 'Nama pilihan tidak boleh kosong atau sama.']);
        }
        if (! array_filter($pilihan, fn ($p) => $p['aktif'])) {
            throw ValidationException::withMessages(['pilihan_minat' => 'Minimal satu pilihan minat harus aktif.']);
        }
        if (array_diff($this->minatTerpakai($a), array_column($pilihan, 'kode'))) {
            throw ValidationException::withMessages(['pilihan_minat' => 'Pilihan yang sudah digunakan tidak dapat dihapus atau diganti kodenya. Nonaktifkan pilihan tersebut.']);
        }

        return $pilihan;
    }

    public function riwayat(AcaraBukuTamu $a, int $penggunaId, string $aksi, array $perubahan, ?int $responId = null, ?string $alasan = null): void
    {
        DB::table('riwayat_acara_buku_tamu')->insert(['acara_buku_tamu_id' => $a->id, 'respon_id' => $responId,
            'pengguna_id' => $penggunaId, 'aksi' => $aksi, 'perubahan' => json_encode($perubahan), 'alasan' => $alasan, 'created_at' => now()]);
    }
}
