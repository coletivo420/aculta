/**
 * Alterna o lado das respostas da conversa pela ordem exibida: 1.ª espelhada, 2.ª normal, e assim por diante.
 */
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.acultaDiscussionSide = {
    attach(context) {
      once('aculta-discussion-side', '[data-aculta-discussion-thread]', context).forEach((thread) => {
        // Só as respostas da raiz: as respostas em nível têm o próprio espelhamento, pelo servidor.
        thread.querySelectorAll('.reply-indent--depth-0 .forum-post-card').forEach((card, index) => {
          const end = index % 2 === 0;
          card.classList.toggle('forum-post-card--side-end', end);
          card.classList.toggle('forum-post-card--side-start', !end);
          card.classList.toggle('forum-post-card--accent-red', end);
          card.classList.toggle('forum-post-card--accent-green', !end);
        });
      });
    },
  };
})(Drupal, once);
