/**
 * Fluxo do popup de foto (aculta_user).
 *
 * - Ao enviar uma foto personalizada (o arquivo muda em relação à foto salva), o popup de recorte abre sozinho.
 * - Ao voltar do recorte (?aculta_recorte=1), reabre o popup da foto para o "Salvar". O recorte não reabre sozinho.
 * - Cada arquivo abre o recorte uma única vez por sessão do navegador.
 */
(function (Drupal, once, drupalSettings) {
  'use strict';

  const ASKED = 'acultaCropAsked';
  const RETURNED = 'acultaCropReturned';

  function openCrop(fid) {
    const link = document.createElement('a');
    link.href = '/avatar/recortar?fid=' + encodeURIComponent(fid);
    link.className = 'use-ajax';
    link.setAttribute('data-dialog-type', 'modal');
    link.hidden = true;
    document.body.appendChild(link);
    Drupal.attachBehaviors(link);
    link.click();
  }

  Drupal.behaviors.acultaUserPhotoFlow = {
    attach(context) {
      once('aculta-user-photo-flow', '[data-aculta-photo-editor]', context).forEach((editor) => {
        const dialog = editor.querySelector('[data-aculta-photo-dialog]');
        const input = editor.querySelector('input[name="user_picture[0][fids]"]');
        const saved = String((drupalSettings.acultaUser || {}).savedFid || '0');
        const params = new URLSearchParams(window.location.search);

        if (params.has('aculta_recorte')) {
          // Voltou do recorte: reabre o popup da foto para o "Salvar"; não reabre o recorte.
          if (input && input.value) {
            sessionStorage.setItem(RETURNED, input.value);
          }
          window.setTimeout(() => {
            if (dialog && typeof dialog.showModal === 'function' && !dialog.open) {
              dialog.showModal();
            }
          }, 0);
          return;
        }

        const fid = input ? String(input.value || '') : '';
        if (fid !== '' && fid !== '0' && fid !== saved && fid !== sessionStorage.getItem(ASKED) && fid !== sessionStorage.getItem(RETURNED)) {
          sessionStorage.setItem(ASKED, fid);
          window.setTimeout(() => openCrop(fid), 0);
        }
      });
    },
  };
})(Drupal, once, drupalSettings);
