@php
    use App\Models\ResponAcaraBukuTamu as Respon;
    $ikonNilai = [1 => 'frown', 2 => 'annoyed', 3 => 'meh', 4 => 'smile', 5 => 'laugh'];
@endphp
<!DOCTYPE html>
<html lang="id" translate="no">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="google" content="notranslate">
    <meta name="robots" content="noindex,nofollow"><title>Buku Tamu &middot; {{ $acara->nama }} - NUSA</title>
    <link rel="icon" href="{{ asset('images/logo-nusa.png') }}">@fonts
    <link rel="stylesheet" href="{{ asset('css/acara-buku-tamu-publik.css') }}?v={{ filemtime(public_path('css/acara-buku-tamu-publik.css')) }}">
    <script defer src="{{ asset('vendor/lucide/lucide.min.js') }}"></script>
    <script defer src="{{ asset('js/acara-buku-tamu-publik.js') }}?v={{ filemtime(public_path('js/acara-buku-tamu-publik.js')) }}"></script>
</head>
<body>
    <header class="visitor-brand"><div class="visitor-brand-inner"><img src="{{ asset('images/logo-nusa.png') }}" alt="Logo NUSA" width="48" height="48"><div><strong>NUSA<span>.</span></strong><p>SMP Negeri 2 Padang Panjang</p></div><span class="brand-seal"><i data-lucide="book-open" aria-hidden="true"></i>Buku tamu & pameran</span></div></header>
    <main>
        <div class="visitor-cover" data-banner-tampilan="{{ $acara->banner_tampilan }}" style="{{ $acara->gayaBanner() }}"><img src="{{ $acara->urlBanner() }}" alt="{{ $acara->banner ? 'Banner '.$acara->nama : 'Lingkungan SMP Negeri 2 Padang Panjang' }}" fetchpriority="high"><div class="cover-caption"><i data-lucide="school" aria-hidden="true"></i>SMP NEGERI 2 PADANG PANJANG</div><div class="visitor-cover-stripe" aria-hidden="true"><span></span></div></div>
        <div class="visitor-content">
            <section class="visitor-intro"><p class="visitor-eyebrow">BUKU TAMU PENGUNJUNG</p><h1>{{ $acara->nama }}</h1><div class="visitor-meta"><span><i data-lucide="map-pin" aria-hidden="true"></i>{{ $acara->lokasi }}</span><span><i data-lucide="calendar-days" aria-hidden="true"></i>{{ $acara->mulai_pada->locale('id')->translatedFormat('j F Y') }}@if(!$acara->mulai_pada->isSameDay($acara->selesai_pada)) &ndash; {{ $acara->selesai_pada->locale('id')->translatedFormat('j F Y') }}@endif</span></div></section>
            @if($sukses)
                <section class="visitor-result"><div class="result-symbol"><i data-lucide="circle-check" aria-hidden="true"></i></div><p class="visitor-eyebrow">KUNJUNGAN TERCATAT</p><h2>Terima kasih sudah hadir.</h2><p>Kunjungan dan kesan Anda menjadi bagian dari perjalanan karya siswa kami. Selamat menikmati pameran!</p><a class="visitor-submit" href="{{ route('kunjungi-acara.show', $acara->kode_publik) }}"><i data-lucide="user-plus" aria-hidden="true"></i>Pengunjung berikutnya</a></section>
            @elseif(!$acara->menerima() && !($pratinjau ?? false))
                <section class="visitor-result"><div class="result-symbol result-symbol--yellow"><i data-lucide="calendar-clock" aria-hidden="true"></i></div><h2>{{ $acara->status === 'dibuka' && now()->lt($acara->mulai_pada) ? 'Sampai bertemu di pameran.' : 'Terima kasih atas antusiasmenya.' }}</h2><p>{{ $acara->status === 'dibuka' && now()->lt($acara->mulai_pada) ? 'Buku tamu mulai dibuka pada '.$acara->mulai_pada->format('d/m/Y').' pukul '.$acara->mulai_pada->format('H:i').' WIB.' : 'Pengisian buku tamu untuk acara ini sudah ditutup.' }}</p></section>
            @else
                <p class="visitor-welcome">@if($acara->pengantar){{ $acara->pengantar }}@else<strong>Selamat datang di stand kami.</strong> Mari berbagi karya, cerita, dan inspirasi. Kehadiran serta kesan Anda berarti bagi perjalanan siswa kami.@endif</p>
                @if($errors->any())<div class="visitor-errors" role="alert" tabindex="-1"><strong>Isian belum dapat dikirim.</strong><ul>@foreach($errors->all() as $pesan)<li>{{ $pesan }}</li>@endforeach</ul></div>@endif
                @if($pratinjau ?? false)<div class="visitor-preview-notice">Pratinjau petugas &middot; pengiriman dinonaktifkan</div>@endif
                <form method="POST" action="{{ route('kunjungi-acara.store', $acara->kode_publik) }}" id="visitor-form" data-preview="{{ ($pratinjau ?? false) ? 'true' : 'false' }}">@csrf
                    @foreach($tiket as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $errors->has('formulir') ? $value : old($key, $value) }}">@endforeach
                    <div class="visitor-trap" aria-hidden="true"><label for="website">Website</label><input id="website" name="website" type="text" tabindex="-1" autocomplete="off"></div>
                    <section class="visitor-section"><div class="visitor-section-title"><span>01</span><h2>Senang berkenalan dengan Anda</h2></div>
                        <div class="visitor-field"><label for="nama">Nama <span aria-hidden="true">*</span></label><input id="nama" name="nama" value="{{ old('nama') }}" placeholder="Nama Anda atau nama panggilan" autocomplete="name" required minlength="2" maxlength="120" @error('nama') aria-invalid="true" aria-describedby="error-nama" @enderror>@error('nama')<small id="error-nama" class="field-error">{{ $message }}</small>@enderror</div>
                        <div class="visitor-two-fields"><div class="visitor-field"><label for="kategori">Anda hadir sebagai <span aria-hidden="true">*</span></label><select id="kategori" name="kategori" required><option value="">Pilih kategori</option>@foreach(Respon::KATEGORI as $key => $label)<option value="{{ $key }}" @selected(old('kategori') === $key)>{{ $label }}</option>@endforeach</select></div><div class="visitor-field"><label for="instansi">Instansi / sekolah asal <small>Opsional</small></label><input id="instansi" name="instansi" value="{{ old('instansi') }}" maxlength="180" placeholder="Nama instansi atau sekolah" autocomplete="organization"></div></div>
                    </section>
                    <fieldset class="visitor-section"><legend>Apa yang paling menarik perhatian Anda?</legend><p class="visitor-field-note">Boleh lebih dari satu.</p><div class="interest-choices">@foreach($acara->pilihanMinat(true) as $pilihan)<label class="interest-choice{{ mb_strlen($pilihan['nama']) > 30 ? ' interest-choice--wide' : '' }}"><input type="checkbox" name="minat[]" value="{{ $pilihan['kode'] }}" @checked(in_array($pilihan['kode'], (array) old('minat', []), true))><span><i data-lucide="{{ $pilihan['ikon'] }}" aria-hidden="true"></i><span class="interest-label">{{ $pilihan['nama'] }}</span><i data-lucide="check" class="choice-check" aria-hidden="true"></i></span></label>@endforeach</div></fieldset>
                    <fieldset class="visitor-section"><legend>Bagaimana pengalaman Anda di stand kami?</legend><p class="visitor-field-note">Penilaian keseluruhan stand.</p><div class="satisfaction-choices">@foreach(array_reverse(Respon::KEPUASAN, true) as $score => $label)<label class="satisfaction-choice"><input type="radio" name="kepuasan" value="{{ $score }}" required @checked((int) old('kepuasan') === $score)><span class="rating-content" data-score="{{ $score }}"><i data-lucide="{{ $ikonNilai[$score] }}" aria-hidden="true"></i><strong>{{ $label }}</strong></span></label>@endforeach</div></fieldset>
                    <section class="visitor-section visitor-nusa"><div class="nusa-question-icon"><i data-lucide="monitor-smartphone" aria-hidden="true"></i></div><div class="visitor-field"><label for="nilai_nusa">Sudah mencoba NUSA? Bagaimana kesannya?</label><select id="nilai_nusa" name="nilai_nusa"><option value="0">Belum mencoba / tidak menilai</option>@foreach(Respon::KEPUASAN as $score => $label)<option value="{{ $score }}" @selected((int) old('nilai_nusa') === $score)>{{ $label }}</option>@endforeach</select></div></section>
                    <section class="visitor-section"><div class="visitor-field"><label for="saran">Ada kesan atau ide untuk kami? <small>Opsional</small></label><textarea id="saran" name="saran" rows="3" maxlength="1000" placeholder="Kesan, karya favorit, atau saran Anda...">{{ old('saran') }}</textarea></div></section>
                    <div class="visitor-consent"><label><input type="checkbox" name="persetujuan" value="1" required @checked(old('persetujuan'))><span>Saya bersedia data ini digunakan untuk pendataan kunjungan dan evaluasi kegiatan sekolah.</span></label></div>
                    <button class="visitor-submit" type="submit" id="visitor-submit" @disabled($pratinjau ?? false)><i data-lucide="send" aria-hidden="true"></i><span>Kirim kunjungan & kesan</span></button><p class="visitor-privacy"><i data-lucide="shield-check" aria-hidden="true"></i>Nama, kategori, dan waktu kunjungan tampil di layar monitoring pameran. Saran serta data lainnya hanya diakses petugas sekolah yang berwenang.</p>
                </form>
            @endif
        </div>
    </main>
    <footer class="visitor-footer"><strong>Satu Nusa, Terhubung untuk Bertumbuh</strong><span>SMP Negeri 2 Padang Panjang &middot; {{ now()->year }}</span></footer>
</body></html>
