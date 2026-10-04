<style>
    .agenda-metrics { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); margin-bottom:24px; background:#fff; border-block:1px solid var(--line); }
    .agenda-metric { padding:16px 20px; min-width:0; }
    .agenda-metric + .agenda-metric { border-left:1px solid var(--line); }
    .agenda-metric strong { display:block; margin-top:4px; font-size:1.6rem; line-height:1.3; font-variant-numeric:tabular-nums; }
    .agenda-metric span,.agenda-muted { color:var(--muted); font-size:.85rem; }
    .agenda-metrics--three { grid-template-columns:repeat(3,minmax(0,1fr)); }
    .agenda-metric--warning strong { color:#98670a; }
    .agenda-filter { display:grid; grid-template-columns:2fr 1.4fr 1fr; gap:12px; align-items:end; padding:18px 0; }
    .agenda-filter-bottom { display:flex; align-items:end; flex-wrap:wrap; gap:12px; padding-bottom:18px; }
    .agenda-filter-bottom .field { flex:1 1 160px; max-width:220px; }
    .agenda-table { width:100%; border-collapse:collapse; }
    .agenda-table th { background:#f1f5f8; color:#485767; font-size:.8rem; text-align:left; }
    .agenda-table th,.agenda-table td { padding:13px 12px; border-bottom:1px solid var(--line); vertical-align:top; }
    .agenda-table td { font-size:.9rem; }
    .agenda-table td small { display:block; margin-top:4px; color:var(--muted); }
    .agenda-table a:not(.button) { color:var(--primary-dark); font-weight:800; text-decoration:none; }
    .agenda-table a:not(.button):hover { text-decoration:underline; }
    .agenda-table td,.agenda-text,.agenda-facts dd { overflow-wrap:anywhere; }
    .agenda-table-shell { background:#fff; border-block:1px solid var(--line); }
    .agenda-badge { display:inline-flex; gap:5px; align-items:center; padding:4px 9px; border-radius:4px; font-size:.78rem; font-weight:800; background:#e9f0f8; color:#245887; white-space:normal; }
    .agenda-badge--selesai { background:#e8f5ee; color:#276846; }
    .agenda-badge--dibatalkan { background:#f3f3f4; color:#62626b; }
    .agenda-warning { color:#98670a; }
    .agenda-section { background:#fff; padding:22px; border-block:1px solid var(--line); margin-bottom:22px; min-width:0; }
    .agenda-section h2 { font-size:1rem; margin:0 0 16px; }
    .agenda-section h3 { font-size:.94rem; margin:20px 0 8px; }
    .agenda-field-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px; }
    .agenda-field-grid > * { min-width:0; }
    .agenda-text { white-space:pre-wrap; line-height:1.65; }
    .agenda-facts { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px 24px; margin:0; }
    .agenda-facts dt { font-size:.8rem; color:var(--muted); margin-bottom:5px; }
    .agenda-facts dd { margin:0; font-size:.92rem; font-weight:700; }
    .agenda-tabs { display:flex; gap:6px; flex-wrap:wrap; margin:0 0 22px; padding:6px; background:#e8edf1; border-radius:6px; }
    .agenda-tabs a { display:inline-flex; align-items:center; justify-content:center; gap:8px; min-height:42px; padding:10px 14px; border:1px solid #cdd5dd; background:#fff; border-radius:4px; color:#415164; text-decoration:none; font-size:.88rem; font-weight:800; }
    .agenda-tabs a[aria-current="page"] { background:var(--primary); color:#fff; border-color:var(--primary); }
    .agenda-tabs a:hover { box-shadow:0 0 0 2px #bccbdd; }
    .agenda-tabs .agenda-count { font-size:.75rem; opacity:.85; }
    .agenda-actions { display:flex; flex-wrap:wrap; align-items:center; gap:10px; margin-top:18px; }
    .agenda-line { padding-block:16px; border-bottom:1px solid var(--line); }
    .agenda-line:first-child { padding-top:0; }
    .agenda-line:last-child { border-bottom:0; padding-bottom:0; }
    .agenda-presensi .select { min-width:150px; }
    .agenda-presensi .input { min-width:170px; }
    .agenda-invite-row { display:grid; grid-template-columns:1.3fr 1fr 1fr auto; gap:10px; align-items:end; padding-bottom:14px; margin-bottom:14px; border-bottom:1px solid var(--line); }
    .agenda-danger { color:#a43434; border-color:#e4bcbc; }
    .agenda-empty { padding:40px 20px; text-align:center; color:var(--muted); }
    .agenda-empty strong { display:block; color:var(--text); margin-bottom:8px; }
    .agenda-print-actions { display:flex; flex-wrap:wrap; gap:8px; }
    .agenda-heading { min-width:0; }
    .agenda-heading h1 { overflow-wrap:anywhere; font-size:1.65rem; }
    .agenda-qr-grid { display:grid; grid-template-columns:250px minmax(0,1fr); gap:28px; align-items:start; }
    .agenda-qr-image { min-height:232px; display:grid; place-items:center; }
    .agenda-qr-image svg { display:block; width:232px; height:232px; max-width:100%; }
    .agenda-qr-link { margin-top:16px; min-width:0; }
    .agenda-scope { display:flex; flex-wrap:wrap; gap:12px 24px; padding:0; border:0; margin:0 0 18px; }
    .agenda-scope legend { margin-bottom:12px; font-weight:700; font-size:.9rem; }
    .agenda-scope label,.agenda-class-options label { display:flex; align-items:center; gap:8px; font-size:.9rem; cursor:pointer; }
    .agenda-scope input,.agenda-class-options input { width:18px; height:18px; accent-color:var(--primary); flex-shrink:0; }
    .agenda-class-options { display:grid; grid-template-columns:repeat(auto-fit,minmax(130px,1fr)); gap:14px; }
    .agenda-preview-list { max-height:440px; overflow:auto; }
    .agenda-child { display:block; overflow-wrap:anywhere; line-height:1.5; }
    .agenda-confirmation { max-width:760px; }
    [data-scope][hidden] { display:none; }
    @media(max-width:900px) { .agenda-filter { grid-template-columns:1fr 1fr; } .agenda-filter .field:first-child { grid-column:1 / -1; } .agenda-invite-row { grid-template-columns:1fr 1fr; } }
    @media(max-width:600px) {
        .agenda-qr-grid { grid-template-columns:1fr; gap:20px; } .agenda-qr-image { min-height:0; }
        .agenda-metrics { grid-template-columns:repeat(2,minmax(0,1fr)); }
        .agenda-metrics--three .agenda-metric:last-child { grid-column:1 / -1; }
        .agenda-metric { padding:12px; } .agenda-metric:nth-child(3) { border-left:0; } .agenda-metric:nth-child(n+3) { border-top:1px solid var(--line); }
        .agenda-field-grid,.agenda-facts,.agenda-invite-row { grid-template-columns:1fr; }
        .agenda-section { padding:16px; } .agenda-tabs a { flex:1 1 120px; }
        .agenda-filter { grid-template-columns:1fr; } .agenda-filter-bottom .field { max-width:none; }
        .agenda-table--list thead { display:none; } .agenda-table--list,.agenda-table--list tbody,.agenda-table--list tr,.agenda-table--list td { display:block; }
        .agenda-table--list tr { padding:12px 14px; border-bottom:1px solid var(--line); } .agenda-table--list td { padding:5px 0; border:0; }
        .agenda-table--list td[data-label]::before { content:attr(data-label) ": "; font-weight:700; color:var(--muted); font-size:.8rem; }
        .agenda-print-actions .button { flex:1 1 auto; }
    }
</style>
