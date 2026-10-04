<style>
    .humas-dashboard { min-width:0; }
    .hd-head { display:flex; align-items:center; justify-content:space-between; gap:16px; margin-bottom:22px; }
    .hd-kicker { color:var(--primary); font-size:.85rem; font-weight:700; }
    .hd-head h1 { margin:4px 0; font-size:1.8rem; line-height:1.3; }
    .hd-muted { color:#626775; font-size:.85rem; margin:0; }
    .hd-filter { display:grid; grid-template-columns:1.1fr 1.1fr 1fr 1fr auto; align-items:end; gap:14px; padding:18px 0; border-top:1px solid var(--line); border-bottom:1px solid var(--line); margin-bottom:24px; }
    .hd-filter .field { min-width:0; }
    .hd-filter input,.hd-filter select { width:100%; min-width:0; }
    .hd-filter .actions { display:flex; gap:8px; }
    .hd-filter [disabled] { background:#edf0f4; color:#5c6471; opacity:1; }
    .hd-section { min-width:0; padding:22px 0; border-top:1px solid var(--line); }
    .hd-section h2 { margin:0; font-size:1.08rem; }
    .hd-section-head { display:flex; justify-content:space-between; align-items:baseline; flex-wrap:wrap; gap:8px; margin-bottom:14px; }
    .hd-metrics { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:16px 24px; margin:18px 0 22px; }
    .hd-metric { min-width:0; border-bottom:2px solid var(--hd-color); padding:10px 0 14px; color:var(--text); text-decoration:none; }
    .hd-metric:hover .hd-metric-label { text-decoration:underline; }
    .hd-metric-label { display:block; font-size:.85rem; font-weight:600; }
    .hd-metric strong { display:block; font-size:1.85rem; line-height:1.3; margin:5px 0; font-variant-numeric:tabular-nums; }
    .hd-metric small { display:block; font-size:.75rem; color:#626775; line-height:1.45; }
    .hd-color-blue { --hd-color:#2563a0; } .hd-color-green { --hd-color:#18824a; }
    .hd-color-teal { --hd-color:#087e83; } .hd-color-violet { --hd-color:#865aa1; }
    .hd-color-amber { --hd-color:#a87509; }
    .hd-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:28px; }
    .hd-status { display:grid; grid-template-columns:120px minmax(0,1fr) 40px; gap:12px; align-items:center; padding:8px 0; font-size:.85rem; }
    .hd-status strong { text-align:right; font-variant-numeric:tabular-nums; }
    .hd-track { background:#e7eaee; height:8px; border-radius:3px; overflow:hidden; }
    .hd-track span { display:block; height:100%; background:var(--hd-color,#2563a0); }
    .hd-status--selesai { --hd-color:#18824a; } .hd-status--berjalan { --hd-color:#087e83; }
    .hd-status--dibatalkan { --hd-color:#7b818c; }
    .hd-attention { list-style:none; padding:0; margin:0; }
    .hd-attention li + li { border-top:1px solid var(--line); }
    .hd-attention a { display:flex; align-items:center; justify-content:space-between; gap:12px; color:var(--text); text-decoration:none; padding:12px 0; }
    .hd-attention a:hover span:first-child { text-decoration:underline; }
    .hd-count { min-width:32px; text-align:center; padding:3px 8px; border-radius:4px; font-size:.85rem; font-weight:700; font-variant-numeric:tabular-nums; color:#805900; background:#fff4d0; flex-shrink:0; }
    .hd-count--danger { color:#a12b2b; background:#fce8e8; }
    .hd-empty { margin:0; padding:16px 0; color:#626775; font-size:.9rem; }
    .hd-table { width:100%; border-collapse:collapse; table-layout:fixed; font-size:.85rem; }
    .hd-table th { color:#525d6d; background:#edf2f6; font-size:.78rem; font-weight:600; text-align:left; }
    .hd-table th,.hd-table td { padding:11px 10px; border-bottom:1px solid var(--line); vertical-align:top; overflow-wrap:anywhere; }
    .hd-table td a { font-weight:600; color:var(--primary); }
    .hd-table .hd-number { text-align:right; font-variant-numeric:tabular-nums; }
    .hd-program-name { width:45%; } .hd-program-status { width:23%; }
    .hd-program-date { width:18%; } .hd-program-result { width:14%; }
    .hd-table small { display:block; margin-top:3px; color:#626775; font-size:.75rem; }
    .hd-agenda { list-style:none; padding:0; margin:0; }
    .hd-agenda li { padding:13px 0; border-bottom:1px solid var(--line); }
    .hd-agenda time { display:block; font-size:.8rem; color:#626775; margin-bottom:5px; }
    .hd-agenda a { color:var(--primary); font-size:.9rem; font-weight:700; overflow-wrap:anywhere; }
    .hd-agenda p { margin:5px 0 0; color:#626775; font-size:.8rem; overflow-wrap:anywhere; }
    .hd-note { margin:12px 0 0; font-size:.78rem; color:#626775; }
    @media(max-width:1200px) { .hd-filter { grid-template-columns:repeat(2,minmax(0,1fr)); } .hd-filter .actions { grid-column:1/-1; } .hd-grid { grid-template-columns:minmax(0,1fr); gap:0; } }
    @media(max-width:700px) {
        .hd-head { align-items:flex-start; flex-direction:column; } .hd-head h1 { font-size:1.5rem; }
        .hd-metrics { grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px 20px; }
        .hd-program-table thead,.hd-month-table thead,.hd-table colgroup { display:none; }
        .hd-program-table tr,.hd-month-table tr { display:block; padding:12px 0; border-bottom:1px solid var(--line); }
        .hd-program-table td,.hd-month-table td { display:grid; grid-template-columns:105px minmax(0,1fr); gap:12px; padding:5px 0; border:0; text-align:left; }
        .hd-program-table td::before,.hd-month-table td::before { content:attr(data-label); color:#626775; font-size:.78rem; }
        .hd-program-table td > div { min-width:0; } .hd-table .hd-number { text-align:left; }
        .hd-program-table td[colspan],.hd-month-table td[colspan] { display:block; } .hd-program-table td[colspan]::before,.hd-month-table td[colspan]::before { content:none; }
    }
    @media(max-width:400px) { .hd-filter { grid-template-columns:minmax(0,1fr); } .hd-filter .actions { flex-wrap:wrap; } .hd-status { grid-template-columns:105px minmax(0,1fr) 30px; gap:8px; } }
</style>
