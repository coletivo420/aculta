/**
 * Modal da bio (aculta_user).
 *
 * - Limpar só esvazia o campo; não envia nem fecha o modal.
 * - Salvar bio grava pela rota JSON (drupalSettings.acultaUser.bioSave), atualiza o texto da caixa da visão geral
 *   e fecha o modal pelo botão de fechar do diálogo. Sem JS, o envio comum do formulário grava e redireciona.
 */
(function (Drupal, once, drupalSettings) {
  'use strict';

  function closeDialog(form) {
    const dialog = form.closest('.ui-dialog');
    const close = dialog ? dialog.querySelector('.ui-dialog-titlebar-close') : null;
    if (close) {
      close.click();
    }
  }

  Drupal.behaviors.acultaAccountBioForm = {
    attach(context) {
      once('aculta-account-bio-clear', '[data-aculta-bio-clear]', context).forEach((button) => {
        button.addEventListener('click', (event) => {
          // O Drupal renderiza o botão como submit: o clique não pode enviar o formulário.
          event.preventDefault();
          const field = button.form ? button.form.querySelector('textarea[name="bio"]') : null;
          if (field) {
            field.value = '';
            field.focus();
          }
        });
      });

      once('aculta-account-bio-save', '[data-aculta-bio-form]', context).forEach((form) => {
        const settings = (drupalSettings.acultaUser || {}).bioSave;
        if (!settings) {
          return;
        }
        form.addEventListener('submit', (event) => {
          event.preventDefault();
          const submit = form.querySelector('input[name="op"]:not([data-aculta-bio-clear])');
          if (submit) {
            submit.disabled = true;
          }
          fetch(settings.url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: new URLSearchParams(new FormData(form)),
          })
            .then((response) => {
              if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
              }
              return response.json();
            })
            .then((data) => {
              const card = document.getElementById('aculta-account-bio-card');
              const text = card ? card.querySelector('textarea') : null;
              if (text) {
                text.value = data.bio;
              }
              closeDialog(form);
            })
            .catch(() => {
              if (submit) {
                submit.disabled = false;
              }
              window.alert('Não foi possível salvar a bio. Tente novamente.');
            });
        });
      });
    },
  };
})(Drupal, once, drupalSettings);
