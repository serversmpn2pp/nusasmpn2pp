<?php

namespace App\Services\Supervisi;

use App\Models\GuruMataPelajaran;
use App\Models\JadwalPelajaran;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\ProgramSupervisi;
use App\Models\SesiSupervisi;
use App\Models\TahunPelajaran;
use App\Models\VersiInstrumenSupervisi;
use App\Services\Notifikasi\NotifikasiPenggunaService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class JadwalSupervisiService
{
    public function __construct(private AksesSupervisi $akses, private NotifikasiPenggunaService $notifikasi) {}

    public function program(Pengguna $u, array $input, ?ProgramSupervisi $program = null): ProgramSupervisi
    {
        $this->akses->kelola($u);
        $d = Validator::make($input, ['nama' => ['required', 'string', 'max:180'], 'tujuan' => ['required', 'string', 'max:3000'],
            'tahun_pelajaran_id' => ['required', 'integer', 'exists:tahun_pelajaran,id'], 'semester' => ['required', Rule::in(['ganjil', 'genap'])],
            'tanggal_mulai' => ['required', 'date_format:Y-m-d'], 'tanggal_selesai' => ['required', 'date_format:Y-m-d', 'after_or_equal:tanggal_mulai'],
            'status' => ['required', Rule::in(array_keys(ProgramSupervisi::STATUS))],
            'token_pembuatan' => [$program ? 'nullable' : 'required', 'uuid'], 'versi' => [$program ? 'required' : 'nullable', 'integer', 'min:1'],
            'alasan' => [$program ? 'required' : 'nullable', 'string', 'min:10', 'max:1000']])->validate();
        $data = collect($d)->only(['nama', 'tujuan', 'tahun_pelajaran_id', 'semester', 'tanggal_mulai', 'tanggal_selesai', 'status'])->all();
        $data['tahun_pelajaran_id'] = (int) $data['tahun_pelajaran_id'];
        $tahun = TahunPelajaran::findOrFail($data['tahun_pelajaran_id']);
        if (! $tahun->tanggal_mulai || ! $tahun->tanggal_selesai) {
            throw ValidationException::withMessages(['tahun_pelajaran_id' => 'Lengkapi tanggal mulai dan selesai tahun pelajaran sebelum membuat program supervisi.']);
        }
        if ($data['tanggal_mulai'] < $tahun->tanggal_mulai->toDateString() || $data['tanggal_selesai'] > $tahun->tanggal_selesai->toDateString()) {
            throw ValidationException::withMessages(['tanggal_mulai' => 'Periode program harus berada di dalam tahun pelajaran.']);
        }

        return DB::transaction(function () use ($u, $d, $data, $program) {
            $this->akses->kunci();
            if ($program) {
                $p = ProgramSupervisi::lockForUpdate()->findOrFail($program->id);
                $this->akses->versi($p, $d['versi']);
                if ($p->sesi()->exists() && ((int) $p->tahun_pelajaran_id !== $data['tahun_pelajaran_id'] || $p->semester !== $data['semester'])) {
                    throw ValidationException::withMessages(['program' => 'Tahun dan semester tidak dapat diganti setelah sesi dibuat.']);
                }
                if ($p->sesi()->where('status', '!=', 'dibatalkan')->where(fn ($q) => $q->whereDate('waktu_mulai', '<', $data['tanggal_mulai'])->orWhereDate('waktu_selesai', '>', $data['tanggal_selesai']))->exists()) {
                    throw ValidationException::withMessages(['tanggal_mulai' => 'Periode baru harus tetap mencakup sesi yang belum dibatalkan.']);
                }
                $p->update([...$data, 'versi' => $p->versi + 1]);
            } else {
                $sidik = $this->akses->sidik($data);
                if ($p = ProgramSupervisi::where('token_pembuatan', $d['token_pembuatan'])->first()) {
                    $this->akses->pengulangan($p, $u, $sidik);

                    return $p;
                }
                $p = ProgramSupervisi::create([...$data, 'token_pembuatan' => $d['token_pembuatan'], 'sidik_pembuatan' => $sidik, 'pembuat_id' => $u->id, 'versi' => 1]);
            }
            $this->akses->catat($u, $p, $program ? 'Program diperbarui' : 'Program dibuat', $d['alasan'] ?? null);

            return $p->fresh();
        });
    }

    public function tambah(Pengguna $u, ProgramSupervisi $program, array $input): array
    {
        $this->akses->kelola($u);
        $d = Validator::make($input, ['sesi' => ['required', 'array', 'list', 'min:1', 'max:50'],
            'sesi.*.token_pembuatan' => ['required', 'uuid', 'distinct'], 'sesi.*.guru_mata_pelajaran_id' => ['required', 'integer'],
            'sesi.*.versi_instrumen_supervisi_id' => ['required', 'integer'],
            ...$this->aturan('sesi.*.')])->validate();

        return DB::transaction(function () use ($u, $program, $d) {
            $this->akses->kunci();
            $p = ProgramSupervisi::lockForUpdate()->findOrFail($program->id);
            $this->terbuka($p);
            $hasil = [];
            foreach ($d['sesi'] as $baris) {
                $sidik = $this->akses->sidik($this->normal($baris));
                if ($s = SesiSupervisi::where('token_pembuatan', $baris['token_pembuatan'])->first()) {
                    abort_unless($s->program_supervisi_id === $p->id, 404);
                    $this->akses->pengulangan($s, $u, $sidik);
                    $hasil[] = $s;

                    continue;
                }
                $g = GuruMataPelajaran::with(['pegawai', 'kelas', 'mataPelajaran'])->findOrFail($baris['guru_mata_pelajaran_id']);
                if (! $g->aktif || ! $g->pegawai?->aktif || ! $g->kelas?->aktif || ! $g->mataPelajaran?->aktif
                    || (int) $g->tahun_pelajaran_id !== (int) $p->tahun_pelajaran_id || (int) $g->kelas->tahun_pelajaran_id !== (int) $p->tahun_pelajaran_id) {
                    throw ValidationException::withMessages(['sesi' => 'Pilih penugasan guru aktif yang sesuai tahun pelajaran program.']);
                }
                $i = VersiInstrumenSupervisi::with('instrumen')->findOrFail($baris['versi_instrumen_supervisi_id']);
                if ($i->status !== 'terbit' || ! $i->instrumen->aktif) {
                    throw ValidationException::withMessages(['instrumen' => 'Pilih versi instrumen yang sudah terbit dan aktif.']);
                }
                $s = new SesiSupervisi(['program_supervisi_id' => $p->id, 'guru_mata_pelajaran_id' => $g->id,
                    'guru_pegawai_id' => $g->pegawai_id, 'kelas_id' => $g->kelas_id, 'versi_instrumen_supervisi_id' => $i->id,
                    'token_pembuatan' => $baris['token_pembuatan'], 'sidik_pembuatan' => $sidik, 'pembuat_id' => $u->id,
                    'status' => 'draf', 'versi' => 1, 'konteks' => ['guru' => $g->pegawai->nama_lengkap, 'mapel' => $g->mataPelajaran->nama,
                        'kelas' => $g->kelas->nama, 'fase' => 'D', 'instrumen' => $i->nama, 'instrumen_nomor' => $i->nomor]]);
                $s->fill($this->dataWaktu($baris));
                $this->periksa($p, $s);
                $s->save();
                $this->akses->catat($u, $s, 'Draf jadwal dibuat');
                $hasil[] = $s;
            }

            return $hasil;
        });
    }

    public function ubah(Pengguna $u, SesiSupervisi $sesi, array $input): SesiSupervisi
    {
        $this->akses->kelola($u);
        $d = Validator::make($input, [...$this->aturan(), 'versi' => ['required', 'integer', 'min:1'], 'alasan' => ['required', 'string', 'min:10', 'max:1000']])->validate();

        return DB::transaction(function () use ($u, $sesi, $d) {
            $this->akses->kunci();
            $s = SesiSupervisi::lockForUpdate()->findOrFail($sesi->id);
            $this->akses->versi($s, $d['versi']);
            $this->terbuka($s->program);
            if ($s->status === 'dibatalkan') {
                throw ValidationException::withMessages(['sesi' => 'Jadwal dibatalkan tidak dapat diubah. Buat sesi baru.']);
            }
            $lama = (int) $s->supervisor_pegawai_id;
            if ($s->penilaian?->status === 'final') {
                throw ValidationException::withMessages(['sesi' => 'Hasil sudah final. Buka koreksi penilaian terlebih dahulu sebelum mengubah jadwal.']);
            }
            if ($s->penilaian && (int) $d['supervisor_pegawai_id'] !== $lama) {
                throw ValidationException::withMessages(['supervisor_pegawai_id' => 'Supervisor tidak dapat dialihkan setelah penilaian dibuat. Riwayat penilai harus tetap terjaga.']);
            }
            $s->fill($this->dataWaktu($d));
            $this->periksa($s->program, $s);
            $s->versi++;
            $s->save();
            $this->akses->catat($u, $s, 'Jadwal diubah', trim($d['alasan']));
            if ($s->diterbitkan_pada) {
                $this->beritahu($s, 'Jadwal supervisi diperbarui', [$lama]);
            }

            return $s->fresh();
        });
    }

    public function tindakan(Pengguna $u, ProgramSupervisi $program, string $aksi, array $input): array
    {
        $this->akses->kelola($u);
        $d = Validator::make($input, ['sesi' => ['required', 'array', 'list', 'min:1', 'max:100'],
            'sesi.*.id' => ['required', 'integer', 'distinct'], 'sesi.*.versi' => ['required', 'integer', 'min:1'],
            'konfirmasi' => ['required', 'accepted'], 'alasan' => ['required', 'string', 'min:10', 'max:1000']])->validate();

        return DB::transaction(function () use ($u, $program, $aksi, $d) {
            $this->akses->kunci();
            $p = ProgramSupervisi::lockForUpdate()->findOrFail($program->id);
            $this->terbuka($p);
            if ($aksi === 'terbitkan' && $p->status !== 'aktif') {
                throw ValidationException::withMessages(['program' => 'Aktifkan program sebelum menerbitkan jadwal.']);
            }
            $hasil = [];
            foreach ($d['sesi'] as $item) {
                $s = $p->sesi()->lockForUpdate()->findOrFail($item['id']);
                $this->akses->versi($s, $item['versi']);
                if ($aksi === 'terbitkan') {
                    if ($s->status !== 'draf') {
                        throw ValidationException::withMessages(['sesi' => 'Penerbitan hanya untuk draf. Muat ulang daftar jadwal.']);
                    }
                    $this->periksa($p, $s);
                    $s->fill(['status' => 'terjadwal', 'diterbitkan_pada' => now()]);
                } else {
                    if ($s->penilaian?->status === 'final') {
                        throw ValidationException::withMessages(['sesi' => 'Sesi dengan hasil final tidak dapat dibatalkan. Buka koreksi terlebih dahulu.']);
                    }
                    if ($s->status === 'dibatalkan') {
                        throw ValidationException::withMessages(['sesi' => 'Sesi sudah dibatalkan.']);
                    }
                    $s->status = 'dibatalkan';
                }
                $s->versi++;
                $s->save();
                $this->akses->catat($u, $s, 'Jadwal: '.$aksi, trim($d['alasan']));
                if ($s->diterbitkan_pada) {
                    $this->beritahu($s, $aksi === 'terbitkan' ? 'Jadwal supervisi diterbitkan' : 'Jadwal supervisi dibatalkan');
                }
                $hasil[] = $s;
            }

            return $hasil;
        });
    }

    private function aturan(string $p = ''): array
    {
        return [$p.'supervisor_pegawai_id' => ['required', 'integer'], $p.'waktu_mulai' => ['required', 'date_format:Y-m-d\TH:i'],
            $p.'waktu_selesai' => ['required', 'date_format:Y-m-d\TH:i'], $p.'tempat' => ['required', 'string', 'max:180'],
            $p.'topik' => ['nullable', 'string', 'max:500'], $p.'alasan_di_luar_jadwal' => ['nullable', 'string', 'min:10', 'max:1000']];
    }

    private function normal(array $b): array
    {
        return ['guru_mata_pelajaran_id' => (int) $b['guru_mata_pelajaran_id'], 'versi_instrumen_supervisi_id' => (int) $b['versi_instrumen_supervisi_id'],
            'supervisor_pegawai_id' => (int) $b['supervisor_pegawai_id'], 'waktu_mulai' => $b['waktu_mulai'], 'waktu_selesai' => $b['waktu_selesai'],
            'tempat' => trim($b['tempat']), 'topik' => trim($b['topik'] ?? ''), 'alasan_di_luar_jadwal' => trim($b['alasan_di_luar_jadwal'] ?? '')];
    }

    private function dataWaktu(array $b): array
    {
        return ['supervisor_pegawai_id' => (int) $b['supervisor_pegawai_id'],
            'waktu_mulai' => Carbon::createFromFormat('!Y-m-d\TH:i', $b['waktu_mulai'], config('app.timezone')),
            'waktu_selesai' => Carbon::createFromFormat('!Y-m-d\TH:i', $b['waktu_selesai'], config('app.timezone')),
            'tempat' => trim($b['tempat']), 'topik' => trim($b['topik'] ?? ''), 'alasan_di_luar_jadwal' => trim($b['alasan_di_luar_jadwal'] ?? '') ?: null];
    }

    private function terbuka(ProgramSupervisi $p): void
    {
        if ($p->status === 'diarsipkan') {
            throw ValidationException::withMessages(['program' => 'Program diarsipkan. Aktifkan kembali sebelum mengubah jadwal.']);
        }
    }

    private function periksa(ProgramSupervisi $p, SesiSupervisi $s): void
    {
        $g = GuruMataPelajaran::with(['kelas', 'mataPelajaran'])->findOrFail($s->guru_mata_pelajaran_id);
        if (! $g->aktif || ! $g->kelas?->aktif || ! $g->mataPelajaran?->aktif || (int) $g->pegawai_id !== (int) $s->guru_pegawai_id
            || (int) $g->kelas_id !== (int) $s->kelas_id || (int) $g->tahun_pelajaran_id !== (int) $p->tahun_pelajaran_id
            || (int) $g->kelas->tahun_pelajaran_id !== (int) $p->tahun_pelajaran_id) {
            throw ValidationException::withMessages(['penugasan' => 'Penugasan guru telah berubah atau tidak aktif. Periksa kembali sebelum menerbitkan atau mengubah jadwal.']);
        }
        $awal = $s->waktu_mulai;
        $akhir = $s->waktu_selesai;
        if (! $awal->isSameDay($akhir) || ! $akhir->gt($awal) || $awal->toDateString() < $p->tanggal_mulai->toDateString() || $akhir->toDateString() > $p->tanggal_selesai->toDateString()) {
            throw ValidationException::withMessages(['waktu_mulai' => 'Waktu mulai dan selesai harus berurutan pada hari yang sama, di dalam periode program.']);
        }
        $ids = [(int) $s->guru_pegawai_id, (int) $s->supervisor_pegawai_id];
        if ($ids[0] === $ids[1]) {
            throw ValidationException::withMessages(['supervisor_pegawai_id' => 'Guru tidak boleh menjadi supervisor untuk dirinya sendiri.']);
        }
        foreach ($ids as $id) {
            if (! Pegawai::whereKey($id)->where('aktif', true)->whereHas('pengguna', fn ($q) => $q->where('aktif', true)->whereNull('siswa_id')->where('peran', '!=', 'orang_tua'))->exists()) {
                throw ValidationException::withMessages(['supervisor_pegawai_id' => 'Guru dan supervisor harus merupakan pegawai aktif dengan akun aktif.']);
            }
        }
        $bentrok = SesiSupervisi::where('status', '!=', 'dibatalkan')->when($s->exists, fn ($q) => $q->whereKeyNot($s->id))
            ->where('waktu_mulai', '<', $akhir)->where('waktu_selesai', '>', $awal)
            ->where(fn ($q) => $q->whereIn('guru_pegawai_id', $ids)->orWhereIn('supervisor_pegawai_id', $ids)->orWhere('kelas_id', $s->kelas_id))->exists();
        if ($bentrok) {
            throw ValidationException::withMessages(['jadwal' => 'Bentrok dengan sesi supervisi lain untuk guru, supervisor, atau kelas pada waktu yang sama.']);
        }
        $hari = ['minggu', 'senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu'][$awal->dayOfWeek];
        $jadwal = JadwalPelajaran::with(['jamPelajaran', 'guruMataPelajaran'])->where('aktif', true)->where('tahun_pelajaran_id', $p->tahun_pelajaran_id)->where('hari', $hari)
            ->whereHas('jamPelajaran', fn ($q) => $q->where('aktif', true)->where('jenis', 'pelajaran')->where('jam_mulai', '<', $akhir->format('H:i:s'))->where('jam_selesai', '>', $awal->format('H:i:s')))->get();
        foreach ($jadwal as $j) {
            if ((int) $j->guruMataPelajaran?->pegawai_id === $ids[1]) {
                throw ValidationException::withMessages(['jadwal' => 'Supervisor masih memiliki jadwal mengajar pada waktu ini.']);
            }
            if (((int) $j->kelas_id === (int) $s->kelas_id || (int) $j->guruMataPelajaran?->pegawai_id === $ids[0]) && (int) $j->guru_mata_pelajaran_id !== (int) $s->guru_mata_pelajaran_id) {
                throw ValidationException::withMessages(['jadwal' => 'Kelas atau guru sedang dijadwalkan untuk pelajaran lain.']);
            }
        }
        $rentang = $jadwal->where('guru_mata_pelajaran_id', $s->guru_mata_pelajaran_id)->sortBy(fn ($j) => $j->jamPelajaran->jam_mulai);
        $batas = $awal->format('H:i:s');
        foreach ($rentang as $j) {
            if ($j->jamPelajaran->jam_mulai <= $batas && $j->jamPelajaran->jam_selesai > $batas) {
                $batas = $j->jamPelajaran->jam_selesai;
            }
        }
        if ($batas < $akhir->format('H:i:s') && ! filled($s->alasan_di_luar_jadwal)) {
            throw ValidationException::withMessages(['alasan_di_luar_jadwal' => 'Waktu tidak sepenuhnya sesuai jadwal mengajar. Isi alasan penjadwalan khusus minimal 10 karakter.']);
        }
    }

    private function beritahu(SesiSupervisi $s, string $judul, array $tambahan = []): void
    {
        $ids = array_unique([(int) $s->guru_pegawai_id, (int) $s->supervisor_pegawai_id, ...$tambahan]);
        foreach ($ids as $id) {
            $penerima = $this->notifikasi->penggunaUntukPegawai($id)->filter(fn ($u) => $u->akunPegawai() && ! $u->akunSiswa() && ! $u->akunOrangTua());
            $dialihkan = ! in_array($id, [(int) $s->guru_pegawai_id, (int) $s->supervisor_pegawai_id], true);
            $pesan = $s->konteks['mapel'].' - '.$s->konteks['kelas'].', '.$s->waktu_mulai->format('d-m-Y H:i').'. ';
            $pesan .= $dialihkan ? 'Penugasan supervisor telah dialihkan dan tidak lagi tercantum pada jadwal Anda.' : 'Periksa rincian penugasan di NUSA.';
            $this->notifikasi->kirimKeBanyak($penerima, 'informasi', $dialihkan ? 'Penugasan supervisor telah dialihkan' : $judul,
                $pesan, $dialihkan ? '/supervisi' : '/supervisi/sesi/'.$s->id, 'supervisi:'.$s->id.':'.$s->versi);
        }
    }
}
