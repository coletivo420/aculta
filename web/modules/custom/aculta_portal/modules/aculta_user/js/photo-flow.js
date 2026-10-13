/**
 * Fluxo do popup de foto (aculta_user).
 *
 * - Quando um arquivo novo chega ao campo da foto (o upload terminou), o popup de recorte abre sozinho.
 * - Ao voltar do recorte (?aculta_recorte=1), reabre o popup da foto para o "Salvar". Nesse carregamento o recorte não reabre.
 * - Cada arquivo abre o recorte uma única vez por sessão do navegador.
 *
 * Observa o campo oculto user_picture[0][fids] em qualquer contexto: o Drupal troca esse bloco no upload por AJAX,
 * e o contêiner do popup não é trocado (por isso o script antigo não disparava).
 */
(function (Drupal, once, drupalSettings) {
  'use strict';

  const ASKED = 'acultaCropAsked';

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

  function reopenPhotoDialog() {
    window.setTimeout(() => {
      const dialog = document.querySelector('[data-aculta-photo-dialog]');
      if (dialog && typeof dialog.showModal === 'function' && !dialog.open) {
        dialog.showModal();
      }
    }, 0);
  }

  Drupal.behaviors.acultaUserPhotoFlow = {
    attach(context) {
      const params = new URLSearchParams(window.location.search);
      const returned = params.has('aculta_recorte');
      if (returned) {
        reopenPhotoDialog();
      }

      once('aculta-user-photo-fids', 'input[name="user_picture[0][fids]"]', context).forEach((input) => {
        if (returned) {
          return;
        }
        const fid = String(input.value || '');
        const saved = String((drupalSettings.acultaUser || {}).savedFid || '0');
        if (fid === '' || fid === '0' || fid === saved) {
          return;
        }
        if (fid === sessionStorage.getItem(ASKED)) {
          return;
        }
        sessionStorage.setItem(ASKED, fid);
        window.setTimeout(() => openCrop(fid), 0);
      });
    },
  };
})(Drupal, once, drupalSettings);
