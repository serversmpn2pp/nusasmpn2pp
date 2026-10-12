<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Cara meminta penghapusan akun atau data NUSA SMPN 2 Padang Panjang melalui email resmi sekolah, termasuk penjelasan arsip yang dipertahankan.">
    <title>Permintaan Penghapusan Akun/Data NUSA — SMP Negeri 2 Padang Panjang</title>
    <style>
        :root { color-scheme: light; --blue: #15477A; --navy: #0C315C; --yellow: #F1C40F; --line: #DCE5EF; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #F8FAFD; color: var(--navy); font-family: system-ui, -apple-system, "Segoe UI", sans-serif; line-height: 1.75; }
        a { color: var(--blue); text-underline-offset: .2em; overflow-wrap: anywhere; }
        a:focus-visible { outline: 3px solid var(--yellow); outline-offset: 4px; border-radius: 4px; }
        .skip-link { position: absolute; top: -80px; left: 16px; padding: 8px 16px; background: white; }
        .skip-link:focus { top: 12px; }
        .shell { width: min(100% - 40px, 960px); margin-inline: auto; }
        header { background: linear-gradient(120deg, var(--blue), #082F5B); color: white; padding: 28px 0 36px; border-bottom: 4px solid var(--yellow); }
        nav { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px; }
        nav a { color: white; }
        .brand { display: flex; align-items: center; gap: 12px; text-decoration: none; font-size: 24px; font-weight: 800; }
        .brand img { width: 48px; height: 48px; object-fit: contain; }
        .eyebrow { color: var(--yellow); font-size: 12px; font-weight: 700; letter-spacing: .08em; margin: 28px 0 6px; }
        h1 { font-size: clamp(25px, 5vw, 36px); line-height: 1.3; margin: 0 0 12px; }
        .intro { max-width: 700px; margin: 0; color: #E0EBF5; }
        main { padding-block: 24px 36px; }
        .request, article { background: white; border: 1px solid var(--line); border-radius: 20px; padding: clamp(20px, 4vw, 36px); overflow-wrap: anywhere; }
        .request { background: #F1F7FD; margin-bottom: 20px; }
        .request h2 { margin: 0 0 10px; font-size: 21px; }
        .request p { margin: 0 0 12px; }
        .button { display: inline-block; background: var(--blue); color: white; border-radius: 10px; padding: 12px 18px; margin-block: 8px; font-weight: 650; }
        article h2 { font-size: 20px; line-height: 1.4; margin: 32px 0 12px; padding-top: 24px; border-top: 1px solid var(--line); }
        article h2:first-child { margin-top: 0; padding-top: 0; border: 0; }
        article p, article li { font-size: 15px; }
        article p { margin: 0 0 16px; }
        article ol { padding-left: 24px; }
        article li { padding-left: 4px; margin-bottom: 12px; }
        article code { font-size: .9em; padding: 2px 5px; background: #F1F7FD; border-radius: 4px; }
        .privacy { margin: 20px 0 0; }
        footer { padding-block: 0 28px; text-align: center; font-size: 12px; color: #566B82; }
        @media (max-width: 620px) { .shell { width: min(100% - 28px, 960px); } article h2 { font-size: 18px; } }
    </style>
</head>
<body>
    <a class="skip-link" href="#cara-permintaan">Langsung ke cara mengirim permintaan</a>
    <header>
        <div class="shell">
            <nav aria-label="Navigasi halaman">
                <a class="brand" href="{{ url('/') }}" aria-label="Kembali ke NUSA">
                    <img src="{{ asset('images/logo-nusa.png') }}" alt="" width="48" height="48">
                    <span>NUSA</span>
                </a>
                <a href="{{ route('kebijakan-privasi') }}">Kebijakan Privasi</a>
            </nav>
            <p class="eyebrow">SMP NEGERI 2 PADANG PANJANG</p>
            <h1>Permintaan Penghapusan Akun/Data NUSA</h1>
            <p class="intro">Ajukan permintaan tanpa login. Petugas akan memverifikasi dan meninjau permintaan Anda; nilai dan arsip sekolah tidak dihapus otomatis.</p>
        </div>
    </header>
    <main class="shell">
        <section class="request" id="cara-permintaan" aria-labelledby="judul-permintaan">
            <h2 id="judul-permintaan">Kirim permintaan melalui email resmi sekolah</h2>
            <p>Alamat: <a href="mailto:{{ $email }}">{{ $email }}</a><br>Subjek: <strong>Permintaan Penghapusan Akun/Data NUSA</strong></p>
            <a class="button" href="{{ $mailto }}">Tulis email permintaan penghapusan</a>
            <p>Tombol membuka aplikasi email, bukan langsung mengirim permintaan. Periksa isinya lalu tekan Kirim. Jika tombol tidak bekerja, kirim email secara manual ke alamat di atas.</p>
            <p>Jangan sertakan kata sandi, OTP, atau dokumen identitas lengkap pada email awal.</p>
        </section>
        <article aria-label="Prosedur dan cakupan permintaan penghapusan">
            {{-- Hanya Markdown statis yang telah dibersihkan, tanpa data akun atau input URL. --}}
            {!! $isi !!}
        </article>
        <p class="privacy"><a href="{{ route('kebijakan-privasi') }}">Baca Kebijakan Privasi NUSA</a></p>
    </main>
    <footer class="shell">NUSA SMPN 2 Padang Panjang · Pengelola: SMP Negeri 2 Padang Panjang</footer>
</body>
</html>
