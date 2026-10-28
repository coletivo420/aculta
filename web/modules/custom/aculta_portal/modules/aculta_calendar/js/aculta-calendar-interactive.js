/**
 * Visão interativa do calendário (DHTMLX Scheduler).
 *
 * Só inicializa na rota /calendario/interativo. O componente apresenta os itens
 * do aculta_calendar; nenhuma regra de dados fica aqui.
 */
((Drupal) => {
  Drupal.behaviors.acultaCalendarInteractive = {
    attach(context) {
      const container = context.querySelector('#aculta-calendar-interactive');
      if (!container || container.dataset.acultaInit === '1') {
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
      window.scheduler.init(container, new Date(), 'month');
    },
  };
})(Drupal);
