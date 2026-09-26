/* One controller for the static, progressively enhanced shared header. */
(() => {
  const header = document.querySelector('.siteHeader');
  if (!header) return;
  const toggle = header.querySelector('.siteHeader-toggle');
  const nav = header.querySelector('.siteHeader-nav');
  const mobile = window.matchMedia('(max-width:1100px)');
  const english = document.documentElement.lang.startsWith('en');
  const setOpen = (open, restoreFocus = false) => {
    toggle.setAttribute('aria-expanded', String(open));
    toggle.setAttribute('aria-label', english ? (open ? 'Close menu' : 'Open menu') : (open ? 'Zavřít menu' : 'Otevřít menu'));
    toggle.firstElementChild.textContent = open ? '✕' : '☰';
    nav.hidden = mobile.matches && !open;
    if (restoreFocus) toggle.focus();
  };
  toggle.hidden = false;
  setOpen(false);
  toggle.addEventListener('click', () => setOpen(toggle.getAttribute('aria-expanded') !== 'true'));
  nav.addEventListener('click', event => {
    if (event.target.closest('a,button') && mobile.matches) setOpen(false, true);
  });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') setOpen(false, true);
  });
  document.addEventListener('click', event => {
    if (!header.contains(event.target) && mobile.matches) setOpen(false);
  });
  header.addEventListener('focusout', event => {
    if (event.relatedTarget && !header.contains(event.relatedTarget) && mobile.matches) setOpen(false);
  });
  mobile.addEventListener('change', () => {
    const focusInNav = nav.contains(document.activeElement);
    setOpen(false, mobile.matches && focusInNav);
  });
  // Keep the current homepage section when switching languages.
  const sections = english
    ? {apps:'apps', gallery:'gallery', how:'jak-to-funguje', faq:'faq', about:'about', order:'checkout'}
    : {apps:'apps', gallery:'gallery', 'jak-to-funguje':'how', faq:'faq', about:'about', checkout:'order'};
  header.querySelectorAll('[data-lang-target]').forEach(link => {
    link.addEventListener('click', () => {
      const target = new URL(link.href);
      target.hash = sections[location.hash.slice(1)] || '';
      link.href = target.href;
    });
  });
})();
