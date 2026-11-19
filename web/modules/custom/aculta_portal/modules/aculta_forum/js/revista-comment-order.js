/**
 * Revista: comentários por total de reações (desc) e depois data (desc). Cada raiz leva as respostas
 * em nível que vêm logo depois dela, para a conversa continuar agrupada.
 */
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.acultaRevistaCommentOrder = {
    attach(context) {
      once('aculta-revista-comment-order', '.revista-comment-sort', context).forEach((first) => {
        const items = Array.from(document.querySelectorAll('.revista-comment-sort'));
        if (items.length < 2) {
          return;
        }
        const parent = first.parentNode;
        const groups = [];
        items.forEach((item) => {
          const depth = Number(item.querySelector('[data-reply-depth]')?.dataset.replyDepth || 0);
          if (depth === 0 || groups.length === 0) {
            groups.push([item]);
          }
          else {
            groups[groups.length - 1].push(item);
          }
        });
        const head = (group) => group[0];
        groups.sort((a, b) => {
          const ha = head(a);
          const hb = head(b);
          const ta = Number(ha.dataset.sortTotal || 0);
          const tb = Number(hb.dataset.sortTotal || 0);
          if (ta !== tb) {
            return tb - ta;
          }
          return Number(hb.dataset.sortCreated || 0) - Number(ha.dataset.sortCreated || 0);
        });
        const anchor = items[items.length - 1].nextSibling;
        groups.flat().forEach((item) => parent.insertBefore(item, anchor));
        // Recalcula os lados na nova ordem: raiz alterna (1.ª espelhada); resposta em nível é o oposto do pai.
        const sideAt = [];
        let rootIndex = 0;
        groups.flat().forEach((item) => {
          const depth = Number(item.querySelector('[data-reply-depth]')?.dataset.replyDepth || 0);
          let end;
          if (depth === 0) {
            end = rootIndex % 2 === 0;
            rootIndex += 1;
          }
          else {
            end = !sideAt[depth - 1];
          }
          sideAt[depth] = end;
          const card = item.querySelector('.forum-reply-compact');
          if (card) {
            card.classList.toggle('forum-reply-compact--side-end', end);
          }
        });
      });
    },
  };
})(Drupal, once);
