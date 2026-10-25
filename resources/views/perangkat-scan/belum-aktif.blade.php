<!doctype html>
<html lang="id" translate="no">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="google" content="notranslate">
    <title>Aktivasi Scanner - NUSA</title>
    @fonts
    <link rel="icon" href="{{ asset('images/logo-nusa.png') }}" type="image/png">
    <style>
        *{box-sizing:border-box}body{margin:0;background:#f5f8fb;color:#15477A;font-family:Arial,sans-serif;min-height:100vh;display:grid;place-items:center;padding:24px}main{width:min(100%,540px);background:white;border:1px solid #cdddeb;border-top:5px solid #0f766e;border-radius:8px;padding:32px}header{display:flex;align-items:center;gap:12px;font-size:24px;font-weight:700}img{width:48px;height:48px;object-fit:contain}h1{font-size:24px;line-height:1.4;margin-top:28px}p{color:#50667d;line-height:1.6}a{display:inline-flex;align-items:center;justify-content:center;min-height:46px;padding:12px 18px;border-radius:6px;background:#15477A;color:white;text-decoration:none;font-weight:700;margin-top:16px;max-width:100%}.label{display:inline-block;padding:6px 10px;background:#fff5ce;border-radius:4px;font-size:13px;color:#7d5e00}
    </style>
</head>
<body><main>
    <header><img src="{{ asset('images/logo-nusa.png') }}" alt="Logo NUSA"> NUSA</header>
    <h1>Browser scanner belum aktif</h1>
    <span class="label">Scan {{ $jenis }}</span>
    <p>{{ $pesan }}</p>
    <a href="{{ route('perangkat-scan.index', ['jenis' => $jenis]) }}">Aktifkan melalui administrator</a>
</main></body>
</html>
