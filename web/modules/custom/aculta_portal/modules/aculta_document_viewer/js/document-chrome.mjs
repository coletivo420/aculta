/**
 * Barra de controles e status compartilhados pelos leitores DOCX e planilhas.
 *
 * - Zoom em passos fixos, aplicado com a propriedade CSS `zoom` no quadro de conteúdo.
 * - Tela cheia no contêiner do leitor (não cria janela nem muda a URL).
 * - Status em região `aria-live`: carregamento, pronto e falha, sempre em texto.
 *
 * Os rótulos passam por Drupal.t() quando disponível, para tradução.
 */

const ZOOM_LEVELS = [0.75, 0.9, 1, 1.15, 1.3, 1.5, 1.75, 2];
const DEFAULT_ZOOM_INDEX = 2;

const translate = (text) => (window.Drupal && typeof Drupal.t === 'function' ? Drupal.t(text) : text);

function control(name, label) {
  const button = document.createElement('button');
  button.type = 'button';
  button.dataset.acultaDocumentPart = 'control';
  button.dataset.acultaDocumentControl = name;
  button.textContent = label;
  return button;
}

/**
 * Prepara a barra de controles de um leitor.
 *
 * @param {HTMLElement} host Contêiner com data-aculta-document-viewer.
 * @param {HTMLElement} content Elemento cujo zoom e tela cheia são controlados.
 * @returns {{ setStatus: (text: string) => void, toolbar: HTMLElement }}
 */
export function setupDocumentChrome(host, content) {
  const toolbar = host.querySelector('[data-aculta-document-control="toolbar"]');
  const status = host.querySelector('[data-aculta-document-control="status"]');
  let zoomIndex = DEFAULT_ZOOM_INDEX;

  const zoomOutput = document.createElement('output');
  zoomOutput.dataset.acultaDocumentPart = 'zoom';
  zoomOutput.setAttribute('aria-live', 'polite');

  const applyZoom = () => {
    const level = ZOOM_LEVELS[zoomIndex];
    content.style.zoom = String(level);
    zoomOutput.textContent = `${Math.round(level * 100)}%`;
  };

  const zoomOut = control('zoom-out', translate('Diminuir zoom'));
  const zoomReset = control('zoom-reset', translate('Tamanho normal'));
  const zoomIn = control('zoom-in', translate('Aumentar zoom'));
  const fullscreen = control('fullscreen', translate('Tela cheia'));

  zoomOut.addEventListener('click', () => {
    zoomIndex = Math.max(0, zoomIndex - 1);
    applyZoom();
  });
  zoomReset.addEventListener('click', () => {
    zoomIndex = DEFAULT_ZOOM_INDEX;
    applyZoom();
  });
  zoomIn.addEventListener('click', () => {
    zoomIndex = Math.min(ZOOM_LEVELS.length - 1, zoomIndex + 1);
    applyZoom();
  });

  const syncFullscreen = () => {
    const active = document.fullscreenElement === host;
    fullscreen.setAttribute('aria-pressed', active ? 'true' : 'false');
    fullscreen.textContent = active ? translate('Sair da tela cheia') : translate('Tela cheia');
  };
  fullscreen.addEventListener('click', () => {
    if (!document.fullscreenEnabled) {
      return;
    }
    if (document.fullscreenElement === host) {
      document.exitFullscreen();
    }
    else {
      host.requestFullscreen().catch(() => {});
    }
  });
  document.addEventListener('fullscreenchange', syncFullscreen);

  toolbar.append(zoomOut, zoomOutput, zoomReset, zoomIn, fullscreen);
  applyZoom();
  syncFullscreen();

  return {
    toolbar,
    setStatus(text) {
      if (!status) {
        return;
      }
      status.textContent = text;
      status.hidden = text === '';
    },
  };
}
