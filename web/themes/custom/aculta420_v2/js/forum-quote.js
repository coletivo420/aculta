/**
 * Citar comentário e resposta inline na discussão.
 *
 * Em conformidade com C02-05:
 * Interage com o compositor inline nativo HTMX do Core, gerenciando foco e acessibilidade
 * tanto para CKEditor 5 quanto textarea clássico, respeitando prefers-reduced-motion.
 */
(function (Drupal, once) {
  'use strict';

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
