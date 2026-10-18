(() => {
  'use strict';
  const root = document.querySelector('[data-admin-shell] .content-shell');
  if (!root) return;
  const openers = new WeakMap();

  root.addEventListener('click', event => {
    const disabledLink = event.target.closest('a.button[aria-disabled="true"]');
    if (disabledLink) { event.preventDefault(); return; }
    const trigger = event.target.closest('[data-ui-dialog-open]');
    if (trigger && !trigger.disabled && trigger.getAttribute('aria-disabled') !== 'true') {
      const dialog = document.getElementById(trigger.dataset.uiDialogOpen);
      if (!(dialog instanceof HTMLDialogElement) || !dialog.matches('.ui-dialog') || !root.contains(dialog) || dialog.open) return;
      event.preventDefault();
      openers.set(dialog, trigger);
      window.dispatchEvent(new Event('nusa:tooltip-hide'));
      dialog.showModal();
    }
    const close = event.target.closest('[data-ui-dialog-close]');
    const dialog = close?.closest('dialog.ui-dialog');
    if (dialog && !close.disabled) dialog.close();
  });

  root.querySelectorAll('dialog.ui-dialog').forEach(dialog => {
    dialog.addEventListener('keydown', event => {
      if (event.key !== 'Tab') return;
      const controls = [...dialog.querySelectorAll('a[href],button,input,select,textarea,[tabindex],[contenteditable="true"]')]
        .filter(element => element.tabIndex >= 0 && !element.disabled && element.getAttribute('aria-disabled') !== 'true' && !element.closest('[inert]') && element.getClientRects().length && getComputedStyle(element).visibility !== 'hidden');
      if (!controls.length) { event.preventDefault(); return; }
      const first = controls[0], last = controls.at(-1);
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    });
    dialog.addEventListener('close', () => {
      const trigger = openers.get(dialog);
      if (trigger?.isConnected) trigger.focus({preventScroll: true});
      openers.delete(dialog);
    });
  });
})();
