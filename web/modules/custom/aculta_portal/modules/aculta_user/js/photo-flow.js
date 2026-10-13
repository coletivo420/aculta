/**
 * Fluxo do popup de foto (aculta_user).
 *
 * - Quando um arquivo novo chega ao campo da foto (o upload terminou), o modal de recorte abre sozinho.
 * - Aplicar o recorte já troca a foto e recarrega o painel: este script não reabre nada ao voltar.
 * - Sortear atualiza os avatares por AJAX; fechar o modal depois disso recarrega a página.
 * - Cada arquivo abre o recorte uma única vez por sessão do navegador.
 *
 * Observa o campo oculto imagem[fids] (managed_file da UserPictureForm) em qualquer contexto: o Drupal troca esse bloco no upload por AJAX.
 */
(function (Drupal, once, drupalSettings) {
  'use strict';

  const ASKED = 'acultaCropAsked';

  function openCrop(fid) {
    const link = document.createElement('a');
    link.href = '/avatar/recortar?fid=' + encodeURIComponent(fid);
    link.className = 'use-ajax';
    link.setAttribute('data-dialog-type', 'modal');
    // Mesmas opções do modal de recorte (AvatarCropForm::DIALOG_OPTIONS): rolagem quando passa da altura máxima.
    link.setAttribute('data-dialog-options', JSON.stringify({ width: 560, maxHeight: '75%', title: 'Recortar foto' }));
    link.hidden = true;
    document.body.appendChild(link);
    Drupal.attachBehaviors(link);
    link.click();
  }

  // Sorteio (AJAX) marca o body. Fechar o modal depois disso recarrega a página: o painel inteiro reflete a foto.
  // O evento dialog:afterclose borbulha (core/misc/dialog/dialog.js), então um único listener no document basta.
  function reloadAfterAvatarChange() {
    document.addEventListener('dialog:afterclose', () => {
      if (document.body.getAttribute('data-aculta-avatar-changed') === '1') {
        window.location.reload();
      }
    });
  }

  Drupal.behaviors.acultaUserPhotoFlow = {
    attach(context) {
      once('aculta-user-avatar-reload', 'body', context).forEach(reloadAfterAvatarChange);

      once('aculta-user-photo-fids', 'input[name="imagem[fids]"]', context).forEach((input) => {
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
