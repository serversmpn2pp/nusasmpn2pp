<nav class="actions" aria-label="Mode input nilai" style="margin-bottom: 16px;">
    <a href="{{ $tautanPerKomponen }}" class="button {{ $modeKolektif ? 'button-muted' : 'button-primary' }}" @unless ($modeKolektif) aria-current="page" @endunless>
        <i data-lucide="list" aria-hidden="true"></i> Per komponen
    </a>
    <a href="{{ $tautanKolektif }}" class="button {{ $modeKolektif ? 'button-primary' : 'button-muted' }}" @if ($modeKolektif) aria-current="page" @endif>
        <i data-lucide="table-2" aria-hidden="true"></i> Beberapa komponen
    </a>
</nav>
