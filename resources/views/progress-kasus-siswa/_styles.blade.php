<style>
    .case-page{display:grid;gap:18px}
    .case-hero{align-items:center;background:#15477a;border-radius:8px;color:#fff;display:flex;gap:18px;justify-content:space-between;padding:20px 22px}
    .case-hero h1{color:#fff;font-size:1.65rem;letter-spacing:0;margin:0}
    .case-hero p{color:#dbeafe;line-height:1.55;margin:7px 0 0;max-width:720px}
    .case-hero-meta{background:#fff;border-radius:7px;color:#15477a;flex:0 0 auto;font-size:.82rem;font-weight:900;padding:9px 12px;text-align:center}
    .case-summary{display:grid;gap:12px;grid-template-columns:repeat(4,minmax(0,1fr))}
    .case-stat{background:#fff;border:1px solid #dce4eb;border-top:4px solid #15477a;border-radius:8px;min-width:0;padding:16px}
    .case-stat.is-yellow{border-top-color:#f1c40f}
    .case-stat.is-green{border-top-color:#16a34a}
    .case-stat.is-red{border-top-color:#c2413a}
    .case-stat span{color:#64748b;display:block;font-size:.78rem;font-weight:800}
    .case-stat strong{color:#15477a;display:block;font-size:1.75rem;line-height:1;margin-top:9px}
    .case-list{display:grid;gap:12px}
    .case-card{align-items:center;background:#fff;border:1px solid #dce4eb;border-radius:8px;display:grid;gap:16px;grid-template-columns:minmax(0,1fr) auto;padding:17px}
    .case-card-main{min-width:0}
    .case-card-top{align-items:center;display:flex;flex-wrap:wrap;gap:8px;margin-bottom:9px}
    .case-number{color:#15477a;font-size:.8rem;font-weight:900}
    .case-status{border-radius:5px;display:inline-flex;font-size:.72rem;font-weight:900;line-height:1.25;padding:5px 8px}
    .case-status.info{background:#e8f3fb;color:#155b88}
    .case-status.warning{background:#fff5cc;color:#725600}
    .case-status.success{background:#e7f7ec;color:#146c2e}
    .case-status.danger{background:#fdecea;color:#a72c27}
    .case-status.neutral{background:#edf1f4;color:#475569}
    .case-card h2{color:#172536;font-size:1rem;letter-spacing:0;line-height:1.35;margin:0}
    .case-card p{color:#64748b;font-size:.82rem;line-height:1.5;margin:6px 0 0}
    .case-facts{display:flex;flex-wrap:wrap;gap:7px 15px;margin-top:10px}
    .case-facts span{color:#475569;font-size:.77rem;font-weight:700}
    .case-open{align-items:center;background:#15477a;border:1px solid #15477a;border-radius:7px;color:#fff;display:inline-flex;font-size:.8rem;font-weight:900;justify-content:center;min-height:40px;padding:9px 13px;text-decoration:none;white-space:nowrap}
    .case-open:hover{background:#0f365e;color:#fff}
    .case-empty{background:#fff;border:1px dashed #b9c8d6;border-radius:8px;color:#64748b;padding:34px 18px;text-align:center}
    .case-empty strong{color:#15477a;display:block;font-size:1rem;margin-bottom:6px}
    .case-back{align-items:center;background:#fff;border:1px solid #cfd9e3;border-radius:7px;color:#15477a;display:inline-flex;font-size:.82rem;font-weight:900;justify-content:center;min-height:40px;padding:8px 13px;text-decoration:none}
    .case-detail-grid{display:grid;gap:18px;grid-template-columns:minmax(0,1.25fr) minmax(280px,.75fr)}
    .case-panel{background:#fff;border:1px solid #dce4eb;border-radius:8px;overflow:hidden}
    .case-panel-head{align-items:center;border-bottom:1px solid #e5eaf0;display:flex;gap:12px;justify-content:space-between;padding:15px 17px}
    .case-panel-head h2{color:#172536;font-size:1rem;letter-spacing:0;margin:0}
    .case-panel-body{padding:17px}
    .case-progress{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));margin-top:16px}
    .case-progress-step{min-width:0;padding:0 7px;position:relative;text-align:center}
    .case-progress-step::before{background:#d9e2ea;content:"";height:3px;left:-50%;position:absolute;top:15px;width:100%}
    .case-progress-step:first-child::before{display:none}
    .case-progress-step.is-done::before{background:#f1c40f}
    .case-progress-number{align-items:center;background:#e7edf2;border:3px solid #fff;border-radius:50%;box-shadow:0 0 0 1px #cbd6df;color:#64748b;display:inline-flex;font-size:.76rem;font-weight:900;height:32px;justify-content:center;position:relative;width:32px;z-index:1}
    .case-progress-step.is-done .case-progress-number{background:#f1c40f;box-shadow:0 0 0 1px #d5ac00;color:#15477a}
    .case-progress-step strong{color:#475569;display:block;font-size:.73rem;line-height:1.3;margin-top:8px}
    .case-progress-step.is-done strong{color:#15477a}
    .case-info-grid{display:grid;gap:0 18px;grid-template-columns:repeat(2,minmax(0,1fr))}
    .case-info{border-bottom:1px solid #edf1f4;padding:11px 0}
    .case-info span{color:#64748b;display:block;font-size:.75rem;font-weight:800;margin-bottom:4px}
    .case-info strong{color:#172536;display:block;line-height:1.45;overflow-wrap:anywhere}
    .case-narrative{background:#f8fafc;border-left:4px solid #15477a;border-radius:5px;color:#334155;line-height:1.65;margin-top:15px;padding:13px 14px;white-space:pre-line}
    .case-timeline{display:grid}
    .case-timeline-item{display:grid;gap:11px;grid-template-columns:18px minmax(0,1fr);padding-bottom:17px;position:relative}
    .case-timeline-item::before{background:#d9e2ea;content:"";height:100%;left:7px;position:absolute;top:9px;width:2px}
    .case-timeline-item:last-child{padding-bottom:0}
    .case-timeline-item:last-child::before{display:none}
    .case-timeline-dot{background:#f1c40f;border:3px solid #fff;border-radius:50%;box-shadow:0 0 0 1px #d5ac00;height:16px;position:relative;width:16px;z-index:1}
    .case-timeline-item strong{color:#172536;display:block;font-size:.86rem;line-height:1.4}
    .case-timeline-item p{color:#64748b;font-size:.78rem;line-height:1.5;margin:3px 0}
    .case-timeline-item time{color:#8a98a8;font-size:.72rem;font-weight:700}
    .case-decision{border:1px solid #dce4eb;border-radius:7px;padding:14px}
    .case-decision p{color:#475569;line-height:1.55;margin:7px 0 0}
    .case-violation-list{display:grid;gap:8px;margin-top:12px}
    .case-violation{align-items:start;background:#f8fafc;border:1px solid #e3e9ef;border-radius:6px;display:grid;gap:10px;grid-template-columns:minmax(0,1fr) auto;padding:10px 11px}
    .case-violation strong{color:#172536;font-size:.82rem;line-height:1.4}
    .case-violation span{color:#b91c1c;font-size:.8rem;font-weight:900;white-space:nowrap}
    .case-total{align-items:center;border-top:1px solid #e5eaf0;display:flex;justify-content:space-between;margin-top:12px;padding-top:12px}
    .case-total strong{color:#15477a;font-size:1.35rem}
    .case-follow-list{display:grid;gap:9px}
    .case-follow{background:#f8fafc;border:1px solid #e3e9ef;border-radius:6px;padding:11px}
    .case-follow strong{color:#172536;display:block;font-size:.82rem}
    .case-follow span{color:#64748b;display:block;font-size:.75rem;margin-top:4px}
    .case-privacy{background:#eef5fb;border-left:4px solid #2582bd;border-radius:6px;color:#36566f;font-size:.78rem;line-height:1.55;padding:11px 12px}
    @media(max-width:960px){.case-summary{grid-template-columns:repeat(2,minmax(0,1fr))}.case-detail-grid{grid-template-columns:1fr}}
    @media(max-width:640px){.case-hero{align-items:flex-start;flex-direction:column;padding:17px}.case-hero-meta{width:100%}.case-summary{grid-template-columns:repeat(2,minmax(0,1fr))}.case-stat{padding:13px}.case-card{grid-template-columns:1fr}.case-open{width:100%}.case-info-grid{grid-template-columns:1fr}.case-progress-step{padding:0 2px}.case-progress-step strong{font-size:.67rem}.case-panel-body{padding:14px}}
</style>
