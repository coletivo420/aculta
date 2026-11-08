/**
 * Citar comentário e resposta inline na discussão.
 *
 * Em conformidade com C02-05:
 * Interage com o compositor inline nativo HTMX do Core, gerenciando foco e acessibilidade
 * tanto para CKEditor 5 quanto textarea clássico, respeitando prefers-reduced-motion.
 */
(function (Drupal, once) {
  'use strict';

  const composerSelector = '[data-discussion-reply-composer]';
  const triggerSelector = '[data-action="comment-reply"], [data-action="comment-quote"]';

  /**
   * Devolve o contêiner do compositor ao estado fechado (div alvo vazia).
   *
   * O HTMX usa swap outerHTML, portanto o compositor substitui a div alvo.
   * Recriar a div com o mesmo id mantém o próximo "Responder"/"Citar" funcional.
   */
  function closeComposer(composer) {
    const target = document.createElement('div');
    target.id = composer.id;
    target.className = 'discussion-reply-target';
    composer.replaceWith(target);
  }

  /**
   * Fecha todos os compositores de resposta, exceto o alvo da requisição atual.
   */
  function closeOtherComposers(root, currentTarget) {
    root.querySelectorAll(composerSelector).forEach((composer) => {
      if (currentTarget && (composer === currentTarget || currentTarget.contains(composer))) {
        return;
      }
      closeComposer(composer);
    });
  }

  /**
   * Resolve o elemento alvo a partir do atributo hx-target quando o evento
   * não traz detail.target.
   */
  function resolveTarget(element) {
    const selector = element.getAttribute('hx-target') || element.getAttribute('data-hx-target');
    return selector ? document.querySelector(selector) : null;
  }

  Drupal.behaviors.acultaForumSingleComposer = {
    attach(context) {
      once('aculta-single-reply-composer', document.body, context).forEach((body) => {
        body.addEventListener('htmx:beforeRequest', (event) => {
          const detail = event.detail || {};
          const element = detail.elt;
          if (!element || typeof element.closest !== 'function' || !element.closest(triggerSelector)) {
            return;
          }
          const root = element.closest('[data-aculta-discussion-thread], .field--name-field-aculta-discussion, .aculta-forum-topic__content') || document;
          closeOtherComposers(root, detail.target || resolveTarget(element));
        });
      });
    },
  };

  Drupal.behaviors.acultaForumQuote = {
    attach(context) {
      // Monitora o término da carga do compositor via HTMX para colocar o foco no editor.
      once('aculta-composer-focus', '[data-discussion-reply-composer]', context).forEach((composer) => {
        const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        composer.scrollIntoView({ behavior: prefersReducedMotion ? 'auto' : 'smooth', block: 'center' });

        const instances = Drupal.CKEditor5Instances ? [...Drupal.CKEditor5Instances.values()] : [];
        const editor = instances.find((instance) => composer.contains(instance.sourceElement));
        if (editor) {
          editor.editing.view.focus();
        }
        else {
          const textarea = composer.querySelector('textarea');
          if (textarea) {
            textarea.focus();
          }
        }
      });
    },
  };
})(Drupal, once);
