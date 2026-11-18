@pushOnce('styles')
    <style>
        .siswa-terpadu { display: grid; gap: 16px; max-width: 1432px; margin-inline: auto; }
        .siswa-terpadu .page-header { margin: 0; }
        .terpadu-filters { display: flex; flex-wrap: wrap; align-items: end; gap: 12px; padding: 16px; background: var(--primary-soft); border: 1px solid var(--line); border-radius: 6px; }
        .terpadu-field { display: grid; gap: 6px; flex: 1 1 160px; min-width: 0; max-width: 300px; font-size: 13px; font-weight: 700; }
        .terpadu-search { flex: 2 1 270px; max-width: none; }
        .terpadu-field :is(input,select) { width: 100%; min-height: 44px; }
        .terpadu-actions { display: flex; flex-wrap: wrap; gap: 8px; }
        .siswa-terpadu .button { gap: 8px; min-height: 44px; }
        .siswa-terpadu [data-lucide] { width: 17px; height: 17px; flex: none; }
        .terpadu-header { display: flex; gap: 16px; align-items: center; padding: 18px; background: var(--primary-soft); border-left: 4px solid var(--primary); }
        .terpadu-header > div { min-width: 0; }
        .terpadu-header h1 { overflow-wrap: anywhere; }
        .terpadu-avatar { display: grid; place-items: center; width: 76px; aspect-ratio: 3 / 4; object-fit: cover; flex: none; border-radius: 6px; background: var(--panel); color: var(--primary); font-weight: 800; border: 1px solid var(--line); }
        .terpadu-avatar.is-small { width: 36px; aspect-ratio: 1; font-size: 13px; }
        .terpadu-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 4px 14px; list-style: none; padding: 0; margin: 7px 0 0; color: var(--muted); }
        .terpadu-pill { display: inline-block; color: var(--primary-dark); background: var(--panel); border: 1px solid var(--line); border-radius: 4px; padding: 3px 7px; font-size: 12px; font-weight: 800; }
        .terpadu-tabs { display: flex; flex-wrap: wrap; gap: 4px 8px; border-bottom: 1px solid var(--line); }
        .terpadu-tabs a { display: inline-flex; align-items: center; padding: 9px 10px; margin-bottom: -1px; min-height: 44px; border-bottom: 3px solid transparent; color: var(--primary-dark); text-decoration: none; font-weight: 700; font-size: 13px; }
        .terpadu-tabs a:hover, .terpadu-tabs [aria-current="page"] { color: var(--primary); background: var(--primary-soft); border-color: var(--primary); }
        .terpadu-stats { display: grid; grid-template-columns: repeat(auto-fit,minmax(min(100%,155px),1fr)); gap: 10px; }
        .terpadu-stat { display: grid; align-content: space-between; gap: 8px; padding: 12px 14px; border: 1px solid var(--line); border-top: 3px solid var(--primary); background: var(--panel); border-radius: 6px; }
        .terpadu-stat:nth-child(3n+2) { border-top-color: #087d77; }
        .terpadu-stat:nth-child(3n+3) { border-top-color: #be9400; }
        .terpadu-stat :is(span,strong) { overflow-wrap: anywhere; }
        .terpadu-stat span, .terpadu-note { color: var(--muted); font-size: 13px; }
        .terpadu-stat strong { color: var(--primary-dark); font-size: 20px; }
        .terpadu-stat a { width: fit-content; font-weight: 700; font-size: 13px; }
        .terpadu-section { min-width: 0; background: var(--panel); border-bottom: 1px solid var(--line); }
        .terpadu-section > header { background: var(--primary-soft); border-bottom: 1px solid var(--line); padding: 12px 16px; }
        .terpadu-section h2 { margin: 0; font-size: 16px; line-height: 1.35; }
        .terpadu-note { margin: 0; padding: 11px 16px; }
        .terpadu-tabel { width: 100%; border-collapse: collapse; font-size: 14px; }
        .terpadu-tabel :is(th,td) { text-align: left; vertical-align: top; padding: 10px 14px; border-bottom: 1px solid var(--line); overflow-wrap: anywhere; white-space: normal; }
        .terpadu-tabel th { color: var(--muted); font-size: 12px; }
        .terpadu-tabel tbody tr:last-child td { border-bottom: 0; }
        .terpadu-secondary { display: block; margin-top: 3px; color: var(--muted); font-size: 12px; }
        .terpadu-person { display: flex; align-items: center; min-width: 0; gap: 9px; font-weight: 800; color: var(--primary-dark); }
        .terpadu-person span { overflow-wrap: anywhere; }
        .terpadu-facts { padding: 0 16px; display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%,210px),1fr)); gap: 0 24px; margin: 0; }
        .terpadu-facts > div { padding: 11px 0; border-bottom: 1px solid var(--line); min-width: 0; }
        .terpadu-facts dt { color: var(--muted); font-size: 12px; }
        .terpadu-facts dd { margin: 4px 0 0; font-weight: 700; overflow-wrap: anywhere; }
        .terpadu-paginasi { padding: 12px 16px; }
        .terpadu-empty { padding: 20px 16px; margin: 0; color: var(--muted); }
        @media (max-width: 640px) {
            .siswa-terpadu { gap: 12px; }
            .terpadu-header { gap: 12px; padding: 12px; }
            .terpadu-header h1 { font-size: 22px; }
            .terpadu-avatar { width: 55px; }
            .terpadu-field { max-width: none; }
            .terpadu-search, .terpadu-actions { flex-basis: 100%; }
            .terpadu-tabel, .terpadu-tabel tbody { display: block; }
            .terpadu-tabel thead { position: absolute; width: 1px; height: 1px; overflow: hidden; clip-path: inset(50%); }
            .terpadu-tabel tr { display: grid; gap: 7px; padding: 12px 14px; border-bottom: 1px solid var(--line); }
            .terpadu-tabel td { display: grid; grid-template-columns: minmax(0,1fr) minmax(0,1.65fr); gap: 8px; padding: 0; border: 0; }
            .terpadu-tabel td::before { content: attr(data-label); color: var(--muted); font-size: 12px; }
            .terpadu-tabel .terpadu-name-cell, .terpadu-tabel .terpadu-action-cell { display: block; }
            .terpadu-tabel :is(.terpadu-name-cell,.terpadu-action-cell)::before { content: none; }
            .terpadu-tabel .terpadu-action-cell { margin-top: 4px; }
        }
    </style>
@endPushOnce
