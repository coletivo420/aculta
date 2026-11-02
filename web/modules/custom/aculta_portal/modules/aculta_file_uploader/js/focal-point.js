/**
 * @file
 * Editor de ponto focal: clique ou setas posicionam o marcador, em percentual.
 *
 * Marcação esperada: [data-aculta-focal] com uma imagem, um marcador
 * [data-focal-marker], e inputs ocultos [data-focal-x] e [data-focal-y].
 */
(function (Drupal, once) {
  'use strict';

  const clamp = (value) => Math.max(0, Math.min(100, value));

  /**
   * Comportamento: liga o marcador de ponto focal à interação do usuário.
   */
  Drupal.behaviors.acultaFocalPoint = {
    attach(context) {
      once('aculta-focal', '[data-aculta-focal]', context).forEach((editor) => {
        const marker = editor.querySelector('[data-focal-marker]');
        const inputX = editor.querySelector('[data-focal-x]');
        const inputY = editor.querySelector('[data-focal-y]');
        if (!marker || !inputX || !inputY) {
          return;
        }

        // Estado inicial a partir dos inputs; centro quando vazio.
        let x = parseFloat(inputX.value);
        let y = parseFloat(inputY.value);
        x = Number.isFinite(x) ? clamp(x) : 50;
        y = Number.isFinite(y) ? clamp(y) : 50;

        const render = () => {
          marker.style.left = `${x}%`;
          marker.style.top = `${y}%`;
          inputX.value = x.toFixed(2);
          inputY.value = y.toFixed(2);
        };

        const setFromPointer = (event) => {
          const box = editor.getBoundingClientRect();
          if (box.width === 0 || box.height === 0) {
            return;
          }
          x = clamp(((event.clientX - box.left) / box.width) * 100);
          y = clamp(((event.clientY - box.top) / box.height) * 100);
          render();
        };

        // O contêiner precisa ser focável para o teclado chegar às setas.
        if (!editor.hasAttribute('tabindex')) {
          editor.setAttribute('tabindex', '0');
        }
        if (!editor.hasAttribute('role')) {
          editor.setAttribute('role', 'application');
        }
        if (!editor.getAttribute('aria-label')) {
          editor.setAttribute('aria-label', Drupal.t('Ponto focal da imagem'));
        }

        editor.addEventListener('click', setFromPointer);

        // Teclado: setas movem em 1%; com Shift, em 10%.
        editor.addEventListener('keydown', (event) => {
          const step = event.shiftKey ? 10 : 1;
          const moves = {
            ArrowLeft: [-step, 0],
            ArrowRight: [step, 0],
            ArrowUp: [0, -step],
            ArrowDown: [0, step],
          };
          const move = moves[event.key];
          if (!move) {
            return;
          }
          event.preventDefault();
          x = clamp(x + move[0]);
          y = clamp(y + move[1]);
          render();
        });

        render();
      });
    },
  };
})(Drupal, once);
