@extends('layouts.app')

@section('title', 'Survei Pembelajaran - NUSA')

@section('content')
    <style>
        .learning-survey{display:grid;gap:18px;margin:0 auto;max-width:980px}
        .learning-survey-hero{background:#15477a;border-radius:8px;color:#fff;padding:21px 23px}
        .learning-survey-hero .eyebrow{color:#f1c40f;margin-bottom:5px}
        .learning-survey-hero h1{color:#fff;font-size:1.65rem;letter-spacing:0;margin:0}
        .learning-survey-hero p{color:#dbeafe;line-height:1.55;margin:8px 0 0}
        .learning-survey-context{display:grid;gap:1px;grid-template-columns:repeat(4,minmax(0,1fr));overflow:hidden}
        .learning-survey-context div{background:#fff;border:1px solid #dce4eb;min-width:0;padding:14px}
        .learning-survey-context div:first-child{border-radius:8px 0 0 8px}
        .learning-survey-context div:last-child{border-radius:0 8px 8px 0}
        .learning-survey-context span{color:#64748b;display:block;font-size:.7rem;font-weight:800}
        .learning-survey-context strong{color:#172536;display:block;font-size:.84rem;line-height:1.4;margin-top:5px;overflow-wrap:anywhere}
        .learning-survey-scale{align-items:center;background:#fff8d8;border:1px solid #f1c40f;border-radius:7px;color:#654f00;display:flex;flex-wrap:wrap;font-size:.74rem;gap:8px;justify-content:space-between;padding:11px 13px}
        .learning-survey-scale span{font-weight:900}
        .learning-survey-form{display:grid;gap:14px}
        .learning-survey-question{background:#fff;border:1px solid #dce4eb;border-radius:8px;padding:17px}
        .learning-survey-question h2{color:#172536;font-size:.92rem;letter-spacing:0;line-height:1.5;margin:0}
        .learning-survey-number{align-items:center;background:#15477a;border-radius:6px;color:#fff;display:inline-flex;font-size:.72rem;height:26px;justify-content:center;margin-right:8px;vertical-align:1px;width:26px}
        .learning-survey-options{display:grid;gap:8px;grid-template-columns:repeat(5,minmax(0,1fr));margin-top:14px}
        .learning-survey-option{display:block;min-width:0;position:relative}
        .learning-survey-option input{height:1px;opacity:0;position:absolute;width:1px}
        .learning-survey-option span{align-items:center;background:#f8fafc;border:1px solid #d8e1e9;border-radius:7px;color:#506477;cursor:pointer;display:flex;flex-direction:column;font-size:.68rem;font-weight:800;gap:5px;justify-content:center;line-height:1.3;min-height:74px;padding:8px 5px;text-align:center;transition:border-color .15s,background .15s,color .15s}
        .learning-survey-option b{color:#15477a;font-size:1.05rem}
        .learning-survey-option input:checked+span{background:#eef5fb;border:2px solid #15477a;color:#15477a;padding:7px 4px}
        .learning-survey-option input:focus-visible+span{outline:3px solid rgba(241,196,15,.45);outline-offset:2px}
        .learning-survey-comment{background:#fff;border:1px solid #dce4eb;border-radius:8px;padding:17px}
        .learning-survey-footer{align-items:center;display:flex;gap:10px;justify-content:flex-end}
        .learning-survey-note{color:#64748b;font-size:.75rem;line-height:1.45;margin-right:auto;max-width:520px}
        @media(max-width:760px){.learning-survey-context{grid-template-columns:1fr 1fr}.learning-survey-context div:first-child{border-radius:8px 0 0 0}.learning-survey-context div:nth-child(2){border-radius:0 8px 0 0}.learning-survey-context div:nth-child(3){border-radius:0 0 0 8px}.learning-survey-context div:last-child{border-radius:0 0 8px 0}.learning-survey-scale{display:grid;grid-template-columns:1fr 1fr}.learning-survey-scale span:first-child{grid-column:1/-1}.learning-survey-scale span:last-child{text-align:right}.learning-survey-options{grid-template-columns:1fr}.learning-survey-option span{align-items:center;flex-direction:row;justify-content:flex-start;min-height:48px;padding:8px 12px;text-align:left}.learning-survey-option input:checked+span{padding:7px 11px}.learning-survey-footer{align-items:stretch;flex-direction:column-reverse}.learning-survey-footer .button{justify-content:center;width:100%}.learning-survey-note{margin:5px 0 0;max-width:none;text-align:center}}
        @media(max-width:460px){.learning-survey-hero{padding:18px}.learning-survey-context{grid-template-columns:1fr}.learning-survey-context div,.learning-survey-context div:first-child,.learning-survey-context div:nth-child(2),.learning-survey-context div:nth-child(3),.learning-survey-context div:last-child{border-radius:0}.learning-survey-context div:first-child{border-radius:8px 8px 0 0}.learning-survey-context div:last-child{border-radius:0 0 8px 8px}}
    </style>

    <div class="learning-survey">
        <section class="learning-survey-hero">
            <p class="eyebrow">Umpan balik siswa</p>
            <h1>Survei Pembelajaran</h1>
            <p>Berikan jawaban yang jujur sesuai pengalaman belajar Anda pada semester ini.</p>
        </section>

        <section class="learning-survey-context" aria-label="Informasi pembelajaran">
            <div>
                <span>Mata pelajaran</span>
                <strong>{{ $guruMataPelajaran->mataPelajaran?->nama ?: '-' }}</strong>
            </div>
            <div>
                <span>Guru</span>
                <strong>{{ $guruMataPelajaran->pegawai?->nama_lengkap ?: '-' }}</strong>
            </div>
            <div>
                <span>Kelas</span>
                <strong>{{ $guruMataPelajaran->kelas?->nama ?: '-' }}</strong>
            </div>
            <div>
                <span>Periode</span>
                <strong>{{ $guruMataPelajaran->tahunPelajaran?->nama ?: '-' }} - {{ ucfirst($semester) }}</strong>
            </div>
        </section>

        @if ($errors->any())
            <div class="alert alert-danger">
                <strong>Survei belum dapat dikirim.</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="learning-survey-scale" aria-label="Skala jawaban">
            <span>Skala jawaban</span>
            <span>1 = Sangat tidak sesuai</span>
            <span>5 = Sangat sesuai</span>
        </div>

        <form
            method="POST"
            action="{{ route('survei-pembelajaran.store', [$guruMataPelajaran, $semester]) }}"
            class="learning-survey-form"
        >
            @csrf

            @foreach ($daftarPertanyaan as $pertanyaan)
                <section
                    class="learning-survey-question"
                    role="group"
                    aria-labelledby="pertanyaan-{{ $pertanyaan->kode }}"
                >
                    <h2 id="pertanyaan-{{ $pertanyaan->kode }}">
                        <span class="learning-survey-number">{{ $loop->iteration }}</span>{{ $pertanyaan->pernyataan }}
                    </h2>
                    <div class="learning-survey-options">
                        @foreach ($daftarPilihan as $nilai => $label)
                            <label class="learning-survey-option">
                                <input
                                    type="radio"
                                    name="jawaban[{{ $pertanyaan->kode }}]"
                                    value="{{ $nilai }}"
                                    @checked((int) old('jawaban.'.$pertanyaan->kode) === $nilai)
                                    required
                                >
                                <span><b>{{ $nilai }}</b>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('jawaban.'.$pertanyaan->kode)
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </section>
            @endforeach

            <section class="learning-survey-comment">
                <div class="field">
                    <label for="saran">Saran untuk pembelajaran <span class="optional">Opsional</span></label>
                    <textarea
                        id="saran"
                        name="saran"
                        class="textarea @error('saran') is-invalid @enderror"
                        rows="4"
                        maxlength="1000"
                        placeholder="Tuliskan hal yang sudah baik atau yang dapat ditingkatkan."
                    >{{ old('saran') }}</textarea>
                    @error('saran')
                        <p class="error-text">{{ $message }}</p>
                    @enderror
                </div>
            </section>

            <div class="learning-survey-footer">
                <p class="learning-survey-note">Jawaban survei digunakan sebagai umpan balik pembelajaran dan tidak memengaruhi nilai Anda.</p>
                <a
                    href="{{ route('nilai-saya.index', ['tahun_pelajaran_id' => $guruMataPelajaran->tahun_pelajaran_id, 'semester' => $semester]) }}"
                    class="button button-muted"
                >Kembali</a>
                <button type="submit" class="button button-primary">Kirim dan buka nilai</button>
            </div>
        </form>
    </div>
@endsection
