(() => {
  'use strict';
  const body = document.querySelector('[data-admin-shell]');
  if (!body) return;
  const sidebar = body.querySelector('.app-sidebar');
  const content = body.querySelector('.app-content');
  const checkbox = body.querySelector('#sidebar-toggle');
  const toggle = body.querySelector('[data-admin-sidebar-toggle]');
  const close = body.querySelector('[data-admin-sidebar-close]');
  const options = body.querySelector('[data-admin-sidebar-options]');
  const modes = [...body.querySelectorAll('[data-admin-sidebar-mode]')];
  const tooltip = body.querySelector('#admin-tooltip');
  const mobile = matchMedia('(max-width:760px)');
  const compact = matchMedia('(max-width:1080px)');
  const storageKey = body.dataset.adminSidebarStorage;
  let preference = null;
  let lastVisible = compact.matches ? 'collapsed' : 'expanded';
  try {
    const saved = localStorage.getItem(storageKey);
    if (['expanded', 'collapsed', 'hidden'].includes(saved)) preference = saved;
    const previous = localStorage.getItem(`${storageKey}.visible`);
    if (previous === 'expanded' || previous === 'collapsed') lastVisible = previous;
  } catch { /* Sidebar tetap berfungsi saat penyimpanan browser dibatasi. */ }
  window.lucide?.createIcons();

  function desktopMode() {
    return preference || (compact.matches ? 'collapsed' : 'expanded');
  }
  function savePreference(mode) {
    if (mode === 'hidden') lastVisible = desktopMode() === 'hidden' ? lastVisible : desktopMode();
    else lastVisible = mode;
    preference = mode;
    try {
      localStorage.setItem(storageKey, preference);
      localStorage.setItem(`${storageKey}.visible`, lastVisible);
    } catch { /* Preferensi hanya berlaku di halaman ini. */ }
  }
  function hideTooltip() {
    tooltip.hidden = true;
    body.querySelectorAll('[aria-describedby="admin-tooltip"]').forEach(element => element.removeAttribute('aria-describedby'));
  }
  function syncSidebar() {
    const mode = desktopMode();
    const open = mobile.matches ? checkbox.checked : mode !== 'hidden';
    body.classList.toggle('admin-sidebar-collapsed', mode === 'collapsed');
    body.classList.toggle('admin-sidebar-expanded', mode === 'expanded');
    body.classList.toggle('admin-sidebar-hidden', mode === 'hidden');
    body.classList.toggle('admin-drawer-open', mobile.matches && open);
    toggle.setAttribute('aria-expanded', String(open));
    const label = open ? 'Sembunyikan sidebar' : 'Tampilkan sidebar';
    toggle.setAttribute('aria-label', label);
    toggle.dataset.adminTooltip = label;
    toggle.querySelector('[data-admin-sidebar-hide-icon]').toggleAttribute('hidden', !open);
    toggle.querySelector('[data-admin-sidebar-show-icon]').toggleAttribute('hidden', open);
    modes.forEach(input => { input.checked = input.value === mode; });
    sidebar.inert = !open;
    sidebar.setAttribute('aria-hidden', String(sidebar.inert));
    content.inert = mobile.matches && open;
    if (sidebar.inert && sidebar.contains(document.activeElement)) toggle.focus({preventScroll:true});
    hideTooltip();
    requestAnimationFrame(() => window.dispatchEvent(new Event('nusa:admin-shell-resize')));
  }
  function closeSidebar() {
    checkbox.checked = false;
    syncSidebar();
    toggle.focus({preventScroll:true});
  }
  toggle.addEventListener('click', () => {
    if (mobile.matches) checkbox.checked = !checkbox.checked;
    else savePreference(desktopMode() === 'hidden' ? lastVisible : 'hidden');
    options.open = false;
    syncSidebar();
    if (mobile.matches && checkbox.checked) close.focus({preventScroll:true});
  });
  close.addEventListener('click', closeSidebar);
  options.addEventListener('toggle', () => { if (options.open) hideTooltip(); });
  options.querySelector('summary').addEventListener('click', hideTooltip);
  checkbox.addEventListener('change', syncSidebar);
  modes.forEach(input => input.addEventListener('change', () => {
    if (!input.checked) return;
    savePreference(input.value);
    options.open = false;
    syncSidebar();
    toggle.focus({preventScroll:true});
  }));
  mobile.addEventListener('change', () => { checkbox.checked = false; options.open = false; syncSidebar(); });
  compact.addEventListener('change', syncSidebar);
  sidebar.querySelectorAll('details.sidebar-section > summary').forEach(summary => {
    summary.addEventListener('click', () => {
      if (!mobile.matches && desktopMode() === 'collapsed') { savePreference('expanded'); syncSidebar(); }
    });
  });
  sidebar.addEventListener('keydown', event => {
    if (!mobile.matches || !checkbox.checked || event.key !== 'Tab') return;
    const targets = [...sidebar.querySelectorAll('a,button,input,summary')]
      .filter(element => !element.disabled && element.tabIndex >= 0 && element.getClientRects().length);
    if (event.shiftKey && document.activeElement === targets[0]) {
      event.preventDefault(); targets.at(-1)?.focus();
    } else if (!event.shiftKey && document.activeElement === targets.at(-1)) {
      event.preventDefault(); targets[0]?.focus();
    }
  });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') {
      hideTooltip();
      if (options.open) { options.open = false; options.querySelector('summary').focus({preventScroll:true}); }
      if (mobile.matches && checkbox.checked && !document.querySelector('dialog[open]')) closeSidebar();
    }
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
      const search = sidebar.querySelector('[data-sidebar-search-input]');
      if (!search || document.querySelector('dialog[open]')) return;
      event.preventDefault();
      if (mobile.matches) checkbox.checked = true;
      else savePreference('expanded');
      options.open = false;
      syncSidebar(); search.focus({preventScroll:true}); search.select();
    }
  });
  function showTooltip(button) {
    if (button.closest('[data-admin-sidebar-options][open]')) { hideTooltip(); return; }
    tooltip.textContent = button.dataset.adminTooltip;
    tooltip.hidden = false;
    const box = button.getBoundingClientRect(), tip = tooltip.getBoundingClientRect();
    tooltip.style.left = `${Math.max(8, Math.min(innerWidth - tip.width - 8, box.left + box.width / 2 - tip.width / 2))}px`;
    tooltip.style.top = `${Math.max(8, Math.min(innerHeight - tip.height - 8, box.top > tip.height + 12 ? box.top - tip.height - 8 : box.bottom + 8))}px`;
    button.setAttribute('aria-describedby', 'admin-tooltip');
  }
  document.addEventListener('pointerover', event => {
    const button = event.target.closest('[data-admin-tooltip]');
    if (button) showTooltip(button);
  });
  document.addEventListener('focusin', event => {
    const button = event.target.closest('[data-admin-tooltip]');
    if (button) showTooltip(button); else hideTooltip();
  });
  document.addEventListener('pointerout', event => { if (event.target.closest('[data-admin-tooltip]')) hideTooltip(); });
  document.addEventListener('focusout', hideTooltip);
  document.addEventListener('scroll', hideTooltip, true);
  document.addEventListener('nusa:tooltip-hide', hideTooltip);
  window.addEventListener('resize', hideTooltip);
  syncSidebar();
  requestAnimationFrame(() => {
    if (mobile.matches || desktopMode() !== 'expanded') return;
    const nav = sidebar.querySelector('[data-sidebar-nav]'), active = nav.querySelector('[aria-current="page"]');
    if (!active) return;
    const box = active.getBoundingClientRect(), area = nav.getBoundingClientRect();
    if (box.bottom > area.bottom) nav.scrollTop += box.bottom - area.bottom + 8;
    else if (box.top < area.top) nav.scrollTop -= area.top - box.top + 8;
  });
})();
