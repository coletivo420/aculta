/**
 * Citar comentário no fórum: insere o trecho no editor da página atual, sem navegar.
 */
(function (Drupal, once) {
  'use strict';

  const escapeHtml = (value) => value
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');

  Drupal.behaviors.acultaForumQuote = {
    attach(context) {
      once('aculta-forum-quote', 'a[href*="?quote="]', context).forEach((link) => {
        link.addEventListener('click', (event) => {
          const form = document.querySelector('form#comment-form');
          if (!form) {
            // Sem formulário na página: deixa o link seguir o fluxo padrão.
            return;
          }
          const card = link.closest('.forum-post-card');
          const author = card?.querySelector('.forum-post-card__author-name')?.textContent.trim() || '';
          const body = card?.querySelector('.forum-post-card__body')?.innerText.trim() || '';

          event.preventDefault();

          const quote = `<blockquote><p><strong>${escapeHtml(author)}</strong></p><p>${escapeHtml(body)}</p></blockquote><p>&nbsp;</p>`;
          const instances = Drupal.CKEditor5Instances ? [...Drupal.CKEditor5Instances.values()] : [];
          const editor = instances.find((instance) => form.contains(instance.sourceElement));

          if (editor) {
            editor.setData(editor.getData() + quote);
            editor.editing.view.focus();
          }
          else {
            const textarea = form.querySelector('textarea');
            if (textarea) {
              textarea.value += `\n> ${author}: ${body}\n`;
              textarea.focus();
            }
          }
          form.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
      });
    },
  };
})(Drupal, once);
