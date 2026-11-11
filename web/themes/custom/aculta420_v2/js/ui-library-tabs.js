/**
 * Abas da biblioteca de componentes e filtro em tempo real de componentes.
 * Clicar numa aba mostra o painel e os componentes correspondentes ao grupo.
 * O campo de busca filtra componentes por nome, ID ou grupo.
 */
(function (Drupal, once) {
  'use strict';

  let selectedGroupId = null;

  Drupal.behaviors.acultaUiLibraryTabs = {
    attach(context) {
      once('aculta-ui-library-tabs', '.ui_patterns_quicklinks', context).forEach((box) => {
        const links = Array.from(box.querySelectorAll('nav.aculta-tabs a'));
        const panels = Array.from(box.querySelectorAll('.aculta-component-library__panel'));
        const components = document.getElementById('aculta-components-container');
        const groups = components ? Array.from(components.querySelectorAll('.aculta-component-library__group-wrapper')) : [];
        const select = (link) => {
          const panelId = link.getAttribute('href').slice(1);
          const groupId = panelId.replace(/^painel-/, '');
          selectedGroupId = groupId;
          links.forEach((item) => item.removeAttribute('aria-current'));
          link.setAttribute('aria-current', 'page');
          panels.forEach((panel) => {
            panel.hidden = panel.id !== panelId;
          });
          groups.forEach((group) => {
            group.hidden = group.getAttribute('data-group-id') !== groupId;
          });
        };

        links.forEach((link) => {
          link.addEventListener('click', (event) => {
            event.preventDefault();
            select(link);
            window.history.replaceState(null, '', link.getAttribute('href'));
          });
        });

        // Aceita hash de painel ou de componente e seleciona seu grupo.
        const fromHash = decodeURIComponent(window.location.hash.slice(1));
        let initial = links.find((link) => link.getAttribute('href').slice(1) === fromHash);
        if (!initial && fromHash) {
          const target = document.getElementById(fromHash);
          const group = target && target.closest('.aculta-component-library__group-wrapper');
          const heading = group && group.querySelector('.aculta-component-library__group-title');
          if (heading) {
            initial = links.find((link) => link.getAttribute('href') === `#painel-${heading.id}`);
          }
        }
        initial = initial || links[0];
        if (initial) {
          select(initial);
        }
        if (fromHash && fromHash.includes('--')) {
          const target = document.getElementById(fromHash);
          if (target) {
            requestAnimationFrame(() => target.scrollIntoView({ behavior: 'smooth', block: 'start' }));
          }
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
            groups.forEach((grp) => {
              grp.hidden = grp.getAttribute('data-group-id') !== selectedGroupId;
            });
            return;
          }

          groups.forEach((grp) => {
            let hasVisible = false;
            const groupComponents = grp.querySelectorAll('.ui_patterns_component');
            groupComponents.forEach((comp) => {
              const name = comp.getAttribute('data-component-name') || '';
              const id = comp.getAttribute('data-component-id') || '';
              const group = comp.getAttribute('data-group-name') || '';
              const matches = name.includes(query) || id.toLowerCase().includes(query) || group.includes(query);
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
