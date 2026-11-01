/**
 * Visão interativa do calendário (DHTMLX Scheduler).
 *
 * Só inicializa na rota /calendario/interativo. O componente apresenta os itens
 * do aculta_calendar; nenhuma regra de dados fica aqui.
 */
((Drupal, drupalSettings) => {
  Drupal.behaviors.acultaCalendarInteractive = {
    attach(context) {
      const container = context.querySelector('#aculta-calendar-interactive');
      if (!container) {
        return;
      }
      const agenda = document.getElementById('aculta-calendar-agenda');
      const botao = document.getElementById('aculta-calendar-contrast');
      if (botao && botao.dataset.acultaContraste !== '1') {
        botao.dataset.acultaContraste = '1';
        botao.addEventListener('click', () => {
          const atual = document.documentElement.getAttribute('data-scheduler-theme');
          const proximo = atual === 'contrast-black' ? 'contrast-white' : atual === 'contrast-white' ? '' : 'contrast-black';
          if (proximo) {
            document.documentElement.setAttribute('data-scheduler-theme', proximo);
          }
          else {
            document.documentElement.removeAttribute('data-scheduler-theme');
          }
          botao.setAttribute('aria-pressed', proximo ? 'true' : 'false');
          botao.textContent = proximo === 'contrast-black' ? 'Alto contraste (branco)' : proximo === 'contrast-white' ? 'Voltar ao tema padrão' : 'Alto contraste';
        });
      }
      const mq = window.matchMedia('(max-width: 767px)');
      const aplicar = () => {
        // Abaixo de md, a grade dá lugar à agenda em HTML (a alternativa).
        if (mq.matches) {
          container.hidden = true;
          if (botao) {
            botao.hidden = true;
          }
          if (agenda) {
            agenda.hidden = false;
          }
          return;
        }
        container.hidden = false;
        if (botao) {
          botao.hidden = false;
        }
        if (agenda) {
          agenda.hidden = true;
        }
        if (container.dataset.acultaInit === '1') {
          return;
        }
        if (typeof window.scheduler === 'undefined' || typeof window.scheduler.init !== 'function') {
          return;
        }
        container.dataset.acultaInit = '1';
        // O calendário só é alcançável por Tab com o plugin key_nav, antes do init.
        if (typeof window.scheduler.plugins === 'function') {
          window.scheduler.plugins({ key_nav: true });
        }
        const settings = drupalSettings.acultaCalendar || {};
        const inicio = settings.date ? new Date(settings.date.replace(' ', 'T') + ':00') : new Date();
        window.scheduler.init(container, inicio, 'month');
        const items = settings.items || [];
        if (items.length > 0) {
          window.scheduler.parse(items, 'json');
        }
      };
      if (container.dataset.acultaLayout !== '1') {
        mq.addEventListener('change', aplicar);
        container.dataset.acultaLayout = '1';
      }
      aplicar();
    },
  };
})(Drupal, drupalSettings);
