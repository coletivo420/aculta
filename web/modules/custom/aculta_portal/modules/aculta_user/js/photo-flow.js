/**
 * Fluxo do popup de foto (aculta_user).
 *
 * - Quando um arquivo novo chega ao campo da foto (o upload terminou), o popup de recorte abre sozinho.
 * - Ao voltar do recorte (?aculta_recorte=1), reabre o popup da foto uma única vez para o "Salvar".
 *   O parâmetro é lido e removido da URL na carga da página: senão cada conteúdo carregado pelo Drupal (o próprio popup)
 *   reabriria o popup de novo, em loop.
 * - Cada arquivo abre o recorte uma única vez por sessão do navegador.
 *
 * Observa o campo oculto imagem[fids] (managed_file da UserPictureForm) em qualquer contexto: o Drupal troca esse bloco no upload por AJAX,
 * e o contêiner do popup não é trocado (por isso o script antigo não disparava).
 */
(function (Drupal, once, drupalSettings) {
  'use strict';

  const ASKED = 'acultaCropAsked';
  const RETURN = 'aculta_recorte';

  // Lido uma vez por carregamento de página, antes de qualquer behavior: o parâmetro sai da URL.
  const url = new URL(window.location.href);
  const returned = url.searchParams.has(RETURN);
  let reopenPending = returned;
  if (returned) {
    url.searchParams.delete(RETURN);
    window.history.replaceState(null, '', url.pathname + url.search + url.hash);
  }

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

  // Ao voltar do recorte, reabre o popup da foto (o gatilho é o link de modal do avatar). Uma única vez.
  function reopenPhotoPopup() {
    window.setTimeout(() => {
      document.querySelector('[data-aculta-photo-open]')?.click();
    }, 0);
  }

  Drupal.behaviors.acultaUserPhotoFlow = {
    attach(context) {
      if (reopenPending) {
        reopenPending = false;
        reopenPhotoPopup();
      }

      once('aculta-user-photo-fids', 'input[name="imagem[fids]"]', context).forEach((input) => {
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
