<style>
    * { box-sizing:border-box; }
    html { background:#e9edf2; color:#182d40; font-family:Arial,sans-serif; font-size:9.5pt; }
    body { margin:0; line-height:1.4; }
    .print-toolbar { position:sticky; top:0; z-index:5; padding:12px 20px; background:#164576; color:#fff; display:flex; align-items:center; justify-content:space-between; gap:16px; }
    .print-toolbar button { padding:10px 18px; background:#fff; color:#164576; border:0; border-radius:4px; font:700 10pt Arial,sans-serif; cursor:pointer; }
    .paper { max-width:210mm; padding:14mm; margin:20px auto; background:#fff; }
    .kop { display:grid; grid-template-columns:19mm minmax(0,1fr) 19mm; gap:5mm; align-items:center; text-align:center; padding-bottom:4mm; border-bottom:2px solid #164576; margin-bottom:5mm; break-inside:avoid; }
    .kop img { width:17mm; height:19mm; object-fit:contain; }
    .kop p { margin:0; font-size:10pt; }
    .kop strong { display:block; font-size:14pt; }
    .doc-title { text-align:center; margin:0 0 5mm; break-inside:avoid; }
    .doc-title h1 { font-size:13pt; margin:0 0 2mm; }
    .doc-title p { margin:0; color:#53687b; font-size:9pt; }
    h2 { font-size:11pt; background:#e8f0f8; color:#164576; border-bottom:1px solid #b5cadd; padding:2mm 3mm; margin:4mm 0 2mm; break-after:avoid; }
    h3 { font-size:10pt; margin:4mm 0 2mm; break-after:avoid; }
    p { margin:2mm 0; }
    .muted { color:#52677b; font-size:9pt; }
    .prose { white-space:pre-wrap; overflow-wrap:anywhere; }
    .facts { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:2mm 8mm; margin:0 0 3mm; }
    .facts div { min-width:0; }
    .facts dt { font-size:9pt; color:#53687b; }
    .facts dd { margin:0; font-weight:600; overflow-wrap:anywhere; }
    table { width:100%; border-collapse:collapse; table-layout:fixed; font-size:9pt; }
    th,td { padding:2mm; border:1px solid #b5c6d6; text-align:left; vertical-align:top; overflow-wrap:anywhere; }
    th { background:#e8f0f8; font-weight:700; }
    thead { display:table-header-group; }
    tr { break-inside:avoid; }
    tfoot { display:table-row-group; }
    .numeric { text-align:right; font-variant-numeric:tabular-nums; }
    .center { text-align:center; }
    .result { background:#e5f1ec; }
    .status { border:1px solid #b5c6d6; padding:3mm; background:#f1f6fa; margin-top:4mm; }
    .status strong { display:block; }
    .signatures { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12mm; text-align:center; margin-top:6mm; break-inside:avoid; }
    .signatures .sign-name { margin-top:14mm; font-weight:700; text-decoration:underline; overflow-wrap:anywhere; }
    .doc-meta { margin-top:5mm; font-size:8pt; color:#53687b; border-top:1px solid #c9d6e1; padding-top:2mm; }
    .section-start { break-before:page; }
    .report + .report { break-before:page; }
    .field-block { margin:2mm 0 3mm; }
    .field-block > strong { display:block; font-size:9pt; margin-bottom:1mm; break-after:avoid; }
    .attachment-list { font-size:9pt; padding-left:5mm; }
    .attachment-list li { overflow-wrap:anywhere; }
    @media screen and (max-width:700px) { .paper { padding:20px 16px; margin:0; } .kop { grid-template-columns:40px minmax(0,1fr) 40px; gap:8px; } .kop img { width:40px; height:48px; } .kop strong { font-size:11pt; } .kop p { font-size:8pt; } .facts { grid-template-columns:1fr; } .print-toolbar { flex-wrap:wrap; } }
    @page { size:A4 portrait; margin:14mm; @bottom-right { content:'Halaman ' counter(page) ' / ' counter(pages); font:8pt Arial,sans-serif; color:#53687b; } }
    @media print {
        html,body { background:#fff; }
        .print-toolbar { display:none; }
        .paper { max-width:none; width:auto; margin:0; padding:0; box-shadow:none; }
        * { print-color-adjust:exact; -webkit-print-color-adjust:exact; }
    }
</style>
