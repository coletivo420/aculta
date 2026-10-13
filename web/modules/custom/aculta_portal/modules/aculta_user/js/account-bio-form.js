/**
 * Modal da bio (aculta_user). Tudo passa pelo evento de envio do formulário, identificando o botão de origem:
 *
 * - Limpar (data-aculta-bio-clear): só esvazia o campo. Cancela o envio: não grava e não fecha o modal.
 * - Salvar bio: grava pela rota JSON (drupalSettings.acultaUser.bioSave), atualiza o texto da caixa da visão geral
 *   e fecha o modal pela API de diálogo do Drupal. Erro mantém o modal aberto com aviso.
 * Sem JS, o envio comum do Salvar grava e redireciona para a visão geral.
 */
(function (Drupal, once, drupalSettings) {
  'use strict';

  // Depois de gravar, a visão geral é recarregada: o modal fecha e a caixa mostra a bio salva.
  function finishSave() {
    window.location.reload();
  }

  Drupal.behaviors.acultaAccountBioForm = {
    attach(context) {
      once('aculta-account-bio-form', '[data-aculta-bio-form]', context).forEach((form) => {
        const settings = (drupalSettings.acultaUser || {}).bioSave;
        form.addEventListener('submit', (event) => {
          const origin = event.submitter;

          if (origin && origin.hasAttribute('data-aculta-bio-clear')) {
            event.preventDefault();
            const field = form.querySelector('textarea[name="bio"]');
            if (field) {
              field.value = '';
              field.focus();
            }
            return;
          }

          if (!settings) {
            return;
          }
          event.preventDefault();
          const submit = origin && origin.name === 'op' ? origin : form.querySelector('input[name="op"]:not([data-aculta-bio-clear])');
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
              finishSave();
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
