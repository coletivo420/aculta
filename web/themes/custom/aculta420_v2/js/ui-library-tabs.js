/**
 * Abas da biblioteca de componentes. Clicar numa aba mostra só o painel do grupo (subcomponentes),
 * sem navegar pelo endereço, para a página não rolar. A aba escolhida recebe aria-current, com o
 * mesmo destaque do componente de navegação de suíte.
 */
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.acultaUiLibraryTabs = {
    attach(context) {
      once('aculta-ui-library-tabs', '.ui_patterns_quicklinks', context).forEach((box) => {
        const links = Array.from(box.querySelectorAll('nav.aculta-tabs a'));
        const panels = Array.from(box.querySelectorAll('.aculta-component-library__panel'));

        const select = (link) => {
          const panelId = link.getAttribute('href').slice(1);
          links.forEach((item) => item.removeAttribute('aria-current'));
          link.setAttribute('aria-current', 'page');
          panels.forEach((panel) => {
            panel.hidden = panel.id !== panelId;
          });
        };

        links.forEach((link) => {
          link.addEventListener('click', (event) => {
            event.preventDefault();
            select(link);
            window.history.replaceState(null, '', link.getAttribute('href'));
          });
        });

        // Endereço direto de um grupo (com acentos codificados) ou, por padrão, a primeira aba.
        const fromHash = decodeURIComponent(window.location.hash.slice(1));
        const initial = links.find((link) => link.getAttribute('href').slice(1) === fromHash) || links[0];
        if (initial) {
          select(initial);
        }
      });
    },
  };
})(Drupal, once);
