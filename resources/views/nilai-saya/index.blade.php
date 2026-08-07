@extends('layouts.app')

@section('title', 'Nilai Saya - NUSA')

@section('content')
    <style>
        .my-grade-page{display:grid;gap:18px}
        .my-grade-hero{align-items:center;background:#15477a;border-radius:8px;color:#fff;display:flex;gap:18px;justify-content:space-between;padding:20px 22px}
        .my-grade-hero h1{color:#fff;font-size:1.65rem;letter-spacing:0;margin:0}
        .my-grade-hero p{color:#dbeafe;line-height:1.55;margin:7px 0 0;max-width:720px}
        .my-grade-class{background:#fff;border-radius:7px;color:#15477a;flex:0 0 auto;font-size:.84rem;font-weight:900;padding:9px 13px;text-align:center}
        .my-grade-filter{display:grid;gap:14px;grid-template-columns:minmax(220px,1fr) minmax(180px,.65fr) auto}
        .my-grade-filter .actions{align-self:end;justify-content:flex-end}
        .my-grade-summary{display:grid;gap:12px;grid-template-columns:repeat(3,minmax(0,1fr))}
        .my-grade-stat{background:#fff;border:1px solid #dce4eb;border-top:4px solid #15477a;border-radius:8px;padding:16px}
        .my-grade-stat.is-green{border-top-color:#16a34a}
        .my-grade-stat.is-yellow{border-top-color:#f1c40f}
        .my-grade-stat span{color:#64748b;display:block;font-size:.78rem;font-weight:800}
        .my-grade-stat strong{color:#15477a;display:block;font-size:1.75rem;line-height:1;margin-top:9px}
        .my-grade-list{display:grid;gap:12px}
        .my-grade-card{background:#fff;border:1px solid #dce4eb;border-radius:8px;overflow:hidden}
        .my-grade-card[open]{box-shadow:0 10px 26px rgba(31,41,55,.06)}
        .my-grade-card.is-locked{border-color:#e5cf61}
        .my-grade-card.is-locked .my-grade-card-summary{grid-template-columns:minmax(0,1fr) auto}
        .my-grade-card-summary{align-items:center;cursor:pointer;display:grid;gap:16px;grid-template-columns:minmax(0,1fr) auto auto;list-style:none;padding:17px}
        .my-grade-card-summary::-webkit-details-marker{display:none}
        .my-grade-subject{min-width:0}
        .my-grade-subject h2{color:#172536;font-size:1rem;letter-spacing:0;line-height:1.35;margin:0;overflow-wrap:anywhere}
        .my-grade-subject p{color:#64748b;font-size:.78rem;line-height:1.45;margin:5px 0 0}
        .my-grade-final{min-width:108px;text-align:right}
        .my-grade-final span{color:#64748b;display:block;font-size:.7rem;font-weight:800}
        .my-grade-final strong{color:#15477a;display:block;font-size:1.45rem;line-height:1.1;margin-top:4px}
        .my-grade-final small{display:block;font-size:.7rem;font-weight:900;margin-top:4px}
        .my-grade-final small.pass{color:#15803d}
        .my-grade-final small.pending{color:#8a6a00}
        .my-grade-chevron{align-items:center;background:#eef5fb;border-radius:6px;color:#15477a;display:inline-flex;height:36px;justify-content:center;transition:transform .2s;width:36px}
        .my-grade-chevron::before{border-bottom:2px solid currentColor;border-right:2px solid currentColor;content:"";height:7px;transform:rotate(45deg) translate(-1px,-1px);width:7px}
        .my-grade-card[open] .my-grade-chevron{transform:rotate(180deg)}
        .my-grade-body{border-top:1px solid #e5eaf0;padding:17px}
        .my-grade-category{border:1px solid #e1e7ed;border-radius:7px;display:grid;grid-template-columns:repeat(5,minmax(0,1fr));overflow:hidden}
        .my-grade-category-item{border-right:1px solid #e1e7ed;min-width:0;padding:12px}
        .my-grade-category-item:last-child{border-right:0}
        .my-grade-category-item span{color:#64748b;display:block;font-size:.72rem;font-weight:800;line-height:1.35}
        .my-grade-category-item strong{color:#172536;display:block;font-size:1.05rem;margin-top:6px}
        .my-grade-category-item small{color:#8a98a8;display:block;font-size:.68rem;margin-top:4px}
        .my-grade-components{display:grid;margin-top:15px}
        .my-grade-component{align-items:center;border-bottom:1px solid #edf1f4;display:grid;gap:14px;grid-template-columns:130px minmax(0,1fr) auto;padding:11px 0}
        .my-grade-component:last-child{border-bottom:0;padding-bottom:0}
        .my-grade-kind{color:#15477a;font-size:.73rem;font-weight:900}
        .my-grade-component-name{min-width:0}
        .my-grade-component-name strong{color:#172536;display:block;font-size:.84rem;line-height:1.4;overflow-wrap:anywhere}
        .my-grade-component-name small{color:#64748b;display:block;font-size:.72rem;margin-top:3px}
        .my-grade-value{color:#15477a;font-size:1rem;font-weight:900;text-align:right;white-space:nowrap}
        .my-grade-note{background:#eef5fb;border-left:4px solid #2582bd;border-radius:6px;color:#36566f;font-size:.78rem;line-height:1.55;margin-top:14px;padding:11px 12px}
        .my-grade-lock{align-items:center;background:#fffbea;border-top:1px solid #f1dc79;display:flex;gap:16px;justify-content:space-between;padding:15px 17px}
        .my-grade-lock strong{color:#594700;display:block;font-size:.86rem}
        .my-grade-lock p{color:#77672d;font-size:.75rem;line-height:1.5;margin:4px 0 0}
        .my-grade-lock .button{flex:0 0 auto}
        .my-grade-lock-label{background:#fff4bd;border-radius:6px;color:#725b00;display:inline-flex;font-size:.72rem;font-weight:900;padding:7px 9px;white-space:nowrap}
        .my-grade-empty{background:#fff;border:1px dashed #b9c8d6;border-radius:8px;color:#64748b;padding:34px 18px;text-align:center}
        .my-grade-empty strong{color:#15477a;display:block;font-size:1rem;margin-bottom:6px}
        @media(max-width:850px){.my-grade-filter{grid-template-columns:1fr 1fr}.my-grade-filter .actions{grid-column:1/-1}.my-grade-category{grid-template-columns:repeat(2,minmax(0,1fr))}.my-grade-category-item{border-bottom:1px solid #e1e7ed}.my-grade-category-item:nth-child(2n){border-right:0}.my-grade-category-item:last-child{grid-column:1/-1;border-bottom:0}}
        @media(max-width:640px){.my-grade-hero{align-items:flex-start;flex-direction:column;padding:17px}.my-grade-class{width:100%}.my-grade-filter{grid-template-columns:1fr}.my-grade-filter .actions{grid-column:auto}.my-grade-filter .actions .button{flex:1}.my-grade-summary{grid-template-columns:repeat(3,minmax(0,1fr));gap:7px}.my-grade-stat{padding:12px 9px}.my-grade-stat span{font-size:.68rem}.my-grade-stat strong{font-size:1.4rem}.my-grade-card-summary{gap:10px;grid-template-columns:minmax(0,1fr) auto;padding:14px}.my-grade-final{min-width:70px}.my-grade-chevron{display:none}.my-grade-body{padding:14px}.my-grade-component{align-items:start;grid-template-columns:1fr auto}.my-grade-kind{grid-column:1/-1}.my-grade-component-name{grid-column:1}.my-grade-value{grid-column:2;grid-row:2}.my-grade-lock{align-items:stretch;flex-direction:column}.my-grade-lock .button{justify-content:center;width:100%}}
    </style>

    @php
        $labelPredikat = fn (?string $predikat) => match ($predikat) {
            'SB' => 'SB - Sangat Baik',
            'B' => 'B - Baik',
            'C' => 'C - Cukup',
            'K' => 'K - Kurang',
            default => 'Belum dinilai',
        };
        $labelKategori = [
            'formatif' => 'Formatif',
            'sumatif' => 'Sumatif',
            'sts' => 'STS',
            'sas_saj' => (int) $anggotaKelas?->kelas?->tingkat === 9 ? 'SAJ' : 'SAS',
        ];
    @endphp

    <div class="my-grade-page">
        <section class="my-grade-hero">
            <div>
                <h1>Nilai Saya</h1>
                <p>Nilai yang tampil di halaman ini telah dipublikasikan oleh guru mata pelajaran.</p>
            </div>
            <div class="my-grade-class">
                {{ $anggotaKelas?->kelas?->nama ?: 'Belum ditempatkan di kelas' }}
            </div>
        </section>

        @if (session('berhasil'))
            <div class="alert">{{ session('berhasil') }}</div>
        @endif

        <form method="GET" class="panel panel-pad">
            <div class="my-grade-filter">
                <div class="field">
                    <label for="tahun_pelajaran_id">Tahun pelajaran</label>
                    <select id="tahun_pelajaran_id" name="tahun_pelajaran_id" class="select">
                        @foreach ($daftarTahunPelajaran as $tahun)
                            <option value="{{ $tahun->id }}" @selected((int) $tahunPelajaranId === (int) $tahun->id)>
                                {{ $tahun->nama }}{{ $tahun->aktif ? ' (aktif)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="semester">Semester</label>
                    <select id="semester" name="semester" class="select">
                        <option value="ganjil" @selected($semester === 'ganjil')>Ganjil</option>
                        <option value="genap" @selected($semester === 'genap')>Genap</option>
                    </select>
                </div>
                <div class="actions">
                    <button type="submit" class="button button-primary">Tampilkan</button>
                </div>
            </div>
        </form>

        @if (! $siswa)
            <section class="my-grade-empty">
                <strong>Akun belum terhubung ke data siswa</strong>
                Hubungi administrator sekolah agar akun ini dihubungkan dengan data siswa yang benar.
            </section>
        @elseif (! $anggotaKelas)
            <section class="my-grade-empty">
                <strong>Data kelas tidak ditemukan</strong>
                Anda belum tercatat sebagai anggota kelas pada tahun pelajaran yang dipilih.
            </section>
        @else
            <section class="my-grade-summary" aria-label="Ringkasan nilai siswa">
                <article class="my-grade-stat">
                    <span>Mata pelajaran dirilis</span>
                    <strong>{{ $ringkasan['mata_pelajaran'] }}</strong>
                </article>
                <article class="my-grade-stat is-green">
                    <span>Nilai sudah terbuka</span>
                    <strong>{{ $ringkasan['nilai_terbuka'] }}</strong>
                </article>
                <article class="my-grade-stat is-yellow">
                    <span>Survei perlu diisi</span>
                    <strong>{{ $ringkasan['survei_belum_diisi'] }}</strong>
                </article>
            </section>

            <section class="my-grade-list" aria-label="Daftar nilai per mata pelajaran">
                @forelse ($daftarNilai as $index => $item)
                    @if (! $item['survei_diisi'])
                        <article class="my-grade-card is-locked" id="mapel-{{ $item['guru_mata_pelajaran']->id }}">
                            <header class="my-grade-card-summary">
                                <div class="my-grade-subject">
                                    <h2>{{ $item['mata_pelajaran']?->nama ?: 'Mata pelajaran' }}</h2>
                                    <p>
                                        {{ $item['guru_mata_pelajaran']?->pegawai?->nama_lengkap ?: 'Guru belum dicantumkan' }}
                                        - Dipublikasikan {{ $item['publikasi']->dipublikasikan_pada?->locale('id')->translatedFormat('d F Y') }}
                                    </p>
                                </div>
                                <span class="my-grade-lock-label">Nilai terkunci</span>
                            </header>
                            <div class="my-grade-lock">
                                <div>
                                    <strong>Isi survei pembelajaran untuk membuka nilai</strong>
                                    <p>Survei singkat ini hanya perlu diisi satu kali untuk mata pelajaran dan semester ini.</p>
                                </div>
                                <a
                                    href="{{ route('survei-pembelajaran.create', [$item['guru_mata_pelajaran'], $semester]) }}"
                                    class="button button-primary"
                                >Isi survei</a>
                            </div>
                        </article>
                    @else
                    <details class="my-grade-card" id="mapel-{{ $item['guru_mata_pelajaran']->id }}" @if ($index === 0) open @endif>
                        <summary class="my-grade-card-summary">
                            <div class="my-grade-subject">
                                <h2>{{ $item['mata_pelajaran']?->nama ?: 'Mata pelajaran' }}</h2>
                                <p>
                                    {{ $item['guru_mata_pelajaran']?->pegawai?->nama_lengkap ?: 'Guru belum dicantumkan' }}
                                    - Dipublikasikan {{ $item['publikasi']->dipublikasikan_pada?->locale('id')->translatedFormat('d F Y') }}
                                </p>
                            </div>
                            <div class="my-grade-final">
                                @if ($item['menggunakan_predikat'])
                                    <span>Jenis nilai</span>
                                    <strong>Predikat</strong>
                                @else
                                    <span>Nilai akhir</span>
                                    <strong>{{ $item['nilai_akhir'] === null ? '-' : number_format($item['nilai_akhir'], 2, ',', '.') }}</strong>
                                    @if ($item['nilai_akhir'] !== null && $item['kkm'] !== null)
                                        <small class="{{ $item['tuntas'] ? 'pass' : 'pending' }}">
                                            {{ $item['tuntas'] ? 'Tuntas' : 'Belum tuntas' }} - KKM {{ $item['kkm'] }}
                                        </small>
                                    @elseif (! $item['lengkap'] && ! $item['menggunakan_predikat'])
                                        <small class="pending">Belum lengkap</small>
                                    @endif
                                @endif
                            </div>
                            <span class="my-grade-chevron" aria-hidden="true"></span>
                        </summary>

                        <div class="my-grade-body">
                            @if (! $item['menggunakan_predikat'])
                                <div class="my-grade-category">
                                    @foreach ($labelKategori as $kode => $label)
                                        <div class="my-grade-category-item">
                                            <span>{{ $label }}</span>
                                            <strong>
                                                {{ $item['kategori'][$kode]['rata'] === null ? '-' : number_format($item['kategori'][$kode]['rata'], 2, ',', '.') }}
                                            </strong>
                                            <small>
                                                {{ $item['kategori'][$kode]['terisi'] }}/{{ $item['kategori'][$kode]['target'] }} nilai
                                                - Bobot {{ $item['kategori'][$kode]['bobot'] }}%
                                            </small>
                                        </div>
                                    @endforeach
                                    <div class="my-grade-category-item">
                                        <span>Nilai akhir</span>
                                        <strong>{{ $item['nilai_akhir'] === null ? '-' : number_format($item['nilai_akhir'], 2, ',', '.') }}</strong>
                                        <small>{{ $skemaBobotNilai ? 'Berdasarkan skema bobot' : 'Skema bobot belum tersedia' }}</small>
                                    </div>
                                </div>
                            @endif

                            <div class="my-grade-components">
                                @foreach ($item['komponen'] as $komponen)
                                    <div class="my-grade-component">
                                        <span class="my-grade-kind">{{ $komponen['label_jenis'] }}</span>
                                        <div class="my-grade-component-name">
                                            <strong>{{ $komponen['nama'] }}</strong>
                                            @if ($komponen['tanggal'])
                                                <small>{{ $komponen['tanggal']->locale('id')->translatedFormat('d F Y') }}</small>
                                            @endif
                                            @if ($komponen['catatan'])
                                                <small>{{ $komponen['catatan'] }}</small>
                                            @endif
                                        </div>
                                        <span class="my-grade-value">
                                            @if ($item['menggunakan_predikat'])
                                                {{ $labelPredikat($komponen['predikat']) }}
                                            @else
                                                {{ $komponen['nilai'] === null ? '-' : number_format($komponen['nilai'], 2, ',', '.') }}
                                            @endif
                                        </span>
                                    </div>
                                @endforeach
                            </div>

                            @if (! $item['menggunakan_predikat'] && ! $item['lengkap'])
                                <div class="my-grade-note">
                                    Nilai akhir belum tersedia karena komponen berbobot belum lengkap atau skema bobot belum ditetapkan.
                                </div>
                            @endif
                        </div>
                    </details>
                    @endif
                @empty
                    <div class="my-grade-empty">
                        <strong>Belum ada nilai yang dipublikasikan</strong>
                        Nilai akan tampil setelah guru mata pelajaran mempublikasikannya.
                    </div>
                @endforelse
            </section>
        @endif
    </div>
@endsection
