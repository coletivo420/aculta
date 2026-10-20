// Keep this value in sync with the mozilla/pdfjs-dist version pinned in
// composer.json. Query versioning prevents a year-long cache from pairing a
// stale API module with a freshly fetched worker (or the reverse).
const PDFJS_VERSION = '6.4.299';
// Keep a single page canvas below common browser surface limits and avoid
// allocating hundreds of megabytes for unusually large PDF page dimensions.
const MAX_CANVAS_PIXELS = 16_000_000;
const MAX_CANVAS_DIMENSION = 16_384;

const initialized = new WeakSet();

async function initialize(viewer) {
  if (initialized.has(viewer)) {
    return;
  }
  initialized.add(viewer);

  const url = viewer.dataset.acultaPdfUrl;
  const canvas = viewer.querySelector('[data-aculta-pdf-control="page"]');
  const status = viewer.querySelector('[data-aculta-pdf-control="status"]');
  const previous = viewer.querySelector('[data-aculta-pdf-control="previous"]');
  const next = viewer.querySelector('[data-aculta-pdf-control="next"]');
  const zoomOut = viewer.querySelector('[data-aculta-pdf-control="zoom-out"]');
  const zoomIn = viewer.querySelector('[data-aculta-pdf-control="zoom-in"]');
  const zoomReset = viewer.querySelector('[data-aculta-pdf-control="zoom-reset"]');
  const zoomStatus = viewer.querySelector('[data-aculta-pdf-control="zoom-status"]');
  const fullscreen = viewer.querySelector('[data-aculta-pdf-control="fullscreen"]');
  const searchInput = viewer.querySelector('[data-aculta-pdf-control="search-input"]');
  const searchSubmit = viewer.querySelector('[data-aculta-pdf-control="search-submit"]');
  const searchPrevious = viewer.querySelector('[data-aculta-pdf-control="search-previous"]');
  const searchNext = viewer.querySelector('[data-aculta-pdf-control="search-next"]');
  const searchStatus = viewer.querySelector('[data-aculta-pdf-control="search-status"]');
  if (!url || !url.startsWith('/') || url.startsWith('//') || !canvas || !status) {
    return;
  }

  try {
    const pdfjsLib = await import(`/libraries/pdf.js/build/pdf.mjs?v=${PDFJS_VERSION}`);
    pdfjsLib.GlobalWorkerOptions.workerSrc = `/libraries/pdf.js/build/pdf.worker.mjs?v=${PDFJS_VERSION}`;
    const pdf = await pdfjsLib.getDocument({ url }).promise;
    let pageNumber = 1;
    let zoom = 1;
    let renderSequence = 0;
    let renderTask;
    let searchSequence = 0;
    let matchingPages = [];
    let matchingPageIndex = -1;

    const render = async () => {
      const sequence = ++renderSequence;
      if (renderTask) {
        const previousTask = renderTask;
        previousTask.cancel();
        try {
          await previousTask.promise;
        }
        catch (error) {
          if (error.name !== 'RenderingCancelledException') {
            throw error;
          }
        }
        if (sequence !== renderSequence) {
          return;
        }
        renderTask = undefined;
      }
      const page = await pdf.getPage(pageNumber);
      if (sequence !== renderSequence) {
        return;
      }
      const baseViewport = page.getViewport({ scale: 1 });
      const availableWidth = Math.max(240, (viewer.clientWidth || 800) - 32);
      const fitScale = Math.min(1.5, availableWidth / baseViewport.width);
      const requestedScale = Math.max(0.5, Math.min(3, fitScale * zoom));
      const maxScaleByArea = Math.sqrt(MAX_CANVAS_PIXELS / (baseViewport.width * baseViewport.height));
      const maxScaleByDimension = MAX_CANVAS_DIMENSION / Math.max(baseViewport.width, baseViewport.height);
      const renderScale = Math.min(requestedScale, maxScaleByArea, maxScaleByDimension);
      const viewport = page.getViewport({ scale: renderScale });
      const context = canvas.getContext('2d');
      canvas.width = Math.ceil(viewport.width);
      canvas.height = Math.ceil(viewport.height);
      const task = page.render({ canvasContext: context, viewport });
      renderTask = task;
      try {
        await task.promise;
      }
      catch (error) {
        if (error.name === 'RenderingCancelledException') {
          return;
        }
        throw error;
      }
      if (sequence !== renderSequence) {
        return;
      }
      renderTask = undefined;
      canvas.hidden = false;
      status.textContent = `Página ${pageNumber} de ${pdf.numPages}`;
      previous.disabled = pageNumber <= 1;
      next.disabled = pageNumber >= pdf.numPages;
      zoomStatus.textContent = `${Math.round((renderScale / fitScale) * 100)}%`;
      zoomOut.disabled = zoom <= 0.5;
      zoomIn.disabled = zoom >= 2.5;
    };

    const goToPage = (target) => {
      pageNumber = Math.max(1, Math.min(pdf.numPages, target));
      render().catch(() => {
        status.textContent = 'Não foi possível renderizar esta página do PDF.';
      });
    };

    previous.addEventListener('click', () => goToPage(pageNumber - 1));
    next.addEventListener('click', () => goToPage(pageNumber + 1));
    zoomOut.addEventListener('click', () => {
      zoom = Math.max(0.5, zoom - 0.25);
      render().catch(() => {
        status.textContent = 'Não foi possível ajustar a visualização do PDF.';
      });
    });
    zoomIn.addEventListener('click', () => {
      zoom = Math.min(2.5, zoom + 0.25);
      render().catch(() => {
        status.textContent = 'Não foi possível ajustar a visualização do PDF.';
      });
    });
    zoomReset.addEventListener('click', () => {
      zoom = 1;
      render().catch(() => {
        status.textContent = 'Não foi possível restaurar o zoom do PDF.';
      });
    });

    const updateFullscreenLabel = () => {
      const isFullscreen = document.fullscreenElement === viewer;
      fullscreen.setAttribute('aria-pressed', isFullscreen ? 'true' : 'false');
      fullscreen.setAttribute('aria-label', isFullscreen ? 'Sair da tela cheia' : 'Abrir leitor em tela cheia');
      fullscreen.textContent = isFullscreen ? 'Sair da tela cheia' : 'Tela cheia';
      render().catch(() => {});
    };
    fullscreen.addEventListener('click', async () => {
      try {
        if (document.fullscreenElement === viewer) {
          await document.exitFullscreen();
        }
        else if (viewer.requestFullscreen) {
          await viewer.requestFullscreen();
        }
        else {
          status.textContent = 'Tela cheia não está disponível neste navegador.';
        }
      }
      catch {
        status.textContent = 'Não foi possível abrir o leitor em tela cheia.';
      }
    });
    viewer.addEventListener('fullscreenchange', updateFullscreenLabel);

    const selectSearchResult = (index) => {
      if (!matchingPages.length) {
        return;
      }
      matchingPageIndex = (index + matchingPages.length) % matchingPages.length;
      const targetPage = matchingPages[matchingPageIndex];
      searchStatus.textContent = `Resultado ${matchingPageIndex + 1} de ${matchingPages.length} — página ${targetPage}`;
      goToPage(targetPage);
    };
    const search = async () => {
      const sequence = ++searchSequence;
      const query = searchInput.value.trim().toLocaleLowerCase();
      matchingPages = [];
      matchingPageIndex = -1;
      searchPrevious.disabled = true;
      searchNext.disabled = true;
      if (!query) {
        searchStatus.textContent = 'Digite um termo para buscar no PDF.';
        return;
      }

      searchStatus.textContent = 'Buscando texto no PDF…';
      for (let page = 1; page <= pdf.numPages; page += 1) {
        if (sequence !== searchSequence) {
          return;
        }
        const pageData = await pdf.getPage(page);
        const text = await pageData.getTextContent();
        const pageText = text.items
          .filter((item) => typeof item.str === 'string')
          .map((item) => item.str)
          .join(' ')
          .toLocaleLowerCase();
        if (pageText.includes(query)) {
          matchingPages.push(page);
        }
        if (page % 20 === 0 || page === pdf.numPages) {
          searchStatus.textContent = `Buscando página ${page} de ${pdf.numPages}…`;
        }
      }
      if (sequence !== searchSequence) {
        return;
      }
      searchPrevious.disabled = matchingPages.length === 0;
      searchNext.disabled = matchingPages.length === 0;
      if (!matchingPages.length) {
        searchStatus.textContent = 'Nenhum resultado encontrado.';
        return;
      }
      selectSearchResult(0);
    };
    searchSubmit.addEventListener('click', () => {
      search().catch(() => {
        searchStatus.textContent = 'Não foi possível pesquisar o texto deste PDF.';
      });
    });
    searchInput.addEventListener('keydown', (event) => {
      if (event.key === 'Enter') {
        event.preventDefault();
        searchSubmit.click();
      }
    });
    searchPrevious.addEventListener('click', () => selectSearchResult(matchingPageIndex - 1));
    searchNext.addEventListener('click', () => selectSearchResult(matchingPageIndex + 1));
    window.addEventListener('resize', () => {
      render().catch(() => {});
    });

    await render();
  }
  catch {
    status.textContent = 'Não foi possível exibir este PDF. Use o link para abrir o arquivo.';
  }
}

function attach(context = document) {
  const selector = '[data-aculta-pdf-viewer="true"]';
  if (context instanceof Element && context.matches(selector)) {
    initialize(context);
  }
  context.querySelectorAll?.(selector).forEach(initialize);
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => attach(), { once: true });
}
else {
  attach();
}

if (window.Drupal?.behaviors) {
  window.Drupal.behaviors.acultaPdfViewer = {
    attach,
  };
}
