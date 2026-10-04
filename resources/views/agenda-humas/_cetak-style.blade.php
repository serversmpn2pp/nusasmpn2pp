<style>
        * { box-sizing:border-box; }
        body { margin:0; background:#e9edf1; color:#202b35; font:10pt Arial,Helvetica,sans-serif; }
        .toolbar { padding:12px 20px; display:flex; gap:10px; align-items:center; flex-wrap:wrap; background:#fff; border-bottom:1px solid #c9d2da; }
        .toolbar a,.toolbar button { font:700 10pt Arial,sans-serif; border:1px solid #b6c4d1; color:#173f69; background:#fff; text-decoration:none; padding:10px 14px; border-radius:4px; cursor:pointer; }
        .toolbar button { background:#173f69; color:#fff; }
        .toolbar span { color:#687582; font-size:9pt; }
        .sheet { width:210mm; min-height:273mm; background:#fff; margin:18px auto; padding:12mm; }
        .header { display:grid; grid-template-columns:18mm 1fr 18mm; gap:4mm; align-items:center; border-bottom:1px solid #23496d; padding-bottom:4mm; margin-bottom:5mm; text-align:center; }
        .header img { width:16mm; height:18mm; object-fit:contain; }
        .header h1 { font-size:13pt; margin:0 0 1mm; }
        .header p { margin:1mm 0; font-size:10pt; }
        .heading { text-align:center; margin:0 0 4mm; font-size:12pt; }
        .draft { color:#825621; text-align:center; font-size:9pt; margin:0 0 3mm; }
        .facts { width:100%; border-collapse:collapse; margin-bottom:4mm; }
        .facts td { padding:.8mm 0; vertical-align:top; font-size:9pt; overflow-wrap:anywhere; }
        .facts td:first-child { width:30mm; }
        .records { width:100%; border-collapse:collapse; table-layout:fixed; font-size:8.5pt; }
        .records th,.records td { border:1px solid #a4b0bc; padding:1.2mm 1.8mm; vertical-align:middle; overflow-wrap:anywhere; }
        .records th { background:#eef2f6; height:8mm; font-size:8pt; }
        .records td { height:6.5mm; }
        .sheet--attendance .records td { height:5.5mm; padding:.8mm 1.5mm; font-size:8pt; line-height:1.15; }
        .sheet--attendance .records th { height:7mm; }
        .sheet--attendance .signatures { margin-top:5mm; }
        .sheet--attendance .signature-space { height:14mm; }
        .center { text-align:center; }
        h3 { margin:5mm 0 2mm; font-size:10pt; break-after:avoid; }
        .text { white-space:pre-wrap; overflow-wrap:anywhere; line-height:1.5; }
        .signatures { display:grid; grid-template-columns:1fr 1fr; gap:20mm; text-align:center; margin-top:7mm; break-inside:avoid; }
        .signatures p { margin:1mm 0; font-size:9pt; }
        .signature-date { margin:5mm 0 0; text-align:right; font-size:9pt; }
        .signature-space { height:18mm; }
        .foot { margin-top:4mm; font-size:8pt; color:#687582; }
        @page { size:A4 portrait; margin:12mm; }
        @media print {
            body { background:#fff; } .toolbar { display:none; } .sheet { width:auto; min-height:0; margin:0; padding:0; break-after:page; } .sheet:last-child { break-after:auto; }
            .sheet--attendance { width:186mm; }
            thead { display:table-header-group; } tr { break-inside:avoid; } a { color:inherit; }
        }
        @media screen and (max-width:820px) { .sheet { width:100%; min-height:0; padding:20px; } .header { grid-template-columns:50px 1fr 50px; gap:8px; } .header img { max-width:100%; } .header h1 { font-size:11pt; } }
    </style>
