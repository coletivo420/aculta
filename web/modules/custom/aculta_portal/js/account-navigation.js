(function (Drupal, once) {
  'use strict';

  const mobileQuery = window.matchMedia('(max-width: 767.98px)');

  // Sanfona do celular: abre a seção e carrega o conteúdo completo dela, pela mesma requisição AJAX.
  const loadAccordionItem = async (details) => {
    const panel = details.querySelector('[data-portal-account-accordion-panel]');
    if (!panel || panel.dataset.loaded === '1' || panel.dataset.loading === '1') return;
    // Sem busca AJAX de conteúdo: o painel leva à página da seção (ver navegação acima).
    const link = document.createElement('a');
    link.href = panel.dataset.src;
    link.textContent = Drupal.t('Abrir a página');
    const paragraph = document.createElement('p');
    paragraph.append(link);
    panel.replaceChildren(paragraph);
    panel.dataset.loaded = '1';
  };

  const setupAccordion = (layout) => {
    if (!mobileQuery.matches) return;
    // No celular o conteúdo vive só na sanfona: a coluna de conteúdo sai do DOM para não duplicar IDs.
    layout.querySelector('[data-portal-account-content]')?.remove();
    layout.querySelectorAll('.portal-account__item').forEach((details) => {
      if (details.dataset.acultaAccordion === '1') return;
      details.dataset.acultaAccordion = '1';
      details.addEventListener('toggle', () => {
        if (!details.open) return;
        // Só uma sanfona expandida por vez: as outras da mesma conta recolhem.
        layout.querySelectorAll('.portal-account__item[open]').forEach((other) => {
          if (other !== details) other.open = false;
        });
        loadAccordionItem(details);
      });
      if (details.open) loadAccordionItem(details);
    });
  };

  Drupal.behaviors.acultaPortalNavigation = {
    attach(context) {
      once('aculta-portal-navigation', '[data-portal-account-layout]', context).forEach((layout) => {
        setupAccordion(layout);
        // Navegação entre seções: página completa. A troca parcial de conteúdo não carrega as bibliotecas nem as
        // configurações da página destino (formulários com AJAX deixavam de funcionar). Ver docs/portal/REDIRECTS.md.
      });
    },
  };

  Drupal.behaviors.acultaPortalPhotoEditor = {
    attach(context) {
      once('aculta-portal-photo-editor', '[data-aculta-photo-editor]', context).forEach((editor) => {
        const dialog = editor.querySelector('[data-aculta-photo-dialog]');
        const trigger = editor.querySelector('[data-aculta-photo-open]');
        const closeButton = editor.querySelector('[data-aculta-photo-close]');
        const heading = dialog?.querySelector('#portal-account-photo-title');
        if (!dialog || !trigger || typeof dialog.showModal !== 'function') return;

        // The dialog starts open so its form remains available without JS.
        editor.classList.add('is-enhanced');
        if (dialog.open) dialog.close();

        trigger.addEventListener('click', () => {
          if (!dialog.open) dialog.showModal();
          window.requestAnimationFrame(() => heading?.focus());
        });
        closeButton?.addEventListener('click', () => dialog.close());
        dialog.addEventListener('click', (event) => {
          if (event.target === dialog) dialog.close();
        });
        dialog.addEventListener('close', () => trigger.focus());
      });
    },
  };
})(Drupal, once);
