<style>
    .sts-workspace { --sts-teal: #17675c; --sts-teal-soft: #e4f3ed; --sts-amber: #765716; --sts-amber-soft: #fff2c9; min-width: 0; }
    .sts-workspace .button { white-space: normal; line-height: 1.4; border-radius: 6px; }
    .sts-workspace .page-header { align-items: center; gap: 16px; }
    .sts-workspace .page-header p:last-child:not(.eyebrow) { margin: 8px 0 0; color: var(--muted); font-size: .9rem; }
    .sts-workspace .sts-section { padding: 0; border: 0; margin: 20px 0; }
    .sts-workspace .sts-section.sts-filters { padding: 20px 24px; background: #fff; }
    .sts-band { background: #fff; min-width: 0; }
    .sts-band-head { display: flex; align-items: center; justify-content: space-between; gap: 20px; padding: 18px 24px; background: #e6edf5; min-width: 0; }
    .sts-band-title { display: flex; align-items: center; gap: 14px; min-width: 0; }
    .sts-band-title > div { min-width: 0; }
    .sts-workspace .sts-band-title h2 { margin: 0; font-size: 1.05rem; line-height: 1.45; }
    .sts-band-title p { margin: 4px 0 0; font-size: .82rem; color: #4d6177; line-height: 1.5; }
    .sts-step { display: grid; place-items: center; width: 40px; height: 40px; flex: 0 0 40px; background: var(--primary); color: #fff; font-size: .85rem; font-weight: 800; border-radius: 6px; }
    .sts-band-body { padding: 20px 24px; min-width: 0; }
    .sts-band--blue .sts-band-head { background: #dce9f7; color: var(--primary-dark); }
    .sts-band--blue .sts-band-body { background: #f0f5fb; }
    .sts-band--teal .sts-band-head { background: #d5ece3; color: #154f46; }
    .sts-band--teal .sts-step { background: var(--sts-teal); }
    .sts-band--teal .sts-band-title p { color: #3d665c; }
    .sts-band--amber .sts-band-head { background: #ffedb0; color: var(--sts-amber); }
    .sts-band--amber .sts-step { background: #886217; }
    .sts-band--amber .sts-band-title p { color: #705b2f; }
    .sts-band--amber .sts-band-body { background: #fff9e8; }
    .sts-band-footer { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px; margin-top: 20px; padding-top: 16px; border-top: 1px solid #cedae5; }
    .sts-band-footer .help-text { flex: 1 1 260px; margin: 0; }
    .sts-section-note { margin: 0; font-size: .85rem; line-height: 1.6; color: #526273; }
    .sts-workspace .sts-notice { background: #fff0c9; color: #735113; border: 0; border-radius: 4px; padding: 12px 16px; margin: 16px 0 0; line-height: 1.6; }
    .sts-workspace .sts-summary { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; padding: 0; border: 0; margin: 20px 0; }
    .sts-metric { display: flex; flex-direction: column; gap: 8px; padding: 18px 20px; background: #fff; border: 1px solid #d7e2ee; border-radius: 6px; min-width: 0; }
    .sts-workspace .sts-metric strong { font-size: 1.65rem; line-height: 1.15; margin: 0; color: var(--primary-dark); font-variant-numeric: tabular-nums; }
    .sts-workspace .sts-metric span { font-size: .82rem; line-height: 1.45; color: #516275; }
    .sts-metric--blue { background: #e6eff9; border-color: #bacfe5; }
    .sts-metric--teal { background: #e1f2e9; border-color: #b2d5c5; }
    .sts-workspace .sts-metric--teal strong { color: #155e4e; }
    .sts-metric--amber { background: #fff2c9; border-color: #ebd38a; }
    .sts-workspace .sts-metric--amber strong { color: #755513; }
    .sts-publish-band { display: flex; align-items: center; justify-content: space-between; gap: 24px; padding: 24px; background: var(--primary); color: #fff; }
    .sts-workspace .sts-section.sts-publish-band { padding: 24px; }
    .sts-publish-band > div { min-width: 0; flex: 1; }
    .sts-workspace .sts-publish-band h2 { margin: 0 0 8px; font-size: 1.1rem; }
    .sts-publish-band p { margin: 0; color: #e4edf7; font-size: .85rem; line-height: 1.6; max-width: 680px; }
    .sts-publish-band .button { background: #fff; border-color: #fff; color: var(--primary-dark); flex-shrink: 0; }
    .sts-publish-band .button:hover { background: #e8f0f8; }
    .sts-context { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; padding: 0 0 4px; font-size: .85rem; color: #56677a; }
    .sts-context strong { background: var(--primary-soft); color: var(--primary-dark); padding: 5px 10px; border-radius: 4px; }
    @media (max-width: 1000px) { .sts-workspace .sts-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 950px) {
        .sts-band-head, .sts-publish-band { flex-direction: column; align-items: stretch; gap: 16px; }
        .sts-band-head > .button, .sts-publish-band > .button { width: 100%; }
    }
    @media (max-width: 640px) {
        .sts-band-head, .sts-publish-band { flex-direction: column; align-items: stretch; padding: 18px 16px; gap: 16px; }
        .sts-band-body { padding: 18px 16px; }
        .sts-workspace .sts-section.sts-filters { padding: 18px 16px; }
        .sts-workspace .sts-section.sts-publish-band { padding: 18px 16px; }
        .sts-band-head > .button, .sts-publish-band > .button, .sts-band-footer > .button { width: 100%; }
        .sts-band-title { gap: 10px; }
        .sts-step { width: 34px; height: 34px; flex-basis: 34px; }
        .sts-metric { padding: 14px; }
        .sts-workspace .sts-metric strong { font-size: 1.45rem; }
        .sts-workspace .page-header { align-items: stretch; }
    }
</style>
