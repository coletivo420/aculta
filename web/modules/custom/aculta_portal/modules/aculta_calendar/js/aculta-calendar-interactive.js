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
      window.scheduler.init(container, new Date(), 'month');
    },
  };
})(Drupal);
