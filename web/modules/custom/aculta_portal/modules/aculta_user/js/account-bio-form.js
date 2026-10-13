/**
 * Modal da bio (aculta_user). Um único ouvinte de envio no documento (fase de captura) trata o formulário da bio,
 * identificado por data-aculta-bio-form. Não depende do momento em que o formulário entra na página.
 *
 * - Limpar (data-aculta-bio-clear): só esvazia o campo. Cancela o envio: não grava e não fecha o modal.
 * - Salvar bio: grava pela rota JSON (drupalSettings.acultaUser.bioSave). Depois de gravar, a visão geral é
 *   recarregada, o que fecha o modal e mostra a bio salva. Erro mantém o modal aberto com aviso.
 * Sem JS, o envio comum do Salvar grava e redireciona para a visão geral.
 */
(function (Drupal, drupalSettings) {
  'use strict';

  function onSubmit(event) {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-aculta-bio-form')) {
      return;
    }
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

    const settings = (drupalSettings.acultaUser || {}).bioSave;
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
      .then(() => {
        window.location.reload();
      })
      .catch(() => {
        if (submit) {
          submit.disabled = false;
        }
        window.alert('Não foi possível salvar a bio. Tente novamente.');
      });
  }

  Drupal.behaviors.acultaAccountBioForm = {
    attach() {
      if (!window.acultaAccountBioFormBound) {
        document.addEventListener('submit', onSubmit, true);
        window.acultaAccountBioFormBound = true;
      }
    },
  };
})(Drupal, drupalSettings);
