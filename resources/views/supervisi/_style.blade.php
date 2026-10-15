<style>
    .sp-workspace { min-width:0; }
    .sp-filters.sp-recap-filters { grid-template-columns:repeat(4,minmax(0,1fr)); }
    .sp-recap-filter-actions { display:flex; flex-direction:column; justify-content:flex-end; gap:8px; }
    .sp-recap-filter-actions .sp-check { margin:0; }
    .sp-summary.sp-recap-summary { grid-template-columns:repeat(4,minmax(0,1fr)); }
    .sp-table.sp-recap-table { min-width:1100px; table-layout:fixed; }
    .sp-recap-table td { overflow-wrap:anywhere; }
    .sp-recap-table .sp-actions { min-width:85px; }
    .sp-numeric { font-variant-numeric:tabular-nums; }
    .sp-recap-toolbar .sp-actions { font-size:.8rem; }
    .sp-recap-toolbar .button:disabled { background:#cbd5e0; color:#52677b; cursor:not-allowed; }
    @media(max-width:1050px) { .sp-summary.sp-recap-summary,.sp-filters.sp-recap-filters { grid-template-columns:repeat(2,minmax(0,1fr)); } }
    @media(max-width:760px) { .sp-filters.sp-recap-filters { grid-template-columns:1fr; } }
    @media(max-width:640px) { .sp-summary.sp-recap-summary .sp-stat:last-child { grid-column:auto; } }
    .sp-workspace .button { white-space:normal; line-height:1.4; border-radius:6px; }
    .sp-nav { display:flex; flex-wrap:wrap; gap:6px; border-bottom:1px solid var(--line); margin:0 0 24px; padding-bottom:12px; }
    .sp-nav a { padding:9px 14px; border-radius:4px; color:#56667a; font-size:.88rem; font-weight:700; }
    .sp-nav a[aria-current=page] { background:var(--primary); color:#fff; }
    .sp-summary { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:12px; margin-bottom:24px; }
    .sp-stat { border:1px solid #bed2e7; background:#e7f0fa; border-radius:6px; padding:18px 20px; min-width:0; }
    .sp-stat span { display:block; color:#4b6178; font-size:.85rem; line-height:1.5; }
    .sp-stat strong { display:block; margin-top:6px; font-size:1.65rem; color:#164576; font-variant-numeric:tabular-nums; }
    .sp-stat.teal { background:#e1f2e9; border-color:#b1d5c4; }
    .sp-stat.teal strong { color:#176052; }
    .sp-stat.amber { background:#fff1c5; border-color:#ead28c; }
    .sp-stat.amber strong { color:#795812; }
    .sp-band { margin:22px 0; background:#fff; min-width:0; }
    .sp-head { display:flex; align-items:center; justify-content:space-between; gap:16px; padding:18px 24px; background:#dceaf8; color:#17436f; }
    .sp-head h2 { font-size:1.03rem; margin:0; line-height:1.5; }
    .sp-head p { font-size:.82rem; margin:5px 0 0; color:#4f6780; }
    .sp-band.teal > .sp-head { background:#d7ede5; color:#17574b; }
    .sp-band.amber > .sp-head { background:#fff0b8; color:#785610; }
    .sp-body { padding:22px 24px; min-width:0; }
    .sp-filters,.sp-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:18px; }
    .sp-filters { grid-template-columns: minmax(180px,2fr) repeat(3,minmax(140px,1fr)); padding:20px 24px; background:#fff; margin-bottom:24px; }
    .sp-wide { grid-column:1/-1; }
    .sp-three { grid-template-columns:repeat(3,minmax(0,1fr)); }
    .sp-footer { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:16px; padding-top:18px; margin-top:20px; border-top:1px solid var(--line); }
    .sp-footer > p { margin:0; color:var(--muted); font-size:.85rem; flex:1 1 280px; }
    .sp-table-wrap { overflow:auto; max-width:100%; border:1px solid #d3dfeb; background:#fff; border-radius:6px; }
    .sp-table { width:100%; min-width:720px; border-collapse:collapse; font-size:.87rem; }
    .sp-table th,.sp-table td { text-align:left; padding:15px 16px; border-bottom:1px solid #dce5ef; vertical-align:top; }
    .sp-table th { background:#e9f0f8; color:#36526e; font-size:.78rem; }
    .sp-table tr:nth-child(even) td { background:#f6f9fc; }
    .sp-table tr:has(input:checked) td { background:#e7f3ed; }
    .sp-table tr:last-child td { border-bottom:0; }
    .sp-table small,.sp-muted { display:block; margin-top:6px; color:var(--muted); line-height:1.6; font-size:.8rem; }
    .sp-actions { display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
    .sp-badge { display:inline-block; padding:5px 9px; border-radius:4px; font-size:.75rem; font-weight:700; background:#e8edf4; color:#536477; }
    .sp-badge.terjadwal,.sp-badge.aktif,.sp-badge.terbit { background:#dff2e8; color:#236546; }
    .sp-badge.draf { background:#fff0c5; color:#7d5a0a; }
    .sp-badge.dibatalkan,.sp-badge.diarsipkan { background:#f8e3df; color:#963f36; }
    .sp-note { padding:13px 16px; background:#fff1ca; color:#76541c; font-size:.85rem; line-height:1.6; margin:16px 0; }
    .sp-empty { padding:42px 24px; text-align:center; color:var(--muted); background:#fff; }
    .sp-facts { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:20px; margin:0; }
    .sp-facts dt { color:var(--muted); font-size:.82rem; margin-bottom:7px; }
    .sp-facts dd { margin:0; font-size:.92rem; font-weight:700; overflow-wrap:anywhere; line-height:1.6; }
    .sp-check { display:flex; align-items:flex-start; gap:10px; font-size:.88rem; line-height:1.6; margin:14px 0; }
    .sp-check input,.sp-table input[type=checkbox] { width:18px; height:18px; accent-color:var(--primary); flex-shrink:0; }
    .sp-toolbar { display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap; }
    .sp-icon { display:inline-grid; place-items:center; width:36px; height:36px; padding:0; border:1px solid #c9d7e5; background:#fff; color:#244c72; cursor:pointer; border-radius:4px; font-size:1.1rem; }
    .sp-icon:disabled { opacity:.45; cursor:not-allowed; }
    .sp-indicators { padding:0; list-style:none; margin:0; }
    .sp-indicators li { display:flex; align-items:flex-start; gap:10px; padding:12px 0; border-bottom:1px solid var(--line); }
    .sp-indicators textarea { flex:1; min-width:0; min-height:50px; resize:vertical; line-height:1.6; }
    .sp-scale { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:10px; }
    .sp-scale > div { padding:12px; background:#f0f5fb; }
    .sp-scale .input { margin-top:8px; }
    .sp-scale strong { font-size:.82rem; }
    .sp-workspace textarea { resize:vertical; }
    .sp-workspace [hidden] { display:none !important; }
    .sp-workspace .select,.sp-workspace .input { min-width:0; max-width:100%; }
    .sp-context { display:grid; grid-template-columns:1.1fr 1fr; gap:24px; background:#164576; color:#fff; padding:24px; margin:22px 0 16px; }
    .sp-context-label,.sp-context dt { font-size:.8rem; color:#c9dfef; }
    .sp-context h2 { margin:8px 0; font-size:1.3rem; line-height:1.5; overflow-wrap:anywhere; }
    .sp-context p { margin:0; color:#e1edf6; line-height:1.6; }
    .sp-context dl { margin:0; display:grid; gap:16px; align-content:center; }
    .sp-context dd { margin:5px 0 0; line-height:1.5; font-weight:600; }
    .sp-stage-nav { display:flex; flex-wrap:wrap; gap:10px; margin-bottom:24px; }
    .sp-stage-nav a { display:flex; align-items:center; gap:10px; padding:12px 16px; background:#e7edf5; color:#3a536f; font-weight:700; border-bottom:3px solid transparent; }
    .sp-stage-nav a[aria-current=page] { background:#d9eafb; border-color:#164576; color:#164576; }
    .sp-step { display:grid; place-items:center; width:26px; height:26px; background:#fff; border-radius:4px; flex-shrink:0; }
    .sp-badge.terkirim,.sp-badge.final { background:#dff2e8; color:#236546; }
    .sp-badge.dikembalikan { background:#fff0c5; color:#7d5a0a; }
    .sp-badge.diterima,.sp-badge.selesai { background:#dff2e8; color:#236546; }
    .sp-badge.ditugaskan { background:#dceafa; color:#1d527a; }
    .sp-badge.diajukan { background:#fff0c5; color:#7d5a0a; }
    .sp-task-list { display:grid; gap:14px; margin:20px 0; }
    .sp-task { border:1px solid #cbdcec; border-radius:6px; background:#fff; overflow:hidden; }
    .sp-task-top { display:flex; justify-content:space-between; align-items:center; gap:12px; padding:12px 18px; background:#e4eff9; }
    .sp-task:nth-child(even) .sp-task-top { background:#def0e8; }
    .sp-task-body { padding:18px; display:grid; grid-template-columns:minmax(0,1fr) auto; gap:18px; align-items:center; }
    .sp-task h3 { margin:0; font-size:1rem; line-height:1.6; overflow-wrap:anywhere; }
    .sp-late { color:#9c3b31; font-weight:700; }
    .sp-reflection { margin:0; padding:0; list-style:none; counter-reset:reflection; }
    .sp-reflection li { counter-increment:reflection; padding:20px 0; border-bottom:1px solid var(--line); }
    .sp-reflection label,.sp-reflection h3 { display:block; font-size:.93rem; line-height:1.6; margin:0 0 12px; font-weight:700; }
    .sp-reflection label::before,.sp-reflection h3::before { content:counter(reflection) '. '; color:#36678b; }
    .sp-bulk-plan { display:flex; align-items:flex-start; gap:12px; padding:12px 0; border-bottom:1px solid var(--line); line-height:1.6; }
    .sp-bulk-plan input { margin-top:4px; width:18px; height:18px; accent-color:var(--primary); flex-shrink:0; }
    .sp-plan-preview { white-space:pre-wrap; overflow-wrap:anywhere; }
    .sp-dimmed { opacity:.8; }
    .sp-new-plan { margin:22px 0; background:#fff; border:1px solid #bfd2e5; border-radius:6px; }
    .sp-new-plan > summary { cursor:pointer; padding:16px 20px; background:#e3eef9; color:#17436f; font-weight:700; line-height:1.6; }
    .sp-plan-editor { padding:20px; }
    .sp-reflection-panel > summary { cursor:pointer; list-style:none; }
    .sp-reflection-panel > summary h2 { flex:1; }
    .sp-reflection-panel > summary::after { content:'+'; font-size:1.3rem; }
    .sp-reflection-panel[open] > summary::after { content:'-'; }
    @media(max-width:640px) { .sp-task-top { flex-wrap:wrap; padding:12px 16px; } .sp-task-body { grid-template-columns:1fr; padding:16px; } .sp-task-body .button { width:100%; } }
    .sp-prose { white-space:pre-wrap; overflow-wrap:anywhere; line-height:1.7; font-size:.9rem; }
    .sp-readonly { margin:0; }
    .sp-readonly dt { color:var(--muted); font-size:.83rem; margin-bottom:8px; }
    .sp-readonly dd { margin:0; white-space:pre-wrap; overflow-wrap:anywhere; line-height:1.7; }
    .sp-field-error { font-size:.8rem; color:#ac352e; }
    .sp-file-row { display:flex; align-items:center; justify-content:space-between; gap:16px; border-bottom:1px solid var(--line); padding:14px 0; }
    .sp-file-row > div:first-child { min-width:0; overflow-wrap:anywhere; }
    .sp-file-row .sp-actions { flex-shrink:0; }
    .sp-upload { display:grid; grid-template-columns:minmax(0,1fr) auto; align-items:center; gap:16px; margin-top:20px; padding:18px; background:#edf7f2; }
    .sp-savebar { position:sticky; bottom:12px; z-index:5; display:flex; justify-content:space-between; align-items:center; gap:16px; padding:16px 20px; background:#e2edf8; border:1px solid #b6cde4; border-radius:6px; box-shadow:0 4px 12px #173f6520; margin-top:20px; }
    .sp-savebar span { color:#39556f; font-size:.85rem; line-height:1.5; }
    .sp-score-groups { padding-top:8px; }
    .sp-score-group { border-bottom:1px solid var(--line); }
    .sp-score-group summary { cursor:pointer; display:flex; align-items:center; justify-content:space-between; gap:12px; padding:18px 0; font-size:.93rem; font-weight:700; }
    .sp-score-group summary::before { content:'+'; font-size:1.2rem; width:20px; flex-shrink:0; }
    .sp-score-group[open] summary::before { content:'−'; }
    .sp-score-group summary > span:first-child { flex:1; }
    .sp-score-group summary .sp-badge { flex-shrink:0; }
    .sp-score-row { display:grid; grid-template-columns:minmax(0,1.3fr) minmax(160px,.65fr) minmax(0,1fr); gap:18px; padding:18px 12px; align-items:start; border-top:1px solid #e0e8f0; }
    .sp-score-row:nth-child(even) { background:#f5f8fc; }
    .sp-indicator-label { display:flex; align-items:flex-start; gap:12px; font-size:.89rem; line-height:1.7; }
    .sp-item-number { display:grid; place-items:center; min-width:25px; height:25px; background:#e1ebf5; color:#24547b; border-radius:4px; font-size:.8rem; flex-shrink:0; }
    .sp-score-control .sp-muted { margin:0 0 6px; }
    .sp-score-row textarea { min-height:72px; line-height:1.5; }
    .sp-score-row .field label { font-size:.8rem; }
    .sp-score-value { font-size:1.3rem; color:#164576; }
    @media(max-width:1100px) { .sp-score-row { grid-template-columns:minmax(0,1fr) minmax(160px,240px); } .sp-evidence { grid-column:1/-1; } }
    @media(max-width:760px) { .sp-context { grid-template-columns:1fr; padding:20px 16px; gap:20px; } .sp-context h2 { font-size:1.15rem; } .sp-file-row,.sp-savebar { flex-wrap:wrap; } .sp-upload { grid-template-columns:1fr; padding:14px; } .sp-stage-nav a { width:100%; flex-wrap:wrap; } .sp-savebar .button { width:100%; } }
    @media(max-width:520px) { .sp-score-row { grid-template-columns:1fr; gap:12px; padding:16px 8px; } .sp-score-group summary { flex-wrap:wrap; } .sp-score-group summary .sp-badge { margin-left:32px; } }
    @media(max-width:1050px) { .sp-filters { grid-template-columns:repeat(2,minmax(0,1fr)); } }
    @media(max-width:760px) { .sp-head { flex-direction:column; align-items:stretch; padding:16px; } .sp-body,.sp-filters { padding:18px 16px; } .sp-grid,.sp-facts,.sp-filters { grid-template-columns:1fr; } .sp-footer > .button { width:100%; } .sp-summary { gap:8px; } .sp-stat { padding:12px; } .sp-stat strong { font-size:1.35rem; } .sp-stat span { font-size:.78rem; } .sp-actions { width:100%; } .sp-actions .button { flex:1 1 140px; } }
    @media(max-width:640px) { .sp-summary { grid-template-columns:repeat(2,minmax(0,1fr)); } .sp-summary .sp-stat:last-child { grid-column:1/-1; } }
</style>
<nav class="sp-nav" aria-label="Supervisi akademik">
    <a href="{{ route('supervisi.rekap.index') }}" @if(request()->routeIs('supervisi.rekap.*','supervisi.laporan.*')) aria-current="page" @endif>Rekap &amp; laporan</a>
    <a href="{{ route('supervisi.index') }}" @if(request()->routeIs('supervisi.index','supervisi.sesi.*')) aria-current="page" @endif>Jadwal supervisi</a>
    <a href="{{ route('supervisi.program.index') }}" @if(request()->routeIs('supervisi.program.*','supervisi.jadwal.*')) aria-current="page" @endif>Program</a>
    @if(auth()->user()->memilikiIzin('supervisi.instrumen_kelola'))<a href="{{ route('supervisi.instrumen.index') }}" @if(request()->routeIs('supervisi.instrumen.*')) aria-current="page" @endif>Instrumen</a>@endif
</nav>
