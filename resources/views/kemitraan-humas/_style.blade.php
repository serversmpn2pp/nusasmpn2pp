@include('agenda-humas._style')
<style>
    .mitra-page { max-width:1280px; margin:0 auto; }
    .mitra-page [hidden] { display:none !important; }
    .mitra-page .page-header { flex-wrap:wrap; gap:16px; }
    .mitra-page h1 { font-size:28px; overflow-wrap:anywhere; }
    .mitra-page .button { white-space:normal; text-align:center; }
    .mitra-page .agenda-section { padding:22px 0; background:transparent; }
    .mitra-page .field,.mitra-page .actions { min-width:0; }
    .mitra-page .agenda-facts dd { font-weight:500; }
    .mitra-filters { display:grid; grid-template-columns:2fr 1fr 1fr; gap:14px; margin-bottom:16px; }
    .mitra-report-filters { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:14px; margin-bottom:16px; }
    .mitra-form { max-width:980px; }
    .mitra-summary { display:flex; flex-wrap:wrap; align-items:center; gap:12px; margin:0 0 18px; }
    .mitra-heading { min-width:0; }
    .mitra-table { table-layout:fixed; background:#fff; }
    .mitra-table th,.mitra-table td { overflow-wrap:anywhere; }
    .mitra-table th:last-child { width:12%; }
    .mitra-table--mou th:first-child { width:29%; }
    .mitra-table--mou th:nth-child(2) { width:23%; }
    .mitra-table--mitra th:first-child { width:30%; }
    .mitra-table--mitra th:nth-child(2) { width:25%; }
    .mitra-badge { display:inline-block; padding:5px 9px; border-radius:4px; font-size:12px; font-weight:700; background:#eaf0f6; color:#365876; }
    .mitra-badge--berlaku,.mitra-badge--aktif { color:#1b6755; background:#e5f4ed; }
    .mitra-badge--segera_berakhir { color:#8b610a; background:#fff5d7; }
    .mitra-badge--kedaluwarsa { color:#983d35; background:#ffefec; }
    .mitra-badge--draf,.mitra-badge--diakhiri,.mitra-badge--arsip { color:#566673; background:#edf0f2; }
    .mitra-activity { padding:18px 0; border-bottom:1px solid var(--line); }
    .mitra-activity h3 { font-size:16px; margin:0 0 8px; overflow-wrap:anywhere; }
    .mitra-history { list-style:none; padding:0; margin:0; }
    .mitra-history > li { border-bottom:1px solid var(--line); padding:16px 0; }
    .mitra-history summary { cursor:pointer; color:var(--primary-dark); font-size:13px; font-weight:700; margin-top:10px; }
    .mitra-diff { display:grid; grid-template-columns:160px 1fr 1fr; gap:14px; padding:10px 0; font-size:13px; overflow-wrap:anywhere; border-bottom:1px solid #e5ebef; }
    .mitra-upload { padding:16px; margin-top:16px; border:1px solid #a1bfd0; background:#eef6fa; border-radius:6px; }
    .mitra-upload progress { display:block; width:100%; height:12px; margin-top:12px; accent-color:#1b6755; }
    .mitra-upload--error { color:#983d35; border-color:#dfb2ad; background:#ffefec; }
    @media(max-width:900px) { .mitra-report-filters { grid-template-columns:1fr 1fr; } }
    @media(max-width:600px) { .mitra-filters,.mitra-report-filters,.mitra-diff { grid-template-columns:minmax(0,1fr); } .mitra-page h1 { font-size:24px; } .mitra-page .agenda-section { padding:18px 0; } .mitra-table { width:100%; } }
</style>
