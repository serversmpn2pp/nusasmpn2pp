<style>
    @page { size:A4 portrait; margin:14mm; }
    * { box-sizing:border-box; }
    body { margin:0; font:11pt/1.5 Arial,sans-serif; color:#182c3a; background:white; }
    .print-page { width:100%; max-width:182mm; margin:18px auto; }
    .print-toolbar { display:flex; gap:12px; align-items:center; padding:16px; border-bottom:1px solid #d9e1e7; font-size:13px; }
    .print-toolbar button,.print-toolbar a { border:1px solid #c2ccd4; background:white; color:#233a4b; padding:9px 14px; text-decoration:none; border-radius:4px; cursor:pointer; font:inherit; }
    .print-header { display:grid; grid-template-columns:17mm minmax(0,1fr) 17mm; gap:7mm; align-items:center; text-align:center; border-bottom:1px solid #274d66; padding-bottom:5mm; margin-bottom:6mm; }
    .print-header img { width:17mm; height:20mm; object-fit:contain; }
    .print-header strong { font-size:14pt; display:block; }
    .print-header h1 { font-size:12pt; margin:2mm 0 0; }
    h2 { font-size:12pt; margin:5mm 0 2mm; break-after:avoid; }
    h3 { font-size:11pt; margin:4mm 0 1mm; break-after:avoid; }
    p { margin:1mm 0 3mm; }
    .print-facts { display:grid; grid-template-columns:40mm minmax(0,1fr); gap:2mm 4mm; margin:4mm 0; }
    .print-facts dt { color:#586571; }
    .print-facts dd { margin:0; }
    .print-text { white-space:pre-wrap; overflow-wrap:anywhere; }
    table { width:100%; border-collapse:collapse; table-layout:fixed; margin:4mm 0; }
    th,td { border:1px solid #acb7be; padding:2.5mm; vertical-align:top; text-align:left; overflow-wrap:anywhere; font-size:10pt; }
    th { background:#f1f4f6; }
    thead { display:table-header-group; }
    tr { break-inside:avoid; }
    .print-note { font-size:9pt; color:#586571; border-top:1px solid #bac6ce; padding-top:2mm; margin-top:6mm; }
    .print-signature { margin:8mm 0 0 auto; width:75mm; text-align:center; break-inside:avoid; }
    .print-signature .sign-space { height:19mm; }
    .print-status { font-weight:bold; font-size:10pt; }
    @media(max-width:700px) { .print-page { padding:12px; } .print-header { grid-template-columns:12mm minmax(0,1fr) 12mm; gap:3mm; } .print-header img { width:12mm; height:16mm; } .print-header strong { font-size:12pt; } .print-facts { grid-template-columns:32mm minmax(0,1fr); } }
    @media print { .print-toolbar { display:none; } .print-page { max-width:none; margin:0; padding:0; } .print-header { grid-template-columns:17mm minmax(0,1fr) 17mm; gap:7mm; } .print-header img { width:17mm; height:20mm; } .print-header strong { font-size:14pt; } .print-facts { grid-template-columns:40mm minmax(0,1fr); } }
</style>
