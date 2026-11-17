<style>
    .package-context { margin-bottom: 16px; border-top: 3px solid var(--primary); }
    .package-heading { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 10px 18px; align-items: flex-start; }
    .package-heading h2 { margin: 0; font-size: 1.1rem; }
    .package-heading p:not(.eyebrow) { margin: 4px 0 0; color: var(--muted); }
    .package-facts { display: flex; flex-wrap: wrap; gap: 12px 28px; margin: 16px 0 0; }
    .package-facts dt { color: var(--muted); font-size: .8rem; }
    .package-facts dd { margin: 2px 0 0; font-weight: 700; }
    .package-section-head { align-items: center; padding: 14px 16px; border-bottom: 1px solid var(--line); background: var(--primary-soft); }
    .package-question-list { margin: 0; padding: 0; list-style: none; }
    .package-question { display: grid; grid-template-columns: 28px minmax(0, 1.5fr) minmax(112px, .5fr) auto; align-items: start; gap: 12px; padding: 14px 16px; border-bottom: 1px solid var(--line); }
    .package-question:last-child { border-bottom: 0; }
    .package-question-position { display: grid; place-items: center; width: 28px; height: 28px; border-radius: 5px; background: var(--primary-soft); color: var(--primary-dark); font-weight: 800; font-size: .8rem; }
    .package-question-check, .package-shuffle input[type="checkbox"] { width: 18px; height: 18px; accent-color: var(--primary); }
    .package-question-picker { display: grid; width: 100%; min-height: 32px; place-items: start center; padding-top: 4px; cursor: pointer; }
    .package-bank .package-question:has(input:checked:not(:disabled)) { background: #f4f8fc; }
    .package-question-text, .package-question-meta { min-width: 0; overflow-wrap: anywhere; }
    .package-question-text p { margin: 4px 0 0; line-height: 1.45; }
    .package-question-text small, .package-question-meta small { display: block; margin-top: 4px; color: var(--muted); }
    .package-question-meta strong { font-size: .84rem; }
    .package-question-actions, .package-question-tools { display: flex; gap: 6px; flex-wrap: wrap; }
    .package-question-actions .button { min-height: 36px; padding: 6px 9px; font-size: .78rem; }
    .package-question-tools .button, .package-bank-pagination .button { min-width: 36px; justify-content: center; }
    .package-question-tools .button:disabled, .package-bank button:disabled { opacity: .52; cursor: default; }
    .package-empty { padding: 32px 16px; text-align: center; color: var(--muted); }
    .package-shuffle { margin-top: 14px; }
    .package-shuffle-options { display: flex; flex-wrap: wrap; gap: 10px 22px; margin-top: 10px; }
    .package-shuffle-options label, .package-lock-note { display: flex; align-items: center; gap: 8px; }
    .package-form-actions { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 10px; position: sticky; bottom: 0; z-index: 3; margin-top: 12px; padding: 12px 14px; border: 1px solid var(--line); border-radius: 7px; background: #fff; }
    .package-bank { width: min(920px, calc(100% - 24px)); max-height: min(800px, calc(100dvh - 24px)); margin: auto; border: 1px solid var(--line); border-radius: 8px; padding: 0; color: var(--ink); background: #fff; }
    .package-bank[open] { display: flex; flex-direction: column; }
    .package-bank::backdrop { background: rgba(15, 35, 55, .55); }
    .package-bank .package-section-head { align-items: start; flex-wrap: nowrap; }
    .package-bank .package-section-head > .button { flex: 0 0 42px; width: 42px; min-width: 42px; padding-inline: 0; justify-content: center; }
    .package-bank-scroll { min-height: 100px; overflow: auto; overscroll-behavior: contain; }
    .package-bank-filters { display: grid; grid-template-columns: minmax(0, 1.25fr) repeat(3, minmax(0, 1fr)); gap: 10px; padding: 12px 16px; border-bottom: 1px solid var(--line); }
    .package-bank-filters .field, .package-bank-filters .select, .package-bank-filters .input { min-width: 0; width: 100%; }
    .package-bank-footer, .package-bank-pagination { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px 12px; padding: 10px 16px; border-top: 1px solid var(--line); }
    .package-bank-pagination { justify-content: center; }
    .package-bank-footer > span { color: var(--muted); font-weight: 700; }
    .package-bank [hidden], [data-package-form] [hidden], [data-package-empty][hidden] { display: none !important; }
    @media (max-width: 950px) {
        .package-question { grid-template-columns: 28px minmax(0, 1fr) minmax(112px, .4fr); }
        .package-question-actions { grid-column: 2 / -1; }
    }
    @media (max-width: 600px) {
        .package-question { grid-template-columns: 28px minmax(0, 1fr); padding: 12px; gap: 8px; }
        .package-question-meta { grid-column: 2; }
        .package-question-meta strong, .package-question-meta small { display: inline-block; margin-right: 5px; }
        .package-bank-filters { grid-template-columns: repeat(2, minmax(0, 1fr)); padding: 10px 12px; }
        .package-bank-footer { display: grid; grid-template-columns: minmax(0, 1fr); padding: 10px 12px; }
        .package-bank-footer .actions { display: grid; grid-template-columns: minmax(0, .88fr) minmax(0, 1.12fr); gap: 8px; }
        .package-bank-footer .button { min-width: 0; white-space: normal; padding-inline: 8px; }
        .package-bank-footer [data-bank-add] { gap: 5px; white-space: nowrap; }
        .package-form-actions { position: static; }
        .package-form-actions .actions { min-width: 0; }
    }
    @media (max-height: 470px) {
        .package-bank { max-height: calc(100dvh - 12px); overflow: auto; }
        .package-bank-scroll { min-height: 120px; }
    }
</style>
