<style>
    .tamu-page { max-width:1280px; margin:0 auto; }
    .tamu-page * { min-width:0; }
    .tamu-page [hidden] { display:none !important; }
    .tamu-page .page-header { gap:16px; align-items:center; flex-wrap:wrap; }
    .tamu-page .button { white-space:normal; text-align:center; }
    .tamu-tabs { display:flex; gap:8px; border-bottom:1px solid #d6dfe6; padding-bottom:14px; margin:20px 0; flex-wrap:wrap; }
    .tamu-tabs a { display:inline-flex; align-items:center; min-height:42px; padding:9px 18px; border:1px solid #b9c9d8; border-radius:6px; font-weight:700; color:#27475d; background:#fff; text-decoration:none; }
    .tamu-tabs a[aria-current=page] { background:#184a6f; border-color:#184a6f; color:#fff; }
    .tamu-metrics { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); border-block:1px solid #d6dfe6; margin:18px 0 24px; }
    .tamu-metric { padding:16px; border-right:1px solid #d6dfe6; }
    .tamu-metric:last-child { border-right:0; }
    .tamu-metric span { display:block; color:#546976; font-size:13px; }
    .tamu-metric strong { display:block; color:#203642; font-size:25px; margin-top:4px; }
    .tamu-metric--active strong { color:#146b57; }
    .tamu-metric--cancel strong { color:#984141; }
    .tamu-filter { display:grid; grid-template-columns:2fr 1fr 1fr; gap:14px; }
    .tamu-period { display:flex; gap:14px; flex-wrap:wrap; align-items:end; margin-top:14px; }
    .tamu-period > .field { flex:1 1 180px; }
    .tamu-period-fields { display:flex; gap:14px; flex:2 1 340px; }
    .tamu-period-fields > .field { flex:1; }
    .tamu-actions { display:flex; flex-wrap:wrap; align-items:center; gap:8px; }
    .tamu-filter-actions { display:flex; flex-wrap:wrap; align-items:center; gap:8px; margin:16px 0 22px; }
    .tamu-filter-actions .tamu-actions { margin-left:auto; }
    .tamu-summary { display:flex; flex-wrap:wrap; gap:8px 20px; margin:20px 0; color:#526774; font-size:13px; }
    .tamu-summary strong { color:#203642; }
    .tamu-list { width:100%; border-collapse:collapse; table-layout:fixed; background:#fff; }
    .tamu-list th { background:#edf2f6; color:#435e70; text-align:left; font-size:12px; padding:12px; }
    .tamu-list td { border-bottom:1px solid #dfe6ec; padding:16px 12px; vertical-align:top; overflow-wrap:anywhere; }
    .tamu-list small { display:block; color:#697f8c; margin-top:5px; }
    .tamu-list td:first-child a { font-weight:700; text-decoration:none; color:#184a6f; }
    .tamu-list th:nth-child(1) { width:25%; }
    .tamu-list th:nth-child(2) { width:28%; }
    .tamu-list th:nth-child(3) { width:21%; }
    .tamu-list th:nth-child(4) { width:16%; }
    .tamu-list th:nth-child(5) { width:10%; }
    .tamu-badge { display:inline-block; padding:5px 9px; border-radius:5px; font-size:12px; font-weight:700; background:#eef1f4; color:#4b606d; }
    .tamu-badge--berkunjung { color:#14624f; background:#e3f4ec; }
    .tamu-badge--dibatalkan { color:#984141; background:#fff0ef; }
    .tamu-empty { text-align:center; padding:46px 20px; color:#617783; border-block:1px solid #dfe6ec; }
    .tamu-section { border-top:1px solid #d6dfe6; padding-top:22px; margin-top:26px; }
    .tamu-section h2 { font-size:18px; margin:0 0 18px; color:#203642; }
    .tamu-form { max-width:960px; }
    .tamu-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:18px; }
    .tamu-wide { grid-column:1/-1; }
    .tamu-page label { font-weight:600; }
    .tamu-page .input,.tamu-page .select,.tamu-page .textarea { width:100%; }
    .tamu-page textarea { resize:vertical; }
    .tamu-hint { color:#697f8c; font-size:12px; margin:6px 0 0; }
    .tamu-footer { display:flex; gap:10px; flex-wrap:wrap; margin-top:28px; padding-top:20px; border-top:1px solid #d6dfe6; }
    .tamu-details { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:20px; margin:0; }
    .tamu-details dt { font-size:12px; color:#697f8c; margin-bottom:6px; }
    .tamu-details dd { margin:0; overflow-wrap:anywhere; white-space:pre-line; }
    .tamu-details .tamu-wide { grid-column:1/-1; }
    .tamu-files { display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:16px; }
    .tamu-file { border:1px solid #d6dfe6; border-radius:6px; padding:12px; background:#fff; overflow-wrap:anywhere; }
    .tamu-file img { width:100%; height:160px; object-fit:contain; background:#f5f7f8; margin-bottom:12px; }
    .tamu-file strong { display:block; font-size:13px; margin:8px 0; }
    .tamu-file .tamu-actions { margin-top:14px; }
    .tamu-upload-state { margin-top:16px; padding:16px; background:#edf5f9; border:1px solid #9abaca; border-radius:6px; }
    .tamu-upload-state progress { display:block; width:100%; height:12px; margin-top:10px; accent-color:#146b57; }
    .tamu-upload-error { color:#984141; background:#fff0ef; border-color:#e0b6b0; }
    .tamu-history { list-style:none; margin:0; padding:0; }
    .tamu-history > li { border-bottom:1px solid #dfe6ec; padding:14px 0; }
    .tamu-history time { color:#697f8c; font-size:12px; }
    .tamu-history details { margin-top:10px; }
    .tamu-history summary { cursor:pointer; color:#184a6f; font-weight:600; }
    .tamu-audit { display:grid; grid-template-columns:160px 1fr 1fr; gap:10px; padding:8px 0; overflow-wrap:anywhere; border-bottom:1px solid #edf2f6; font-size:13px; }
    .tamu-audit-label { color:#697f8c; }
    .tamu-cancel { margin-top:24px; border-top:1px solid #d6dfe6; padding-top:18px; }
    .tamu-cancel summary { cursor:pointer; color:#984141; font-weight:600; }
    .tamu-cancel form { max-width:650px; margin-top:18px; }
    @media(max-width:900px) {
        .tamu-list thead { display:none; }
        .tamu-list,.tamu-list tbody,.tamu-list tr,.tamu-list td { display:block; width:100%; }
        .tamu-list tr { padding:16px 0; border-bottom:1px solid #d6dfe6; }
        .tamu-list td { border:0; padding:7px 0; }
        .tamu-list td[data-label]::before { content:attr(data-label); display:block; font-size:11px; color:#697f8c; margin-bottom:4px; }
        .tamu-details { grid-template-columns:repeat(2,minmax(0,1fr)); }
    }
    @media(max-width:600px) {
        .tamu-metrics { grid-template-columns:repeat(2,minmax(0,1fr)); }
        .tamu-metric { padding:13px 10px; }
        .tamu-metric:nth-child(2) { border-right:0; }
        .tamu-metric:nth-child(-n+2) { border-bottom:1px solid #d6dfe6; }
        .tamu-filter,.tamu-grid,.tamu-details { grid-template-columns:minmax(0,1fr); }
        .tamu-period-fields { flex-basis:100%; flex-wrap:wrap; }
        .tamu-period-fields > .field { flex-basis:100%; }
        .tamu-filter-actions .tamu-actions { margin-left:0; flex-basis:100%; margin-top:8px; }
        .tamu-audit { grid-template-columns:1fr; gap:5px; }
        .tamu-tabs a { flex:1; padding-inline:10px; }
    }
</style>
