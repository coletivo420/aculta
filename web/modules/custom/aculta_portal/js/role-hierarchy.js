/**
 * @file
 * Arraste da cadeia de papéis (mouse e toque). O clique nas setas continua funcionando sem este script.
 *
 * Itens: [data-portal-role-item] dentro de [data-portal-role-list]. Ao soltar, atualiza o campo oculto [data-portal-role-order]
 * com a nova ordem (do mais alto ao mais baixo), que o servidor usa ao salvar.
 */
(function (Drupal, once) {
  'use strict';

  function atualizarOrdem(lista) {
    const campo = lista.closest('form')?.querySelector('[data-portal-role-order]');
    if (!campo) {
      return;
    }
    const ordem = Array.from(lista.querySelectorAll(':scope > [data-portal-role-item]')).map((item) => item.dataset.portalRoleItem);
    campo.value = JSON.stringify(ordem);
  }

  function iniciarArraste(item, evento) {
    // Botões dentro do item (setas) continuam clicáveis.
    if (evento.target.closest('button, a, input, select, textarea')) {
      return;
    }
    const lista = item.parentElement;
    evento.preventDefault();
    item.dataset.portalDragging = 'true';
    item.setPointerCapture(evento.pointerId);

    const mover = (ev) => {
      const irmaos = Array.from(lista.querySelectorAll(':scope > [data-portal-role-item]:not([data-portal-dragging])'));
      const alvo = irmaos.find((irmao) => {
        const caixa = irmao.getBoundingClientRect();
        return ev.clientY < caixa.top + caixa.height / 2;
      });
      if (alvo) {
        lista.insertBefore(item, alvo);
      }
      else {
        lista.appendChild(item);
      }
    };

    const soltar = () => {
      delete item.dataset.portalDragging;
      item.removeEventListener('pointermove', mover);
      item.removeEventListener('pointerup', soltar);
      item.removeEventListener('pointercancel', soltar);
      atualizarOrdem(lista);
    };

    item.addEventListener('pointermove', mover);
    item.addEventListener('pointerup', soltar);
    item.addEventListener('pointercancel', soltar);
  }

  Drupal.behaviors.roleHierarchyDrag = {
    attach(context) {
      once('role-hierarchy-drag', '[data-portal-role-list]', context).forEach((lista) => {
        lista.querySelectorAll(':scope > [data-portal-role-item]').forEach((item) => {
          item.style.touchAction = 'none';
          item.addEventListener('pointerdown', (evento) => iniciarArraste(item, evento));
        });
      });
    },
  };
})(Drupal, once);
