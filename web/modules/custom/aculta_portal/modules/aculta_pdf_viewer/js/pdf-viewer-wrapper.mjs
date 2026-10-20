const parameters = new URLSearchParams(window.location.search);
const file = parameters.get('file') || '';
const iframe = document.getElementById('pdf-viewer');
const error = document.getElementById('viewer-error');
const scrollMode = parameters.get('scroll') === 'horizontal' ? 1 : 0;
const doublePage = parameters.get('spread') === '1';
const doublePageMobile = parameters.get('spread_mobile') === '1';
const hidePrint = parameters.get('print') === '0';
const hideDownload = parameters.get('download') === '0';
const mobileLayout = window.matchMedia('(max-width: 40rem)');

function showError(message) {
  if (!error) {
    return;
  }
  error.textContent = message;
  error.hidden = false;
}

function isAllowedFileUrl(value) {
  if (!value.startsWith('/') || value.startsWith('//')) {
    return false;
  }
  try {
    const url = new URL(value, window.location.origin);
    if (url.origin !== window.location.origin) {
      return false;
    }
    if (url.pathname.toLowerCase().endsWith('.pdf')) {
      return true;
    }
    return url.pathname === '/system/files'
      && (new URLSearchParams(url.search).get('file') || '').toLowerCase().endsWith('.pdf');
  }
  catch {
    return false;
  }
}

if (!iframe || !error || !isAllowedFileUrl(file)) {
  showError('O endereço deste PDF não é válido. Volte à página do documento e tente novamente.');
}
else {
  const viewerUrl = new URL('/libraries/pdf.js/web/viewer.html', window.location.origin);
  viewerUrl.searchParams.set('file', file);
  viewerUrl.searchParams.set('locale', 'pt-BR');

  // PDF.js 6.4.299 dispatches `webviewerloaded` on the parent document before
  // calling PDFViewerApplication.run(). Set the pinned viewer options there so
  // defaults are applied before the PDF and user preferences are initialized.
  document.addEventListener('webviewerloaded', (event) => {
    const viewerWindow = event.detail?.source;
    if (!viewerWindow || viewerWindow !== iframe.contentWindow) {
      return;
    }
    const options = viewerWindow.PDFViewerApplicationOptions;
    const application = viewerWindow.PDFViewerApplication;
    if (!options?.set || !application?.initializedPromise) {
      showError('O leitor PDF.js não expôs a configuração esperada. Use o link do documento na página anterior.');
      return;
    }

    options.set('scrollModeOnLoad', scrollMode);
    options.set('spreadModeOnLoad', doublePage && (!mobileLayout.matches || doublePageMobile) ? 1 : 0);

    const applyHiddenControls = () => {
      const hiddenIds = [
        ...(hidePrint ? ['printButton', 'secondaryPrint'] : []),
        ...(hideDownload ? ['downloadButton', 'secondaryDownload'] : []),
      ];
      for (const id of hiddenIds) {
        const control = viewerWindow.document.getElementById(id);
        if (!control) {
          continue;
        }
        control.hidden = true;
        control.setAttribute('aria-hidden', 'true');
        control.tabIndex = -1;
      }
    };
    applyHiddenControls();

    application.initializedPromise.then(() => {
      const applySpreadMode = () => {
        if (!application.pdfViewer) {
          return;
        }
        application.pdfViewer.spreadMode = doublePage && (!mobileLayout.matches || doublePageMobile) ? 1 : 0;
        applyHiddenControls();
      };
      application.eventBus.on('pagesloaded', applySpreadMode, { once: true });
      mobileLayout.addEventListener('change', applySpreadMode);
    }).catch(() => {
      showError('Não foi possível aplicar as opções de leitura deste documento.');
    });
  }, { once: true });

  iframe.src = viewerUrl.toString();
}
