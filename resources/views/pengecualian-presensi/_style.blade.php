<style>
    .exception-section { margin: 0 0 28px; padding-top: 20px; border-top: 1px solid var(--line); }
    .exception-section h2 { margin: 0 0 18px; font-size: 1.05rem; }
    .exception-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; }
    .exception-grid .wide { grid-column: 1 / -1; }
    .exception-note { color: var(--muted); font-size: .86rem; line-height: 1.6; margin: 8px 0 0; overflow-wrap: anywhere; }
    .exception-actions { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-top: 18px; }
    .exception-actions .button { display: inline-flex; align-items: center; justify-content: center; gap: 8px; }
    .exception-actions .button-primary img { filter: brightness(0) invert(1); }
    .exception-section .alert-warning { color: #795300; border-color: #e5d298; background: #fff9e8; }
    .exception-history { display: grid; gap: 14px; }
    .exception-item { padding: 18px 20px; background: var(--panel); border: 1px solid var(--line); border-left: 4px solid #18755c; border-radius: 8px; }
    .exception-item.is-cancelled { border-left-color: #8892a0; }
    .exception-head { display: flex; justify-content: space-between; align-items: start; gap: 16px; }
    .exception-head h3 { margin: 0; font-size: 1rem; overflow-wrap: anywhere; }
    .exception-reason { margin: 12px 0; overflow-wrap: anywhere; }
    .exception-item details { padding-top: 14px; border-top: 1px solid var(--line); margin-top: 14px; }
    .exception-item summary { color: var(--danger); cursor: pointer; font-weight: 700; }
    .exception-item details form { margin-top: 16px; }
    .exception-check { display: flex; align-items: start; gap: 10px; margin-top: 16px; font-size: .9rem; }
    .exception-check input { width: 18px; height: 18px; margin: 2px 0 0; flex-shrink: 0; }
    .exception-stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 18px; margin: 22px 0; padding: 18px 0; border-top: 1px solid var(--line); border-bottom: 1px solid var(--line); }
    .exception-stats dt { color: var(--muted); font-size: .84rem; }
    .exception-stats dd { font-size: 1.5rem; font-weight: 800; margin: 6px 0 0; }
    .exception-table { width: 100%; border-collapse: collapse; }
    .exception-table th, .exception-table td { padding: 12px 10px; border-bottom: 1px solid var(--line); text-align: left; font-size: .9rem; overflow-wrap: anywhere; }
    .exception-table th { color: var(--muted); background: #f1f5f8; }
    .exception-table td:last-child { text-align: right; font-variant-numeric: tabular-nums; }
    @media (max-width: 620px) {
        .exception-grid { grid-template-columns: minmax(0, 1fr); }
        .exception-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .exception-item { padding: 16px; }
        .exception-actions .button { width: 100%; }
    }
</style>
