/**
 * Abas da biblioteca de componentes e filtro em tempo real de componentes.
 * Clicar numa aba mostra só o painel do grupo (subcomponentes), sem navegar pelo endereço.
 * O campo de busca filtra componentes por nome, ID ou grupo.
 */
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.acultaUiLibraryTabs = {
    attach(context) {
      // Se a URL contém hash com dois-pontos (ex.: #aculta420_v2:auth-dialog),
      // removemos temporariamente antes de scripts de tabs tentarem usar como seletor jQuery.
      if (window.location.hash && window.location.hash.includes(':')) {
        const rawId = window.location.hash.slice(1);
        const targetElement = document.getElementById(rawId);
        if (targetElement) {
          // Mantém a rolagem até o componente sem quebrar seletores do jQuery.
          setTimeout(() => {
            targetElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
          }, 100);
        }
      }

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

      // Busca e filtragem em tempo real de componentes (UP-06)
      once('aculta-ui-library-search', '#aculta-ui-search', context).forEach((searchInput) => {
        const components = Array.from(document.querySelectorAll('.ui_patterns_component'));
        const groups = Array.from(document.querySelectorAll('.aculta-component-library__group-wrapper'));

        searchInput.addEventListener('input', () => {
          const query = searchInput.value.trim().toLowerCase();
          if (!query) {
            components.forEach((comp) => { comp.hidden = false; });
            groups.forEach((grp) => { grp.hidden = false; });
            return;
          }

          groups.forEach((grp) => {
            let hasVisible = false;
            const groupComponents = grp.querySelectorAll('.ui_patterns_component');
            groupComponents.forEach((comp) => {
              const name = comp.getAttribute('data-component-name') || '';
              const id = comp.getAttribute('data-component-id') || '';
              const matches = name.includes(query) || id.toLowerCase().includes(query);
              comp.hidden = !matches;
              if (matches) {
                hasVisible = true;
              }
            });
            grp.hidden = !hasVisible;
          });
        });
      });
    },
  };
})(Drupal, once);
