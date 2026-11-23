<style>
    .bulk-grade-context { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 12px; margin-bottom: 16px; }
    .bulk-grade-context .field, .bulk-grade-context .select { min-width: 0; width: 100%; }
    .bulk-grade-pick { margin-bottom: 16px; }
    .bulk-grade-pick summary { padding: 14px 16px; cursor: pointer; font-weight: 800; }
    .bulk-grade-pick summary .badge { margin-left: 8px; }
    .bulk-grade-picker { border-top: 1px solid var(--line); padding: 14px 16px; }
    .bulk-grade-options { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(215px, 100%), 1fr)); gap: 8px; padding: 0; margin: 0; border: 0; }
    .bulk-grade-option { display: grid; grid-template-columns: 18px minmax(0, 1fr); align-items: start; gap: 8px; padding: 10px 12px; border: 1px solid var(--line); border-radius: 6px; overflow-wrap: anywhere; cursor: pointer; }
    .bulk-grade-option:has(input:checked) { background: var(--primary-soft); border-color: var(--primary); }
    .bulk-grade-option:has(input:disabled) { cursor: default; }
    .bulk-grade-option input { width: 17px; height: 17px; margin: 2px 0 0; accent-color: var(--primary); }
    .bulk-grade-option small, .bulk-grade-table small { display: block; margin-top: 3px; color: var(--muted); font-size: .8rem; font-weight: normal; }
    .bulk-grade-option .bulk-grade-locked { display: flex; align-items: center; gap: 5px; }
    .bulk-grade-locked svg { width: 14px; height: 14px; flex: none; }
    .bulk-grade-picker-end { margin-top: 12px; display: flex; justify-content: flex-end; }
    .bulk-grade-workspace { min-width: 0; }
    .bulk-grade-heading, .bulk-grade-footer { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px 16px; padding: 14px 16px; }
    .bulk-grade-heading { border-bottom: 1px solid var(--line); }
    .bulk-grade-heading h2 { margin: 0; font-size: 1.1rem; }
    .bulk-grade-heading p { margin: 4px 0 0; color: var(--muted); }
    .bulk-grade-scroll { max-height: min(65vh, 600px); overflow: auto; }
    .bulk-grade-table { width: max-content; min-width: 100%; border-spacing: 0; }
    .bulk-grade-table th, .bulk-grade-table td { border-right: 1px solid var(--line); border-bottom: 1px solid var(--line); padding: 8px 10px; vertical-align: top; }
    .bulk-grade-table th { text-align: left; overflow-wrap: anywhere; }
    .bulk-grade-table thead th { position: sticky; top: 0; z-index: 2; width: 142px; min-width: 142px; max-width: 142px; background: var(--primary-soft); }
    .bulk-grade-table th:first-child { position: sticky; left: 0; z-index: 1; width: clamp(128px, 28vw, 232px); min-width: clamp(128px, 28vw, 232px); max-width: 232px; }
    .bulk-grade-table thead th:first-child { z-index: 3; }
    .bulk-grade-table tbody th { background: #fff; font-size: .88rem; }
    .bulk-grade-table tbody tr:nth-child(even) > :is(th, td) { background: #f8fafc; }
    .bulk-grade-table td .input, .bulk-grade-table td .select { width: 100%; min-height: 38px; padding: 7px 9px; }
    .bulk-grade-table td:has([data-changed]) { background: var(--primary-soft); }
    .bulk-grade-cell-error { max-width: 120px; margin: 5px 0 0; overflow-wrap: anywhere; font-size: .76rem; }
    .bulk-grade-footer { color: var(--muted); }
    .bulk-grade-footer > div { min-width: 0; }
    .bulk-grade-footer .error-text { margin: 4px 0 0; }
    .bulk-grade-footer [hidden], .bulk-grade-cell-error[hidden] { display: none !important; }
    @media (max-width: 800px) {
        .bulk-grade-context { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 420px) {
        .bulk-grade-table thead th:not(:first-child) { width: 132px; min-width: 132px; max-width: 132px; font-size: .88rem; }
        .bulk-grade-table th, .bulk-grade-table td { padding-inline: 7px; }
        .bulk-grade-table tbody th { max-width: 128px; }
        .bulk-grade-footer .button { width: 100%; }
    }
</style>
