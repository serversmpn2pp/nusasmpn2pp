@extends('layouts.app')

@section('title', 'Kunci Jawaban OMR - NUSA')

@section('content')
    <style>
        .omr-answer-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 10px;
            margin-top: 16px;
        }

        .omr-answer-item {
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
            padding: 10px;
        }

        .omr-answer-number {
            margin: 0 0 8px;
            color: var(--primary-dark);
            font-weight: 900;
        }

        .omr-answer-options {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 4px;
        }

        .omr-answer-option {
            display: grid;
            min-height: 34px;
            place-items: center;
            border: 1px solid var(--line);
            border-radius: 6px;
            cursor: pointer;
            font-size: .82rem;
            font-weight: 900;
        }

        .omr-answer-option:has(input:checked) {
            border-color: var(--primary);
            background: var(--primary);
            color: #fff;
        }

        .omr-answer-option input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        @media (max-width: 1080px) {
            .omr-answer-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 620px) {
            .omr-answer-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
    </style>

    @php
        $kunciTersimpan = $versiSoalUjianOmr->kunciJawaban->pluck('jawaban', 'nomor_soal');
    @endphp

    <div class="page-header">
        <div>
            <p class="eyebrow">Penilaian OMR</p>
            <h1 class="page-title">Kunci jawaban versi {{ $versiSoalUjianOmr->kode }}</h1>
            <p class="help-text" style="margin-top: 6px;">{{ $ujianOmr->nama }} - {{ $ujianOmr->jumlah_soal }} soal pilihan A-D</p>
        </div>
        <a href="{{ route('ujian-omr.show', $ujianOmr) }}" class="button button-muted">Kembali</a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>Ada kunci jawaban yang perlu diperbaiki.</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('ujian-omr.kunci-jawaban.update', [$ujianOmr, $versiSoalUjianOmr]) }}" method="POST">
        @csrf
        @method('PUT')

        <section class="panel panel-pad">
            <div class="omr-answer-grid">
                @foreach (range(1, $ujianOmr->jumlah_soal) as $nomorSoal)
                    @php
                        $jawabanTerpilih = old('jawaban.' . $nomorSoal, $kunciTersimpan[$nomorSoal] ?? null);
                    @endphp
                    <div class="omr-answer-item">
                        <p class="omr-answer-number">Nomor {{ $nomorSoal }}</p>
                        <div class="omr-answer-options">
                            @foreach (['A', 'B', 'C', 'D'] as $jawaban)
                                <label class="omr-answer-option">
                                    <input type="radio" name="jawaban[{{ $nomorSoal }}]" value="{{ $jawaban }}" @checked($jawabanTerpilih === $jawaban) required>
                                    <span>{{ $jawaban }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <div class="form-actions">
            <a href="{{ route('ujian-omr.show', $ujianOmr) }}" class="button button-muted">Batal</a>
            <button type="submit" class="button button-primary">Simpan kunci jawaban</button>
        </div>
    </form>
@endsection
